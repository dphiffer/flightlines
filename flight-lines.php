<?php

require_once __DIR__ . '/debug.php';
require_once __DIR__ . '/password.php';

class FlightLines {
	
	function __construct() {
		require_once __DIR__ . '/config.php';
		date_default_timezone_set($timezone);
		$this->setup_db($db_host, $db_name, $db_user, $db_password);
		if (!empty($upstream_href)) {
			$this->upstream_href = $upstream_href;
		}
		$this->setup_session();
		$this->dispatch();
	}

	function dispatch() {
		if (empty($_GET['method']) ||
		    !method_exists($this, 'api_' . $_GET['method'])) {
			$this->api_help();
		} else {
			$method = 'api_' . $_GET['method'];
			$this->$method();
		}
	}
	
	function get_video($location = null, $date = null, $num = null) {
		if (!empty($location) && is_array($location) &&
		    !empty($location['location_id']) &&
				!empty($location['video_date']) &&
				!empty($location['video_num'])) {
			$video = $location;
		} else if (!empty($location) && !empty($date) && !empty($num)) {
			$video = $this->get_video_by_num($location, $date, $num);
		}
		if (empty($video)) {
			$video = $this->get_random_video();
		}
	  $image = $this->get_image($video);
		$location = $this->get_location($video);
		$video_dir = date('Ymd', strtotime($video['video_created']));
		$video_path = "/videos/{$location['location_id']}/$video_dir/{$video['video_id']}.mp4";
		$response = array(
			'video_url' => $this->get_url($video_path)
		);
		$response = array_merge($response, $video);
		$response = array_merge($response, $image);
		$response = array_merge($response, $location);
		$response = array_merge($response, $this->get_viewer());
		return $response;
	}
	
