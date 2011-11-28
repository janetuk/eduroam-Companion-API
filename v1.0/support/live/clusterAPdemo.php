<?php

/* clusterAP.php
 * Created by: Ashley Browning
 * Created on: 02/03/2011
 * Version: v1.00 (02/03/2011)
 */

/* This script is used to create Access Points (APs) by clustering tags together

*/

//////////////////
//GLOBAL VARIABLES
//////////////////

error_reporting(E_ALL);


$APRANGE = 0.020;	//Radius of an Access Point in Km
$DIAMETER = $APRANGE * 2;
$REPORT_ERRORS = 0;
$MINIMUM_NO_OF_TAGS_FOR_CLUSTER = 2;


/////////////////////
//Connect to database
/////////////////////
require_once('../inc/dbcred.php");

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdbdbdb);
if(mysqli_connect_errno()){
	if($REPORT_ERRORS == 1){
		die('{"error":{"code":1, "msg":"Issue with connecting to database"}}');
	}
}


///////////
//Get sites
///////////
$sites;		//store all the of site IDs

//Get all of the sites, processes them, then find out if they are currently active or not
$query = "SELECT id FROM SubSites";
$stmt = $mysqli->prepare($query);
if(!$stmt->execute()){
	if($REPORT_ERRORS == 1){
		die('{"error":{"code":2, "msg":"Problem with SQL - selecting site ids"}}');
	}
}
$stmt->bind_result($siteid);

$i = 0;
while($stmt->fetch()){
	$sites[$i] = $siteid;
	$i++;
}
$stmt->close();


/////////////////
//START ALGORITHM
/////////////////

