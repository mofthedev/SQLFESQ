<?php

/**
 * Author: Möf Selvi
 * SQLFESQ: SQL with Fast, Easy and Safe Queries
 * Licensed under MIT.
 * 
 * @package     MofSelvi\SQLFESQ
 * @author      Möf Selvi (@mofthedev)
 * @copyright   Möf Selvi (Muhammed Ömer Faruk Selvi, mofselvi)
 * @license     http://opensource.org/licenses/MIT MIT License
 */

namespace MofSelvi\SQLFESQ;

class SQLFESQ
{
    /** All SQL variables are public, so anything that is a part of the SQL engine is accessable. */
    public $db;
    public $errno = 0;
    public $error = '';
    public $stmt;
    public $insertID;
    public $numOfRows;
    public $affectedRows;

    public $lastQuery;
    public $lastValues;

    public $fetchType = MYSQLI_ASSOC; //MYSQLI_ASSOC,MYSQLI_NUM,MYSQLI_BOTH

    /**
     * First instantiated object of this class.
     * 
     * @var SQLFESQ
     */
    private static $instance;

    /**
     * Last instantiated object of this class.
     */
    private static $lastInstance;
 
    public function __construct($hostname=NULL, $username=NULL, $password=NULL, $database=NULL)
    {
        if(is_null($hostname ?? $username ?? $password ?? $database))
        {
            $this->errno = 1;
            $this->error = "No connection. This object still can be used for test purposes.";
            // return false;
        }
        /** Only MySQLi is supported for now. If other SQL drivers do not cause any trouble, they can also be added to this class in the future. */
        try
        {
            $this->db = new \mysqli($hostname, $username, $password, $database);
            if ($this->db->connect_errno)
            {
                $this->errno = $this->db->connect_errno;
                $this->error = $this->db->connect_error;
                // return false;
            }
            else
            {
                self::$instance = self::$instance ?? $this;
                self::$lastInstance = $this;
            }
            // $this->query("SET character_set_results=utf8;");
            $this->query("SET NAMES 'utf8mb4';");
            // return true;
        }
        catch (\mysqli_sql_exception $e)
        {
            $this->error .= " ## ".$e;
            // return false;
        }
        catch(\Exception $e)
        {
            $this->error .= " ## ".$e;
            // return false;
        }
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    public static function getLastInstance()
    {
        return self::$lastInstance;
    }

    function handleError()
    {
        if ($this->db->errno)
        {
            $this->errno = $this->db->errno;
            $this->error = $this->db->error;
        }
        else
        {
            $this->errno = 0;
            $this->error = "";
        }
    }

    public function __call($name, $arguments)
    {
        if(!is_null($this->db) && method_exists($this->db,$name))
        {
            return $this->db->$name($arguments);
        }
        $this->errno = 2;
        $this->error = "No such a function. Nothing will happen.";
        return false;
    }

    public function query(...$q)
    {
        $qv = $this->processQuery(...$q);
        // print_r($qv);
        $query = $qv['query'];
        $values = $qv['values'];

        $this->lastQuery = $query;
        $this->lastValues = $values;


        $values_len = count($values);
        $bind_types = str_repeat('s', $values_len);
        // $bind_types = implode('', array_fill(0, $values_len, 's'));
        // echo "\n\n".$query."\n\n";
        $this->stmt = $this->db->prepare($query);
        if($values_len > 0)
        {
            $this->stmt->bind_param($bind_types, ...$values);
        }
        $this->stmt->execute();


        $this->affectedRows = $this->db->affected_rows;


        $rows = [];
        $this->numOfRows = 0;

        $result = $this->stmt->get_result();
        if($result)
        {
            $this->numOfRows = $result->num_rows;
            $rows = $result->fetch_all($this->fetchType);//MYSQLI_ASSOC,MYSQLI_NUM,MYSQLI_BOTH
        }

        $this->insertID = $this->db->insert_id;

        $this->handleError();

        return $rows;
    }
    
    public function arrayHasNoKeys(array $arr)
    {
        if (!function_exists('array_is_list'))
        {
            if ($arr === []) {
                return true;
            }
            return array_keys($arr) === range(0, count($arr) - 1);
        }
        else
        {
            return array_is_list($arr);
        }
    }


    public function processQuery(...$params)
    {
        $query = '';
        $values = array();

        $last_param = '';
        foreach ($params as $param)
        {
            if (is_string($param))
            {
                $query .= $param . ' ';
            }
            elseif(is_array($param) && $this->arrayHasNoKeys($param))
            {
                $param_len = count($param);
                if($param_len > 0)
                {
                    $prepared_params = array_fill(0, $param_len, '?');
                    if(is_array($last_param))
                    {
                        $query .= ',';
                    }
                    $query .= '('.implode(', ',$prepared_params).') ';
                    $values = array_merge($values, $param);
                }
            }
            elseif (is_array($param))
            {
                $nestedQuery = $this->processNestedLogic($param);
                $query .= $nestedQuery['query'] . ' ';
                $values = array_merge($values, $nestedQuery['values']);
            }
            $last_param = $param;
        }

        $query = rtrim($query, ' ');
        if(substr($query,-1)!==";")
        {
            $query .= ';';
        }

        return array('query' => $query, 'values' => $values);
    }

    public function processNestedLogic($arr, $op=',')
    {
        $query = '';
        $values = array();

        // $prm_keys = array_keys($arr);
        $i = 0;
        $len = count($arr);
        foreach ($arr as $prm_key => $prm_val)
        {
            if (is_array($prm_val))
            {
                $nestedQuery = $this->processNestedLogic($prm_val, $prm_key);
                if ($len!==1){$query .= '(';}
                $query .= $nestedQuery['query'];
                if ($len!==1){$query .= ')';}
                if ($i!==($len-1))
                {
                    $query .= " ".$op." ";
                }
                $values = array_merge($values, $nestedQuery['values']);
            }
            else
            {
                $query .= $prm_key." ?";
                if ($i!==($len-1))
                {
                    $query .= " ".$op." ";
                }
                $values[] = $prm_val;
            }
            $i++;
        }

        return array('query' => $query, 'values' => $values);
    }
}
