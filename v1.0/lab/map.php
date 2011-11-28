<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
<style type="text/css">
  html { height: 100% }
  body { height: 100%; margin: 0px; padding: 0px }
  #map_canvas { height: 100% }
</style>
<script type="text/javascript"
    src="http://maps.google.com/maps/api/js?sensor=false">
</script>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js">
</script>
<script type="text/javascript" src="https://eduroam-app-api.dev.ja.net/v1.0/lab/markerclusterer.js">
</script>
<script type="text/javascript">
	$(document).ready(function(){
	
 	//Initialising the map
    var latlng = new google.maps.LatLng(50.93, -1.4);
    var myOptions = {
      zoom: 12,
      center: latlng,
      mapTypeId: google.maps.MapTypeId.ROADMAP,
      disableDoubleClickZoom: 1
    };
    var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
    
    

	/////////////////
	//Event Listeners
	/////////////////
	google.maps.event.addListener(map, 'idle', refresh);
	google.maps.event.addListener(map, 'dblclick', addTag);
	
	var markers = [];
	var tags = [];		//Tags
	var aps = [];		//Access Points
	var apcircle = [];	//Access Point Circles


	var latlng = new google.maps.LatLng(50.9375, -1.3972);
	var icon = 'https://eduroam-app-api.dev.ja.net/v1.0/lab/apmarker.png';

	
/*
	var marker = new google.maps.Marker({
		position: latlng,
		title: "centre of circle",
		icon: icon,
		map: map
	});

	var circle = new google.maps.Circle({
		map: map,
		radius: 15,
		//center: latlng,
		fillColor: '#0000FF',
		fillOpacity: 0.15,
		strokeColor: '#FFFFFF',
		strokeWeight: 1,
		strongOpacity: 0.8,
		clickable: 0
	});
	
	circle.bindTo('center', marker, 'position');
	
	
	var latlng = new google.maps.LatLng(50.9375, -1.3965);
	var marker = new google.maps.Marker({
		position: latlng,
		title: "centre of circle",
		icon: icon,
		map: map
	});

	var circle = new google.maps.Circle({
		map: map,
		radius: 15,
		//center: latlng,
		fillColor: '#0000FF',
		fillOpacity: 0.15,
		strokeColor: '#FFFFFF',
		strokeWeight: 1,
		strongOpacity: 0.8,
		clickable: 0
	});
	
	circle.bindTo('center', marker, 'position');
*/
		
	/////////////////////
	//Get all the markers
	/////////////////////
	$.getJSON("https://eduroam-app-api.dev.ja.net/v1.0/lab/getmarkers.php?lngmin="+0+"&lngmax="+0+"&latmin="+0+"&latmax="+0+"&zoom="+1, function(data){
		
		//If there has been an error, display the error to console
		if('error' in data){
			console.debug(data.error);
		} else {
			console.debug("Results: "+ data.results);
			if(data.results != 0){
				$.each(data.markers, function(key, value){
					//For each marker, create a new marker and add it to the array
					//console.debug("Marker: "+value.name);
					var latlon = new google.maps.LatLng(value.lat, value.lng);
					var marker = new google.maps.Marker({
						position: latlon,
						title: value.name
					});
					markers.push(marker);
					//console.debug(marker.getTitle());
				});
			}	
		}
		
		var options = {
			gridSize: 50,
			maxZoom: 12
		};
		var markerCluster = new MarkerClusterer(map, markers, options);
	
	});

		console.debug("Done Markers");
		
		
	function refresh(){
		getAPs();
		getTags();
	}

	
	/////////
	//getTags - used to get all the tags for Eduroam sites
	/////////
	function getTags(){
		
		
		//Remove all tags from the array
		//var i = 0;
		for(i=0; i<tags.length; i++){
			tags[i].setMap(null);
		}
		
		//Restrict calls when zoomed out too far
		var zoom = map.getZoom();
		if(zoom < 14){
			return 0;
		}

		//Get the bounds of the screen
		var latmin = map.getBounds().getSouthWest().lat();
		var latmax = map.getBounds().getNorthEast().lat();
		var lngmin = map.getBounds().getSouthWest().lng();
		var lngmax = map.getBounds().getNorthEast().lng();
	
		//Where the JSON call magic happens
		$.getJSON("https://eduroam-app-api.dev.ja.net/v1.0/lab/gettags.php?lngmin="+lngmin+"&lngmax="+lngmax+"&latmin="+latmin+"&latmax="+latmax+"&zoom="+zoom, function(data){
			//If an error has occured server side, output to console
			if('error' in data){
				console.debug(data.error);
			} else {
				console.debug("Tag Results: " + data.results);
				var i = 0;
				if(data.results != 0){
					$.each(data.tags, function(key, value){
						var latlon = new google.maps.LatLng(value.lat, value.lng);
						var icon = 'https://eduroam-app-api.dev.ja.net/v1.0/lab/tagred.png';
						var marker = new google.maps.Marker({
							position: latlon,
							map: map,
							icon: icon,
							title: value.id + ''
						});
						tags[i] = marker;
						i++;
					});
				}
			}
		});
	}
	
		function getAPs(){
		
		//Remove all tags from the array
		//var i = 0;
		for(i=0; i<aps.length; i++){
			aps[i].setMap(null);
		}
		
		for(i=0; i<apcircle.length; i++){
			apcircle[i].setMap(null);
		}
		
		//Restrict calls when zoomed out too far
		var zoom = map.getZoom();
		if(zoom < 14){
			return 0;
		}

		//Get the bounds of the screen
		var latmin = map.getBounds().getSouthWest().lat();
		var latmax = map.getBounds().getNorthEast().lat();
		var lngmin = map.getBounds().getSouthWest().lng();
		var lngmax = map.getBounds().getNorthEast().lng();
		
		var icon = new google.maps.MarkerImage(
			'https://eduroam-app-api.dev.ja.net/v1.0/lab/apmarker.png',
			new google.maps.Size(15,15),
			new google.maps.Point(0,0),
			new google.maps.Point(7,7)
		);
			console.debug("IN the getAPs function!");

		//Where the JSON call magic happens
		$.getJSON("https://eduroam-app-api.dev.ja.net/v1.0/lab/getaps.php?lngmin="+lngmin+"&lngmax="+lngmax+"&latmin="+latmin+"&latmax="+latmax+"&zoom="+zoom, function(data){
			//If an error has occured server side, output to console
			if('error' in data){
				console.debug(data.error);
			} else {
				console.debug("AP Results: " + data.results);
				var i = 0;
				if(data.results != 0){
					$.each(data.aps, function(key, value){
						var latlon = new google.maps.LatLng(value.lat, value.lng);
						var marker = new google.maps.Marker({
							position: latlon,
							map: map,
							icon: icon,
							title: 'access point'
						});
						var circle = new google.maps.Circle({
							map: map,
							radius: value.range,
							//center: latlng,
							fillColor: '#0000FF',
							fillOpacity: 0.15,
							strokeColor: '#FFFFFF',
							strokeWeight: 1,
							strongOpacity: 0.8,
							clickable: 0
						});
						
						circle.bindTo('center', marker, 'position');

						
						aps[i] = marker;
						apcircle[i] = circle;
						
						i++;
					});
				}
			}
		});
	}
	
	
	function addTag(mouse){
	
		if(map.getZoom() < 14){
			return 0;
		}
		
		var lat = mouse.latLng.lat();
		var lng = mouse.latLng.lng();
		var user = 'webash';
		var site = 1577;
		var accuracy = 10;
		
		$.getJSON("https://eduroam-app-api.dev.ja.net/v1.0/lab/tag.php?lat="+lat+"&lng="+lng+"&user="+user+"&site="+site+"&accuracy="+accuracy, function(data){
			if('error' in data){
				console.debug(data.error.msg);
			} else {
				refresh();
			}
		});	
	}
	
	
  });
</script>
</head>
<body>
  <div id="map_canvas" style="width:100%; height:100%"></div>
</body>
</html>



















