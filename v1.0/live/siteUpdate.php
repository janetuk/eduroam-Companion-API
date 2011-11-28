<?php
/* siteUpdate.php
 * Created by: Ashley Browning
 * Created on: 17/04/2011
 * Version: v1.00 (17/04/2011)
 */
 
 
/* This script is used to obtain the latest information about Eduroam Sites by comparing timestamps

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
				-	50	- Number of updates exceeds specified amount
			- msg		- A message to describe the outcome (optional)
/////THIS NEEDS UPDATING
- date		- This is the date the operation was conducted on the server
- size		- Specifies the maximum size for the update. If this is exceeded, rcode of 50 is returned
- delete	- Array of ids to be deleted from the database
- insert	- Dictionary of objects to with key ID and value an array of data
			- <id>		- ID of the row
					- Array: name
- update	- Dictionary of objects with key ID and value an array of the data
			- <id>		- ID of the row
					- Array: name

*/


$msgFlag = null;
$maxSize = 1000000;

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

//Maximum size of the 
if (isset($_GET["size"])){
	if (!is_numeric($_GET["size"])){
		die(json_encode(rcode(10, "Parameter 'size' is not numeric")));
	}
	$maxSize = $_GET["size"];
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
$currentTime = time();

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



//CONSTANTS FOR THE HISTORY OPERATIONS
$ADDED = 1;
$UPDATED = 2;
$DELETED = 3;

$row;
$output;
$deleteCount = 0;

//find how many things there are to update
$nofRows = 0;



$query = "SELECT DISTINCT site FROM SiteHistory WHERE date > FROM_UNIXTIME(?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $date);
$stmt->execute();
$stmt->store_result();
$nofRows = $stmt->num_rows;
$stmt->bind_result($result["siteid"]);

$j=0;
while($stmt->fetch()){
	$row[$j]["siteid"] = $result["siteid"];
	//$row[$j]["lastop"] = $result["lastop"];
	$j++;
}
$stmt->free_result();
$stmt->close();

//Subsite
$subsiteRow;
$query = "SELECT DISTINCT subsite FROM SubSiteHistory WHERE date > FROM_UNIXTIME(?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $date);
$stmt->execute();
$stmt->store_result();
$nofRows += $stmt->num_rows;
if($nofRows > $maxSize){
	//Too many rows, send back rcode of 50
	$stmt->free_result();
	$stmt->close();
	die(json_encode(rcode(50, "Number of updates exceeds given maximum")));
}
$stmt->bind_result($result["siteid"]);


$j=0;
while($stmt->fetch()){
	$subsiteRow[$j]["siteid"] = $result["siteid"];
	//$row[$j]["lastop"] = $result["lastop"];
	$j++;
}
$stmt->free_result();
$stmt->close();




//For each site
for($i=0; count($row)>$i; $i++){
	
	$latestID;		//ID of the last operation, can either be add, update or delete
	$mostRecentID;	//ID of the last add/delete operation
	$previousID;	//ID of previous add/delete op from the iphone timestamp
	$site;			//The site in question
	
	//Get previous ID
	$query = "SELECT id, operation FROM SiteHistory WHERE site = ? AND date < FROM_UNIXTIME(?) AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("ii", $row[$i]["siteid"], $date);
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
	$query = "SELECT id, operation FROM SiteHistory WHERE site = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $row[$i]["siteid"]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["operation"]);
	while($stmt->fetch()){
	}
	
	$mostRecentID["id"] = $result["id"];
	$mostRecentID["op"] = $result["operation"];
	
	
	//Get the most recent action
	$query = "SELECT id, operation, site FROM SiteHistory WHERE site = ? ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $row[$i]["siteid"]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["operation"], $result["site"]);
	while($stmt->fetch()){
	}
	
	$latestID["id"] = $result["id"];
	$latestID["op"] = $result["operation"];
	$latestID["site"] = $result["site"];
	
	
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
			$data = getSiteDetails($latestID["site"]);
			$output["site"]["insert"][$data["id"]]["name"] = $data["name"];
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
				$data = getSiteDetails($latestID["site"]);
				$output["site"]["insert"][$data["id"]]["name"] = $data["name"];
				continue;
			}
		}
	
	//If the last operation on the iphone was added
	} else if($previousID["op"] == $ADDED){
		
		//If the most recent operation is add, update
		if($latestID["op"] == $ADDED){
			$data = getSiteDetails($latestID["site"]);
			$output["site"]["update"][$data["id"]]["name"] = $data["name"];
			continue;
		}
		
		//If the most recent operation is delete, delete!
		if($latestID["op"] == $DELETED){
			$output["site"]["delete"][$deleteCount] = $latestID["site"];
			continue;
		}
		
		
		//If the most recent operation is update, then check if row is currently active or not
		if($latestID["op"] == $UPDATED){
			
			//If it is currently active, update
			if($mostRecentID["op"] == $ADDED){
				$data = getSiteDetails($latestID["site"]);
				$output["site"]["update"][$data["id"]]["name"] = $data["name"];
				continue;
			} else if ($mostRecentID["op"] == $DELETED){
				$output["site"]["delete"][$deleteCount] = $latestID["site"];
				continue;
			}
		}
		
	}
		
}


