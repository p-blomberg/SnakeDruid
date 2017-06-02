<?php

$path = realpath(dirname(dirname(__FILE__)));
$settings_file = $path . "/tests/settings.php";

if(file_exists($settings_file)) {
	include $settings_file;
} else {
	echo "\033[0;31m";
	echo "Please configure test database in settings.php (see settings.php.sample)\n";
	echo "\033[0;37m";
	exit(-1);
}

/*
 * Setup database for testing
 */
function db_init() {
	global $db, $db_settings;
	/* Database */
	$db = new CountingDB(
		$db_settings['host'],
		$db_settings['username'],
		$db_settings['password'],
		"postgres",
		$db_settings['port'],
		$db_settings['charset']
	);

	$db->query("DROP DATABASE IF EXISTS {$db_settings['database']}");
	$db->query("CREATE DATABASE {$db_settings['database']}");
	db_select_database();
	db_run_file("db.sql");
}

function db_select_database() {
	global $db, $db_settings;
	$db->select_db($db_settings['database']);
}

function db_run_file($filename) {
	global $db;
	$path = realpath(dirname(__FILE__) . "/" . $filename );
	$contents = file_get_contents($path);

	if(!$db->multi_query($contents)) {
		throw new Exception("Failed to execute query: {$db->error()}\n");
	}
}

function db_query($query) {
	global $db;
	if(!$db->query($db)) {
		throw new Exception("Failed execute manual query '$query': ".$db->error);
	}
}

function db_close() {
	global $db, $db_settings;
	$db->close();
}

/**
 * Counting database class
 */

class CountingDB extends \SnakeDruid\PGDatabase {

	public static $queries = 0;

	public function prepare($query) {
		return new CountingStatement($this, $query);
	}
}
