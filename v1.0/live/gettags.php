<?php
/* gettags.php
 * Created by: Ashley Browning
 * Created on: 14/06/2011
 * Version: v1.00 (14/06/2011)
 */
 
 
/* gettags will return all of the tags (anonymously) that have been submitted to the server

INPUT
- key		- The key, validates the platform/service sending the data
- msg		- Flag for if rcode messages to be sent


OUTPUT
All output will be in JSON format. The following can be output

- rcode 	- This is the return code from this code 
			- code		- This number will uniquely identify the outocme
				-	0	- All went well
				-	1	- Parameter Key is not set
				-	2	- Key passed in is invalid
				-	3	- Problem connecting to the MYSQL database
				-	4	- Error conducting the SQL operation
			- msg		- A message to describe the outcome (optional)
/////THIS NEEDS UPDATING
- tags		- This is an dictionary of tag objects, with the key being the tag ID and value is a tag object
			- [key] - tag ID
			- [value] - tag
					- time		- UNIX timestamp of when the tag was added to the database
					- lat 		- The latitude of the tag's location
					- lng		- The longitude of the tag's location
					- accuracy 	- The accuracy of the tag's location (in metres)
					- subsite	- The subsite the tag is related to

- date		- The UNIX timestamp of the server when this page was requested
						
*/

error_reporting(E_ALL);
ini_set ('display_errors', 1);


/////////
//GLOBALS
/////////
$key = "mlbXAFxFROmhvjuKwdYLjTDeBOXOAFIZMsDjWUXVuqAJDfmXTrYJMquNOYoLjrnH"; 	//This is Matteo's key (IAM, ECS, UoS)
$msgFlag = null;
$output;


//////////////
//CHECK PARAMS
//////////////

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


if (!isset($_GET["key"])){
	die(json_encode(rcode(1, "Parameter 'key' is not set")));
}


///////////
//CHECK KEY
///////////

if (strcmp($key, $_GET["key"]) != 0){
	//fail!
	die(json_encode(rcode(2, "Incorrect key entered")));
}



//////////////
//MYSQL SET UP
//////////////
$currentTime = time();

require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die(json_encode(rcode(3, "Contact System Administrator")));
}
//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");




$query = "SELECT id, UNIX_TIMESTAMP(tagtime), lat, lng, accuracy, subsite FROM Tags";
$stmt = $mysqli->prepare($query);
echo $mysqli->error;

if(!$stmt->execute()){
	die(json_encode(rcode(4, "Error performing request")));
}

echo $mysqli->error;
//Array 'r' for 'results'

$stmt->bind_result($r["id"], $r["time"], $r["lat"], $r["lng"], $r["accuracy"], $r["subsite"]);

while($stmt->fetch()){
	$output["tags"][$r["id"]]["time"] = $r["time"];
	$output["tags"][$r["id"]]["lat"] = $r["lat"];
	$output["tags"][$r["id"]]["lng"] = $r["lng"];
	$output["tags"][$r["id"]]["accuracy"] = $r["accuracy"];
	$output["tags"][$r["id"]]["subsite"] = $r["subsite"];
}

$stmt->close();

$output["date"] = $currentTime;
$output["rcode"]["code"] = 0;

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
