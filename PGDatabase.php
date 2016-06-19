<?php
class PGResult implements SeekableIterator, arrayaccess{
	private $result, $row;

	public function __construct($result) {
		$this->result = $result;
		$this->row = 0;
		$this->max = pg_num_rows($result);
	}

	public function seek($offset) {
		$this->row = $offset;
	}

	public function current(){
		return $this->offsetGet($this->row);
	}

	public function key() {
		return $this->row;
	}
	public function next() {
		++$this->row;
	}
	public function rewind() {
		$this->row = 0;
	}

	public function valid() {
		return $this->offsetExists($this->row);
	}

	public function offsetExists($offset) {
		return 0 <= $offset && $offset < $this->max;
	}

	public function offsetGet($offset) {
		return pg_fetch_array($this->result, $offset, PGSQL_ASSOC);
	}
	public function offsetSet($offset, $value) {}
	public function offsetUnset($offset) {}
}

class PGDatabase {
	private $db, $port, $host, $user, $password, $database;

	public function __construct($host, $user, $password, $database, $port) {
		set_error_handler('self::ErrorHandler');
		$this->port = $port;
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->database = $database;
		$this->db = self::get_conn($host, $user, $password, $database, $port);
		if(empty($this->db)) {
			throw new PGDatabaseException("Failed to connect to database");
		}
		restore_error_handler();
	}

	public function query($query, $params=[]) {
		$para = [];
		foreach($params as $p) {
			if(is_array($p)) {
				$p = self::escape_array($p);
			}
			$para[] = $p;
		}
		return new PGResult(pg_query_params($this->db, $query, $para));
	}

	public function select_db($database) {
		$this->database = $database;
		pg_close($this->db);
		$this->db = self::get_conn($this->host, $this->user, $this->password, $this->database, $this->port);
	}

	public function multi_query($query) {
		return pg_query($this->db, $query);
	}

	public function error() {
		pg_last_error($this->db);
	}

	public function close() {
		pg_close($this->db);
	}

	private static function get_conn($host, $user, $password, $database, $port) {
		$s = '';
		if($database) $s .= "dbname=$database";
		if($host) $s .= " host=$host";
		if($port) $s .= " port=$port";
		if($user) $s .= " user=$user";
		if($password) $s .= " password=$password";
		return pg_connect($s);
	}

	private static function escape_array($value) {
		$ret = [];
		foreach($value as $v) {
			if(is_array($v)) {
				$ret[] = escape_array($v);
			} else {
				if(is_numeric($v)) {
				} elseif(is_null($v)) {
					$ret[] = 'NULL';
				} elseif(is_bool($v)) {
					$ret[] = $v ? 'TRUE' : 'FALSE';
				} else {
					$v = str_replace('\\', '\\\\', $v);
					$v= '"'.str_replace('"', '\\"', $v).'"';
				}
				$ret[] = $v;
			}
		}
		return '{'.implode(',', $ret).'}';
	}

	public static function ErrorHandler($errno, $errstr, $errfile, $errline) {
		restore_error_handler();
		throw new PGDatabaseException($errstr, $errno);
	}
}

class PGDatabaseException extends Exception {
}
