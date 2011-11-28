<?php
/* allkmlparse.php
 * Created by: Ashley Browning
 * Created on: 10/02/2011
 * Version: v1.01 (10/02/2011)
 */

/* This script is used to read in the kml file of all Eduroam sites, parse the data, and insert it into the database
*/

//Open the KML file
$filename = "all.kml";
$file = fopen($filename, "r");

if (!$file){
	die("Error 001: Error opening the file");
}


//open the file to write to
/*
$fileoutputname = "output.kml";
$outputfile = fopen($fileoutputname, "w");

if(!$outputfile){
	die("htmlentitydecode Error 002: Could not open output file");
}
*/



//State information for the Parser
$booleanArray["kml"] = FALSE;
$booleanArray["doc"] = FALSE;
$booleanArray["placeID"] = FALSE;
$booleanArray["name"] = FALSE;
$booleanArray["desc"] = FALSE;
$booleanArray["point"] = FALSE;
$booleanArray["coord"] = FALSE;

$record["placeID"] = -1;
$record["name"] = "";
$recrod["desc"]["name"] = "";
$record["desc"]["address"] = "";
$record["desc"]["ssid"] = "";
$record["desc"]["encryption"] = "";
$record["desc"]["ap"] = -1;
$record["lat"] = 0;
$record["lng"] = 0;
$record["alt"] = 0;

$count = 0;
$date = time();


require_once('../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die("allkmlparse Error 003: Could not connect to the database");
}

//THIS IS REQUIRED FOR UTF8 TO STORE CORRECTLY
$mysqli->query("SET NAMES 'utf8'");



//KICK START THE DB WITH A VERSION NUMBER
$date = time();

$query = "INSERT INTO Info (version, dateUpdated) VALUES (1, FROM_UNIXTIME(?))";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $date);
if($stmt->execute()){
	echo "Inserted the info data correctly";
} else {
	echo "Error 007: inserting the info data failed";
	echo "	sqlerror: " . $mysqli->error;
}
$stmt->close();

$xml_parser = xml_parser_create();

xml_set_element_handler($xml_parser, "startTag", "endTag");

xml_set_character_data_handler($xml_parser, "contents");

$data = fread($file, filesize($filename));

if(!(xml_parse($xml_parser, $data, feof($file)))){ 
    die("Error on line " . xml_get_current_line_number($xml_parser)); 
} 

xml_parser_free($xml_parser);

fclose($file);

echo "DONE! Added $count rows";


//contentes deals with the bits in between a tag
function contents($parser, $data){
	
	global $date;
	global $booleanArray;
	global $record;
	//global $outputfile;

	//If we are in the name tag then concatenate the data as the parser splits when it sees a html entity
	if($booleanArray["name"]){
		$record["name"] .= $data; 
	}
	
	//If we come across a description, then rip the data out of the CDATA description 
	if($booleanArray["desc"]){
		//Guard against empty input given by the parser
		$data = trim($data);
		if(strlen($data)){
			if(preg_match('/^<b>Name:<\/b>(?P<name>.*)<br><b>Address:<\/b>(?P<address>.*)<br><b>SSID:<\/b>(?P<ssid>.*)<br><b>Enc:<\/b>(?P<enc>.*)<br><b>AP:<\/b>(?P<ap>.*)<br>.*$/', $data, $regex)){

				
				$record["desc"]["name"] = trim($regex["name"]);
				$record["desc"]["address"] = trim($regex["address"]);
				$record["desc"]["ssid"] = trim($regex["ssid"]);
				$record["desc"]["encryption"] = trim($regex["enc"]);
				$record["desc"]["ap"] = trim($regex["ap"]);
				
				//Decode the entities that may exist
				$record["desc"]["name"] = html_entity_decode($record["desc"]["name"], ENT_NOQUOTES, "UTF-8");
				$record["desc"]["address"] = html_entity_decode($record["desc"]["address"], ENT_NOQUOTES, "UTF-8");
			}
		}
	}
		
	
	//If we are in the coordinate tags, then get the lat, lon and altitude data
	if($booleanArray["coord"]){
		$data = trim($data);
		if(preg_match('/^(?P<lng>.*),(?P<lat>.*),(?P<alt>.*)$/', $data, $regex)){
		
			$precision = 8;
		
			$record["lng"] = round($regex["lng"], $precision);
			$record["lat"] = round($regex["lat"], $precision);
			$record["alt"] = round($regex["alt"], $precision);
		}
	}
}


