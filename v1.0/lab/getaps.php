<?php

/* getaps.php
 * Created by: Ashley Browning
 * Created on: 09/03/2011
 * Version: v1.0 (09/03/2011)
 */

/* This script is used to retrieve Eduroam Access points for a given area. The output is in JSON format

INPUT
 - lngmin - This is the longitude of the bottom left corner of the scope of the search
 - lngmax - This is the longitude of the top right corner of the scope of the search
 - latmin - This is the latitude of the bottom left corner of the scope of the search
 - latmax - This is the latitude of the top right corner of the scope of the search
 - zoom   - This is used to cluster nodes together to avoid cluttering


OUTPUT
All output will be in JSON format. The following are outcomes of the script:

/////
error - This is when something goes wrong with the script
/////
- error 	- The error object will be output when something goes wrong. 
			- code		- This number will uniquely identify the error
			- msg		- A message to describe the error
			
///////
success
///////
- results	- This will specify the number of results there are from the SQL query
- tags		- This object will represent a tag
*/

header('Content-type: application/json; charset=UTF-8');

//Check all get parameteres are present
if(!(isset($_GET["lngmin"]) && isset($_GET["lngmax"]) && isset($_GET["latmin"]) && isset($_GET["latmax"]) && isset($_GET["zoom"]))){
	die('{"error":{"code":1, "msg":"At least one parameter is not set"}}');

}

$swlng = $_GET["lngmin"];
$nelng = $_GET["lngmax"];
$swlat = $_GET["latmin"];
$nelat = $_GET["latmax"];
$zoom = $_GET["zoom"];

//Check the numericness
if(!is_numeric($swlng)){
	die('{"error":{"code":2, "msg":"lngmin is not numeric"}}');
}

if(!is_numeric($nelng)){
	die('{"error":{"code":3, "msg":"lngmax is not numeric"}}');
}

if(!is_numeric($swlat)){
	die('{"error":{"code":4, "msg":"latmin is not numeric"}}');
}

if(!is_numeric($nelat)){
	die('{"error":{"code":5, "msg":"latmax is not numeric"}}');
}

if(!is_numeric($zoom)){
	die('{"error":{"code":6, "msg":"zoom is not numeric"}}');
}

/////////////////////////
//Connect To the database
/////////////////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdb);
if(mysqli_connect_errno()){
	die('{"error":{"code":7, "msg":"Issue with connecting to database"}}');
}

//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");


//CONSTANTS FOR THE HISTORY OPERATIONS
$ADDED = 1;
$UPDATED = 2;
$DELETED = 3;

$row;
$output;
$deleteCount = 0;
$date = 0;


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
$output["results"] = count($row);

$j=0;
//Loop through each
for($i=0; count($row)>$i; $i++){


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

	if($mostRecentID["op"] == 3){
		//Currently deleted, so do not send
		continue;
	}
	
	$stmt->close();
	
	$query = "SELECT id, lat, lng, subsite FROM APs WHERE id = ?";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $row[$i]["apid"]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["lat"], $result["lng"], $result["subsite"]);
	while($stmt->fetch()){
	}
	

	$output["aps"][$j]["id"] = $result["id"];
	$output["aps"][$j]["lat"] = $result["lat"];
	$output["aps"][$j]["lng"] = $result["lng"];
	$output["aps"][$j]["site"] = $result["subsite"];
	$output["aps"][$j]["range"] = 20;

	$j++;
	$stmt->close();
}


echo json_encode($output);




/*
$query = "SELECT id, lat, lng, site FROM APs WHERE lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("dddd", $latmin, $latmax, $lngmin, $lngmax);
if(!$stmt->execute()){
	//fail
	die('{"error":{"code":8, "msg":"Problem executing the query"}}');
}
$stmt->bind_result($result["id"], $result["lat"], $result["lng"], $result["site"]);
$stmt->store_result();
$output["results"] = $stmt->num_rows;


////////////
//Fetch Loop
////////////

$j = 0;
while($stmt->fetch()){
	
	$output["aps"][$j]["id"] = $result["id"];
	$output["aps"][$j]["lat"] = $result["lat"];
	$output["aps"][$j]["lng"] = $result["lng"];
	$output["aps"][$j]["site"] = $result["site"];
	$output["aps"][$j]["range"] = 20;
	
	$j++;

}


//Finish up and encode the JSON
echo json_encode($output);

$stmt->free_result();
$stmt->close();
*/










?>
