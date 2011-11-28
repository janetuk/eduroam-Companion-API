<?php

$string = "Université d'Angers ";
$string2 = 'Institut "Ruđer Bošković" ';
require_once('../inc/dbcred.php');

$mysqli = new mysqli($host, $user, $pass, $db);
if(mysqli_connect_errno()){
	die('{"error":"Error 006 - Issue with connecting to database"}');
}


echo "This is the original string: $string \n</br>";

echo "This is the string encoded with HTML entities: " . htmlentities($string, ENT_QUOTES, "UTF-8") . "\n</br>";

echo "This is the string with real_escape_string: " . $mysqli->real_escape_string($string) . "\n\n</br></br>";

echo "This is the original string2: $string2 \n</br>";

echo "This is the string2 encoded with HTML entities: " . htmlentities($string2, ENT_QUOTES, "UTF-8") . "\n</br>";

echo "This is the string2 with real_escape_string: " . $mysqli->real_escape_string($string2) . "\n</br>";


?>
