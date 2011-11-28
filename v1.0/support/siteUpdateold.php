<?php
/* siteUpdate.php
 * Created by: Ashley Browning
 * Created on: 17/04/2011
 * Version: v1.00 (17/04/2011)
 */
 
 
/* This script is used to accept Eduroam 'tags' and then add them to the database

INPUT
- date		- The earliest date in the database in UNIXTime
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
			
- date		- This is the date the operation was conducted on the server
- delete	- Array of ids to be deleted from the database
- insert	- Dictionary of objects to with key ID and value an array of data
			- <id>		- ID of the row
					- Array: name
- update	- Dictionary of objects with key ID and value an array of the data
			- <id>		- ID of the row
					- Array: name

*/


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
if (!isset($_GET["date"])){
	die(json_encode(rcode(1, "Parameter 'date' is not set")));
}

if (!isset($_GET["key"])){
	die(json_encode(rcode(2, "Parameter 'key' is not set")));
}

//Further Validation for types
if (!is_numeric($_GET["date"])){
	die(json_encode(rcode(3, "Parameter 'date' is not numeric")));
}


$date = $_GET["date"];
$key = $_GET["key"];
$output;

///////////////
//SET UP MYSQLi
///////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die(json_encode(rcode(4, "Contact System Administrator")));
}
//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");


/*
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
	die(json_encode(rcode(4, "Error with the key")));
} else {
	$stmt->bind_result($platform);
	while ($stmt->fetch()){}
}
$stmt->free_result();	
$stmt->close();
*/

///////////////////////
//FIND VALUES TO DELETE
///////////////////////
$query = "SELECT id FROM Site WHERE dateDeleted > FROM_UNIXTIME(?) AND active = 0 AND dateAdded < FROM_UNIXTIME(?)";
$stmt = $mysqli->prepare($query);
//echo "SQL:" . $mysqli->error;
$stmt->bind_param("ii", $date, $date);
//echo "HI";

$stmt->execute();
$stmt->bind_result($result["id"]);

$j = 0;
while($stmt->fetch()){
	$output["delete"][$j] = $result["id"];
	$j++;
}

$stmt->close();


///////////////////////
//FIND VALUES TO INSERT
///////////////////////
$query = "SELECT id, name FROM Site WHERE dateAdded > FROM_UNIXTIME(?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $date);
$stmt->execute();
$stmt->bind_result($result["id"], $result["name"]);

while($stmt->fetch()){
	$output["insert"][$result["id"]] = $result["name"];
}

$stmt->close();


///////////////////////
//FIND VALUES TO UPDATE
///////////////////////
$query = "SELECT id, name FROM Site WHERE dateUpdated > FROM_UNIXTIME(?) AND dateAdded < FROM_UNIXTIME(?) AND active = 1";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $date, $date);
$stmt->execute();
$stmt->bind_result($result["id"], $result["name"]);
while($stmt->fetch()){
	$output["update"][$result["id"]] = $result["name"];
}

$stmt->close();



echo json_encode($output);




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
