<?php
require_once 'PGDatabase.php';
require_once 'QueryBuilder.php';
require_once 'DBSchema.php';

class SnakeDruid {
	public $_data, $_exists;
	public static $output_htmlspecialchars = true;
	private static $column_ids = [];

	protected static function default_order() {
		return null;
	}

	public function commit() {
		global $db;
		if($this->_exists) {
			throw new Exception("update not implemented");
		} else {
			$query = 'INSERT INTO "'.$this->table_name().'" ("'.
				join('", "', array_keys($this->_data))."\") VALUES\n(";
			$p = [];
			for($i = 1; $i <= count($this->_data); ++$i) {
				$p[] = $i;
			}
			$query .= '$'.join(', $', $p).")\n";
			$query .= "RETURNING *\n";
			$res = $db->query($query, $this->_data);
			$this->_exists = true;
			$this->_data = $res[0];
		}
	}

	public function delete() {
		global $db;
		$table = static::table_name();
		$pk = DBSchema::primary_key($table);
		$query = "
			DELETE FROM \"$table\"
			WHERE ";
		$w = [];
		$params = [];
		foreach($pk as $i => $k) {
			$w[] = "\"$k\" = $".($i+1);
			$params[] = $this->$k;
		}
		$query .= join(' AND ', $w);
		$db->query($query, $params);
		$this->_exists = false;
	}

	public function __call($method, $args) {
		if(class_exists($method) && is_subclass_of($method, 'SnakeDruid')){
			$connection = static::_connection($method);

			$params = [];
			if(count($args) > 0) $params = $args[0];
			foreach($connection['fields'] as $from => $to) {
				$params[$to] = $this->_data[$from];
			}
			$data = $method::selection($params);
			if($connection['outgoing']) {
				return $data[0];
			}
			if(count($data) >= 0) {
				return $data;
			}
			return null;
		}
		throw new Exception("$method is not implemented");
	}

	public function __get($key) {
		if($key == 'id' && !array_key_exists($key, $this->_data)) {
			$key = static::_id_name();
		}
		if(array_key_exists($key, $this->_data)) {
			$ret = $this->_data[$key];
			if(static::$output_htmlspecialchars) {
				$ret = htmlspecialchars($ret, ENT_QUOTES, 'utf-8');
			}
			return $ret;
		}
		if(!static::_in_table($column)) {
			if(class_exists($method) && is_subclass_of($method, 'SnakeDruid')){
				return $this->$method();
			}
			static::_assert_in_table($key);
		}

		return null;
	}

	public function __set($key, $value) {
		if(class_exists($key) && is_subclass_of($key, 'SnakeDruid')){
			if(!is_a($value, $key) && $value != null) {
				throw new Exception("$value is not a $key!");
			}
			$con = static::_connection($key);

			foreach($con['fields'] as $from => $to) {
				if($value == null) {
					$this->_data[$from] = null;
				} else {
					$this->_data[$from] = $value->$to;
				}
			}
			return $value;
		}

		static::_assert_in_table($key);
		return $this->_data[$key] = $value;
	}

	public function __isset($key) {
		if(isset($this->_data[$key])) {
			return true;
		}
		try {
			$data = $this->$key;
			return isset($data);
		} catch(Exception $e) {
			return false;
		}
	}

	public function __construct($data=[], $exists=false) {
		if($exists && empty($data)) {
			throw new Exception("Can't create new instance marked as existing with an empty data array");
		}

		$columns = self::_columns(static::table_name());
		foreach($data as $key => $value) {
			if($key == 'id' && !in_array('id', $columns)) {
				$data[static::_id_name()] = $data['id'];
				unset($data['id']);
			} elseif(!in_array($key, $columns)) {
				unset($data[$key]);
				// FIXME: should we raise instead?
			}
		}
		$this->_exists = $exists;
		$this->_data = $data;
	}

	public static function selection($params=[]) {
		global $db;
		$query = static::_build_query($params, '*');
		$query->default_order = static::default_order();

		try {
			$rows = $db->query($query->query(), $query->params());
		} catch(Exception $e) {
			echo $e->getMessage()."\n";
			echo "Error in query\n".$query->query()."params: ".join(', ', $query->params());
			throw $e;
		}
		$ret = [];
		foreach($rows as $r) {
			$ret[] = new static($r, true);
		}
		return $ret;
	}

