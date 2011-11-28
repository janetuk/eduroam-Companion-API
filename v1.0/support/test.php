<?php

//Update one of the rows on the database
//delete one
//then run the script

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

$row;
$date = time();
$operation = 2;

$query = "SELECT DISTINCT site FROM SiteHistory";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->bind_result($r["site"]);
$j = 0;
while($stmt->fetch()){
	$row[$j] = $r["site"];
	$j++;
}

$stmt->close();

$query = "INSERT INTO SiteHistory (site, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";

for($i=0; $i<count($row); $i++){
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("iii", $row[$i], $operation, $date);
	$stmt->execute();
	$stmt->close();
}



//Now for the subsites
unset($row);
$row;

$query = "SELECT DISTINCT subsite FROM SubSiteHistory";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stmt->bind_result($r["site"]);
$j = 0;
while($stmt->fetch()){
	$row[$j] = $r["site"];
	$j++;
}

$stmt->close();

$query = "INSERT INTO SubSiteHistory (subsite, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";

for($i=0; $i<count($row); $i++){
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("iii", $row[$i], $operation, $date);
	$stmt->execute();
	$stmt->close();
}


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