//SITE LOOP - Loop through each site
for($i = 0; $i<count($sites); $i++){


	$tags = array();		//Associative array to store tags based on tag id - keeps track of unclustered tags
	$finalClusters = array();		//Stores all of the clusters to be pushed to the database
	
	$existingAPs = array();		//List of existing accesspoints
	
	//Get the tags for the site
	$query = "SELECT id, lat, lng, accuracy, user, UNIX_TIMESTAMP(tagtime) FROM Tags WHERE subsite = ?";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $sites[$i]);
	if(!$stmt->execute()){
		if($REPORT_ERRORS == 1){
			die('{"error":{"code":3, "msg":"Problem with SQL - getting the tags for a site"}}');
		}
	}
	$stmt->bind_result($result["id"], $result["lat"], $result["lng"], $result["accuracy"], $result["user"], $results["tagdate"]);
	
	$j = 0;
	while($stmt->fetch()){
		//Load into an array that will track the 'un clustered' tags
		//$tags[$j] = new Tag($result["id"], $result["lat"], $result["lng"]);
		$tags[$result["id"]] = new Tag($result["id"], $result["lat"], $result["lng"], $result["accuracy"], $result["user"], $results["tagdate"]);
		$j++;
		//echo "Tag: {$tags[$result["id"]]->__toString()}\n";
	}
	
	$stmt->close();


	
	/////////////////////////////////
	//BUILD EUCLUDIAN DISTANCE MATRIX
	/////////////////////////////////
	//Reference by tag ID no
	//$matrix[count($tags)][count($tags)];
	$matrix = array();
	
	foreach($tags as $tag1){
		foreach($tags as $tag2){
			$matrix[$tag1->getID()][$tag2->getID()] = haversine($tag1, $tag2);
		}
	}
	
	//print_r($matrix);
	
	//////////////
	//GET CLUSTERS
	//////////////

	//UNASSIGNED TAG LOOP - For all the tags that are still not assigned to a proper cluster
	while(count($tags) > 0){

		$clusters = array();	//Associative array to store clusters 
	
		//Now that we have all the data, lets get clustering!
		//For every tag unassigned, we will create a cluster starting with that tag
		$j = 0;
		
		//CANDIDATE CLUSTER LOOP - Start a cluster from every unassigned tag
		foreach($tags as $currentTag){
			
			$unclusteredTags = $tags;		//Tags not in a candidate cluster
			unset($unclusteredTags[$currentTag->getID()]);		//Remove tag starting off the cluster 			
			$clusters[$j] = new Cluster($currentTag);
			
			//Loop through unclustered tags
			$nofTags = count($unclusteredTags);
			
			
			//UNASSIGNED CANDIDATE CLUSTER TAG LOOP - for all tags that are not part of the initial cluster - ensures coverage
			for($k=0; $k<$nofTags; $k++){
			

				//Find the closest tag to the cluster - this is defined by the tag closest to the furthest tag within the cluster
				$closestTag = null;
				$distance = null;
				
				//print_r($clusters[$j]->getTags());
				//Problem with the inner bit, $closest tag is not being assigned anything!
				
				//UNASSIGNED CANDIDATE CLUSTER DISTANCE LOOP For all the tags yet to be assigned to a candidate cluster
				foreach($unclusteredTags as $tempTag){
								

					$tagdistance = null;
					
					//CLUSTER TAG DISTANCE LOOP - needs tempTag, cluster's Tags, distance Matrix
					foreach($clusters[$j]->getTags() as $clusterTag){

						
						//Loop initialisation
						if($tagdistance == null){
							$tagdistance = $matrix[$tempTag->getID()][$clusterTag->getID()];
							continue;
						}
						
						//echo count($clusterTag) . "\n";
						
						if($tagdistance < $matrix[$tempTag->getID()][$clusterTag->getID()]){
							$tagdistance = $matrix[$tempTag->getID()][$clusterTag->getID()];
						}
					}

					
					if($distance == null || $closestTag == null){
						$distance = $tagdistance;
						$closestTag = $tempTag;
					}
					
					
					//If the distance between the tag and cluster is smaller than the current, then update
					if($distance > $tagdistance){
						$distance = $tagdistance;
						$closestTag = $tempTag;
					}
				}
				
				//If the closest tag is closer than the diameter of the network, then add to cluster
				if($distance <= $DIAMETER){
					$clusters[$j]->addTag($closestTag);
					unset($unclusteredTags[$closestTag->getID()]);
				} else {
					break; //No point in continuing as closest tag is too far away
				}
			}
			$j++;
		}
		
		//Now that we have the candidate clusters, remove tags from the same user, keeping the most recent one
		
		
		
		//CHOOSING CANDIDATE CLUSTER - We have clusters, need to see which has the most members
		if(count($clusters) > 0){
			
			//echo "\n Starting new loop \n";
			$candidateCluster = $clusters[0];
			for($j=1; $j<count($clusters); $j++){
				//print_r($clusters);	
				//echo "number of unique users in cluster: " . $clusters[$j]->getUniqueSize() . "\n";		
				//print_r($clusters[$j]);
				if($clusters[$j]->getSize() > $candidateCluster->getSize()){
					$candidateCluster = $clusters[$j];
				}
			}
			
/*
			echo "A candidate cluster\n";
			print_r($candidateCluster);
*/
			//If there are more tags than the minimum amount, then add to final clusters
			if($candidateCluster->getSize() >= $MINIMUM_NO_OF_TAGS_FOR_CLUSTER){
				$finalClusters[] = $candidateCluster;
			}
			
			//echo "Cluster: $candidateCluster\n";		
		
			//Remove tags in the cluster from tags array
			foreach($candidateCluster->getTags() as $rmTag){
				//echo "about to remove \n";
				unset($tags[$rmTag->getID()]);
			}
		}
	}
	
	////////////////////////////////////////////////////////////
	//HAVE ALL THE CLUSTERS, LOOK TO ADDING THEM TO THE DATABASE
	////////////////////////////////////////////////////////////
	$active;
	