//Now get the subsites
$deleteCount = 0;
unset($row);
$row;

/*
$query = "SELECT DISTINCT subsite FROM SubSiteHistory WHERE date > FROM_UNIXTIME(?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $date);
$stmt->execute();
$stmt->bind_result($result["siteid"]);


$j=0;
while($stmt->fetch()){
	$row[$j]["siteid"] = $result["siteid"];
	//$row[$j]["lastop"] = $result["lastop"];
	$j++;
}
$stmt->close();
*/


//For each site
for($i=0; count($subsiteRow)>$i; $i++){
	
	$latestID;		//ID of the last operation, can either be add, update or delete
	$mostRecentID;	//ID of the last add/delete operation
	$previousID;	//ID of previous add/delete op from the iphone timestamp
	$site;			//The site in question
	
	//Get previous ID
	$query = "SELECT id, operation FROM SubSiteHistory WHERE subsite = ? AND date < FROM_UNIXTIME(?) AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("ii", $subsiteRow[$i]["siteid"], $date);
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
	$query = "SELECT id, operation FROM SubSiteHistory WHERE subsite = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $subsiteRow[$i]["siteid"]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["operation"]);
	while($stmt->fetch()){
	}
	
	$mostRecentID["id"] = $result["id"];
	$mostRecentID["op"] = $result["operation"];
	
	
	//Get the most recent action
	$query = "SELECT id, operation, subsite FROM SubSiteHistory WHERE subsite = ? ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $subsiteRow[$i]["siteid"]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["operation"], $result["site"]);
	while($stmt->fetch()){
	}
	
	$latestID["id"] = $result["id"];
	$latestID["op"] = $result["operation"];
	$latestID["site"] = $result["site"];
	
	
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
			$data = getSubSiteDetails($latestID["site"]);
			$output["subsite"]["insert"][$data["id"]]["site"] = $data["site"];
			$output["subsite"]["insert"][$data["id"]]["name"] = $data["name"];
			$output["subsite"]["insert"][$data["id"]]["address"] = $data["address"];
			$output["subsite"]["insert"][$data["id"]]["lat"] = $data["lat"];
			$output["subsite"]["insert"][$data["id"]]["lng"] = $data["lng"];
			$output["subsite"]["insert"][$data["id"]]["altitude"] = $data["altitude"];
			$output["subsite"]["insert"][$data["id"]]["ssid"] = $data["ssid"];
			$output["subsite"]["insert"][$data["id"]]["encryption"] = $data["encryption"];
			$output["subsite"]["insert"][$data["id"]]["accesspoints"] = $data["accesspoints"];
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
				$data = getSubSiteDetails($latestID["site"]);
				$output["subsite"]["insert"][$data["id"]]["site"] = $data["site"];
				$output["subsite"]["insert"][$data["id"]]["name"] = $data["name"];
				$output["subsite"]["insert"][$data["id"]]["address"] = $data["address"];
				$output["subsite"]["insert"][$data["id"]]["lat"] = $data["lat"];
				$output["subsite"]["insert"][$data["id"]]["lng"] = $data["lng"];
				$output["subsite"]["insert"][$data["id"]]["altitude"] = $data["altitude"];
				$output["subsite"]["insert"][$data["id"]]["ssid"] = $data["ssid"];
				$output["subsite"]["insert"][$data["id"]]["encryption"] = $data["encryption"];
				$output["subsite"]["insert"][$data["id"]]["accesspoints"] = $data["accesspoints"];
				continue;
			}
		}
	
	//If the last operation on the iphone was added
	} else if($previousID["op"] == $ADDED){
		
		//If the most recent operation is add, update
		if($latestID["op"] == $ADDED){
			$data = getSubSiteDetails($latestID["site"]);
				$output["subsite"]["update"][$data["id"]]["site"] = $data["site"];
				$output["subsite"]["update"][$data["id"]]["name"] = $data["name"];
				$output["subsite"]["update"][$data["id"]]["address"] = $data["address"];
				$output["subsite"]["update"][$data["id"]]["lat"] = $data["lat"];
				$output["subsite"]["update"][$data["id"]]["lng"] = $data["lng"];
				$output["subsite"]["update"][$data["id"]]["altitude"] = $data["altitude"];
				$output["subsite"]["update"][$data["id"]]["ssid"] = $data["ssid"];
				$output["subsite"]["update"][$data["id"]]["encryption"] = $data["encryption"];
				$output["subsite"]["update"][$data["id"]]["accesspoints"] = $data["accesspoints"];
			continue;
		}
		
		//If the most recent operation is delete, delete!
		if($latestID["op"] == $DELETED){
			$output["subsite"]["delete"][$deleteCount] = $latestID["site"];
			continue;
		}
		
		
		//If the most recent operation is update, then check if row is currently active or not
		if($latestID["op"] == $UPDATED){
			
			//If it is currently active, update
			if($mostRecentID["op"] == $ADDED){
				$data = getSubSiteDetails($latestID["site"]);
				$output["subsite"]["update"][$data["id"]]["site"] = $data["site"];
				$output["subsite"]["update"][$data["id"]]["name"] = $data["name"];
				$output["subsite"]["update"][$data["id"]]["address"] = $data["address"];
				$output["subsite"]["update"][$data["id"]]["lat"] = $data["lat"];
				$output["subsite"]["update"][$data["id"]]["lng"] = $data["lng"];
				$output["subsite"]["update"][$data["id"]]["altitude"] = $data["altitude"];
				$output["subsite"]["update"][$data["id"]]["ssid"] = $data["ssid"];
				$output["subsite"]["update"][$data["id"]]["encryption"] = $data["encryption"];
				$output["subsite"]["update"][$data["id"]]["accesspoints"] = $data["accesspoints"];
				continue;
			} else if ($mostRecentID["op"] == $DELETED){
				$output["subsite"]["delete"][$deleteCount] = $latestID["site"];
				continue;
			}
		}
		
	}
		
}





function getSiteDetails($id){
	
	global $mysqli;
	
	$query = "SELECT id, name FROM Site WHERE id = ?";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["name"]);
	while($stmt->fetch()){
	}
	
	return $result;

}


function getSubSiteDetails($id){
	
	global $mysqli;
	
	$query = "SELECT id, site, name, address, lat, lng, altitude, ssid, encryption, accesspoints FROM SubSites WHERE id = ?";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["site"], $result["name"], $result["address"], $result["lat"], $result["lng"], $result["altitude"], $result["ssid"], $result["encryption"], $result["accesspoints"]);
	while($stmt->fetch()){
	}
	
	return $result;

}


//$rcode = rcode(0, "Update successful");

$output["rcode"]["code"] = 0;
$output["date"] = $currentTime;

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
