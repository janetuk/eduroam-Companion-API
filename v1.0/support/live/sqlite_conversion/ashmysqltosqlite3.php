<?php
/* 2mysqltosqlite3.php
 * Created by: Ashley Browning
 * Created on: 20/04/2011
 * Version: v1.00 (20/04/2011)
 */
 
 
/* Script to take data from mysql and output to file so it can be read by sqlite3

INPUT
[filename]	- The name of the output file

OUTPUT
-writing to the output file

			

*/


///////////////
//SET UP MYSQLi
///////////////
require_once('../../../inc/dbcred.php');

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdbdb);
if(mysqli_connect_errno()){
	die();
}
//Needed as the text is in UTF-8 format and need to set the connection to UTF-8
$mysqli->query("SET NAMES 'utf8'");

//replacing escaped text in data
$escs = array(
	'f' => array("@\\\'@",'@\\\"@','@\\\n@','@\\\r@'),
	't' => array("''",'"',"\n","\r")
);



define('WORK_DIR', './');
// pass mysql dump FILE NAME as parameter
//$dbSql = $argv[1] . '.sql';
if (!isset($argv[1])){
	die("Please pass a file to output to!\n");
}

$output = WORK_DIR . $argv[1] . '.lsq3';



//Get the file if it exists
if(file_exists($output)){
	echo "about to remove the file\n";
	unlink($output);
}


//Lets get started with the info table

$query = "SELECT id, version, dateUpdated FROM Info";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->bind_result($r["id"], $r["version"], $r["dateUpdated"]);
while($stmt->fetch()){
	$line = "INSERT INTO \"Info\" VALUES ({$r['id']},{$r['version']},'{$r['dateUpdated']}');\n";
	file_put_contents($output, cnvEscapes($line), FILE_APPEND);
}
$stmt->close();


//Sites now
$query = "SELECT id, name FROM Site";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($r["id"], $r["name"]);
while($stmt->fetch()){


	//Escape the single quotes
	$r["name"] = addslashes($r["name"]);


	//Lookup the history of this site, then get the most recent update/add
	
	
	//Is it active? Is the most recent add/delete an add?
	$query = "SELECT operation FROM SiteHistory WHERE site = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt2 = $mysqli->prepare($query);
	$stmt2->bind_param("i", $r["id"]);
	$stmt2->execute();
	$stmt2->bind_result($r["op"]);
	while($stmt2->fetch()){}

	//If the latest is deleted, dont add to the file and continue
	if($r["op"] == 3){
		continue;
	}
	$stmt2->close();
	
	//Find out the date of the most recent add or update
	$query = "SELECT date FROM SiteHistory WHERE site = ? AND (operation = 1 OR operation = 2) ORDER BY date DESC LIMIT 1";
	$stmt2 = $mysqli->prepare($query);
	$stmt2->bind_param("i", $r["id"]);
	$stmt2->execute();
	$stmt2->bind_result($r["date"]);
	while($stmt2->fetch()){}
	
	$line = "INSERT INTO \"Site\" VALUES ({$r["id"]},'{$r["name"]}','{$r["date"]}');\n";
	file_put_contents($output, cnvEscapes($line), FILE_APPEND);
	
	$stmt2->close();
	
}
$stmt->free_result();
$stmt->close();




//sub sites
$query = "SELECT id, site, name, address, lat, lng, altitude, ssid, encryption, accesspoints FROM SubSites";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($r["id"], $r["site"], $r["name"], $r["address"], $r["lat"], $r["lng"], $r["altitude"], $r["ssid"], $r["encryption"], $r["accesspoints"]);
while($stmt->fetch()){
	
	//escape the text
	$r["name"] = addslashes($r["name"]);
	$r["address"] = addslashes($r["address"]);
	$r["ssid"] = addslashes($r["ssid"]);	
	$r["encryption"] = addslashes($r["encryption"]);
	
	
	//Lookup the history of this site, then get the most recent update/add
	
	
	//Is it active? Is the most recent add/delete an add?
	$query = "SELECT operation FROM SubSiteHistory WHERE subsite = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt2 = $mysqli->prepare($query);
	$stmt2->bind_param("i", $r["id"]);
	$stmt2->execute();
	$stmt2->bind_result($r["op"]);
	while($stmt2->fetch()){}

	//If the latest is deleted, dont add to the file and continue
	if($r["op"] == 3){
		continue;
	}
	$stmt2->close();
	
	//Find out the date of the most recent add or update
	$query = "SELECT date FROM SubSiteHistory WHERE subsite = ? AND (operation = 1 OR operation = 2) ORDER BY date DESC LIMIT 1";
	$stmt2 = $mysqli->prepare($query);
	$stmt2->bind_param("i", $r["id"]);
	$stmt2->execute();
	$stmt2->bind_result($r["date"]);
	while($stmt2->fetch()){}
	
	$line = "INSERT INTO \"SubSites\" VALUES ({$r["id"]},{$r["site"]},'{$r["name"]}','{$r["address"]}',{$r["lat"]},{$r["lng"]},{$r["altitude"]},'{$r["ssid"]}','{$r["encryption"]}',{$r["accesspoints"]},'{$r["date"]}');\n";
	file_put_contents($output, cnvEscapes($line), FILE_APPEND);
	
	$stmt2->close();
	
}



$stmt->close();



//Access Points
$query = "SELECT id, lat, lng, subsite, rating FROM APs";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($r["id"], $r["lat"], $r["lng"], $r["subsite"], $r["rating"]);
while($stmt->fetch()){

	//Escape the single quotes!




	//Lookup the history of this site, then get the most recent update/add
	
	
	//Is it active? Is the most recent add/delete an add?
	$query = "SELECT operation FROM APHistory WHERE ap = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt2 = $mysqli->prepare($query);
	$stmt2->bind_param("i", $r["id"]);
	$stmt2->execute();
	$stmt2->bind_result($r["op"]);
	while($stmt2->fetch()){}

	//If the latest is deleted, dont add to the file and continue
	if($r["op"] == 3){
		continue;
	}
	$stmt2->close();
	
	//Find out the date of the most recent add or update
	$query = "SELECT date FROM APHistory WHERE ap = ? AND (operation = 1 OR operation = 2) ORDER BY date DESC LIMIT 1";
	$stmt2 = $mysqli->prepare($query);
	$stmt2->bind_param("i", $r["id"]);
	$stmt2->execute();
	$stmt2->bind_result($r["date"]);
	while($stmt2->fetch()){}
	
	$line = "INSERT INTO \"APs\" VALUES ({$r["id"]},{$r["lat"]},{$r["lng"]},{$r["subsite"]},{$r["rating"]},'{$r["date"]}');\n";
	file_put_contents($output, cnvEscapes($line), FILE_APPEND);
	
	$stmt2->close();
	
}

$stmt->close();



function cnvEscapes($str){
	global $escs; 
	return preg_replace($escs['f'], $escs['t'], $str);
}


function escapeQuote($str){
	
}

















?>
