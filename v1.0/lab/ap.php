<?php

/* ap.php
 * Created by: Ashley Browning
 * Created on: 28/02/2011
 * Version: v1.00 (28/02/2011)
 */

/* This is used in the AJAX request for access points given a rectuangular field. The output is in the JSON format

INPUT
 - lngmin - This is the longitude of the bottom left corner of the scope of the search
 - lngmax - This is the longitude of the top right corner of the scope of the search
 - latmin - This is the latitude of the bottom left corner of the scope of the search
 - latmax - This is the latitude of the top right corner of the scope of the search
 - zoom   - This is used to cluster nodes together to avoid cluttering
*/


//TODO Convert all error messages to the JSON error object
header('Content-type: application/json; charset=UTF-8');

//Check all get parameteres are present
if(!(isset($_GET["lngmin"]) && isset($_GET["lngmax"]) && isset($_GET["latmin"]) && isset($_GET["latmax"]) && isset($_GET["zoom"]))){
	die('{"error":{"code":1, "msg":"At least one parameter is not set"}}');
}


$lngmin = $_GET["lngmin"];
$lngmax = $_GET["lngmax"];
$latmin = $_GET["latmin"];
$latmax = $_GET["latmax"];
$zoom = $_GET["zoom"];

//Check the numericness
if(!is_numeric($lngmin)){
	die('{"error":"Error 002 - lngmin is not numeric"}');
}

if(!is_numeric($lngmax)){
	die('{"error":"Error 003 - lngmax is not numeric"}');
}

if(!is_numeric($latmin)){
	die('{"error":"Error 004 - latmin is not numeric"}');
}

if(!is_numeric($latmax)){
	die('{"error":"Error 005 - latmax is not numeric"}');
}

if(!is_numeric($zoom)){
	die('{"error":"Error 007 - zoom is not numeric"}');
}

/////////////////////////
//Connect To the database
/////////////////////////

require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdb);
if(mysqli_connect_errno()){
	die('{"error":"Error 006 - Issue with connecting to database"}');
}

//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");



//Query SQL for markers in the defined bounds
$query = "SELECT * FROM SourceData WHERE lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('dddd', $latmin, $latmax, $lngmin, $lngmax);
$stmt->execute();
$stmt->bind_result($result["id"], $result["placemarkID"], $result["name"], $result["descname"], $result["address"], $result["ssid"], $result["enc"], $result["ap"], $result["lat"], $result["lng"], $result["alt"]);

//Get the number of rows
$stmt->store_result();
$output["results"] = $stmt->num_rows;

$j = 0;


////////////
//Fetch Loop
////////////

while($stmt->fetch()){
	
	$output["markers"][$j]["id"] = $result["id"];
	$output["markers"][$j]["name"] = $result["name"];
	$output["markers"][$j]["lat"] = $result["lat"];
	$output["markers"][$j]["lng"] = $result["lng"];
	
	$j++;

}

//Finish up and encode the JSON
echo json_encode($output);

$stmt->free_result();
$stmt->close();

?>
