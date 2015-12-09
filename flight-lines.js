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
var countdown = 100;

var decayStep = 1.12;
var decayLimit = 200;

var i, k0, k1, k2, diff;
var frame0, frame1, frame2;

var pixelDelta = 0;
document.getElementById('threshold').innerHTML = threshold;

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
			'<video id="v" width="1024" height="576" crossorigin="anonymous" autoplay>' +
				'<source src="' + response.video_url + '" id="s" type="video/mp4">' +
			'</video>';
		v = document.getElementById('v');
		currVideo = response;
		var d = currVideo.video.created.match(/(\d{4})-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)/);
		currVideo.start = new Date(
			parseInt(d[1]),
			parseInt(d[2]) - 1,
			parseInt(d[3]),
			parseInt(d[4]),
			parseInt(d[5]),
			parseInt(d[6])
		);
		setupVideo();
		currVideo.viewer.render_time = response.viewer.render_time;
		updateViewer();
		updateURL();
	}
}

function updateURL() {
	var time = new Date(
		currVideo.start.getTime() +
		parseInt(v.currentTime * 1000)
	);
	var yyyy = time.getFullYear();
	var mm = zeroPrefix(time.getMonth() + 1);
	var dd = zeroPrefix(time.getDate());
	var hh = zeroPrefix(time.getHours());
	var min = zeroPrefix(time.getMinutes());
	var sec = zeroPrefix(time.getSeconds());
	var url = '#/' + currVideo.location.id + '/' +
						yyyy + '-' + mm + '-' + dd + '/' +
						hh + ':' + min + ':' + sec;
	//location.href = url;
}

function updateViewer() {
	var viewer = document.getElementById('viewer');
	var sec = parseInt(currVideo.viewer.render_time);
	var hh = zeroPrefix(Math.floor(sec / 3600));
	sec -= parseInt(hh) * 3600;
	var mm = zeroPrefix(Math.floor(sec / 60));
	sec -= parseInt(mm) * 60;
	var ss = zeroPrefix(sec);
	viewer.innerHTML = '<span class="gray50">Your render time contribution:</span> ' +
	                   hh + ':' + mm + ':' + ss;
}

function updateViewerTime() {
	var DONE = this.DONE || 4;
	if (this.readyState === DONE) {
		var response = JSON.parse(this.responseText);
		currVideo.viewer.render_time = response.viewer.render_time;
		updateViewer();
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
	} else {
		request.onreadystatechange = updateViewerTime;
	}
	if (pixelDelta > 0 ||
	    finished) {
		var dataURI = c2.toDataURL('image/jpeg', 0.7);
		data += '&image_data_uri=' + encodeURIComponent(dataURI);
	}
	request.open('POST', url, true);
	request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	request.send(data);
	updateURL();
}

function nextVideo() {
	playing = false;
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
	c2.className = 'intro';
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
			setTimeout(function() {
				c2.className = '';
			}, 3000);
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
		countdown = 100;
		playing = false;
		if (currVideo && currVideo.video.status != 'rendered') {
			saveImage(true);
		} else {
			nextVideo();
		}
	}, false);
	v.addEventListener('timeupdate', function() {
		var time = new Date(
			currVideo.start.getTime() +
			parseInt(v.currentTime * 1000)
		);
		var ampm = 'AM';
		var hour = time.getHours();
		if (time.getHours() > 12) {
			hour -= 12;
			ampm = 'PM';
		}
		document.getElementById('status').innerHTML = 
			hour + ':' + zeroPrefix(time.getMinutes()) + ':' + zeroPrefix(time.getSeconds()) + ' ' + ampm + ' ' +
			time.getFullYear() + '-' + zeroPrefix(time.getMonth() + 1) + '-' + zeroPrefix(time.getDate()) + '<br><span class="gray50">' +
			currVideo.location.title + ' [' + currVideo.location.lat + ', ' + currVideo.location.lng + ']</span>';
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
			e.preventDefault();
	  	if (threshold + threshShift <= 255) {
	  		threshold += threshShift;
	  	}
	  } else if (e.keyCode == 40) {
			e.preventDefault();
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
		document.getElementById('threshold').innerHTML = threshold;
	}, false);
	
	document.addEventListener('keyup', function(e) {
		if (e.keyCode == 86) {
	  	document.body.className = '';
	  }
	}, false);
	
	var frame = document.getElementById('frame');
	frame.addEventListener('mousedown', function() {
		document.body.className = 'show-video';
	}, false);
	
	frame.addEventListener('mouseup', function() {
		document.body.className = '';
	}, false);
	
	document.getElementById('next').addEventListener('click', function(e) {
		e.preventDefault();
		nextVideo();
	}, false);
	
	var toggle = document.getElementById('toggle');
	toggle.addEventListener('click', function(e) {
		var more = document.getElementById('more');
		e.preventDefault();
		if (more.className.indexOf('hidden') == -1) {
			more.className = 'hidden';
			toggle.innerHTML = 'Show controls';
		} else {
			more.className = '';
			toggle.innerHTML = 'Hide controls';
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

function zeroPrefix(n) {
	if (n < 10) {
		n = '0' + n;
	}
	return n;
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
