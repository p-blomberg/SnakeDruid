<?php
class PGDatabaseTest extends PHPUnit_Framework_TestCase {
	/**
	 * @expectedException PGDatabaseException
	 */
	public function testConnectionFailure() {
		$host = "localhost";
		$user = "invalid_user";
		$password = "invalid_password";
		$database = "invalid_database";
		$port = 3;
		$db = new PGDatabase($host, $user, $password, $database, $port);
	}

	public function testConnectionFailureErrorHandlerReset() {
		$phpunit_error_handler = self::get_error_handler();
		$host = "localhost";
		$user = "invalid_user";
		$password = "invalid_password";
		$database = "invalid_database";
		$port = 3;
		try {
			$db = new PGDatabase($host, $user, $password, $database, $port);
		} catch(PGDatabaseException $e) {
		}
		$this->assertEquals(self::get_error_handler(), $phpunit_error_handler);
	}
	public static function get_error_handler() {
		$error_handler = set_error_handler(null);
		set_error_handler($error_handler);
		return $error_handler;
	}
}
