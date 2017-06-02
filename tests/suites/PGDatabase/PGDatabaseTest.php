<?php
class PGDatabaseTest extends PHPUnit_Framework_TestCase {
	public static function get_error_handler() {
		$error_handler = set_error_handler(null);
		set_error_handler($error_handler);
		return $error_handler;
	}

	/**
	 * @expectedException \SnakeDruid\PGDatabaseException
	 */
	public function testConnectionFailure() {
		$host = "localhost";
		$username = "invalid_username";
		$password = "invalid_password";
		$database = "invalid_database";
		$port = 3;
		$charset = 'utf8';
		$db = new \SnakeDruid\PGDatabase($host, $username, $password, $database, $port, $charset);
	}

	public function testConnectionFailureErrorHandlerReset() {
		$phpunit_error_handler = self::get_error_handler();
		$host = "localhost";
		$username = "invalid_username";
		$password = "invalid_password";
		$database = "invalid_database";
		$port = 3;
		$charset = 'utf8';
		try {
			$db = new \SnakeDruid\PGDatabase($host, $username, $password, $database, $port, $charset);
		} catch(\SnakeDruid\PGDatabaseException $e) {
		}
		$this->assertEquals(self::get_error_handler(), $phpunit_error_handler);
	}

	public function testConnectionSuccessWithHebrewCharset() {
		require dirname(dirname(__DIR__))."/settings.php";
		extract($db_settings);
		$charset="WIN1255";
		$db = new \SnakeDruid\PGDatabase($host, $username, $password, 'postgres', $port, $charset);
		$this->assertContains("WIN1255", $db->get_options());
	}
}