	public static function sum($field, $params=[]) {
		global $db;
		$query = static::_build_query($params, '*');

		if(is_array($field)) {
			throw new Exception('Not implemented');
		} else {
			static::_assert_in_table($field);
			$q = "
				SELECT SUM(\"$field\") AS sum
				FROM (".$query->query().") AS s";
			$ret = $db->query($q, $query->params());
			return $ret[0]['sum'];
		}
	}

	public static function count($params=[]) {
		global $db;
		$query = static::_build_query($params, '*');

		$q = "
			SELECT COUNT(*) AS count
			FROM (".$query->query().") AS s";
		$ret = $db->query($q, $query->params());
		return $ret[0]['count'];
	}

	public static function one($params=[]) {
		$res = static::selection($params);
		switch(count($res)) {
			case 0:
				return null;
			case 1:
				return $res[0];
			default:
				throw new Exception("Expected at most one match for query ".print_r($params, true)." but got ".count($sel));
		}
	}

	public static function first($params=[]) {
		$res = static::selection(array_merge($params, ['@limit' => 1]));
		if(empty($res)) return null;
		return $res[0];
	}

	public function duplicate() {
		$dup  = clone $this;
		$dup->_exists = false;
		foreach(static::_primary_key() as $key) {
			unset($dup->_data[$key]);
		}
		return $dup;
	}

	public static function from_field($field, $value) {
		return static::one([$field => $value]);
	}

	public static function from_id($id) {
		return static::from_field(static::_id_name(), $id);
	}

	protected static function _id_name($class=null) {
		$pk = static::_primary_key($class);
		switch(count($pk)) {
			case 0:
				return null;
			case 1:
				return $pk[0];
			default:
				return $pk;
		}
	}

	protected static function _connection($to, $from=null) {
		$to = static::_class_to_table($to);
		$from = static::_class_to_table($from);
		return DBSchema::connection($from, $to);
	}

	protected static function _primary_key($class=null) {
		return DBSchema::primary_key(static::_class_to_table($class));
	}

	protected static function _unique_identifier($class=null) {
		$table = static::_class_to_table($class);
		$pk = DBSchema::primary_key($table);
		return '"'.$table.'"."'.join('", "'.$table.'"."', $pk).'"';
	}

	protected static function _class_to_table($class) {
		if(empty($class)) {
			return static::table_name();
		} elseif(class_exists($class) && is_subclass_of($class, 'SnakeDruid')){
			return $class::table_name();
		} else {
			return $class;
		}
	}

	protected static function _columns($class) {
		return DBSchema::columns(static::_class_to_table($class));
	}

	private static function _build_query($params, $select) {
		$query = new QueryBuilder($select, static::table_name());
		self::_handle_params($query, $params);
		return $query;
	}

	protected static function _in_table($column, $class=null) {
		return DBSchema::in(static::_class_to_table($class), $column);
	}

	protected static function _assert_in_table($column, $class=null) {
		$table = static::_class_to_table($class);
		if(!static::_in_table($column, $table)) {
			throw new Exception("No such column '$column' in table '$table'");
		}
	}

	private static function _join($table, $path, &$query) {
		$prev = $table;
		while($curr = array_shift($path)) {
			$curr = static::_class_to_table($curr);
			$query->join($prev, $curr);
			$prev = $curr;
		}
		return $prev;
	}

	private static function _handle_params(&$query, $params, $glue='AND') {
		$table_name = static::table_name();
		foreach($params as $column => $value) {
			if($column[0] == '@') {
				switch($column) {
				case '@limit':
					$query->limit($value);
					break;
				case '@order':
					$query->order($value);
					break;
				case '@join':
					foreach($value as $column => $v) {
						$column = explode(':', $column);
						$operator = 'using';
						if(count($column) > 1) {
							$operator = $column[1];
						}
						$path = explode('.', $column[0]);
						$column = static::_class_to_table(array_pop($path));
						$table = $table_name;
						if(count($path) > 0) {
							$table = static::_join($table_name, $path, $query);
						}
						
						$query->join($table, $column, [
							'params' => $value,
							'operator' => $operator
						]);
					}
					break;
				default:
					throw new Exception("not implemented: '$column'");
				}
				continue;
			} else {
				$column = explode(':', $column);
				if(count($column) > 1) {
					throw new Exception('not implemented');
				} else {
					$operator = '=';
				}
			}

			$column = $column[0];
			$path = explode('.', $column);
			if(count($path) > 1) {
				$column = array_pop($path);
				$table = static::_join($table_name, $path, $query);
			} else {
				static::_assert_in_table($column, $table_name);
				$table = $table_name;
			}

			$query->where($table, $column, $operator, $value);
		}
	}
}
