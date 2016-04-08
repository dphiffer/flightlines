var state = null;
var started = false;
var playing = false;
var v = null;
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
var suspendURLUpdates = false;
var loadTime = null;
var gradCtx = null;
var color;

document.getElementById('threshold').innerHTML = threshold;

function init() {
	if (!checkBrowserSupport()) {
		console.log('Error: no browser support');
		return;
	}
	setupHashListener();
	if (!window.onhashchange()) {
		getRandomVideo();
	}
	setupControls();
	setupGradient();
	setupImages();
	setupLogin();
}
window.addEventListener('DOMContentLoaded', init, false);

function handleVideo(response) {
	suspendURLUpdates = false;
	if (!response || !response.video_url) {
		return;
	}
	countdown = 100;
	document.getElementById('vh').innerHTML =
		'<video id="v" width="1024" height="576" crossorigin="anonymous" autoplay>' +
			'<source src="' + response.video_url + '" id="s" type="video/mp4">' +
		'</video>';
	v = document.getElementById('v');
	if (state) {
		state.previous_video_date = state.video_date;
	}
	state = response;
	var d = state.video_created.match(/(\d{4})-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)/);
	state.video_start = new Date(
		parseInt(d[1]),
		parseInt(d[2]) - 1,
		parseInt(d[3]),
		parseInt(d[4]),
		parseInt(d[5]),
		parseInt(d[6])
	);
	setupVideo();
	updateViewer();
	updateURL();
}

function updateURL() {
	if (suspendURLUpdates) {
		return;
	}
	var url = '#/' + state.location_id + '/' +
	          state.video_date + '/' +
	          state.video_num;
	location.href = url;
}

function updateViewer() {
	var viewer = document.getElementById('viewer');
	var sec = parseInt(state.viewer_render_time);
	var hh = zeroPrefix(Math.floor(sec / 3600));
	sec -= parseInt(hh) * 3600;
	var mm = zeroPrefix(Math.floor(sec / 60));
	sec -= parseInt(mm) * 60;
	var ss = zeroPrefix(sec);
	viewer.innerHTML = '<span class="gray50">Your render time contribution:</span> ' +
	                   hh + ':' + mm + ':' + ss;
}

function updateViewerTime(response) {
	state.viewer_render_time = response.viewer_render_time;
	updateViewer();
}

function saveImage(finished) {
	var args = {
		video_id: state.video_id,
		video_num: state.video_num,
		image_time: parseInt(v.currentTime),
		image_delta: pixelDelta
	};
	var callback = updateViewerTime;
	if (finished) {
		args.video_status = 'rendered';
		callback = handleVideo;
	}
	if (pixelDelta > 0 ||
	    finished) {
		args.image_data_uri = c2.toDataURL('image/jpeg', 0.7);
		args.image_color = color;
	}
	apiPost('save_image', args, callback);
}

function getVideo(location, date, num) {
	playing = false;
	var args = {
		location_id: location,
		video_date: date,
		video_num: num
	};
	if (loadTime) {
		args.image_time = loadTime;
	}
	apiGet('get_video', args, handleVideo);
}

function getRandomVideo() {
	playing = false;
	apiGet('get_random_video', null, handleVideo);
}

function apiXHR(callback) {
	var request = new XMLHttpRequest();
	if (callback) {
		request.onreadystatechange = function() {
			var DONE = this.DONE || 4;
			if (this.readyState === DONE) {
				var response = JSON.parse(this.responseText);
				callback(response);
			}
		};
	}
	
	return request;
}

function apiQuery(args) {
	var query = [];
	if (args) {
		for (var key in args) {
			if (args[key] != null) {
				query.push(
					encodeURIComponent(key) + '=' +
					encodeURIComponent(args[key])
				);
			}
		}
	}
	return query.join('&');
}

function apiGet(method, args, callback) {
	xhr = apiXHR(callback);
	var url = 'flight-lines.php?method=' + method;
	var query = apiQuery(args);
	if (query) {
		url += '&' + query;
	}
	xhr.open('GET', url, true);
	xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	xhr.send(null);
}

function apiPost(method, args, callback) {
  xhr = apiXHR(callback);
	var url = 'flight-lines.php?method=' + method;
	var query = apiQuery(args);
	xhr.open('POST', url, true);
	xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	xhr.send(query);
}