/*
	if($sites[$i] == 1577){
		echo "Clusters: \n";
		print_r($finalClusters);
	}
*/
/*
	if($sites[$i] == 1577){
		echo "Done the clustering\n";
		echo "Final Clusters has " . count($finalClusters) . "\n";
	}
*/
	
	
	//check if the subsite is currently active
	//echo "Site ID: " . $sites[$i] . "\n";
	$query = "SELECT operation FROM SubSiteHistory WHERE subsite = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
	$stmt = $mysqli->prepare($query);
	//echo "SQL Error: " . $mysqli->error;
	$stmt->bind_param("i", $sites[$i]);
	$stmt->execute();
	$stmt->bind_result($result["operation"]);
	while($stmt->fetch()){
	}
	
	if($result["operation"] == 3){
		//Means the site is currently inactive
		$active = 0;
	} else {
		//the site is currently active
		$active = 1;
	}
		
	$stmt->close();
	
	//Get all of the access points currently in the database for the subsite


	$query = "SELECT id, lat, lng, subsite, rating FROM APs WHERE subsite = ?";
	$stmt = $mysqli->prepare($query);
	$stmt->bind_param("i", $sites[$i]);
	$stmt->execute();
	$stmt->bind_result($result["id"], $result["lat"], $result["lng"], $result["subsite"], $result["rating"]);
	$j = 0;
	while($stmt->fetch()){
		$existingAPs[$result["id"]] = new AP($result["id"], $result["lat"], $result["lng"], $result["rating"], $result["subsite"]);
	}
	$stmt->close();
	
/*
	if($sites[$i] == 1577){
		echo "final clusters: \n";
		print_r($finalClusters);
	}
*/
	
	
	//Get details for each cluster and filter ones that have less than 2 unique tags
	//foreach($finalClusters as $cluster){
	for($j=0; $j<count($finalClusters); $j++){
		$cluster = $finalClusters[$j];

		//prepare the values here
		$coord = $cluster->getCentre();
		$cluster->setLat($coord["lat"]);
		$cluster->setLng($coord["lng"]);
		$cluster->setSubSite($sites[$i]);
		if($cluster->getSize() >= (10 + $MINIMUM_NO_OF_TAGS_FOR_CLUSTER)){
			//give it a rating of 1
			$cluster->setRating(1);
		} else {
			//give it a rating of uniquesize/10
			$cluster->setRating(($cluster->getSize() - $MINIMUM_NO_OF_TAGS_FOR_CLUSTER + 1) / 10);
		}
	}
	
	
