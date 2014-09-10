<?php

class DatabaseTestCase extends PHPUnit_Framework_TestCase {
	protected $backupGlobalsBlacklist = array('db');

	public static function setUpBeforeClass() {
		global $cache;
		db_init();
	}

	public function setUp() {
		SnakeDruid::$output_htmlspecialchars = false;
	}

	public static function tearDownAfterClass() {
		db_close();
	}
}