//startTag defines what to do with an opening tag
function startTag($parser, $data, $attributes){


	global $booleanArray;
	global $record;

	switch($data){
		case "KML":
			$booleanArray["kml"] = TRUE;
			break;
		case "DOCUMENT":
			$booleanArray["doc"] = TRUE;
			break;
		case "PLACEMARK":
			//Add placemark attribute 
			$booleanArray["placeID"] = TRUE;
			$record["placeID"] = $attributes['ID'];
			break;
		case "NAME":
			$booleanArray["name"] = TRUE;
			break;
		case "DESCRIPTION":
			$booleanArray["desc"] = TRUE;
			break;
		case "POINT":
			$booleanArray["point"] = TRUE;
			break;
		case "COORDINATES":
			$booleanArray["coord"] = TRUE;
			break;
	
	}

}

//endTag defines what to do with a closing tag

function endTag($parser, $data){

	global $date;
	global $booleanArray;
	global $record;
	global $count;
	global $mysqli;

//CONSTANTS FOR THE HISTORY OPERATIONS
$ADDED = 1;
$UPDATED = 2;
$DELETED = 3;


	switch($data){
		case "KML":
			$booleanArray["kml"] = FALSE;
			break;
		case "DOCUMENT":
			$booleanArray["doc"] = FALSE;
			break;
		case "PLACEMARK":
			$booleanArray["placeID"] = FALSE;
						
			//Do stuff here
			
			
			//SITE
			
			//Check for unique name, if not, add it
			$siteID = -1;	//This will represent the Site ID
			

			///////////
			//SITE DATA
			///////////
			
			$query = "SELECT id FROM Site WHERE name = ?";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("s", $record["name"]);
			if(!$stmt->execute()){
				//fail
				echo 'allkmlparse Error 005: Error selecting Site \n';
			}
			$stmt->bind_result($siteID);
			$stmt->store_result();
			
			if($stmt->num_rows == 0){

				//Get the unixdate, prepare insert and get the siteID
				$query = "INSERT INTO Site (name, active) VALUES (?, TRUE)";
				$stmt2 = $mysqli->prepare($query);
				$stmt2->bind_param("s", $record["name"]);
				if($stmt2->execute()){
					$siteID = $mysqli->insert_id;
					echo "New site added- id:$siteID name:{$record["name"]}\n";
					
					$stmt2->close();
					
					$query = "INSERT INTO SiteHistory (site, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
					$stmt2 = $mysqli->prepare($query);
					$stmt2->bind_param("iii", $siteID, $ADDED, $date);
					if(!$stmt2->execute()){
						echo $mysqli->error;
						echo "allkmlparse Error 004b: Error inserting new site\n";
					}
					
					$stmt2->close();
					
				} else {
					echo "allkmlparse Error 004: Error inserting new site {$record["name"]} \n";
					echo "   sqlerror: {$stmt2->error} \n";
				}
			} else {
				while ($stmt->fetch()){
					//ID is bound to the siteID variable.
				}
			}
			
			$stmt->close();
			
			
			
			
			
			//////////////
			//SUBSITE DATA
			//////////////
			
			//Insert subsite data into the database
			$query = "INSERT INTO SubSites (site, name, address, lat, lng, altitude, ssid, encryption, accesspoints, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("issdddssi", $siteID, $record["desc"]["name"], $record["desc"]["address"], $record["lat"], $record["lng"], $record["alt"], $record["desc"]["ssid"], $record["desc"]["encryption"], $record["desc"]["ap"]);
			if($stmt->execute()){
				$count++;
				echo "$count subsite added - {$record["desc"]["name"]}\n";
				
					$siteID = $mysqli->insert_id;
					$query = "INSERT INTO SubSiteHistory (subsite, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
					$stmt2 = $mysqli->prepare($query);
					$stmt2->bind_param("iii", $siteID, $ADDED, $date);
					if(!$stmt2->execute()){
					
						echo "allkmlparse Error 006b: Error inserting new site\n";
					}
					
					$stmt2->close();
				
			} else {
				echo "allkmlparse Error 006: Error inserting placeID {$record["desc"]["name"]} \n";
				echo "   sqlerror: {$stmt->error} \n";
			}
			
		

			//Clear the record array
			$record["placeID"] = -1;
			$record["name"] = "";
			$recrod["desc"]["name"] = "";
			$record["desc"]["address"] = "";
			$record["desc"]["ssid"] = "";
			$record["desc"]["encryption"] = "";
			$record["desc"]["ap"] = -1;
			$record["lat"] = 0;
			$record["lng"] = 0;
			$record["alt"] = 0;
			break;
		case "NAME":
			$booleanArray["name"] = FALSE;	
			//echo "{$record["name"]} </br>";
			
			break;
		case "DESCRIPTION":
			$booleanArray["desc"] = FALSE;
			break;
		case "POINT":
			$booleanArray["point"] = FALSE;
			break;
		case "COORDINATES":
			$booleanArray["coord"] = FALSE;
			break;
	
	}

}




?>