/*
	if ($sites[$i] == 1577){
		print_r($finalClusters);
	}
*/
	$clustersToAdd = array();
	
	if(count($existingAPs) == 0){
		foreach($finalClusters as $cluster){
			$clustersToAdd[] = $cluster;
		}
	} else {
		//Loop through each cluster and see if it already exists.
		foreach($finalClusters as $cluster){
		
		//print_r($cluster);
			if(count($existingAPs) == 0){
				$clustersToAdd[] = $cluster;
				break;
			}
			
			//for each existing AP
			$entryFlag = 0;
			foreach($existingAPs as $ap){
			
				//echo "in the existing AP loop\n";
				
				if(($cluster->getLat() == $ap->getLat()) && ($cluster->getLng() == $ap->getLng()) && ($cluster->getRating() == $ap->getRating())){
					//If cluster and existingAP are the same, then update APTags, check current status of APHistory
					
					//echo "Cluster is same as an AP!\n";
					
					//Sort out APTags
					//Delete entries for the given AP
					$query = "DELETE FROM APTags WHERE AP = ?";
					$stmt = $mysqli->prepare($query);
					$stmt->bind_param("i", $ap->getID());
					if(!$stmt->execute()){
						if($REPORT_ERRORS == 1){
							die('{"error":{"code":7, "msg":"Delete APTag failed"}}');
						}
					}
					$stmt->close();
					
					//Enter all of the tags into AP tags
					$tagid;
					$query = "INSERT INTO APTags (AP, Tag) VALUES (?, ?)";
					$stmt = $mysqli->prepare($query);
					$stmt->bind_param("ii", $ap->getID(), $tagid);
					foreach($cluster->getTags() as $tag){
						$tagid = $tag->getID();
						if(!$stmt->execute()){
							if($REPORT_ERRORS == 1){
								die('{"error":{"code":8, "msg":"Insert APTag failed"}}');
							}
						}
					}
					
					$stmt->close();
					
					//Check whether the ap is currently active, compare to the subsite
					$query = "SELECT operation FROM APHistory WHERE ap = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
					$stmt = $mysqli->prepare($query);
					$stmt->bind_param("i", $ap->getID());
					$stmt->execute();
					$stmt->bind_result($result["operation"]);
					while($stmt->fetch()){
					}
					$stmt->close();
					
					$APactive;
					if($result["operation"] == 3){
						//Means the site is currently inactive
						$APactive = 0;
					} else {
						//the site is currently active
						$APactive = 1;
					}
					
					//If AP and Subsite are not the same active
					if($APactive != $active){
						//Amend by inserting into APHistory
						$date = time();
						if($active == 0){
							//Pop in a delete entry into APHistory
							$op = 3;
							$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
							$stmt = $mysqli->prepare($query);
							$stmt->bind_param("iii", $ap->getID(), $op, $date);
							if(!$stmt->execute()){
								if($REPORT_ERRORS == 1){
									die('{"error":{"code":9, "msg":"Insert delete APHistory failed"}}');
								}
							}
							$stmt->close();
						} else {
							//Insert an add
							$op = 1;
							$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
							$stmt = $mysqli->prepare($query);
							$stmt->bind_param("iii", $ap->getID(), $op, $date);
							if(!$stmt->execute()){
								if($REPORT_ERRORS == 1){
									die('{"error":{"code":10, "msg":"Insert add APHistory failed"}}');
								}
							}
							$stmt->close();	
						}
					}
					
					//remove ap and cluster from their arrays
					unset($existingAPs[$ap->getID()]);
					$entryFlag = 1;
					//Do not need to add to clusterToAdd as we have dealt with it
					break;				
				} else {
					//Need to deal with this cluster in a bit
					continue;
				}
			}
			
			//If at the end of the loop nothing has happened, add to clustersToAdd
			if($entryFlag == 0){
				$clustersToAdd[] = $cluster;
			}
		}
	}
	
	
	//Add the clusters currently in finalclusters into the clusterstoadd
/*
	foreach($finalClusters as $cluster){
		$clustersToAdd[] = $cluster;
	}
*/
	
