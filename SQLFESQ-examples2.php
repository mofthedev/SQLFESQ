<?php

use MofSelvi\SQLFESQ\SQLFESQ;

require dirname(__FILE__)."/SQLFESQ.php";

// Give the connection
$db = new SQLFESQ(new mysqli("localhost", "root", "", "appuidb"));

// Or connect through the instance
$db = new SQLFESQ();
$db->connectMysql("localhost", "root", "", "appuidb");

// Or open a db file
$db->connectSqlite("litedb.db");

// All DB types will support errno and error variables
echo $db->errno.": ".$db->error."\n<br>\n";




$db->query("CREATE TABLE users(
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name varchar(200),
    score int
    );");




// [C]RUD
$newusers = [["muhammed", 0],["omer", 10],["faruk", 20], ["selvi", 30]];
$db->query("INSERT INTO users (name,score) VALUES ", ...$newusers);

echo $db->lastQuery;




// C[R]UD
$db->fetchType = MYSQLI_ASSOC;
$getusers = $db->query("SELECT * FROM users WHERE", ["score >="=>"12.2"]);
print_r($getusers);
print_r(array_keys($getusers[0]));
echo json_encode($getusers);
echo "\n<br>".$db->numOfRows." rows fetched.";
echo "\n<br>\n----\n<br>\n";


$db->fetchType = MYSQLI_NUM;
$getusers = $db->query("SELECT * FROM users WHERE", ["score >="=>"12.2"]);
print_r($getusers);
print_r(array_keys($getusers[0]));
echo "\n<br>".$db->numOfRows." rows fetched.";
echo json_encode($getusers);


$getusers = $db->query("SELECT * FROM users WHERE name LIKE", ["%mu%"]);
$getusers = $db->query("SELECT * FROM users WHERE", ["OR" => [[ "name LIKE"=>"%mu%"], ["name LIKE"=>"%se%" ]] ]);
print_r($getusers);
// print_r(array_keys($getusers[0]));
echo json_encode($getusers);
echo "\n<br>".$db->numOfRows." rows fetched.";
echo "\n<br>\n----\n<br>\n";






// CR[U]D
$db->query("UPDATE users SET", ["score =" => "15.5"],"WHERE",["score >" => "12.2"]);
$db->query("UPDATE users SET score=score+15","WHERE",["score >" => "12.2"]);
$db->query("UPDATE users SET score=score+",[15],"WHERE",["score >" => "12.2"]);
$db->query("UPDATE users SET name=CONCAT(name,",[" surname"],") WHERE",["score >" => "12.2"]);
echo $db->affectedRows." records have been updated.";




// CRU[D]
$db->query("DELETE FROM users WHERE", ["AND" => [["score >="=>"12.2"], ["score <="=>"22.2"]]]);
echo $db->affectedRows." records have been deleted.";

