<?php
/*
 * This is just an academic R&D, trying to convert a mysql dump taken using
 * mysqldump --compact --compatible=ansi --default-character-set=binary --extended-insert=false
 *										 --default-character-set=utf8
 */
 
 //try --default-character-set=utf8


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



define('WORK_DIR', './');
// pass mysql dump FILE NAME as parameter
$dbSql = $argv[1] . '.sql';
$dbSqlite = $argv[1] . '.lsq3';
$dataonly = 0;
if(isset($argv[2])){
	if($argv[2] == 'dataonly'){
		$dataonly = 1;
	}
}

$dbSql_Data = WORK_DIR . 'Data_' . $dbSql;		//This will store the data going into the database file
$dbSql_Sche = WORK_DIR . 'Schema_' . $dbSql;	//This will store the schema
$dbSql_Proc = WORK_DIR . 'Routines_' . $dbSql;	//This will store the routines 


if(!file_exists($dbSql)){
	die("SQL Source not found");
}

$fp = fopen($dbSql, 'r');
if(!$fp) exit();

if(file_exists($dbSql_Sche)) unlink($dbSql_Sche);
if(file_exists($dbSql_Data)) unlink($dbSql_Data);
if(file_exists($dbSql_Proc)) unlink($dbSql_Proc);
if(file_exists($dbSqlite)) unlink($dbSqlite);

//regex replacements for datatype conversion
$regex = array(
	'f' => array("@ COMMENT .*@",'@ UNSIGNED @i','@ on update [^,]*@','@ (small|tiny|medium|big|)int\([0-9]*\) @', '/CONSTRAINT \"[^"]+\" FOREIGN KEY .*/i', '/PRIMARY KEY \(\"[^"]+\"\),?/', '/(UNIQUE)? KEY \"[^"]+\" \(\"[^"]+\"\),?/'),
	't' => array(',',' ','',' integer ', '', '', '')
);

//replacing escaped text in data
$escs = array(
	'f' => array("@\\\'@",'@\\\"@','@\\\n@','@\\\r@'),
	't' => array("''",'"',"\n","\r")
);

$skipping = false;

while(!feof($fp)){
  $line = fgets($fp);
  list($key,) = explode(' ', trim($line));
  switch (strtoupper($key)){
  	case 'SET':
  	case '/*!50003':
  	case 'call':
  		if($skipping){
 			file_put_contents($dbSql_Proc, $line, FILE_APPEND);
  		}  		
  	break;
  	case 'DELIMITER':
 		file_put_contents($dbSql_Proc, cnvEscapes($line), FILE_APPEND);
  		$skipping = ($skipping == false)?true:false;
  	break;
  	case 'INSERT':
  		if($skipping == false){
  		//got an insert statement - need to find what table it belongs to
  		$atablename = "";
		if(preg_match('/^INSERT INTO "(?P<site>.+)" VALUES.*$/', $line, $regex)){
			$atablename = $regex["site"];
		}
		
		//Got the table name now, lets get the data that we want
		switch ($atablename) {
			case "Site":
				if(preg_match('/^INSERT INTO "Site" VALUES \((?P<id>.*),\'(?P<name>.*)\',\'(?P<dateAdded>.*)\',\'(?P<dateUpdated>.*)\',\'?(?P<dateDeleted>.*)\'?,(?P<active>.*)\);$/', $line, $regex)){
					
					
					
					$line = "INSERT INTO \"Site\" VALUES ({$regex["id"]},'{$regex["name"]}','{$regex["dateUpdated"]}');";
				}
				break;
			case "SubSites":
				if(preg_match("/^INSERT INTO \"SubSites\" VALUES \((?P<id>.*),(?P<site>.*),'(?P<name>.*)','(?P<address>.*)',(?P<lat>.*),(?P<lng>.*),(?P<alt>.*),(?P<ssid>.*),(?P<encryption>.*),(?P<accesspoints>.*),'(?P<dateAdded>.*)','?(?P<dateDeleted>.*)'?,'(?P<dateUpdated>.*)',(?P<active>.*)\);$/", $line, $regex)){
					$line = "INSERT INTO \"SubSites\" VALUES ({$regex["id"]},{$regex["site"]},'{$regex["name"]}','{$regex["address"]}',{$regex["lat"]},{$regex["lng"]},{$regex["alt"]},{$regex["ssid"]},{$regex["encryption"]},{$regex["accesspoints"]},'{$regex["dateUpdated"]}');";
				}
				break;
			case "APs":
				if(preg_match("/^INSERT INTO \"APs\" VALUES \((?P<id>.*),(?P<lat>.*),(?P<lng>.*),(?P<subsite>.*),'(?P<dateAdded>.*)','(?P<dateUpdated>.*)','?(?P<dateDeleted>.*)'?,(?P<active>.*)\);$/", $line, $regex)){
					$line = "INSERT INTO \"APs\" VALUES ({$regex["id"]},{$regex["lat"]},{$regex["lng"]},{$regex["subsite"]},'{$regex["dateUpdated"]}');";
					echo $line . "\n";
				}
				break;
			case "Info":
				if(preg_match("/^INSERT INTO \"Info\" VALUES \((?P<id>.*),(?P<baseversion>.*),'(?P<dateUpdated>.*)'\);$/", $line, $regex)){
					$line = "INSERT INTO \"Info\" VALUES ({$regex["id"]},{$regex["baseversion"]},'{$regex["dateUpdated"]}');";
				}
			break;
		}
  		
			file_put_contents($dbSql_Data, cnvEscapes($line), FILE_APPEND);
  		}else{
 			file_put_contents($dbSql_Proc, $line, FILE_APPEND);
  		}	
  	break;
  	default:
  		if($skipping == false){
  			file_put_contents($dbSql_Sche, $line, FILE_APPEND);
  		}else{
 			file_put_contents($dbSql_Proc, $line, FILE_APPEND);
  		}	
  	break;
  }	
}