function setupVideo() {
	var lastSave;
	c2.className = 'intro';
	v.addEventListener('canplay', function() {
		if (!playing) {
			lastSave = 0;
			if (state.image_url) {
				var img = new Image();
				img.onload = function() {
					ctx1.drawImage(img, 0, 0);
					ctx2.drawImage(img, 0, 0);
					frame1 = ctx2.getImageData(0, 0, w, h);
					frame2 = ctx2.getImageData(0, 0, w, h);
				};
				img.src = state.image_url;
			} else if (state.previous_video_date != state.video_date) {
				ctx1.fillStyle = '#ffffff';
				ctx2.fillStyle = '#ffffff';
				ctx1.fillRect(0, 0, w, h);
				ctx2.fillRect(0, 0, w, h);
				frame1 = ctx1.getImageData(0, 0, w, h);
				frame2 = ctx2.getImageData(0, 0, w, h);
			}
			if (state.image_time) {
				v.currentTime = state.image_time;
				lastSave = state.image_time;
				updateURL();
			}
			
			playing = true;
			setTimeout(function() {
				c2.className = '';
			}, 3000);
			if (loadTime) {
				v.currentTime = loadTime;
				loadTime = null;
				lastSave = loadTime;
			}
		}
		document.getElementById('page').className = '';
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
		playing = false;
		if (state && state.video_status != 'rendered') {
			saveImage(true);
		} else {
			var next_num = parseInt(state.video_num) + 1;
			if (next_num < 10) {
				next_num = '00' + next_num;
			} else if (next_num < 100) {
				next_num = '0' + next_num;
			}
			getVideo(state.location_id, state.video_date, next_num);
		}
	}, false);
	v.addEventListener('timeupdate', function() {
		var time = new Date(
			state.video_start.getTime() +
			parseInt(v.currentTime * 1000)
		);
		var ampm = 'AM';
		var hour = time.getHours();
		if (time.getHours() > 11) {
			hour -= 12;
			ampm = 'PM';
		}
		document.getElementById('when').innerHTML =
			hour + ':' +
			zeroPrefix(time.getMinutes()) + ':' +
			zeroPrefix(time.getSeconds()) + ' ' +
			ampm + ' ' +
			time.getFullYear() + '-' +
			zeroPrefix(time.getMonth() + 1) + '-' +
			zeroPrefix(time.getDate());
		document.getElementById('where').innerHTML =
			state.location_title +
			' [' + state.location_lat + ', ' +
			state.location_lng + ']</span>';
		if (playing && v.currentTime - lastSave > 10) {
			saveImage();
			pixelDelta = 0;
			lastSave = v.currentTime;
		}
		var dayMax = 10;
		var dayPercent = (
			time.getHours() + time.getMinutes() / 60 - 7
		) / dayMax;
		var dayPos = Math.floor(
			1004 * dayPercent
		);
		dayPos = Math.max(0, dayPos);
		dayPos = Math.min(1003, dayPos);
		var gradPos = Math.floor(
			1024 * dayPercent
		);
		gradPos = Math.max(0, gradPos);
		gradPos = Math.min(1023, gradPos);
		var c = gradCtx.getImageData(gradPos, 5, 1, 1).data;
		color = '#' + ('000000' + rgbToHex(c[0], c[1], c[2])).slice(-6);
		document.getElementById('timeline-pos').style.left = dayPos + 'px';
		document.getElementById('timeline-pos').style.borderBottomColor = color;
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
			countdown = 100;
	  } else if (e.keyCode == 39) {
	  	if (v.currentTime + timeShift < v.duration) {
	  		v.currentTime += timeShift;
	  	}
			countdown = 100;
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
			if (document.activeElement &&
			    document.activeElement.getAttribute('id') == 'permalink') {
				if (e.metaKey || e.ctrlKey) {
					var label = document.getElementById('link-label');
					var labelText = label.innerHTML;
					label.innerHTML = 'URL copied!';
					setTimeout(function() {
						document.getElementById('link').className = '';
						document.activeElement.blur();
					}, 500);
					setTimeout(function () {
						label.innerHTML = labelText;
					}, 1000);
				}
				return;
			}
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
	
	document.getElementById('skip').addEventListener('click', function(e) {
		e.preventDefault();
		getRandomVideo();
	}, false);
	
	var toggleMore = document.getElementById('toggle-more');
	toggleMore.addEventListener('click', function(e) {
		var more = document.getElementById('more');
		e.preventDefault();
		if (more.className.indexOf('visible') != -1) {
			more.className = '';
			toggleMore.innerHTML = 'Show controls';
		} else {
			more.className = 'visible';
			toggleMore.innerHTML = 'Hide controls';
		}
	}, false);
	
	var toggleLink = document.getElementById('toggle-link');
	toggleLink.addEventListener('click', function(e) {
		var link = document.getElementById('link');
		e.preventDefault();
		if (link.className.indexOf('visible') != -1) {
			link.className = '';
		} else {
			var href = location.protocol + '//' +
			           location.hostname + location.pathname +
			           '#/' + state.location_id + '/' +
			           state.video_date + '/' +
			           state.video_num + '/' +
			           parseInt(v.currentTime);
			link.className = 'visible';
			var input = document.getElementById('permalink');
			input.value = href;
			input.select();
			saveImage();
		}
	}, false);
	
	var close = document.getElementById('link-close');
	close.addEventListener('click', function(e) {
		e.preventDefault();
		document.getElementById('link').className = '';
	}, false);
}

function setupHashListener() {
	window.onhashchange = function() {
		var hash = location.hash.match(/#\/([^\/]+)\/(\d{8})\/(\d\d\d)(\/\d+)?/);
		if (hash) {
			var hashLocation = hash[1];
			var hashDate = hash[2];
			var hashNum = hash[3];
			if (hash[4]) {
				loadTime = parseInt(hash[4].substr(1));
			}
			if (!state ||
			    hashLocation != state.location_id ||
			    hashDate != state.video_date ||
			    hashNum != state.video_num) {
				getVideo(hashLocation, hashDate, hashNum);
				return true;
			}
		}
		return false;
	};
}

function setupGradient() {
	gradCtx = document.getElementById('gradient').getContext('2d');
	var img = new Image();
	img.onload = function() {
		gradCtx.drawImage(img, 0, 0, 1024, 10);
	}
	img.src = 'images/gradient.jpg';
}

function setupImages() {
	apiGet('get_images', null, function(response) {
		if (!response.images) {
			return;
		}
		var html = '';
		for (var i = 0, img; i < response.images.length; i++) {
			img = response.images[i];
			html += '<a href="' + img.href + '">' +
			        '<img src="' + img.url + '"></a>';
		}
		document.getElementById('images').innerHTML = html;
		if (response.login && response.login.email) {
			setupLoginOptions(response);
		}
	});
}

function setupLogin() {
	var tabs = document.querySelectorAll('#login a');
	var login = document.getElementById('login');
	document.getElementById('viewer').addEventListener('click', function(e) {
		if (!e.shiftKey) {
			return;
		}
		if (login.className.indexOf('visible') == -1) {
			login.className = 'visible register';
		} else {
			login.className = '';
		}
	}, false);
	tabs[0].addEventListener('click', function(e) {
		e.preventDefault();
		login.className = 'visible register';
	}, false);
	tabs[1].addEventListener('click', function(e) {
		e.preventDefault();
		login.className = 'visible login';
	}, false);
	document.getElementById('register-form').addEventListener('submit', function(e) {
		e.preventDefault();
		var email = document.querySelector('#register-form input[name=email]').value;
		var password = document.querySelector('#register-form input[name=password]').value;
		apiPost('register', {
			email: email,
			password: password
		}, function(response) {
			if (response.error) {
				document.getElementById('login-response').innerHTML = response.error;
			} else if (response.login) {
				setupLoginOptions(response);
			}
		});
	}, false);
	document.getElementById('login-form').addEventListener('submit', function(e) {
		e.preventDefault();
		var email = document.querySelector('#login-form input[name=email]').value;
		var password = document.querySelector('#login-form input[name=password]').value;
		apiPost('login', {
			email: email,
			password: password
		}, function(response) {
			if (response.error) {
				document.getElementById('login-response').innerHTML = response.error;
			} else if (response.login) {
				setupLoginOptions(response);
			}
		});
	}, false);
}

function setupLoginOptions(response) {
	document.getElementById('login').innerHTML = 'You are logged in as <strong>' + response.login.email + '</strong>';
	document.getElementById('status').className = 'login-' + response.login.status;
	if (document.getElementById('status').className.indexOf('login-admin') != -1) {
		document.getElementById('first').addEventListener('click', function(e) {
			e.preventDefault();
			apiGet('get_first_video', null, handleVideo);
		}, false);
		document.getElementById('prev').addEventListener('click', function(e) {
			e.preventDefault();
			var args = {
				before_video_id: state.video_id
			};
			if (e.shiftKey) {
				args.before_video_date = state.video_date;
			}
			apiGet('get_prev_video', args, handleVideo);
		}, false);
		document.getElementById('next').addEventListener('click', function(e) {
			e.preventDefault();
			var args = {
				after_video_id: state.video_id
			};
			if (e.shiftKey) {
				args.after_video_date = state.video_date;
			}
			apiGet('get_next_video', args, handleVideo);
		}, false);
		document.getElementById('last').addEventListener('click', function(e) {
			e.preventDefault();
			apiGet('get_last_video', null, handleVideo);
		}, false);
		document.getElementById('remove').addEventListener('click', function(e) {
			e.preventDefault();
			if (confirm('Remove ' + state.video_id + '?')) {
				apiGet('remove_video', {
					video_id: state.video_id
				}, handleVideo);
			}
		}, false);
	}
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
			ctx2.globalCompositeOperation = 'multiply';
			ctx2.fillStyle = color;
			ctx2.fillRect(0, 0, w, h);
			ctx2.globalCompositeOperation = 'source-over';
		}
	}
	if (countdown > 0) {
		countdown--;
	}
	frame0 = frame1;
}

function rgbToHex(r, g, b) {
	if (r > 255 || g > 255 || b > 255) {
		throw "Invalid color component";
	}
	return ((r << 16) | (g << 8) | b).toString(16);
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

// Every 20s show the video for 3s
setInterval(function() {
	document.getElementById('c2').className = 'intro';
	setTimeout(function() {
		document.getElementById('c2').className = '';
	}, 5000);
}, 20000);
