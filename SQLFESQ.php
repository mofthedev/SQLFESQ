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
    public $db_type;
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
     * 
     * @var SQLFESQ
     */
    private static $lastInstance;

    public function __construct($connection=NULL)
    {
        $this->db = $connection;

        // @Attention: For each DB type
        // Define types. DB type better be the pure name of the DB used. Not Mysqli, not Sqlite3, no uppercases.
        if($connection instanceof \mysqli)
        {
            $this->db_type = "mysql";
            $this->fetchType = MYSQLI_ASSOC;
        }
        elseif($connection instanceof \SQLite3)
        {
            $this->db_type = "sqlite";
            $this->fetchType = SQLITE3_ASSOC;
        }
        else
        {
            $this->errno = 3;
            $this->error = "This DB type is not supported yet. Please, consider contributing to the library.";
            $this->db_type = "unknown";
            $this->db = NULL;
        }
    }
 
    public function connectMysql($hostname=NULL, $username=NULL, $password=NULL, $database=NULL)
    {
        if(is_null($hostname ?? $username ?? $password ?? $database))
        {
            $this->errno = 1;
            $this->error = "No connection. This object still can be used for test purposes.";
            return false;
        }
        
        try
        {
            $this->db = new \mysqli($hostname, $username, $password, $database);
            if ($this->db->connect_errno)
            {
                $this->errno = $this->db->connect_errno;
                $this->error = $this->db->connect_error;
                return false;
            }
            else
            {
                self::$instance = self::$instance ?? $this;
                self::$lastInstance = $this;
            }
            // $this->query("SET character_set_results=utf8;");
            $this->query("SET NAMES 'utf8mb4';");

            $this->db_type = "mysql";
            $this->fetchType = MYSQLI_ASSOC;
            return true;
        }
        catch (\mysqli_sql_exception $e)
        {
            $this->error .= " ## ".$e;
            return false;
        }
        catch(\Exception $e)
        {
            $this->error .= " ## ".$e;
            return false;
        }
    }

    public function connectSqlite($dbfilepath)
    {
        if(is_null($dbfilepath))
        {
            $this->errno = 1;
            $this->error = "No connection. This object still can be used for test purposes.";
            return false;
        }
        
        try
        {
            $this->db = new \SQLite3($dbfilepath);
            if ($this->db->lastErrorCode())
            {
                $this->errno = $this->db->lastErrorCode();
                $this->error = $this->db->lastErrorMsg();
                return false;
            }
            else
            {
                self::$instance = self::$instance ?? $this;
                self::$lastInstance = $this;
            }
            
            $this->db_type = "sqlite";
            $this->fetchType = SQLITE3_ASSOC;
            return true;
        }
        catch(\Exception $e)
        {
            $this->error .= " ## ".$e;
            return false;
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
        // @Attention: For each DB type
        // Get the error number and error message, if any.
        if ($this->db_type=='mysql' && property_exists($this->db,'errno'))
        {
            $this->errno = $this->db->errno;
            $this->error = $this->db->error;
        }
        elseif ($this->db_type=='sqlite' && method_exists($this->db,'lastErrorCode'))
        {
            $this->errno = $this->db->lastErrorCode();
            $this->error = $this->db->lastErrorMsg();
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



        $this->stmt = $this->db->prepare($query);
        if($values_len > 0)
        {
            if($this->db_type=='mysql')
            {
                $bind_types = str_repeat('s', $values_len);
                $this->stmt->bind_param($bind_types, ...$values);
            }
            elseif($this->db_type=='sqlite')
            {
                foreach ($values as $idx => $val)
                {
                    $this->stmt->bindValue($idx+1, $val, SQLITE3_TEXT);
                }
            }
        }
        
        $execute_result = $this->stmt->execute();

        if($this->db_type=='sqlite')
        {
            $this->stmt->reset();
        }


        // @Attention: For each DB type
        // Get number of affected rows
        if($this->db_type=='mysql')
        {
            $this->affectedRows = $this->db->affected_rows;
        }
        elseif($this->db_type=='sqlite')
        {
            $this->affectedRows = $this->db->changes();
        }


        $rows = [];
        $this->numOfRows = 0;

        if($this->db_type=='mysql')
        {
            $result = $this->stmt->get_result();
            if($result)
            {
                $this->numOfRows = $result->num_rows;
                $rows = $result->fetch_all($this->fetchType);//MYSQLI_ASSOC,MYSQLI_NUM,MYSQLI_BOTH
            }
        }
        elseif($this->db_type=='sqlite')
        {
            while ($row = $execute_result->fetchArray($this->fetchType))
            {
                $rows[] = $row;
            }
            $this->numOfRows = count($rows);
        }


        // @Attention: For each DB type
        // Get last insert ID
        if($this->db_type=='mysql')
        {
            $this->insertID = $this->db->insert_id;
        }
        elseif($this->db_type=='sqlite')
        {
            $this->insertID = $this->db->lastInsertRowID();
        }
        

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
