<?php

require_once __DIR__ . '/debug.php';

class FlightLines {
	
	var $db_version = 1;
	var $locations = array(
		'nowhere',
		'jcal'
	);
	
	function __construct() {
		require_once __DIR__ . '/config.php';
		date_default_timezone_set($timezone);
		$this->setup_db($db_host, $db_name, $db_user, $db_password);
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
		$rendered = false;
	  $video = $this->get_pending_video();
	  if (empty($video)) {
	  	$video = $this->get_next_video($after_id);
	  	$rendered = true;
	  }
	  $date_dir = date('Ymd', strtotime($video['created']));
	  $video_url = $this->get_url("/videos/{$video['location']}/$date_dir/{$video['id']}.mp4");
	  $this->respond(array(
	  	'video_url' => $video_url,
	  	'video' => $video,
	  	'after_id' => $after_id,
	  	'rendered' => $rendered
		));
	}

	function get_pending_video() {
		$query = $this->db->query("
			SELECT *
			FROM video
			WHERE status = 'pending'
			ORDER BY created
			LIMIT 1
		");
		if ($query->rowCount() == 0) {
			return null;
		}
		$video = $query->fetch();
		$query = $this->db->prepare("
			UPDATE video
			SET status = 'in-progress'
			WHERE id = ?
		");
		$query->execute(array($video['id']));
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

	function api_index() {
		$all_dates = !empty($_GET['all_dates']);
		$videos = array();
	  foreach ($this->locations as $location) {
	  	if ($all_dates) {
	  		$location_videos = $this->index_location($location);
	  		$videos = array_merge($videos, $location_videos);
	  	} else {
	  		$today = date('Ymd');
	  		$date_videos = $this->index_date($location, $today);
	  		$videos = array_merge($videos, $date_videos);
	  	}
	  }
	  $indexed = $this->index_videos($videos);
	  $this->respond(array(
	  	'indexed' => $indexed,
	  	'all_dates' => $all_dates
		));
	}
	
	function api_save_rendering() {
		$id = strtolower($_GET['id']);
		$id = trim($id);
		if (!preg_match('/^(\w+)-(\d{8})-(\d{6})$/', $id, $matches)) {
			$this->respond(array(
				'error' => 'Invalid video ID.',
				'id' => $id
			), 500);
		}
		list(, $location, $date, $time) = $matches;
		if (!file_exists(__DIR__ . "/videos/$location/$date/$id.mp4")) {
			$this->respond(array(
				'error' => 'Video not found.',
				'id' => $id
			), 404);
		}
	  $base64 = substr($_POST['image_data'], strlen('data:image/png;base64,'));
	  $binary = base64_decode($base64);
	  $path = "/videos/$location/$date/$id.png";
	  $filename = __DIR__ . $path;
	  file_put_contents($filename, $binary);
	  $this->respond(array(
	  	'image_url' => $this->get_url($path),
	  	'id' => $id
		));
		$query = $this->db->prepare("
			UPDATE video
			SET status = 'rendered'
			WHERE id = ?
		");
		$query->execute(array($id));
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

	function respond($args, $http_status = 200) {
		$http_message = 'Mysterious';
		$http_messages = array(
			200 => 'OK',
			404 => 'Not Found',
			403 => 'Not Allowed',
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
	}
	
	function get_url($path) {
		$url = parse_url($_SERVER['REQUEST_URI']);
	  $base_dir = dirname($url['path']);
	  $host = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['SERVER_NAME']}";
	  return "{$host}{$base_dir}{$path}";
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
			dbug('exists');
			return;
		}
		$db = $this->db;
		require_once __DIR__ . '/db/setup.php';
		if (is_writable(__DIR__)) {
			file_put_contents(__DIR__ . '/db/.db_version', $this->db_version);
		}
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
