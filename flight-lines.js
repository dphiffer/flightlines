(function() {

var video_id = null;
var started = false;
var playing = false;
var v; //= document.getElementById('v');
var s = document.getElementById('s');
var c1 = document.getElementById('c1');
var ctx1 = c1.getContext('2d');
var c2 = document.getElementById('c2');
var ctx2 = c2.getContext('2d');

var w = 960;
var h = 540;
var threshold = 30;
var countdown = 600;

var decayStep = 1.12;
var decayLimit = 200;

var i, k0, k1, k2, diff;
var frame0, frame1, frame2;

function init() {
	if (!checkBrowserSupport()) {
		console.log('Error: no browser support');
		return;
	}
	next_video();
	setup_controls();
}
window.addEventListener('DOMContentLoaded', init, false);

function next_video() {
  var request = new XMLHttpRequest();
	request.onreadystatechange = function() {
		var DONE = this.DONE || 4;
		if (this.readyState === DONE) {
			var response = JSON.parse(this.responseText);
			document.getElementById('vh').innerHTML =
				'<video id="v" width="960" height="540" autoplay>' +
					'<source src="' + response.video_url + '" id="s" type="video/mp4">' +
				'</video>';
			v = document.getElementById('v');
			video_id = response.video.id;
			setup_video();
		}
	};
	var url = 'flight-lines.php?method=get_video';
	if (video_id) {
		url += '&after_id=' + video_id;
	}
	request.open('GET', url, true);
	request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	request.send(null);
}

function setup_video() {
	v.addEventListener('canplay', function() {
		if (!playing) {
			console.log('canplay');
			playing = true;
			ctx2.fillStyle = '#ffffff';
      ctx2.fillRect(0, 0, w, h);
      frame2 = ctx2.getImageData(0, 0, w, h);
		}
	});
	v.addEventListener('play', function() {
		if (v.paused || v.ended || started) {
      return;
    }
    started = true;
    v.volume = 0;
    (function animationLoop() {
			window.requestAnimationFrame(animationLoop);
			render();
		})();
	}, false);
	v.addEventListener('ended', function() {
		save_image();
		threshold = 30;
		countdown = 600;
		ctx2.fillStyle = '#ffffff';
		ctx2.fillRect(0, 0, w, h);
		for (i = 0; i < frame1.data.length; i += 4) {
			frame2.data[i] = 255;
			frame2.data[i + 1] = 255;
			frame2.data[i + 2] = 255;
		}
		next_video();
	}, false);
	v.addEventListener('timeupdate', function() {
		var min = Math.floor(parseInt(v.currentTime) / 60);
		if (min < 10) {
			min = '0' + min;
		}
		var sec = parseInt(v.currentTime) % 60;
		if (sec < 10) {
			sec = '0' + sec;
		}
		var time = min + ':' + sec;
	  document.getElementById('status').innerHTML = video_id + ' / ' + threshold + ' / ' + time;
	}, false);
	
	/*navigator.getUserMedia({
		video: true,
		audio: false
	}, playHandler, errorHandler);*/
}

function setup_controls() {
  document.addEventListener('keydown', function(e) {
		//console.log(e.keyCode);
		var timeShift = e.shiftKey ? 60 : 10;
		var threshShift = e.shiftKey ? 5 : 1;
	  if (e.keyCode == 37) {
	  	if (v.currentTime > timeShift) {
	  		v.currentTime -= timeShift;
	  	} else {
	  		v.currentTime = 0;
	  	}
	  } else if (e.keyCode == 39) {
	  	if (v.currentTime + timeShift < v.duration) {
	  		v.currentTime += timeShift;
	  	}
	  } else if (e.keyCode == 38) {
	  	if (threshold + threshShift <= 255) {
	  		threshold += threshShift;
	  	}
	  } else if (e.keyCode == 40) {
	  	if (threshold - threshShift >= 0) {
	  		threshold -= threshShift;
	  	}
	  } else if (e.keyCode == 67) {
	  	ctx2.fillStyle = '#ffffff';
			ctx2.fillRect(0, 0, w, h);
			for (i = 0; i < frame1.data.length; i += 4) {
				frame2.data[i] = 255;
				frame2.data[i + 1] = 255;
				frame2.data[i + 2] = 255;
			}
	  } else if (e.keyCode == 86) {
	  	document.body.className = 'show-video';
	  }
	}, false);
	
	document.addEventListener('keyup', function(e) {
		if (e.keyCode == 86) {
	  	document.body.className = '';
	  }
	}, false);
}

function save_image() {
	var data_uri = c2.toDataURL();
  var request = new XMLHttpRequest();
	var url = 'flight-lines.php?method=save_rendering&id=' + video_id;
	request.open('POST', url, true);
	request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	request.send('image_data=' + encodeURIComponent(data_uri));
}

function render() {
	if (!playing) {
		return;
	}
	
	try {
		ctx1.drawImage(v, 0, 0, v.width, v.height);
	} catch(e) {
		return;
	}
	
	frame1 = ctx1.getImageData(0, 0, w, h);
	
	for (i = 0; i < frame1.data.length; i += 4) {
		k1 = 0.34 * frame1.data[i] +
		     0.5 * frame1.data[i + 1] +
		     0.16 * frame1.data[i + 2];
	  k2 = frame2.data[i];
	  if (countdown == 0) {
	  	k0 = frame0.data[i];
			diff = Math.abs(k0 - k1);
			if (diff > threshold) {
				frame2.data[i] = 0;
				frame2.data[i + 1] = 0;
				frame2.data[i + 2] = 0;
			} else {
				k2 = decay(k2);
				frame2.data[i] = k2;
				frame2.data[i + 1] = k2;
				frame2.data[i + 2] = k2;
			}
		}
	}
	if (countdown > 0) {
		countdown--;
	}
	if (frame0) {
		ctx2.putImageData(frame2, 0, 0);
	}
	frame0 = frame1;
}

function decay(k) {
  if (k + decayStep <= decayLimit) {
  	k += decayStep;
  }
  return k;
}

function checkBrowserSupport() {
	navigator.getUserMedia = (navigator.getUserMedia ||
	                          navigator.webkitGetUserMedia ||
	                          navigator.mozGetUserMedia ||
	                          navigator.msGetUserMedia);
	window.requestAnimationFrame = (window.requestAnimationFrame ||
	                                window.webkitRequestAnimationFrame ||
	                                window.mozRequestAnimationFrame ||
	                                function(callback) {
	                                	window.setTimeout(callback, 1000 / 60);
	                                });
	return true; //navigator.getUserMedia;
}

function playHandler(stream) {
	console.log('playHandler');
	v.src = window.URL.createObjectURL(stream);
	v.play();
}

function errorHandler() {
	console.log("Error: " + err);
}

})();
