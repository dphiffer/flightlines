<?php

ini_set('display_errors', false);
ini_set('log_errors', true);
ini_set('error_log', __DIR__ . '/logs/debug.log');
error_reporting(E_ALL);

function dbug() {
	foreach (func_get_args() as $arg) {
		if (is_scalar($arg)) {
			error_log($arg);
		} else {
			$arg = print_r($arg, true);
			$arg = trim($arg);
			error_log($arg);
		}
	}
}