/*
	if($sites[$i] == 1577){
		echo "ClustersToAdd: \n";
		print_r($clustersToAdd);
	}
*/
	if($sites[$i] == 1577){
		echo "Clusters to add: " . count($clustersToAdd) . "\n";
		//print_r($clustersToAdd);
	}
	$index = 0;
	$nofClusters = count($clustersToAdd);
	//Add the clusters to existing APs by overwriting them
	foreach($existingAPs as $ap){
		
		//Make sure that we have a cluster to put away
		if($index < $nofClusters){
			$cluster = $clustersToAdd[$index];
			
			echo "trying to print cluster of index: " . $index . "\n";
			//print_r($clustersToAdd[$index]);
			
			//update data in the AP table
			$query = "UPDATE APs SET lat=?, lng=?, rating=? WHERE id=?";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("dddi", $cluster->getLat(), $cluster->getLng(), $cluster->getRating(), $ap->getID());
			if(!$stmt->execute()){
				if($REPORT_ERRORS == 1){
					die('{"error":{"code":11, "msg":"Update to existing AP failed"}}');
				}
			}
			$stmt->close();
			
			//Update APTags
			
			//Remove all APTags for AP
			$query = "DELETE FROM APTags WHERE AP = ?";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("i", $ap->getID());
			if(!$stmt->execute()){
				if($REPORT_ERRORS == 1){
					die('{"error":{"code":12, "msg":"Delete APTag failed"}}');
				}
			}
			$stmt->close();
			
			//Enter all of the tags into AP tags
			$tagid;
			$query = "INSERT INTO APTags (AP, Tag) VALUES (?, ?)";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("ii", $ap->getID(), $tagid);
			foreach($cluster->getTags() as $tag){
				$tagid = $tag->getID();
				if(!$stmt->execute()){
					if($REPORT_ERRORS == 1){
						die('{"error":{"code":14, "msg":"Insert APTag failed"}}');
					}
				}
			}
			$stmt->close();	
	
			
			
			//Update AP History
			//Check whether the ap is currently active, compare to the subsite
			$query = "SELECT operation, UNIX_TIMESTAMP(date) FROM APHistory WHERE ap = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("i", $ap->getID());
			$stmt->execute();
			$stmt->bind_result($result["operation"], $result["date"]);
			while($stmt->fetch()){
			}
			$stmt->close();
			$addate = $result["date"];		//store the date of the previous add/delete
			
			$APactive;
			if($result["operation"] == 3){
				//Means the site is currently inactive
				$APactive = 0;
			} else {
				//the site is currently active
				$APactive = 1;
			}
			
			if($APactive == $active){
			
				$toInsert = 0;
				//see if there is a previous update, if there is, just update the date
				$query = "SELECT id, UNIX_TIMESTAMP(date) FROM APHistory WHERE ap = ? AND operation = 2 ORDER BY date DESC LIMIT 1";
				$stmt = $mysqli->prepare($query);
				$stmt->bind_param("i", $ap->getID());
				$stmt->execute();
				$stmt->bind_result($result["id"], $result["date"]);
				$stmt->store_result();
				if($stmt->num_rows == 0){
					//No previous update, so insert is needed
					$toInsert = 1;
				}
				while($stmt->fetch()){
				}

				$stmt->close();
			
				if ($toInsert == 1){
				
					//update
					$date = time();
					$op = 2;
					$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
					$stmt = $mysqli->prepare($query);
					$stmt->bind_param("iii", $ap->getID(), $op, $date);
					if(!$stmt->execute()){
						if($REPORT_ERRORS == 1){
							die('{"error":{"code":24, "msg":"Insert update APHistory failed"}}');
						}
					}
					$stmt->close();	
				} else {
					
					//if happened after the last add/delete, then update
					if ($result["date"] > $addate){
						//update
						$date = time();
						$query = "UPDATE APHistory SET date=FROM_UNIXTIME(?) WHERE id=?";
						$stmt = $mysqli->prepare($query);
						$stmt->bind_param("ii", $date, $result["id"]);
						if(!$stmt->execute()){
							if($REPORT_ERRORS == 1){
								die('{"error":{"code":25, "msg":"Insert update APHistory failed"}}');
							}
						}
						
					} else {
						//insert
						$date = time();
						$op = 2;
						$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
						$stmt = $mysqli->prepare($query);
						$stmt->bind_param("iii", $ap->getID(), $op, $date);
						if(!$stmt->execute()){
							if($REPORT_ERRORS == 1){
								die('{"error":{"code":26, "msg":"Insert update APHistory failed"}}');
							}
						}
						$stmt->close();	
					}
					
				}

			} else {
				//Amend by inserting into APHistory
				$date = time();
				if($active == 0){
					//Pop in a delete entry into APHistory
					$op = 3;
					$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
					$stmt = $mysqli->prepare($query);
					$stmt->bind_param("iii", $ap->getID(), $op, $date);
					if(!$stmt->execute()){
						if($REPORT_ERRORS == 1){
							die('{"error":{"code":16, "msg":"Insert delete APHistory failed"}}');
						}
					}
					$stmt->close();	
				} else {
					//Insert an add
					$op = 1;
					$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
					$stmt = $mysqli->prepare($query);
					$stmt->bind_param("iii", $ap->getID(), $op, $date);
					if(!$stmt->execute()){
						if($REPORT_ERRORS == 1){
							die('{"error":{"code":17, "msg":"Insert add APHistory failed"}}');
						}
					}
					$stmt->close();	
				}			
			}		
			
			//remove ap and cluster
			unset($existingAPs[$ap->getID()]);
			unset($clustersToAdd[$index]);			
		} else {
			//There are more APs than Clusters, so deactivate those aps if not already deactivated 
			
			//Check whether the ap is currently active, compare to the subsite
			$query = "SELECT operation FROM APHistory WHERE ap = ? AND (operation = 1 OR operation = 3) ORDER BY date DESC LIMIT 1";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("i", $ap->getID());
			$stmt->execute();
			$stmt->bind_result($result["operation"]);
			while($stmt->fetch()){
			}
			$stmt->close();
			
			$APactive;
			if($result["operation"] == 1){
				//Means the site is currently active, so lets change that
				$op = 3;
				$date = time();
				$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
				$stmt = $mysqli->prepare($query);
				$stmt->bind_param("iii", $ap->getID(), $op, $date);
				if(!$stmt->execute()){
					if($REPORT_ERRORS == 1){
						die('{"error":{"code":18, "msg":"Insert delete APHistory failed"}}');
					}
				}
				$stmt->close();
			} 		
			
			

		}
		$index++;
	}
	
	//if there are clusters still awaiting entry, add them if site is active
	if($active == 1){
		foreach($clustersToAdd as $cluster){
		
			//Create a new AP 
			$query = "INSERT INTO APs (lat, lng, subsite, rating) VALUES (?, ?, ?, ?)";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("ddid", $cluster->getLat(), $cluster->getLng(), $cluster->getSubSite(), $cluster->getRating());
			if(!$stmt->execute()){
				if($REPORT_ERRORS == 1){
					die('{"error":{"code":19, "msg":"Insert AP failed"}}');
				}
			}
			$apID = $mysqli->insert_id;
			$stmt->close();
			
			//Insert the APTags
			//Enter all of the tags into AP tags
			$tagid;
			$query = "INSERT INTO APTags (AP, Tag) VALUES (?, ?)";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("ii", $apID, $tagid);
			foreach($cluster->getTags() as $tag){
				$tagid = $tag->getID();
				if(!$stmt->execute()){
					if($REPORT_ERRORS == 1){
						die('{"error":{"code":20, "msg":"Insert APTag failed"}}');
					}
				}
			}
			$stmt->close();	
	
			//Add to APHistory
			//Insert an add
			$date = time();
			$op = 1;
			$query = "INSERT INTO APHistory (ap, operation, date) VALUES (?, ?, FROM_UNIXTIME(?))";
			$stmt = $mysqli->prepare($query);
			$stmt->bind_param("iii", $apID, $op, $date);
			if(!$stmt->execute()){
				if($REPORT_ERRORS == 1){
					die('{"error":{"code":21, "msg":"Insert add APHistory failed"}}');
				}
			}
			$stmt->close();	
		}
	}
}

