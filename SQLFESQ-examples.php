<?php

use MofSelvi\SQLFESQ\SQLFESQ;
require dirname(__FILE__)."/SQLFESQ.php";

// EXAMPLES

$db = new SQLFESQ();
$db->undefined_method();

echo $db->errno.": ".$db->error."\n<br>\n";
// OUTPUT
// 2: No such a function. Nothing will happen.


// For debugging, you can use processQuery method to check if you wrote the query correctly or not.
// This function returns an array with 2 keys: query, values.
// query is used for statement preparation. values is an array of parameters which can consist of user inputs.


// Do not forget using comparison operator at the end of the keys!
$db->fetchType = MYSQLI_ASSOC; // This is actually the default value. You can change this to MYSQLI_NUM, if you want.
print_r( $db->processQuery( "SELECT * FROM table1 WHERE",["id="=>3] ) );
echo "\n<br>\n";
/*
[query] => SELECT * FROM table1 WHERE id= ?;
[values] => Array
    (
        [0] => 3
    )
*/


// If you provide an indexed array, it will convert it to something like: (?,?,?)
print_r( $db->processQuery( "INSERT INTO table1 (col1, col2, col3) VALUES",["val1", "val2", "val3"] ) );
echo "\n<br>\n";
/*
[query] => INSERT INTO table1 (col1, col2, col3) VALUES (?, ?, ?);
[values] => Array
    (
        [0] => val1
        [1] => val2
        [2] => val3
    )
*/


// If you provide a 2D indexed array with the splat operator, it will convert it to: (?,?,?), (?,?,?).
// Make sure all rows have the same amount of items. Don't forget splat operator (...) before the array!
$multiarr = [["val1", "val2", "val3"], ["val4", "val5", "val6"]];
print_r( $db->processQuery( "INSERT INTO table1 (col1, col2, col3) VALUES", ...$multiarr ) );
echo "\n<br>\n";
/*
[query] => INSERT INTO table1 (col1, col2, col3) VALUES (?, ?, ?),(?, ?, ?);
[values] => Array
    (
        [0] => val1
        [1] => val2
        [2] => val3
        [3] => val4
        [4] => val5
        [5] => val6
    )
*/


// For multiple set assignments, pass an associative array as a parameter. Don't forget to use "=" operator at the end of the keys.
// If you need multiple conditions for WHERE clauses, you can use logic operators as the key, just like in MongoDB!
print_r( $db->processQuery( "UPDATE table1 SET",["name ="=>"mof", "lastname ="=>"selvi"], "WHERE", ["AND"=>["id ="=>3, "mail ="=>"mail@example.com"]] ) );
echo "\n<br>\n";
/*
[query] => UPDATE table1 SET name = ? , lastname = ? WHERE id = ? AND mail = ?;
[values] => Array
    (
        [0] => mof
        [1] => selvi
        [2] => 3
        [3] => mail@example.com
    )
*/


// For cases where you need to use the same column name and the same comparison operator for WHERE clauses; make sure you put the statements into a 1-element array.
// Obviously, this is because we cannot have 2 or more elements with the same key in an associative array.
print_r( $db->processQuery( "UPDATE table1 SET",["name ="=>"mof", "lastname ="=>"selvi"], "WHERE", ["AND"=>[["score >"=>3], ["score >"=>5]]] ) );
echo "\n<br>\n";
/*
[query] => UPDATE table1 SET name = ? , lastname = ? WHERE (score > ?) AND (score > ?);
[values] => Array
    (
        [0] => mof
        [1] => selvi
        [2] => 3
        [3] => 5
    )
*/


// For increment/decrement operations using user inputs, you can pass a 1-element array to the function.
// Because "score=score+ (15)" is a valid syntax, it will work.
print_r( $db->processQuery("UPDATE table1 SET score=score+",[15],"WHERE",["score >" => "12.2"]) );
echo "\n<br>\n";
/*
[query] => UPDATE table1 SET score=score+ (?) WHERE score > ?;
[values] => Array
    (
        [0] => 15
        [1] => 12.2
    )
*/


// When a string concatenation is needed, pass the user input as a 1-element array again.
// It may look a bit more confusing at first, but is actually "name=CONCAT(name,(' addition'))".
// And it is also a valid syntax. Works well.
print_r( $db->processQuery("UPDATE table1 SET name=CONCAT(name,",[" addition"],") WHERE",["score >" => "12.2"]) );
echo "\n<br>\n";
/*
[query] => UPDATE table1 SET name=CONCAT(name, (?) ) WHERE score > ?;
[values] => Array
    (
        [0] =>  addition
        [1] => 12.2
    )
*/


// LIKE operator is simple.
// Just add % or _ wildcards to the beginning or the end of the user input string, then pass it as a 1-element array.
// "LIKE CONCAT('%',?,'%')" and ['%'.$input.'%'] both are safe.
print_r( $db->processQuery("SELECT * FROM users WHERE", ["AND" => [ ["name LIKE"=>"%mu%"], ["name LIKE"=>"%se%"] ] ] )  );
echo "\n<br>\n";
/*
[query] => SELECT * FROM users WHERE (name LIKE ?) AND (name LIKE ?);
[values] => Array
    (
        [0] => %mu%
        [1] => %se%
    )
*/


// Another example, but for DELETE statement this time.
print_r( $db->processQuery( "DELETE FROM table1 WHERE", ["AND"=>["id ="=>3, "activetime <"=>"12312313"]] ) );
echo "\n<br>\n";
/*
[query] => DELETE FROM table1 WHERE id = ? AND activetime < ?;
[values] => Array
    (
        [0] => 3
        [1] => 12312313
    )
*/


// If you don't need to use a user input on the right-hand side of a comparison, don't turn it to an array. Just write the comparison as a string.
// Caution! In this case, you have to write the paranthesis before and after the array manually not to break the logic!
print_r( $db->processQuery( "SELECT * FROM table1 as t1 INNER JOIN table2 as t2 ON t1.id=t2.id WHERE t1.name!=t2.name AND (",["OR"=>["t1.score >"=>30, "t2.score <"=>20]],")" ) );
echo "\n<br>\n";
/*
[query] => SELECT * FROM table1 as t1 INNER JOIN table2 as t2 ON t1.id=t2.id WHERE t1.name!=t2.name AND ( t1.score > ? OR t2.score < ? );
[values] => Array
    (
        [0] => 30
        [1] => 20
    )
*/

