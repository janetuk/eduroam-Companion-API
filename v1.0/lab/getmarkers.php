<?php

/* getmarkers.php
 * Created by: Ashley Browning
 * Created on: 21/02/2011
 * Version: v1.02 (27/02/2011)
 */

/* This is used in the AJAX request for all Eduroam site markers. The output is in the JSON format

OUTPUT
- 'error' - In the event of an error, the error attribute is sent along with a value
- 'results' - When returning a valid response, this attribute denotes the number of markers being returned
- 'markers' - This object describes a marker
*/



header('Content-type: application/json; charset=UTF-8');

/////////////////////////
//Connect To the database
/////////////////////////
require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);

if(mysqli_connect_errno()){
	die('{"error":{"code":1, "msg":"Issue with connecting to database"}}');
}

//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");

//Query SQL for markers in the defined bounds
$query = "SELECT SubSites.id, Site.name, SubSites.name, SubSites.address, SubSites.ssid, SubSites.encryption, SubSites.accesspoints, SubSites.lat, SubSites.lng, SubSites.altitude FROM SubSites, Site WHERE Site.id=SubSites.site";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->bind_result($result["id"], $result["name"], $result["descname"], $result["address"], $result["ssid"], $result["enc"], $result["ap"], $result["lat"], $result["lng"], $result["alt"]);

//Get the number of rows
$stmt->store_result();
$output["results"] = $stmt->num_rows;


////////////
//Fetch Loop
////////////

$j = 0;
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
