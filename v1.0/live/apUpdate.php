<?php
/* getAP.php
 * Created by: Ashley Browning
 * Created on: 22/04/2011
 * Version: v1.00 (22/04/2011)
 */
 
 
/* This script takes boundary coordinates and a date, returns AP details that have been changed since the date

INPUT
- swlat		- The latitude of the South-West part of the coordinate boundary
- swlng		- The longitude of the South-West part of the coordinate boundary
- nelat		- The latitude of the North-East part of the coordinate boundary
- nelng		- The longitude of the North-East part of the coordinate bounday
- date		- The UNIX Timestamp from when the lastUpdate occurred
				- '0' value passed if there are no previous last updates
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
- date		- The date the update took place on the server
- insert	- An array of dictionary objects to be inserted into the database
- update	- An array of dictionary objects to be updated into the database
- delete	- An array of ids to delete APs from the database
- swlat		- Return the sw/ne lat/lng to the client so they may update existing APs to the latest date
- swlng
- nelat
- nelng

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

if (!isset($_GET["swlat"])){
	die(json_encode(rcode(3, "Parameter 'swlat' is not set")));
}

if (!isset($_GET["swlng"])){
	die(json_encode(rcode(4, "Parameter 'swlng' is not set")));
}

if (!isset($_GET["nelat"])){
	die(json_encode(rcode(5, "Parameter 'nelat' is not set")));
}

if (!isset($_GET["nelng"])){
	die(json_encode(rcode(6, "Parameter 'nelng' is not set")));
}

//Further Validation for types
if (!is_numeric($_GET["date"])){
	die(json_encode(rcode(7, "Parameter 'date' is not numeric")));
}

if (!is_numeric($_GET["swlat"])){
	die(json_encode(rcode(8, "Parameter 'swlat' is not numeric")));
}

if (!is_numeric($_GET["swlng"])){
	die(json_encode(rcode(9, "Parameter 'swlng' is not numeric")));
}

if (!is_numeric($_GET["nelat"])){
	die(json_encode(rcode(10, "Parameter 'nelat' is not numeric")));
}

if (!is_numeric($_GET["nelng"])){
	die(json_encode(rcode(11, "Parameter 'nelng' is not numeric")));
}


//Additional Lat/Lng validation
if ($_GET["swlat"] > 90.0 || $_GET["swlat"] < -90.0){
	die(json_encode(rcode(15, "swlat is not a valid latitude")));
}

if ($_GET["nelat"] > 90.0 || $_GET["nelat"] < -90.0){
	die(json_encode(rcode(16, "nelat is not a valid latitude")));
}

if ($_GET["swlng"] > 180.0 || $_GET["swlng"] < -180.0){
	die(json_encode(rcode(17, "swlng is not a valid longitude")));
}

if ($_GET["nelng"] > 180.0 || $_GET["nelng"] < -180.0){
	die(json_encode(rcode(18, "nelng is not a valid longitude")));
}

///////////
//GLOBALS
///////////
$currentTime = time();

//Make it easier for myself
$key = $_GET["key"];
$date = $_GET["date"];
$swlat = $_GET["swlat"];
$swlng = $_GET["swlng"];
$nelat = $_GET["nelat"];
$nelng = $_GET["nelng"];

///////////////
//SET UP MYSQLi
///////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die(json_encode(rcode(12, "Contact System Administrator")));
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
	die(json_encode(rcode(14, "Error with the key")));
} else {
	$stmt->bind_result($platform);
	while ($stmt->fetch()){}
}
$stmt->free_result();	
$stmt->close();


//CONSTANTS FOR THE HISTORY OPERATIONS
$ADDED = 1;
$UPDATED = 2;
$DELETED = 3;

$row;
$output;
$deleteCount = 0;


//Get the Access points modified since the date
//SELECT APs.id, APs.lat, APs.lng, APHistory.date FROM APs, APHistory WHERE APs.id = APHistory.ap
$query = "SELECT DISTINCT APs.id FROM APs, APHistory WHERE APs.id = APHistory.ap AND APs.lat > ? AND APs.lng > ? AND APs.lat < ? AND APs.lng < ? AND date > FROM_UNIXTIME(?)";
//$query = "SELECT DISTINCT ap FROM APHistory WHERE date > FROM_UNIXTIME(?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ddddi", $swlat, $swlng, $nelat, $nelng, $date);
$stmt->execute();
$stmt->bind_result($result["apid"]);

$j=0;
while($stmt->fetch()){
	$row[$j]["apid"] = $result["apid"];
	$j++;
}
$stmt->close();


///////////////////////////////////////
//DETERMINE WHAT APS SHOULD BE RETURNED
///////////////////////////////////////
//For each site
for($i=0; count($row)>$i; $i++){
	
	$latestID;		//ID of the last operation, can either be add, update or delete
	$mostRecentID;	//ID of the last add/delete operation
	$previousID;	//ID of previous add/delete op from the iphone timestamp
	$site;			//The site in question
	
	//Get previous ID
	$query = "SELECT id, operation FROM APHistory WHERE ap = ? AND date < FROM_UNIXTIME(?) AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("ii", $row[$i]["apid"], $date);
	$stmt->execute();
	$stmt->store_result();
	$stmt->bind_result($result["id"], $result["operation"]);
	while($stmt->fetch()){
	}
	//print_r($result);
	
	if($stmt->num_rows == 0){
		//This means it is a new row as there is no previous history
		$previousID["id"] = -1;	//Mark that there is no previous history
		$previousID["op"] = $DELETED;	//Effectively deleted
	} else {
		$previousID["id"] = $result["id"];
		$previousID["op"] = $result["operation"];
	}
	
	$stmt->close();
	
	
	//Get the most recent add or delete
	$query = "SELECT id, operation FROM APHistory WHERE ap = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $row[$i]["apid"]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["operation"]);
	while($stmt->fetch()){
	}
	
	$mostRecentID["id"] = $result["id"];
	$mostRecentID["op"] = $result["operation"];
	
	
	//Get the most recent action
	$query = "SELECT id, operation, ap FROM APHistory WHERE ap = ? ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $row[$i]["apid"]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["operation"], $result["ap"]);
	while($stmt->fetch()){
	}
	
	$latestID["id"] = $result["id"];
	$latestID["op"] = $result["operation"];
	$latestID["ap"] = $result["ap"];
	
	
/*
	echo "ROW ". $row[$i]["siteid"] ."</br>";
	echo "previousID </br>";
	print_r($previousID);
	echo "</br>mostRecentID </br>";
	print_r($mostRecentID);
	echo "</br>latestID </br>";
	print_r($latestID);
	echo "</br></br>";
*/
	
	
	//Go through each scenario and add stuff to the output
	//If the iphone's previous operation was delete
	if($previousID["op"] == $DELETED){
		
		//If the most recent operation is add, insert
		if($latestID["op"] == $ADDED){
			$data = getAPDetails($latestID["ap"]);
			$output["insert"][$data["id"]]["lat"] = $data["lat"];
			$output["insert"][$data["id"]]["lng"] = $data["lng"];
			$output["insert"][$data["id"]]["subsite"] = $data["subsite"];
			$output["insert"][$data["id"]]["rating"] = $data["rating"];
			continue;
		}
		
		//If the most recent operation is delete, do nothing!
		if($latestID["op"] == $DELETED){
			continue;
		}
		
		
		//If the most recent operation is update, then check if row is currently active or not
		if($latestID["op"] == $UPDATED){
			
			//If it is currently active
			if($mostRecentID["op"] == $ADDED){
				$data = getAPDetails($latestID["ap"]);
				$output["insert"][$data["id"]]["lat"] = $data["lat"];
				$output["insert"][$data["id"]]["lng"] = $data["lng"];
				$output["insert"][$data["id"]]["subsite"] = $data["subsite"];
				$output["insert"][$data["id"]]["rating"] = $data["rating"];
				continue;
			}
		}
	
	//If the last operation on the iphone was added
	} else if($previousID["op"] == $ADDED){
		
		//If the most recent operation is add, update
		if($latestID["op"] == $ADDED){
			$data = getAPDetails($latestID["ap"]);
			$output["update"][$data["id"]]["lat"] = $data["lat"];
			$output["update"][$data["id"]]["lng"] = $data["lng"];
			$output["update"][$data["id"]]["subsite"] = $data["subsite"];
			$output["update"][$data["id"]]["rating"] = $data["rating"];
			continue;
		}
		
		//If the most recent operation is delete, delete!
		if($latestID["op"] == $DELETED){
			$output["delete"][$deleteCount] = $latestID["ap"];
			continue;
		}
		
		
		//If the most recent operation is update, then check if row is currently active or not
		if($latestID["op"] == $UPDATED){
			
			//If it is currently active, update
			if($mostRecentID["op"] == $ADDED){
				$data = getAPDetails($latestID["ap"]);
				$output["update"][$data["id"]]["lat"] = $data["lat"];
				$output["update"][$data["id"]]["lng"] = $data["lng"];
				$output["update"][$data["id"]]["subsite"] = $data["subsite"];
				$output["update"][$data["id"]]["rating"] = $data["rating"];
				continue;
			} else if ($mostRecentID["op"] == $DELETED){
				$output["delete"][$deleteCount] = $latestID["ap"];
				continue;
			}
		}
		
	}
		
}






$output["rcode"]["code"] = 0;
$output["date"] = $currentTime;
$output["swlat"] = $swlat;
$output["swlng"] = $swlng;
$output["nelat"] = $nelat;
$output["nelng"] = $nelng;

echo json_encode($output);

///////////
//FUNCTIONS
///////////

//------------------------------------------------------
// Return AP details that need to be sent to the devices
//------------------------------------------------------
function getAPDetails($id){
	
	global $mysqli;
	
	$query = "SELECT id, lat, lng, subsite, rating FROM APs WHERE id = ?";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["lat"], $result["lng"], $result["subsite"], $result["rating"]);
	while($stmt->fetch()){
	}
	
	return $result;

}


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