	function get_video_by_num($location, $date, $num) {
		$query = $this->db->prepare("
			SELECT *
			FROM video
			WHERE location_id = ?
			  AND video_date = ?
				AND video_num = ?
			LIMIT 1
		");
		$query->execute(array($location, $date, $num));
		if ($query->rowCount() == 0) {
			return array();
		}
		$video = $query->fetch();
		return $video;
	}

	function api_help() {
		$methods = array();
		foreach (get_class_methods('FlightLines') as $method) {
			if (substr($method, 0, 4) == 'api_') {
				$methods[] = substr($method, 4);
			}
		}
	  $this->respond(array(
	  	'help' => "Please specify a 'method' parameter.",
	  	'methods' => $methods
		));
	}
	
	function api_get_locations() {
		$query = $this->db->query("
			SELECT *
			FROM location
		");
		$locations = array();
		foreach ($query->fetchAll() as $location) {
			$locations[] = $location;
		}
		$this->respond(array(
			'locations' => $locations
		));
	}
	
	function api_get_dates() {
		$dates = array();
		$locations = $this->get_locations();
		foreach ($locations as $location_id) {
			$dirname = __DIR__ . "/videos/$location_id";
			if (file_exists($dirname)) {
				$location_dir = opendir($dirname);
				while ($date = readdir($location_dir)) {
					if (!preg_match('/^\d{8}$/', $date)) {
						continue;
					}
					if (!in_array($date, $dates)) {
						$dates[] = $date;
					}
				}
			}
		}
		$this->respond(array(
			'dates' => $dates
		));
	}
	
	function api_get_video() {
		$location = null;
		$date = null;
		$num = null;
		if (!empty($_GET['location_id'])) {
			$location = $_GET['location_id'];
		}
		if (!empty($_GET['video_date'])) {
			$date = $_GET['video_date'];
		}
		if (!empty($_GET['video_num'])) {
			$num = $_GET['video_num'];
		}
		$this->respond($this->get_video($location, $date, $num));
	}
	
	function api_get_first_video() {
		$query = $this->db->query("
			SELECT *
			FROM video
			WHERE video_status != 'removed'
			ORDER BY video_id
			LIMIT 1
		");
		$video = $query->fetch();
		if (!empty($video)) {
			$this->respond($this->get_video($video));
		} else {
			$this->respond(array(
				'error' => 'Could not find the first video.'
			), 404);
		}
	}
	
	function api_get_last_video() {
		$query = $this->db->query("
			SELECT *
			FROM video
			WHERE video_status != 'removed'
			ORDER BY video_id DESC
			LIMIT 1
		");
		$video = $query->fetch();
		if (!empty($video)) {
			$this->respond($this->get_video($video));
		} else {
			$this->respond(array(
				'error' => 'Could not find the last video.'
			), 404);
		}
	}
	
	function api_get_next_video($video_id = null) {
		if (empty($video_id) &&
		    empty($_GET['after_video_id'])) {
			$this->respond(array(
				'error' => "Please include an 'after_video_id' argument."
			), 500);
		} else if (empty($video_id)) {
			$video_id = $_GET['after_video_id'];
		}
		$where_clause = "
			video_status != 'removed'
			AND video_id > ?
		";
		$values = array($video_id);
		if (!empty($_GET['after_video_date'])) {
			$where_clause .= "
				AND video_date > ?
			";
			$values[] = $_GET['after_video_date'];
		}
		$query = $this->db->prepare("
			SELECT *
			FROM video
			WHERE $where_clause
			ORDER BY video_id
			LIMIT 1
		");
		$query->execute($values);
		$video = $query->fetch();
		if (!empty($video)) {
			$this->respond($this->get_video($video));
		} else {
			$this->api_get_first_video();
		}
	}
	
	function api_get_prev_video() {
		if (empty($_GET['before_video_id'])) {
			$this->respond(array(
				'error' => "Please include a 'before_video_id' argument."
			), 500);
		}
		$where_clause = "
			video_status != 'removed'
			AND video_id < ?
		";
		$values = array($_GET['before_video_id']);
		if (!empty($_GET['before_video_date'])) {
			$where_clause .= "
				AND video_date < ?
			";
			$values[] = $_GET['before_video_date'];
		}
		$query = $this->db->prepare("
			SELECT *
			FROM video
			WHERE $where_clause
			ORDER BY video_id DESC
			LIMIT 1
		");
		$query->execute($values);
		$video = $query->fetch();
		if (!empty($video)) {
			$this->respond($this->get_video($video));
		} else {
			$this->api_get_first_video();
		}
	}
	
	function api_get_random_video() {
		$this->find_latest_image = true;
		$this->respond($this->get_video());
	}
	
	function api_remove_video() {
		if (empty($_SESSION['viewer_id']) ||
		    empty($_SESSION['login_id'])) {
			$this->respond(array(
				'error' => 'You must be logged in to remove videos.'
			), 401);
		}
		$login = $this->get_login();
		if ($login['status'] != 'admin') {
			$this->respond(array(
				'error' => 'You must be logged in as an admin user to remove videos.'
			), 401);
		}
		if (empty($_GET['video_id'])) {
			$this->respond(array(
				'error' => "Please include a 'video_id' argument."
			), 500);
		}
		$query = $this->db->prepare("
			UPDATE video
			SET video_status = 'removed'
			WHERE video_id = ?
		");
		$query->execute(array($_GET['video_id']));
		$this->api_get_next_video($_GET['video_id']);
	}
	
	function api_get_images() {
		$image_urls = array();
		$video_query = $this->db->query("
			SELECT i.video_id, i.video_num, i.location_id, i.video_date
			FROM image AS i,
			     video AS v
			WHERE i.video_id = v.video_id
			  AND v.video_status != 'removed'
			  AND image_delta > 0
			GROUP BY video_id
			ORDER BY image_created DESC
			LIMIT 12;
		");
		$videos = $video_query->fetchAll();
		$time_query = $this->db->prepare("
			SELECT image_id, image_time, viewer_id
			FROM image
			WHERE video_id = ?
			  AND image_delta > 0
			ORDER BY image_time DESC
		");
		foreach ($videos as $video) {
			$time_query->execute(array($video['video_id']));
			$image = $time_query->fetch();
			$image_url = $this->get_image_url(
				$video['video_id'], $video['video_num'],
				$image['image_time'], $image['viewer_id']
			);
			$href = '#/' . $video['location_id'] . '/' .
							$video['video_date'] . '/' .
							$video['video_num'] . '/' .
							$image['image_time'];
			$image_urls[] = array(
				'id' => $image['image_id'],
				'href' => $href,
				'url' => $image_url
			);
		}
		$response = array(
			'images' => $image_urls
		);
		if (!empty($_SESSION['login_id'])) {
			$response['login'] = $this->get_login();
		}
		$this->respond($response);
	}

	function api_get_index() {
		if (empty($_GET['date']) ||
		    !preg_match('/^\d{8}$/', $_GET['date'])) {
			$this->respond(array(
				'error' => "Please specify a 'date' parameter."
			), 500);
		}
		$videos = array();
		$locations = $this->get_locations();
		foreach ($locations as $location) {
			$location_videos = $this->index_date($location, $_GET['date']);
			$videos = array_merge($videos, $location_videos);
		}
		$this->respond(array(
			'videos' => $videos
		));
	}

	function api_update_index() {
		if (!empty($this->upstream_href)) {
			$videos = $this->get_upstream_index();
		} else {
			$videos = array();
			$today = date('Ymd');
			$locations = $this->get_locations();
			foreach ($locations as $location) {
				if (!empty($_GET['all_dates'])) {
					$location_videos = $this->index_location($location);
				} else {
					$location_videos = $this->index_date($location, $today);
				}
				$videos = array_merge($videos, $location_videos);
			}
		}
		$indexed = $this->index_videos($videos);
		$this->respond(array(
			'indexed' => $indexed
		));
	}
	
	function api_save_image() {
		$video_id = strtolower($_POST['video_id']);
		if (!preg_match('/^([a-z0-9-]+)-(\d{8})-(\d{6})$/', $video_id, $matches)) {
			$this->respond(array(
				'error' => 'Invalid video_id.',
				'video_id' => $video_id
			), 500);
		}
		list(, $location_id, $video_date) = $matches;
		$video_num = str_replace('..', '', $_POST['video_num']);
		$image_time = intval($_POST['image_time']);
		$image_delta = intval($_POST['image_delta']);
		$query = $this->db->prepare("
			INSERT INTO image
			(video_id, viewer_id, location_id, video_date, video_num, image_time, image_delta, image_created)
			VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
		");
		$query->execute(array(
			$video_id,
			$_SESSION['viewer_id'],
			$location_id,
			$video_date,
			$video_num,
			$image_time,
			$image_delta
		));
		$image_id = $this->db->lastInsertId();
		if (!empty($_POST['image_data_uri']) &&
		    substr($_POST['image_data_uri'], 0, 23) == 'data:image/jpeg;base64,') {
			$path = $this->get_image_path($video_id, $video_num, $image_time);
			if (!empty($path)) {
				$dir = __DIR__ . dirname($path);
				if (!file_exists($dir)) {
					mkdir($dir, 0777, true);
				}
				$image_data = base64_decode(substr($_POST['image_data_uri'], 23));
				file_put_contents(__DIR__ . $path, $image_data);
			}
		}
		$query = $this->db->prepare("
			UPDATE viewer
			SET viewer_updated = NOW()
			WHERE viewer_id = ?
		");
		$query->execute(array($_SESSION['viewer_id']));
		if (!empty($_POST['video_status']) &&
		    $_POST['video_status'] == 'rendered') {
			$query = $this->db->prepare("
				UPDATE video
				SET video_status = ?
				WHERE video_id = ?
			");
			$query->execute(array(
				$_POST['video_status'],
				$video_id
			));
			$next_video_num = $this->format_video_num($video_num + 1);
			$video = $this->get_video($location_id, $video_date, $next_video_num);
			$this->respond($video);
		} else {
			$image = array(
				'image_id' => $image_id
			);
			$image = array_merge($image, $this->get_viewer());
			$this->respond($image);
		}
	}
	
	function api_register() {
		if (empty($_POST['email']) ||
		    empty($_POST['password'])) {
			$this->respond(array(
				'error' => "Please include 'email' and 'password'."
			), 500);
		}
		if (empty($_SESSION['viewer_id'])) {
			$this->respond(array(
				'error' => 'Your viewer session ID was not found.'
			), 500);
		}
		$query = $this->db->prepare("
			INSERT INTO login
			(login_email, login_password, login_created, login_updated)
			VALUES (?, ?, NOW(), NOW())
		");
		$email = trim(strtolower($_POST['email']));
		$query->execute(array(
			$email,
			password_hash($_POST['password'],  PASSWORD_DEFAULT)
		));
		$_SESSION['login_id'] = $this->db->lastInsertId();
		$query = $this->db->prepare("
			UPDATE viewer
			SET login_id = ?
			WHERE viewer_id = ?
		");
		$query->execute(array(
			$_SESSION['login_id'],
			$_SESSION['viewer_id']
		));
		$this->respond(array(
			'email' => $email,
			'message' => 'You are now logged in.'
		));
	}
	
	function api_login() {
		if (empty($_POST['email']) ||
		    empty($_POST['password'])) {
			$this->respond(array(
				'error' => "Please include 'email' and 'password'."
			), 500);
		}
		$email = trim(strtolower($_POST['email']));
		$query = $this->db->prepare("
			SELECT login_id, login_password
			FROM login
			WHERE login_email = ?
		");
		$query->execute(array($email));
		if ($query->rowCount() == 0) {
			$this->respond(array(
				'error' => "Oops, ‘{$email}’ is not yet registered."
			), 401);
		}
		$login = $query->fetch();
		if (password_verify($_POST['password'], $login['login_password'])) {
			$_SESSION['login_id'] = $login['login_id'];
			$this->respond(array(
				'login' => $this->get_login()
			));
		} else {
			$this->respond(array(
				'error' => "Sorry, that password was incorrect."
			), 401);
		}
	}
	
	function get_random_video() {
		$video = $this->get_video_by_time();
		if (!empty($video)) {
			return $video;
		}
		$query = $this->db->query("
			SELECT *
			FROM video
			WHERE video_status = 'pending'
			ORDER BY RAND()
			LIMIT 1
		");
		if ($query->rowCount() == 0) {
			$query = $this->db->query("
				SELECT *
				FROM video
				WHERE video_status != 'removed'
				ORDER BY RAND()
				LIMIT 1
			");
		}
		$video = $query->fetch();
		return $video;
	}
	
	function get_video_by_time() {
		$query = $this->db->query("
			SELECT *, CURRENT_TIME - TIME(video_created) AS image_time
			FROM video
			WHERE video_status != 'removed'
			  AND CURRENT_TIME - TIME(video_created) > 0
			  AND CURRENT_TIME - TIME(video_created) < 600
			ORDER BY RAND()
			LIMIT 1
		");
		if ($query->rowCount() == 0) {
			return array();
		}
		return $query->fetch();
	}

	function get_image($video) {
		if (isset($_GET['image_time']) ||
		    isset($video['image_time'])) {
			$query = $this->db->prepare("
				SELECT image_id, image_time
				FROM image
				WHERE video_id = ?
				  AND image_time < ?
					AND ? - image_time < 50
				ORDER BY image_time DESC
				LIMIT 1
			");
			if (isset($_GET['image_time'])) {
				$image_time = $_GET['image_time'];
			} else if (isset($video['image_time'])) {
				$image_time = $video['image_time'];
			}
			$query->execute(array(
				$video['video_id'],
				intval($image_time) + 1,
				intval($image_time) + 1
			));
		} else if (!empty($this->find_latest_image)) {
			$query = $this->db->prepare("
				SELECT image_id, image_time
				FROM image
				WHERE video_id = ?
				  AND viewer_id = ?
				  AND image_delta > 0
				ORDER BY image_time DESC
				LIMIT 1
			");
			$query->execute(array(
				$video['video_id'],
				$_SESSION['viewer_id']
			));
		}
		if (empty($query) || $query->rowCount() == 0) {
			return array();
		}
		$image = $query->fetch();
		if (empty($image)) {
			return array();
		} else {
			return array(
				'image_url' => $this->get_image_url(
					$video['video_id'], $video['video_num'],
					$image['image_time'], $image['viewer_id']
				),
				'image_time' => intval($image['image_time'])
			);
		}
	}
	
	function get_image_url($video_id, $video_num, $image_time, $viewer_id = null) {
		$path = $this->get_image_path($video_id, $video_num, $image_time, $viewer_id);
		if (!empty($path) &&
				file_exists(__DIR__ . $path)) {
			return $this->get_url($path, true);
		} else {
			return '';
		}
	}
	
	function get_image_path($video_id, $video_num, $image_time, $viewer_id = null) {
		if (empty($viewer_id)) {
			$viewer_id = $_SESSION['viewer_id'];
		}
		if (!preg_match('/^(.+?)-(\d{8})/', $video_id, $matches)) {
			dbug("Could not decipher video_id $video_id.");
			return null;
		}
		list(, $location_id, $video_date) = $matches;
		$path = "/images/$location_id/$video_date/$video_num" .
		        "/$image_time-$viewer_id.jpg";
		return $path;
	}
	
	function get_location($video) {
		$query = $this->db->prepare("
			SELECT *
			FROM location
			WHERE location_id = ?
		");
		$query->execute(array(
			$video['location_id']
		));
		return $query->fetch();
	}

	function index_videos($videos) {
		$indexed = array();
	  $existing_ids = $this->index_existing_ids($videos);
		$query = $this->db->prepare("
			INSERT INTO video
			(video_id, location_id, video_date, video_num, video_created)
			VALUES (?, ?, ?, ?, ?)
		");
		$video_counter = array();
		foreach ($videos as $video) {
			if (in_array($video['video_id'], $existing_ids)) {
				continue;
			}
			$video_date = date('Ymd', strtotime($video['video_created']));
			if (empty($video_counter[$video_date])) {
				$video_counter[$video_date] = 1;
			} else {
				$video_counter[$video_date]++;
			}
			$video_num = $this->format_video_num($video_counter[$video_date]);
			$query->execute(array(
				$video['video_id'],
				$video['location_id'],
				$video_date,
				$video_num,
				$video['video_created']
			));
			$indexed[] = $video;
		}
	  return $indexed;
	}

	function index_existing_ids($videos) {
		$existing_ids = array();
		if (!empty($videos)) {
			$ids = array();
			$sql_replace = array();
			foreach ($videos as $video) {
				$sql_replace[] = '?';
				$ids[] = $video['video_id'];
			}
			$sql_replace = implode(', ', $sql_replace);
			$query = $this->db->prepare("
				SELECT video_id
				FROM video
				WHERE video_id IN ($sql_replace)
			");
			$query->execute($ids);
			while ($existing_id = $query->fetchColumn(0)) {
				$existing_ids[] = $existing_id;
			}
		}
		return $existing_ids;
	}

	function index_date($location, $date) {
		$videos = array();
		$dirname = __DIR__ . "/videos/$location/$date";
		if (file_exists($dirname)) {
			$date_dir = opendir($dirname);
			while ($file = readdir($date_dir)) {
				$file_regex = "/^{$location}-{$date}-(\d{6})\.mp4$/";
				if (!preg_match($file_regex, $file, $matches)) {
					continue;
				}
				list(, $time) = $matches;
				$created = date('Y-m-d H:i:s', strtotime("$date $time"));
				$videos[] = array(
					'video_id' => substr($file, 0, -4),
					'location_id' => $location,
					'video_created'  => $created
				);
			}
		}
		return $videos;
	}
	
	function index_location($location) {
		$videos = array();
		$dirname = __DIR__ . "/videos/$location";
		if (file_exists($dirname)) {
			$location_dir = opendir($dirname);
			while ($date = readdir($location_dir)) {
				if (!preg_match('/^\d{8}$/', $date)) {
					continue;
				}
				$date_videos = $this->index_date($location, $date);
				$videos = array_merge($videos, $date_videos);
			}
		}
		return $videos;
	}
	
	function get_upstream_index() {
		$videos = array();
		if (!empty($_GET['all_dates'])) {
			$response = $this->upstream_get('get_dates');
			foreach ($response['dates'] as $date) {
				$response = $this->upstream_get('get_index', array(
					'date' => $date
				));
				$videos = array_merge($videos, $response['videos']);
			}
		} else {
			$response = $this->upstream_get('get_index', array(
				'date' => date('Ymd')
			));
			$videos = $response['videos'];
		}
		return $videos;
	}
	
	function upstream_get($method, $args = null) {
		$url = "$this->upstream_href?method=$method";
		if (!empty($args)) {
			foreach ($args as $key => $value) {
				$url .= '&' . urlencode($key) . '=' . urlencode($value);
			}
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
		curl_setopt($ch, CURLOPT_USERAGENT, 'flightlines');
		$json = curl_exec($ch);
		curl_close($ch);
		return json_decode($json, true);
	}
	
	function respond($args, $http_status = 200) {
		$http_message = 'Mysterious';
		$http_messages = array(
			200 => 'OK',
			401 => 'Unauthorized',
			404 => 'Not Found',
			500 => 'Server Error'
		);
		$response = array(
			'ok' => ($http_status == 200)
		);
		foreach ($args as $key => $value) {
			$response[$key] = $value;
		}
		if (isset($http_messages[$http_status])) {
			$http_message = $http_messages[$http_status];
		}
		header("HTTP/1.1 $http_status $http_message");
	  header('Content-Type: application/json', true);
	  echo json_encode($response);
		exit;
	}
	
	function get_url($path, $local = false) {
		if (!$local && !empty($this->upstream_href)) {
			$url = parse_url($this->upstream_href);
		} else {
			$protocol = $this->is_ssl() ? 'https://' : 'http://';
			$url = parse_url($protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		}
	  $base_dir = dirname($url['path']);
		if ($base_dir == '/') {
			$base_dir = '';
		}
	  $host = "{$url['scheme']}://{$url['host']}";
	  return "{$host}{$base_dir}{$path}";
	}
	
	function get_locations() {
		if (!empty($this->locations)) {
			return $this->locations;
		}
		$query = $this->db->query("
			SELECT location_id
			FROM location
		");
		$this->locations = array();
		while ($location = $query->fetchColumn()) {
			$this->locations[] = $location;
		}
		return $this->locations;
	}
	
	function get_viewer() {
		if (empty($_SESSION['viewer_id'])) {
			return array();
		}
		$query = $this->db->prepare("
			SELECT COUNT(*)
			FROM image
			WHERE viewer_id = ?
			  AND image_type = 'render'
		");
		$query->execute(array($_SESSION['viewer_id']));
		$render_time = 0;
		$render_count = $query->fetchColumn();
		if (is_numeric($render_count)) {
			$render_time = $render_count * 10;
		}
		return array(
			'viewer_id' => (int) $_SESSION['viewer_id'],
			'viewer_render_time' => $render_time
		);
	}
	
	function get_login() {
		$query = $this->db->prepare("
			SELECT login_email, login_status
			FROM login
			WHERE login_id = ?
		");
		$query->execute(array($_SESSION['login_id']));
		$login = $query->fetch();
		return array(
			'id' => intval($_SESSION['login_id']),
			'email' => $login['login_email'],
			'status' => $login['login_status']
		);
	}
	
	function is_ssl() {
		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
		    strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
			return true;
		} else if (isset($_SERVER['HTTPS']) &&
		           (strtolower($_SERVER['HTTPS']) == 'on' ||
		            $_SERVER['HTTPS'] == '1')) {
			return true;
		} else if (isset($_SERVER['SERVER_PORT']) &&
		           $_SERVER['SERVER_PORT'] == '443') {
			return true;
		}
		return false;
	}

	function setup_db($db_host, $db_name, $db_user, $db_password) {
		$db_dsn = "mysql:host=$db_host;dbname=$db_name";
		$this->db = new PDO($db_dsn, $db_user, $db_password);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		$this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$this->db->query("
			SET time_zone = '-5:00'
		");
	}
	
	function setup_session() {
		if (!empty($_SERVER['HTTP_USER_AGENT']) &&
		    $_SERVER['HTTP_USER_AGENT'] == 'flightlines') {
			return;
		}
		session_set_save_handler(
			array($this, 'session_open'),
			array($this, 'session_close'),
			array($this, 'session_read'),
			array($this, 'session_write'),
			array($this, 'session_destroy'),
			array($this, 'session_gc')
		);
		session_set_cookie_params(60 * 60 * 24 * 365, '/');
		session_name('flightlines');
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}
		if (empty($_SESSION['viewer_id'])) {
			$query = $this->db->prepare("
				INSERT INTO viewer
				(viewer_ip, viewer_ua, viewer_created, viewer_updated)
				VALUES (?, ?, ?, ?)
			");
			$query->execute(array(
				$_SERVER['REMOTE_ADDR'],
				$_SERVER['HTTP_USER_AGENT'],
				date('Y-m-d H:i:s'),
				date('Y-m-d H:i:s')
			));
			$id = $this->db->lastInsertId();
			$query = $this->db->prepare("
				SELECT viewer_id
				FROM viewer
				WHERE viewer_id = ?
			");
			$query->execute(array($id));
			$_SESSION['viewer_id'] = $query->fetchColumn();
		}
	}
	
	function session_open() {
		return (!empty($this->db));
	}
	
	function session_close() {
		return true;
	}
	
	function session_read($id) {
		$query = $this->db->prepare("
			SELECT session_data
			FROM session
			WHERE session_id = ?
		");
		if ($query->execute(array($id))) {
			return $query->fetchColumn();
		} else {
			return '';
		}
	}
	
	function session_write($id, $data) {
		$query = $this->db->prepare("
			REPLACE INTO session
			(session_id, session_time, session_data)
			VALUES (?, ?, ?)
		");
		$query->execute(array(
			$id,
			time(),
			$data
		));
		return true;
	}
	
	function session_destroy($id) {
		$query = $this->db->prepare("
			DELETE FROM session
			WHERE session_id = ?
		");
		$query->execute(array($id));
		return true;
	}
	
	function session_gc($max) {
		$query = $this->db->prepare("
			DELETE FROM session_id
			WHERE session_time < ?
		");
		$query->execute(array(
			time() - $max
		));
		return true;
	}

	function db_table_exists($db_name, $table_name) {
		$query = $this->db->prepare("
			SELECT COUNT(*)
			FROM information_schema.tables
			WHERE table_schema = ?
			AND table_name = ?
		");
		$query->execute(array($db_name, $table_name));
	  $count = $query->fetchColumn(0);
	  return ($count > 0);
	}
	
	function format_video_num($video_num) {
		if ($video_num < 10) {
			$video_num = "00$video_num";
		} else if ($video_num < 100) {
			$video_num = "0$video_num";
		}
		return $video_num;
	}

}

$fl = new FlightLines();
