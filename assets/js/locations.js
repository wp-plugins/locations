if (typeof($) == 'undefined' && typeof(jQuery) != 'undefined') {
	$ = jQuery;
} else {
	$ = function () { };
}

var $_gp_map;
var $_gp_markers = [];

function gp_initMap()
{
	var originLatLng = new google.maps.LatLng($_gp_map_center.lat, $_gp_map_center.lng);

	var mapOptions = {
		zoom: 11,
		center: originLatLng
	};

	$_gp_map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

	var marker = new google.maps.Marker({
		position: originLatLng,
		map: $_gp_map,
		title: 'You Are Here',
		icon: {
			path: google.maps.SymbolPath.CIRCLE,
			scale: 8,
			strokeColor: 'green'
		}		
	});
	  
	
}

function gp_addLocationMarkers($_gp_map_locations)
{
	var i, me, myPoint, contentString, infowindows = [];
	var markerBounds = new google.maps.LatLngBounds();
	var originLatLng = new google.maps.LatLng($_gp_map_center.lat, $_gp_map_center.lng);
	markerBounds.extend(originLatLng);

	for (i in $_gp_map_locations)
	{
		me = $_gp_map_locations[i];
		myPoint = new google.maps.LatLng(me.lat, me.lng);


		contentString = 
		'<div id="content">'+
			'<div id="siteNotice"></div>' +
			'<h1 id="firstHeading" class="firstHeading">' + me.title + '</h1>'+
			'<div id="bodyContent">' +
				'<p class="addr">' + me.address + '</p>';
		
		if (typeof(me.phone) !== 'undefined') {
			contentString += '<p class="phone" style="margin-bottom: 4px"><strong>Phone:</strong> ' + me.phone + '</p>';
		}
		if (typeof(me.fax) !== 'undefined') {
			contentString += '<p class="fax" style="margin-bottom: 4px"><strong>Fax:</strong> ' + me.fax + '</p>';
		}
		if (typeof(me.email) !== 'undefined') {
			contentString += '<p class="email" style="margin-bottom: 4px"><strong>Email:</strong> <a href="mailto:' + me.email + '">' + me.email + '</p>';
		}
		
		contentString +=
			'</div>' +
		'</div>';


		$_gp_markers[i] = new google.maps.Marker({
			position: myPoint,
			map: $_gp_map,
			title: me.title,
			html: contentString
		});
		


		infowindows[i] = new google.maps.InfoWindow({
			content: contentString,
			maxWidth: 450
		});

		google.maps.event.addListener($_gp_markers[i], 'click', function() {
			infowindows[i].setContent(this.html);
			infowindows[i].open($_gp_map,this);
		});
		
		markerBounds.extend(myPoint);
	}
	$_gp_map.fitBounds(markerBounds);
	
}

$(function ()
{
	// look for a map data that needs to be drawn
	if(typeof($_gp_map_locations) != 'undefined') {
		gp_initMap();
		gp_addLocationMarkers($_gp_map_locations);
	}
});
