$(document).ready(function() {

	// New markers
	var newStyle = {
		"color": "#000",
		"weight": 2,
		"opacity": 1,
		"radius": 6,
		"fillColor": "#fc2302",
		"fillOpacity": 1
	};

	// Old markers
	var oldStyle = {
		"color": "#000",
		"weight": 1,
		"opacity": 1,
		"radius": 4,
		"fillColor": "#ADE66F",
		"fillOpacity": 0.5
	};

	mapzen.whosonfirst.leaflet.tangram.scenefile('tangram/refill.yaml');
	var map, marker, queue = [], timeout, waiting = false;
	$.get('../flight-lines.php?method=get_locations', function(rsp) {
		//var bbox = rsp.geom_bbox.split(',');
		var bbox = [-74.0313720703125, 40.63010897068533,
		            -73.76323699951172, 40.812510020091956];
		map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox('map',
			parseFloat(bbox[1]), parseFloat(bbox[0]),
			parseFloat(bbox[3]), parseFloat(bbox[2])
		);
		window.map = map;

		$.each(rsp.locations, function(i, location) {
			showMarker({
				lat: parseFloat(location.location_lat),
				lng: parseFloat(location.location_lng),
				marker: location.location_title
			}, true);
		});

	});

	function checkQueue() {
		if (! waiting) {
			if (queue.length > 0) {
				showMarker(queue.shift());
			}
		}
	}

	function showMarker(update, isArchived) {
		if (! update.lat || ! update.lng ||
		    ! parseFloat(update.lat) ||
				! parseFloat(update.lng)) {
			console.log('Invalid update', update);
			return;
		}
		marker = L.circleMarker(update, newStyle);
		marker.addTo(map);
		marker.update = update;
		marker.bindPopup(update.marker);
	}

});