function haversine($t1, $t2){
	
		$r = 6371;	//Radius of the Earth in Km
		
		//Funky Maths
		$dlat = deg2rad($t2->getLat() - $t1->getLat());
		$dlng = deg2rad($t2->getLng() - $t1->getLng());
		$a = 	(sin($dlat/2) * sin($dlat/2)) +
				(cos(deg2rad($t1->getLat())) * cos(deg2rad($t2->getLat())) *
				(sin($dlng/2) * sin($dlng/2)));
		$c =	2 * atan2(sqrt($a), sqrt(1-$a));
		$d =	$r * $c;
		
		return $d; //This is the distance between two points in Km
}





////////////
//Cluster represents a collection of tags 
////////////
class Cluster{

	private $tags;
	private $uniqueTags;
	private $mlat;
	private $mlng;
	private $rating;
	private $subsite;
	
	public function __construct($startTag){
		$this->tags = array();
		$this->uniqueTags = array();
		$this->tags[] = $startTag;
		$this->uniqueTags[] = $startTag;
	}
	
	public function addTag($tag){
		//Add to the main array
		$this->tags[] = $tag;
		
		//Check for uniqueness
		$flag = 0; 	//Flag to see if the tag has been dealt with
		
		for($i=0; count($this->uniqueTags)>$i; $i++){
			if($this->uniqueTags[$i]->getUser() == $tag->getUser()){
				$flag = 1;	//found a match
				if($this->uniqueTags[$i]->getTagdate() < $tag->getTagdate()){
					//Same user and newer, so replace
					$this->uniqueTags[$i] = $tag;
				} else {
					//not newer, so continue
					break;
				}
			}
		}
		
		//If nothing is found, add to the array
		if($flag == 0){
			$this->uniqueTags[] = $tag;
		}
	}
	