fclose($fp);

$schema = file_get_contents($dbSql_Sche);

preg_match_all("@CREATE TABLE \"([^\"]+?)\"([^;]+);@isU", $schema, $m);		//Search through the schema for create table lines


$dbSchema = array_combine($m[1], $m[0]);

//Schema stuff here
if(!$dataonly){
	foreach($dbSchema as $table => $schema){
		$sqlite = mkSqlite3($table, $schema);
		file_put_contents(WORK_DIR . $dbSqlite, $sqlite, FILE_APPEND);
	}
}

$fp = fopen($dbSql_Data, 'r');
while(!feof($fp)){
	file_put_contents(WORK_DIR . $dbSqlite, fgets($fp), FILE_APPEND);
}
fclose($fp);

unlink($dbSql_Sche);
unlink($dbSql_Data);

echo "The sql is written to " . $dbSqlite . ' and Stored Routines if any are written to ' . $dbSql_Proc . "\n\n";

//-- functions used

/*
 * this function does all the magic to convert ansi sql create statements to 
 * those which the sqlite3 will digest; thanks to the author of the shell script at
 * http://www.ridingtheclutch.com/2008/10/08/convert-a-mysql-database-to-a-sqlite3-database.html
 */

function mkSqlite3($table, $struct){
	global $regex;
	$keys = array();
	$split = explode("\n", $struct);

	$carry = array();
	foreach($split as $k => $line){
		//echo "1:$line\n";
		$line = preg_replace($regex['f'], $regex['t'], $line);
		//echo "2:$line\n";

		switch(true){
			case (stripos(trim($line), 'PRIMARY KEY') === 0):
				# -- unset($split[$k]);
			break;
			case (stripos($line, 'enum(') !== false):
				preg_match("@enum\(([^)]*)\)@", $line, $m);
				$a = explode(',', str_replace("'", "",$m[1]));
				$g = 0;
				foreach ($a as $t){
					if(strlen($t) > $g) $g = strlen($t); 
				}
				$g = ceil( $g / 10) * 10;
				if($g > 255) $g = 255;

				$carry[] = preg_replace("@ enum\(.*\)@i", " varchar($g)", $line); 
			break;
			case (stripos($line, 'KEY ') !== false):
				$line = str_replace(' FULLTEXT','', $line);
				if(substr($line,-1) == ',')
					$line = substr($line, 0, -1);
				$keys[] = 'CREATE ' . preg_replace("@(KEY) \"([^\"]+?)\"(.*)@","INDEX idx_{$table}_$2 ON \"{$table}\"$3",trim($line)) . ';';
			break;
			default:
				$carry[] = $line;
			break;	
		}
	}
	// test and correct tailing lines commas
	if(substr($carry[(count($carry) - 2)],-1) == ',')
		$carry[(count($carry) -2)] = substr($carry[(count($carry) - 2)], 0, -1);

   $rv = join("\n",$carry);
   $f = array(' auto_increment', ' AUTO_INCREMENT',' CURRENT_TIMESTAMP');
   $t = array(' primary key autoincrement', ' primary key autoincrement', " '0000-00-00 00:00:00'");
   $rv = str_replace($f, $t, $rv);		
   $rv .= "\n" . join("\n", $keys) . "\n";

   return "-- Structure of table '$table'\n" 
   		. cnvEscapes($rv)
   		. "\n\n";
}

function cnvEscapes($str){
	global $escs; 
	return preg_replace($escs['f'], $escs['t'], $str);
}

