<?php

/* tag.php
 * Created by: Ashley Browning
 * Created on: 28/02/2011
 * Version: v1.00 (28/02/2011)
 */

/* This script is used to accept Eduroam 'tags' and then add them to the database

INPUT
- lat		- The latitude of the tag
- lng		- The longitude of the tag
- user		- The unique user ID
- site		- The site the tag applies to

OUTPUT
All output will be in JSON format. The following can be output
- error 	- The error object will be output when something goes wrong. 
			- code		- This number will uniquely identify the error
			- msg		- A message to describe the error
- success	- This specifies the code running successfully
			- code		- This will sepcify the outcome of the code running
			- msg		- This will describe the outcome of the code
*/

////////////////////
//VALIDATION OF DATA
////////////////////


//Check all get parameteres are present
if(!(isset($_GET["lat"]) && isset($_GET["lng"]) && isset($_GET["user"]) && isset($_GET["site"]) && isset($_GET["accuracy"]))){
	die('{"error":{"code":1, "msg":"At least one parameter is not set"}}');
}


$lat = $_GET["lat"];
$lng = $_GET["lng"];
$user = $_GET["user"];
$site = $_GET["site"];
$accuracy = $_GET["accuracy"];

//Check the numericness
if(!is_numeric($lat)){
	die('{"error":{"code":2, "msg":"lat is not numeric"}}');
}

if(!is_numeric($lng)){
	die('{"error":{"code":3, "msg":"lng is not numeric"}}');
}

/*
if(!is_numeric($user)){
	die('{"error":{"code":4, "msg":"user is not numeric"}}');
}
*/

if(!is_numeric($site)){
	die('{"error":{"code":5, "msg":"site is not numeric"}}');
}

if(!is_numeric($accuracy)){
	die('{"error":{"code":6, "msg":"accuracy is not numeric"}}');
}

//CHECK LAT LNG ARE VALID
//latitude is between +/-90
if($lat > 90 || $lat < -90){
	die('{"error":{"code":6, "msg":"Invalid Latitude value entered"}}');
}

//Longitude is between +/-180
if($lat > 180 || $lat < -180){
	die('{"error":{"code":7, "msg":"Invalid Longitude value entered"}}');
}


$platformKey = "sKvxwSGqhTIqzUGNBrLOquTzouCNlywewiLbFnBobcrqnqrrGrgrPsfkiDzxaZSh";

/////////////////////////
//Connect To the database
/////////////////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die('{"error":{"code":8, "msg":"Issue with connecting to database"}}');
}

//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");



//Check the platform
$query = "SELECT id FROM Platforms WHERE pkey=?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $platformKey);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows != 1){
	die('{"error":{"code":9, "msg":"problem verifying platform"}}');
}
$stmt->bind_result($r["pid"]);
while($stmt->fetch()){
}

$platformID = $r["pid"];
$userID;

$stmt->free_result();
$stmt->close();

//CHECK USER EXISTS
$query = "SELECT id FROM Users WHERE userName = ? AND platform = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $user, $platformID);
$stmt->execute();
$stmt->store_result();
$rows = $stmt->num_rows;
$stmt->bind_result($r["userid"]);
while($stmt->fetch()){
	$userID = $r["userid"];
}
$stmt->free_result();
$stmt->close();

/*
if($rows < 1){
	$trust = 0.5; //Default trust value is 0.5
	$query = "INSERT INTO Users (idUsers, trust) VALUES (?, ?)";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("id", $user, $trust);
	if($stmt->execute()){
		//Success
	} else {
		$error["error"]["code"] = 9;
		$error["error"]["msg"] = "Cannot add user to database - Error: " . $stmt->error;
		echo json_encode($error);
		die();
	}
	$stmt->free_result();
	$stmt->close();
}
*/

//ENTER CHECK FOR 3 TAGS A DAY PER USER



//INSERT INTO THE DATABASE

$date = time();

$query = "INSERT INTO Tags (tagtime, lat, lng, accuracy, user, subsite) VALUES (FROM_UNIXTIME(?), ?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("idddii", $date, $lat, $lng, $accuracy, $userID, $site);
if($stmt->execute()){
	//success
	echo '{"success":{"code":1, "msg":"Tag added to database"}}';
} else {
	$error["error"]["code"] = 10;
	$error["error"]["msg"] = "Cannot add tag to database - Error: " . $stmt->error;
	echo json_encode($error);
	die();
}
$stmt->free_result();
$stmt->close();


//Update the access points

//include("../../../eduroam/live/clusterAP.php");



















?>
