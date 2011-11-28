<?php

/* tag.php
 * Created by: Ashley Browning
 * Created on: 13/04/2011
 * Version: v1.00 (13/04/2011)
 */
 
 
/* This script is used to accept Eduroam 'tags' and then add them to the database

INPUT
- lat		- The latitude of the tag
- lng		- The longitude of the tag
- user		- The unique user ID
- site		- The site the tag applies to
- key		- The key, validates the platform/service sending the data
- msg		- Flag for if rcode messages to be sent

OUTPUT
All output will be in JSON format. The following can be output

- rcode 	- This is the return code from this code 
			- code		- This number will uniquely identify the outocme
				-	0	- All went well
				-	1	- Error - The parameter 'user' has not been set
				-	2	- Error - The parameter 'lat' has not been set
			- msg		- A message to describe the outcome (optional)
			

*/

///////////
//CONSTANTS
///////////
$MAX_TAGS_PER_DAY = 3;

////////////////////
//VALIDATION OF DATA
////////////////////

//error_reporting(E_ALL);


$msgFlag = null;

//See if we should return messages
if (isset($_GET["msg"])){
	if($_GET["msg"] == 1){
		$msgFlag = 1;
	} else {
		$msgFlag = 0;
	}
} else {
	$msgFlag = 0;
}

//Validate other parameters
if (!isset($_GET["user"])){
	die(json_encode(rcode(1, "Parameter 'user' is not set")));
}

if (!isset($_GET["lat"])){
	die(json_encode(rcode(2, "Parameter 'lat' is not set")));
}

if (!isset($_GET["lng"])){
	die(json_encode(rcode(3, "Parameter 'lng' is not set")));
}

if (!isset($_GET["site"])){
	die(json_encode(rcode(4, "Parameter 'site' is not set")));
}

if (!isset($_GET["key"])){
	die(json_encode(rcode(5, "Parameter 'key' is not set")));
}

if (!isset($_GET["accuracy"])){
	die(json_encode(rcode(15, "Parameter 'accuracy' is not set")));
}



//Make sure they are of the correct types
/*
if (!is_numeric($_GET["user"])){
	die(json_encode(rcode(6, "Parameter 'user' is not numeric")));
}
*/

if (!is_numeric($_GET["lat"])){
	die(json_encode(rcode(7, "Parameter 'lat' is not numeric")));
}

if (!is_numeric($_GET["lng"])){
	die(json_encode(rcode(8, "Parameter 'lng' is not numeric")));
}

if (!is_numeric($_GET["site"])){
	die(json_encode(rcode(9, "Parameter 'site' is not numeric")));
}

if (!is_numeric($_GET["accuracy"])){
	die(json_encode(rcode(16, "Parameter 'accuracy' is not numeric")));
}


//Additional Lat/Lng validation
if ($_GET["lat"] > 90.0 || $_GET["lat"] < -90.0){
	die(json_encode(rcode(17, "lat is not a valid latitude")));
}

if ($_GET["lng"] > 180.0 || $_GET["lng"] < -180.0){
	die(json_encode(rcode(18, "lng is not a valid longitude")));
}

//Passed validation, convert to variables for rest of code
$user = $_GET["user"];
$site = (int) $_GET["site"];
$lat = $_GET["lat"];
$lng = $_GET["lng"];
$key = $_GET["key"];
$accuracy = $_GET["accuracy"];


///////////////
//SET UP MYSQLi
///////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die(json_encode(rcode(10, "Contact System Administrator")));
}
//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");


///////////
//CHECK KEY
///////////
//Check that the key supplied is valid and store the platform ID in the playform variable
$platformID = -1;		//Store the platform ID if exists

$query = "SELECT id FROM Platforms WHERE pkey = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $key);

$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows != 1) {
	die(json_encode(rcode(11, "Error with the key")));
} else {
	$stmt->bind_result($platform);
	while ($stmt->fetch()){}
}
$stmt->free_result();	
$stmt->close();



////////////
//CHECK USER
////////////
//Check the user exists, if not add them to the database
$userID = -1;
$query = "SELECT id FROM Users WHERE userName = ? AND platform = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("si", $user, $platform);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows != 1){
	//not found, craete new user
	//close up previous stmt
	$stmt->free_result();
	$stmt->close();

	$query = "INSERT INTO Users (platform, userName) VALUES (?, ?)";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("is", $platform, $user);
	if (!$stmt->execute()){
		die(json_encode(rcode(12, "Credentials error")));
	}
	$userID = $mysqli->insert_id;
	$stmt->close();
} else {
	//found the user
	$stmt->bind_result($userID);
	while ($stmt->fetch()){}
	$stmt->free_result();
	$stmt->close();
}


//The below has been removed for the purpose of the TNC2011 demonstrations

//////////////////////////////////////
//CHECK NUMBER OF TIME USER HAS TAGGED
//////////////////////////////////////
$startOfDay = mktime(0, 0, 0, date('n'), date('j'));

$query = "SELECT id FROM Tags WHERE user = ? AND tagtime > FROM_UNIXTIME(?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $userID, $startOfDay);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows >= $MAX_TAGS_PER_DAY){
	//Too many tags!
	die(json_encode(rcode(14, "User has submitted too many tags for one day")));
}
$stmt->free_result();
$stmt->close();

//////////////////////
//CHECK SUBSITE EXISTS
//////////////////////
$query = "SELECT active FROM SubSites WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $site);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows != 1){
	die(json_encode(rcode(17, "Invalid Site ID")));
}
$stmt->free_result();
$stmt->close();


/////////////////////////
//ENTER TAG INTO DATABASE
/////////////////////////
$currentTime = time();
$query = "INSERT INTO Tags (tagtime, lat, lng, accuracy, user, subsite) VALUES (FROM_UNIXTIME(?), ?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("idddii", $currentTime, $lat, $lng, $accuracy, $userID, $site);

if(!$stmt->execute()){
	die(json_encode(rcode(18, "Error accepting the Tag")));
} else {
	die(json_encode(rcode(19, "Tag Accepted")));
}
$stmt->free_result();
$stmt->close();







///////////
//FUNCTIONS
///////////

//------------------------------------------------------
// Return just the code and maybe a msg based on options
//------------------------------------------------------
function rcode($code, $msg){
	global $msgFlag;
	
	$output["rcode"]["code"] = $code;
	if($msgFlag){
		$output["rcode"]["msg"] = $msg;
	}
	
	return $output;
}

?>


















