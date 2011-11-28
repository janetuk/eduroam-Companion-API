<?php

/* gettags.php
 * Created by: Ashley Browning
 * Created on: 01/03/2011
 * Version: v1.00 (01/03/2011)
 */

/* This script is used to retrieve Eduroam tags for a given area. The output is in JSON format

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

$lngmin = $_GET["lngmin"];
$lngmax = $_GET["lngmax"];
$latmin = $_GET["latmin"];
$latmax = $_GET["latmax"];
$zoom = $_GET["zoom"];

//Check the numericness
if(!is_numeric($lngmin)){
	die('{"error":{"code":2, "msg":"lngmin is not numeric"}}');
}

if(!is_numeric($lngmax)){
	die('{"error":{"code":3, "msg":"lngmax is not numeric"}}');
}

if(!is_numeric($latmin)){
	die('{"error":{"code":4, "msg":"latmin is not numeric"}}');
}

if(!is_numeric($latmax)){
	die('{"error":{"code":5, "msg":"latmax is not numeric"}}');
}

if(!is_numeric($zoom)){
	die('{"error":{"code":6, "msg":"zoom is not numeric"}}');
}

/////////////////////////
//Connect To the database
/////////////////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($host, $user, $pass, $db);
if(mysqli_connect_errno()){
	die('{"error":{"code":7, "msg":"Issue with connecting to database"}}');
}

//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");


$query = "SELECT id, tagtime, lat, lng, subsite FROM Tags WHERE lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("dddd", $latmin, $latmax, $lngmin, $lngmax);
if(!$stmt->execute()){
	//fail
	die('{"error":{"code":8, "msg":"Problem executing the query"}}');
}
$stmt->bind_result($result["id"], $result["tagtime"], $result["lat"], $result["lng"], $result["site"]);
$stmt->store_result();
$output["results"] = $stmt->num_rows;


////////////
//Fetch Loop
////////////

$j = 0;
while($stmt->fetch()){
	
	$output["tags"][$j]["id"] = $result["id"];
	$output["tags"][$j]["tagtime"] = $result["tagtime"];
	$output["tags"][$j]["lat"] = $result["lat"];
	$output["tags"][$j]["lng"] = $result["lng"];
	$output["tags"][$j]["site"] = $result["site"];
	
	$j++;

}


//Finish up and encode the JSON
echo json_encode($output);

$stmt->free_result();
$stmt->close();










?>
