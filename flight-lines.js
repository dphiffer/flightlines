var currVideo = null;
var started = false;
var playing = false;
var v; //= document.getElementById('v');
var s = document.getElementById('s');
var c1 = document.getElementById('c1');
var ctx1 = c1.getContext('2d');
var c2 = document.getElementById('c2');
var ctx2 = c2.getContext('2d');

var w = 1024;
var h = 576;
var threshold = 30;
var countdown = 50;

var decayStep = 1.12;
var decayLimit = 200;

var i, k0, k1, k2, diff;
var frame0, frame1, frame2;

var pixelDelta = 0;

function init() {
	if (!checkBrowserSupport()) {
		console.log('Error: no browser support');
		return;
	}
	nextVideo();
	setupControls();
}
window.addEventListener('DOMContentLoaded', init, false);

function handleNextVideo() {
	var DONE = this.DONE || 4;
	if (this.readyState === DONE) {
		var response = JSON.parse(this.responseText);
		document.getElementById('vh').innerHTML =
			'<video id="v" width="1024" height="576" autoplay>' +
				'<source src="' + response.video_url + '" id="s" type="video/mp4">' +
			'</video>';
		v = document.getElementById('v');
		currVideo = response;
		setupVideo();
	}
}

function saveImage(finished) {
	var request = new XMLHttpRequest();
	var url = 'flight-lines.php?method=save_image';
	var data =
		'video=' + currVideo.video.id +
		'&video_time=' + parseInt(v.currentTime) +
		'&pixel_delta=' + pixelDelta;
	if (finished) {
		data = 'status=rendered&' + data;
		request.onreadystatechange = handleNextVideo;
	}
	if (pixelDelta > 0 ||
	    finished) {
		var dataURI = c2.toDataURL();
		data += '&image_data_uri=' + encodeURIComponent(dataURI);
	}
	request.open('POST', url, true);
	request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	request.send(data);
}

function nextVideo() {
  var request = new XMLHttpRequest();
	request.onreadystatechange = handleNextVideo;
	var url = 'flight-lines.php?method=get_video';
	if (currVideo) {
		url += '&after_id=' + currVideo.video.id;
	}
	request.open('GET', url, true);
	request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	request.send(null);
}

function setupVideo() {
	var lastSave;
	v.addEventListener('canplay', function() {
		if (!playing) {
			lastSave = 0;
			if (currVideo.image &&
			    currVideo.image.data_uri != '') {
				v.currentTime = currVideo.image.video_time;
				lastSave = currVideo.image.video_time;
				var img = new Image;
				img.onload = function() {
					ctx1.drawImage(img, 0, 0);
					ctx2.drawImage(img, 0, 0);
					frame1 = ctx2.getImageData(0, 0, w, h);
					frame2 = ctx2.getImageData(0, 0, w, h);
				};
				img.src = currVideo.image.data_uri;
			} else if (!currVideo.previous_id) {
				ctx2.fillStyle = '#ffffff';
				ctx2.fillRect(0, 0, w, h);
				frame2 = ctx2.getImageData(0, 0, w, h);
			}
			playing = true;
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
		threshold = 30;
		countdown = 50;
		playing = false;
		if (currVideo && currVideo.video.status != 'rendered') {
			saveImage(true);
		} else {
			nextVideo();
		}
		//ctx2.fillStyle = '#ffffff';
		//ctx2.fillRect(0, 0, w, h);
		/*for (i = 0; i < frame1.data.length; i += 4) {
			frame2.data[i] = 255;
			frame2.data[i + 1] = 255;
			frame2.data[i + 2] = 255;
		}*/
	}, false);
	v.addEventListener('timeupdate', function() {
		var start = new Date(currVideo.video.created.replace(' ', 'T') + '-0500');
		var time = new Date(start.getTime() + v.currentTime * 1000);
		document.getElementById('status').innerHTML = 
			time.toLocaleTimeString() + ' ' +
			time.toLocaleDateString() + ' <span class="gray50">' +
			currVideo.location.title + '</span>';
		if (playing && v.currentTime - lastSave > 10) {
			saveImage();
			pixelDelta = 0;
			lastSave = v.currentTime;
		}
	}, false);
}

function setupControls() {
  document.addEventListener('keydown', function(e) {
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
	if (frame2) {
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
					pixelDelta++;
				} else {
					k2 = decay(k2);
					frame2.data[i] = k2;
					frame2.data[i + 1] = k2;
					frame2.data[i + 2] = k2;
				}
			}
		}
		if (frame0) {
			ctx2.putImageData(frame2, 0, 0);
		}
	}
	if (countdown > 0) {
		countdown--;
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
	window.requestAnimationFrame = (window.requestAnimationFrame ||
	                                window.webkitRequestAnimationFrame ||
	                                window.mozRequestAnimationFrame ||
	                                function(callback) {
	                                	window.setTimeout(callback, 1000 / 60);
	                                });
	return true;
}

function playHandler(stream) {
	v.src = window.URL.createObjectURL(stream);
	v.play();
}

function errorHandler() {
	console.log("Error: " + err);
}
