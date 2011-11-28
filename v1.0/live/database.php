<?php
/* database.php
 * Created by: Ashley Browning
 * Created on: 20/04/2011
 * Version: v1.00 (20/04/2011)
 */
 
 
/* Check what version of the database is being run. If different version, then return path for full sqlite db

INPUT
- version	- The version of the database being run on the device
- key		- The key, validates the platform/service sending the data
- msg		- Flag for if rcode messages to be sent


OUTPUT
All output will be in JSON format. The following can be output

- rcode 	- This is the return code from this code 
			- code		- This number will uniquely identify the outocme
				-	0	- Database is up to date
				-	1	- An update is required
			- msg		- A message to describe the outcome (optional)
			
- path		- This is the address of the sqlite database to download
- size		- Size of the sqlite database file

*/

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
if (!isset($_GET["version"])){
	die(json_encode(rcode(2, "Parameter 'version' is not set")));
}

if (!isset($_GET["key"])){
	die(json_encode(rcode(3, "Parameter 'key' is not set")));
}

//Further Validation for types
if (!is_numeric($_GET["version"])){
	die(json_encode(rcode(4, "Parameter 'version' is not numeric")));
}



$deviceVersion = (int) $_GET["version"];	//device version
$key = $_GET["key"];
$currentVersion;
$output;


///////////////
//SET UP MYSQLi
///////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die(json_encode(rcode(5, "Contact System Administrator")));
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
	die(json_encode(rcode(6, "Error with the key")));
} else {
	$stmt->bind_result($platform);
	while ($stmt->fetch()){}
}
$stmt->free_result();	
$stmt->close();




//Get the version of the database from sql
$query = "SELECT version FROM Info WHERE id = 0";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows != 1){
	die(json_encode(rcode(7, "Error querying database")));
}
$stmt->bind_result($result["version"]);
while($stmt->fetch()){
	$currentVersion = $result["version"];
}
$stmt->free_result();
$stmt->close();


//check the versions





if ($currentVersion == $deviceVersion){
	//device is up to date
	$output["rcode"]["code"] = 0;
	$output["path"] = "https://eduroam-api.dev.ja.net/v1.0/live/getdb.php";
	$output["size"] = filesize("/var/www/html/v1.0/support/live/sqlite_conversion/eduroam.sqlite");
	
	echo json_encode($output);

} else if ($currentVersion > $deviceVersion) {

	//device needs updating,
	$output["rcode"]["code"] = 1;
	$output["path"] = "https://eduroam-api.dev.ja.net/v1.0/live/getdb.php";
	$output["size"] = filesize("/var/www/html/v1.0/support/live/sqlite_conversion/eduroam.sqlite");

	
	echo json_encode($output);
}


















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
