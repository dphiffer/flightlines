<?php

require_once __DIR__ . '/debug.php';

class FlightLines {
	
	var $db_version = 1;
	var $locations = array(
		'nowhere',
		'jcal',
		'1381-myrtle',
		'flux-factory'
	);
	
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
	
	function get_video($after_id = null) {
		$rendered = false;
	  $video = $this->get_pending_video();
		/*$video = array(
			'id' => '1381-myrtle-20151205-165438',
    	'location' => '1381-myrtle',
    	'status' => 'pending',
    	'created' => '2015-12-05 16:54:38'
		);*/
	  if (empty($video)) {
	  	$video = $this->get_next_video($after_id);
	  	$rendered = true;
	  }
		$image = $this->get_image($video);
		//$image = null;
		$location = $this->get_location($video);
	  $date_dir = date('Ymd', strtotime($video['created']));
	  $video_url = $this->get_url("/videos/{$video['location']}/$date_dir/{$video['id']}.mp4");
	  return array(
			'viewer' => $this->viewer,
	  	'video_url' => $video_url,
	  	'video' => $video,
			'image' => $image,
			'location' => $location,
	  	'after_id' => $after_id,
	  	'rendered' => $rendered
		);
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
		), 404);
	}
	
	function api_get_video() {
		$after_id = null;
		if (!empty($_GET['after_id'])) {
			$after_id = strtolower($_GET['after_id']);
			$after_id = trim($after_id);
		}
		$this->respond($this->get_video($after_id));
	}

	function get_pending_video() {
		$query = $this->db->query("
			SELECT *
			FROM video
			WHERE status = 'pending'
			ORDER BY RAND()
			LIMIT 1
		");
		if ($query->rowCount() == 0) {
			return null;
		}
		$video = $query->fetch();
		return $video;
	}

	function get_next_video($after_id) {
		if (empty($after_id)) {
			return $this->get_first_video();
		}
	  $query = $this->db->prepare("
			SELECT *
			FROM video
			WHERE id > ?
			ORDER BY created
			LIMIT 1
		");
		$query->execute(array($after_id));
		if ($query->rowCount() == 0) {
			return $this->get_first_video();
		} else {
			return $query->fetch();
		}
	}

	function get_first_video() {
	  $query = $this->db->query("
			SELECT *
			FROM video
			ORDER BY created
			LIMIT 1
		");
		return $query->fetch();
	}
	
	function get_image($video) {
		$query = $this->db->prepare("
			SELECT video_time, image_data_uri
			FROM image
			WHERE video = ?
			  AND pixel_delta > 0
			ORDER BY image_timestamp DESC
			LIMIT 1
		");
		$query->execute(array(
			$video['id']
		));
		$image = $query->fetch();
		if (empty($image)) {
			return null;
		} else {
			return array(
				'video_time' => intval($image['video_time']),
				'data_uri' => $image['image_data_uri']
			);
		}
	}
	
	function get_location($video) {
		$query = $this->db->prepare("
			SELECT *
			FROM location
			WHERE id = ?
		");
		$query->execute(array(
			$video['location']
		));
		return $query->fetch();
	}
	
	function api_download_index() {
		$this->api_index(true);
	}

	function api_index($nowrite = false) {
		$videos = array();
		$today = date('Ymd');
		$older_date = $this->get_older_date();
		if (!empty($this->upstream_href)) {
			$videos = $this->get_upstream_videos();
		} else {
			foreach ($this->locations as $location) {
				$today_videos = $this->index_date($location, $today);
				$videos = array_merge($videos, $today_videos);
				if (!empty($older_date)) {
					$older_videos = $this->index_date($location, $older_date);
					$videos = array_merge($videos, $older_videos);
				}
			}
			dbug($older_date);
		}
		if ($nowrite) {
			$this->respond(array(
				'videos' => $videos
			));
		} else {
			$indexed = $this->index_videos($videos);
			$this->respond(array(
				'indexed' => $indexed
			));
		}
	}
	
	function api_save_image() {
		$video = strtolower($_POST['video']);
		$video = trim($video);
		if (!preg_match('/^([a-z0-9-]+)-(\d{8})-(\d{6})$/', $video, $matches)) {
			$this->respond(array(
				'error' => 'Invalid video ID.',
				'video' => $video
			), 500);
		}
		list(, $location, $date, $time) = $matches;
		$video_start = strtotime("$date $time");
		$video_time = intval($_POST['video_time']);
		$image_timestamp = $video_start + $video_time;
		$video_start = date('Y-m-d H:i:s', $video_start);
		$pixel_delta = intval($_POST['pixel_delta']);
		if ($pixel_delta == 0) {
			$data_uri = '';
		} else if (!empty($_POST['image_data_uri']) &&
		           substr($_POST['image_data_uri'], 0, 23) != 'data:image/jpeg;base64,') {
			$this->respond(array(
				'error' => 'Image should be a data URI.'
			), 500);
		} else {
			$data_uri = $_POST['image_data_uri'];
		}
		$query = $this->db->prepare("
			INSERT INTO image
			(viewer, video, location, video_start, video_time, image_timestamp, image_data_uri, pixel_delta)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?)
		");
		$query->execute(array(
			$this->viewer['id'],
			$video,
			$location,
			$video_start,
			$video_time,
			$image_timestamp,
			$data_uri,
			$pixel_delta
		));
		$image = $this->db->lastInsertId();
		$query = $this->db->prepare("
			UPDATE viewer
			SET updated = NOW(),
			    render_time = render_time + 10
			WHERE id = ?
		");
		$query->execute(array($this->viewer['id']));
		$query = $this->db->prepare("
			SELECT render_time
			FROM viewer
			WHERE id = ?
		");
		$query->execute(array($this->viewer['id']));
		$_SESSION['render_time'] = $query->fetchColumn();
		$this->viewer = array(
			'id' => $_SESSION['viewer'],
			'render_time' => $_SESSION['render_time']
		);
		if (!empty($_POST['status']) &&
		    $_POST['status'] == 'rendered') {
			$query = $this->db->prepare("
				UPDATE video
				SET status = ?
				WHERE id = ?
			");
			$query->execute(array(
				$_POST['status'],
				$video
			));
			$video = $this->get_video($video);
			$video['previous_id'] = $video;
			$this->respond($video);
		} else {
			$this->respond(array(
				'image' => $image
			));
		}
	}

	function index_videos($videos) {
		$indexed = array();
	  $existing_ids = $this->index_existing_ids($videos);
		$query = $this->db->prepare("
			INSERT INTO video
			(id, location, status, created)
			VALUES (?, ?, 'pending', ?)
		");
		foreach ($videos as $video) {
			if (in_array($video['id'], $existing_ids)) {
				continue;
			}
			$query->execute(array(
				$video['id'],
				$video['location'],
				$video['created']
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
				$ids[] = $video['id'];
			}
			$sql_replace = implode(', ', $sql_replace);
			$query = $this->db->prepare("
				SELECT id
				FROM video
				WHERE id IN ($sql_replace)
			");
			$query->execute($ids);
			while ($existing_id = $query->fetchColumn(0)) {
				$existing_ids[] = $existing_id;
			}
		}
		return $existing_ids;
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
					'id' => substr($file, 0, -4),
					'location' => $location,
					'created'  => $created
				);
			}
		}
		return $videos;
	}
	
	function get_older_date() {
		$older_date = date('Ymd', strtotime('yesterday'));
		if (!empty($_GET['older_date']) &&
		    preg_match('/^\d{8}$/', $_GET['older_date'])) {
			$older_date = $_GET['older_date'];
		} else {
			$query = $this->db->query("
				SELECT DATE(created)
				FROM video
				ORDER BY created
				LIMIT 1
			");
			if (!empty($query)) {
				$oldest_date = $query->fetchColumn();
				if (!empty($oldest_date)) {
					$oldest_time = strtotime($oldest_date);
					$older_date = date('Ymd', $oldest_time - 24 * 60 * 60);
				}
			}
		}
		return $older_date;
	}
	
	function get_upstream_videos() {
		$older_date = $this->get_older_date();
		$url = "$this->upstream_href?method=download_index&older_date=$older_date";
		$ch = curl_init();
		dbug($url);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
		$json = curl_exec($ch);
		curl_close($ch);
		$response = json_decode($json, true);
		if (!empty($response['videos'])) {
			return $response['videos'];
		} else {
			return array();
		}
	}
	
	function respond($args, $http_status = 200) {
		$http_message = 'Mysterious';
		$http_messages = array(
			200 => 'OK',
			404 => 'Not Found',
			403 => 'Not Allowed',
			500 => 'Server Error'
		);
		$response = array(
			'ok' => ($http_status == 200),
			'viewer' => $this->viewer
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
	
	function get_url($path) {
		if (!empty($this->upstream_href)) {
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
		if (file_exists(__DIR__ . '/db/.db_version')) {
			$curr_version = file_get_contents(__DIR__ . '/db/.db_version');
			if ($curr_version == $this->db_version) {
				return;
			}
		}
		if ($this->db_table_exists($db_name, 'video')) {
			return;
		}
		$db = $this->db;
		require_once __DIR__ . '/db/setup.php';
		if (is_writable(__DIR__)) {
			file_put_contents(__DIR__ . '/db/.db_version', $this->db_version);
		}
	}
	
	function setup_session() {
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
		if (empty($_SESSION['viewer'])) {
			$query = $this->db->prepare("
				INSERT INTO viewer
				(ip_address, user_agent, created, updated)
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
				SELECT id
				FROM viewer
				WHERE id = ?
			");
			$query->execute(array($id));
			$_SESSION['viewer'] = $query->fetchColumn();
			$_SESSION['render_time'] = 0;
		}
		$this->viewer = array(
			'id' => $_SESSION['viewer'],
			'render_time' => $_SESSION['render_time']
		);
	}
	
	function session_open() {
		return (!empty($this->db));
	}
	
	function session_close() {
		return true;
	}
	
	function session_read($id) {
		$query = $this->db->prepare("
			SELECT data
			FROM session
			WHERE id = ?
		");
		if ($query->execute(array($id))) {
			return $query->fetchColumn();
		} else {
			return '';
		}
	}
	
	function session_write($id, $data) {
		$access = time();
		$query = $this->db->prepare("
			REPLACE INTO session
			(id, access, data)
			VALUES (?, ?, ?)
		");
		$query->execute(array(
			$id,
			$access,
			$data
		));
		return true;
	}
	
	function session_destroy($id) {
		$query = $this->db->prepare("
			DELETE FROM session
			WHERE id = ?
		");
		$query->execute(array($id));
		return true;
	}
	
	function session_gc($max) {
		$old = time() - $max;
		$query = $this->db->prepare("
			DELETE FROM session
			WHERE access < ?
		");
		$query->execute(array($old));
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

}

$fl = new FlightLines();