	public function isInCluster($tag){
		return in_array($tag, $this->tags);
	}
	
	public function getTags(){
		return $this->tags;
	}
	
	public function getUniqueTags(){
		return $this->uniqueTags;
	}
	
	public function getSize(){
		//echo "get size: " . count($this->tags) . "\n";
		//echo "GET SIZE\n";
		//print_r($this->tags);
		return count($this->tags);
	}
	
	//Get the number of user-unique tags
	public function getUniqueSize(){
		//Return the count of the array when done
		return count($this->uniqueTags);
	}
	
	public function getCentre(){
			
		//Convert from lat/lng to x,y,z
		$x = 0;
		$y = 0;
		$z = 0;
		
		foreach($this->tags as $tag){
		
			$lat = deg2rad($tag->getLat());
			$lng = deg2rad($tag->getLng());
		
			$x += cos($lat) * cos($lng);
			$y += cos($lat) * sin($lng);
			$z += sin($lat);
		}
		
		//average the x,y,z
		$x = $x/count($this->tags);
		$y = $y/count($this->tags);
		$z = $z/count($this->tags);

		
		//convert back to lat/lng
		$coord["lng"] = rad2deg(atan2($y, $x));
		$hyp = sqrt(($x * $x) + ($y * $y));
		$coord["lat"] = rad2deg(atan2($z, $hyp));
		
		return $coord;
		
	}
	
	public function setLat($lat){
		$this->mlat = $lat;
	}
	
	public function setLng($lng){
		$this->mlng = $lng;
	}
	
	public function setSubSite($site){
		$this->subsite = $site;
	}
	
	public function setRating($rating){
		$this->rating = $rating;
	}
	
	public function getLat(){
		return $this->mlat;
	}
	
	public function getLng(){
		return $this->mlng;
	}
	
	public function getRating(){
		return $this->rating;
	}
	
	public function getSubSite(){
		return $this->subsite;
	}

	public function __toString(){
		$output = "";
		foreach($this->tags as $tag){
			$output .= $tag->getID() . ", ";
		}
		return $output;
	}

}




//This describes a Tag
class Tag{

	private $lat;
	private $lng;
	private $id;
	private $accuracy;
	private $user;
	private $tagdate;

	public function __construct($id, $lat, $lng, $accuracy, $user, $date){
		$this->lat = $lat;
		$this->lng = $lng;
		$this->id = $id;
		$this->accuracy = $accuracy;
		$this->user = $user;
		$this->tagdate = $date;
	}
	
	public function getLat(){
		return $this->lat;
	}

	public function getLng(){
		return $this->lng;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getAccuracy(){
		return $this->accuracy;
	}
	
	public function getUser(){
		return $this->user;
	}
	
	public function getTagdate(){
		return $this->tagdate;
	}

	public function __toString(){
		return $this->id . " : (" . $this->lat . ", " . $this->lng . ")";
	}

}


//This class describes an access point
class AP{
	
	private $id;
	private $lat;
	private $lng;
	private $rating;
	private $subsite;
	
	public function __construct($id, $lat, $lng, $rating, $subsite){
		$this->id = $id;
		$this->lat = $lat;
		$this->lng = $lng;
		$this->rating = $rating;
		$this->subsite = $subsite;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getLat(){
		return $this->lat;
	}
	
	public function getLng(){
		return $this->lng;
	}
	
	public function getRating(){
		return $this->rating;
	}

	public function getSubSite(){
		return $this->subsite;
	}

}





?>


