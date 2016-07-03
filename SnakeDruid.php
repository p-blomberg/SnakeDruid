<?php
require_once 'PGDatabase.php';
require_once 'QueryBuilder.php';
require_once 'DBSchema.php';

/**
 * An abstract class that provides object-relational mapping.
 *
 * It requires a global variable $db that is a PGDatabase object.
 * Extending classes must declair the method table_name() returning
 *   the name of the associated table.
 *
 * Things in the db
 *
 * @method Object|null|array <class_name>(array $filter)
 *   If there is a foreign key from this class to the other the coresponding
 *   object is returned. If there is a foreign key to this table from the other
 *   class a list of objects is returned.
 *   @param array $filter @see SnakeDruid::selection() for syntax.
 *   @throws NoConnectionException if there is no foreign key either direction.
 */
abstract class SnakeDruid {
	/**
	 * @var bool $output_htmlspecialchars
	 * If set variables goten from this class will pass through
	 * htmlspecialchars first.
	 *
	 * htmlspecialchars is called with the options ENT_QUOTES and 'utf-8'.
	 */
	public static $output_htmlspecialchars = true;
	protected $_data, $_exists, $_pk;

	/**
	 * Returns the name of the table the class uses.
	 *
	 * This should be an abstract class but php does not allow abstract and
	 * static at the same time.
	 *
	 * @return string The name of the associated table.
	 */
	protected static function table_name() {
		throw new Exception(get_called_class().' does not implement method table_name()');
	}

	/**
	 * If declared it will set the default order sets of objects will be
	 * returned unless otherwise stated in the query.
	 */
	protected static function default_order() {
		return null;
	}

	/**
	 * Saves all changes to this object the database.
	 *
	 * This object is updated with any the result of any db trigers such
	 * as an automatically updated modified_at field.
	 *
	 * Other objects pointing to the same row are not updated.
	 *
	 * It is suggested to overload this method implementing business logic
	 * validations that span multiple columns here.
	 *
	 * @return void
	 * @todo: implement object update
	 */
	public function commit() {
		global $db;
		if($this->_exists) {
			$query = 'UPDATE "'.$this->table_name()."\" SET\n";
			$params = [];
			$i = 0;
			$set = [];
			foreach($this->_data as $key => $value) {
				$params[] = $value;
				$set[] = '"'.$key.'" = $'.++$i;
			}
			$query .= join(",\n", $set);
			$wheres = [];
			foreach($this->_pk as $key => $value) {
				$params[] = $value;
				$wheres[] = '"'.$key.'" = $'.++$i."\n";
			}
			$query .= 'WHERE '.join('AND ', $wheres)."\n";
			$query .= 'RETURNING *';
			$res = $db->query($query, $params);
			$this->_data = $res[0];
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
		$this->_update_primary_key();
	}

	/**
	 * Deletes this object from the db.
	 *
	 * If this object is later commited, it will be recreated.
	 *
	 * @return void
	 */
	public function delete() {
		global $db;
		$table = static::table_name();
		$pk = DBSchema::primary_key($table);
		$query = "
			DELETE FROM \"$table\"
			WHERE ";
		$w = [];
		$filter = [];
		foreach($pk as $i => $k) {
			$w[] = "\"$k\" = $".($i+1);
			$filter[] = $this->$k;
		}
		$query .= join(' AND ', $w);
		$db->query($query, $filter);
		$this->_update_primary_key();
		$this->_exists = false;
	}

	public function __call($method, $args) {
		if(class_exists($method) && is_subclass_of($method, 'SnakeDruid')){
			$connection = static::_connection($method);

			$filter = [];
			if(count($args) > 0) $filter = $args[0];
			foreach($connection['fields'] as $from => $to) {
				$filter[$to] = $this->_data[$from];
			}
			$data = $method::selection($filter);
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

	/**
	 * @var mixed <column_name> set or get the value of the column for this row.
	 *   Use @see SnakeDruid::commit() to write changes to db.
	 *   @throws NoColumnException if column is not pressent in the table.
	 *
	 *   It is adviced that extending classes overload __set to implement
	 *   validation of single values. __get may be overloaded for changing
	 *   behavior of fetched variables or blocking access.
	 *
	 *   isset on variables works as expected.
	 */
	/**
	 * @var Object|null|array <class_name> get associated Object or array of
	 *   objects associated with foreign keys @see SnakeDruid::<class_name>().
	 *   If the foreign key is outgoing this variable is settable
	 */
	public function __get($key) {
		if($key == 'id' && !array_key_exists($key, $this->_data)) {
			$key = static::_id_name();
			if($key === null) {
				throw new Exception("Failed to find primary key");
			}
		}
		if(array_key_exists($key, $this->_data)) {
			$ret = $this->_data[$key];
			if(static::$output_htmlspecialchars && is_string($ret)) {
				$ret = htmlspecialchars($ret, ENT_QUOTES, 'utf-8');
			}
			return $ret;
		}
		if(!static::_in_table($key)) {
			if(class_exists($key) && is_subclass_of($key, 'SnakeDruid')){
				return $this->$key();
			}
			static::_assert_in_table($key);
		}

		return null;
	}

	public function __set($key, $value) {
		if(class_exists($key) && is_subclass_of($key, 'SnakeDruid')){
			if(!is_a($value, $key) && $value != null) {
				throw new TypeMismatchException("$value is not a $key!");
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

	/**
	 * Create new object.
	 *
	 * @param array $data If provided it is expected to have only keys that are
	 *   columns in the table.
	 * @param bool $exists Tells the object if it the coresponding row exists in
	 *   the database. Do not touch this unless you are sure of what you are doing!
	 */
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
		$this->_update_primary_key();
	}

	/**
	 * Returns a set of objects
	 *
	 * @param array $filter The filter defining which objects to retreive
	 *   where options:
	 *     '[[table name | class name .] ...] column name [: operator [: anything]]' => $value
	 *     example:
	 *     'Foo.FooBar.column1:>' => 42
	 *     This joins in the table for Foo, then the table for FooBar and makes
	 *     sure column1 in FooBar is grater than 42. the tables are required to
	 *     have foreign keys linking them.
	 *   operators:
	 *     If omited = is used.
	 *     The operators =, !=, >=, <=, <>, ~, ~*, !~, !~*, like and ilike all
	 *     work as specified in PostgreSQL documentation.
	 *     regexp is an alias for ~.
	 *     in and not_in checks if the columns value is repressented in the
	 *     supplied array. @throws ParameterException if the value is not an
	 *     array or null.
	 *     null and not_null checks if the column is null.
	 *     distinct_from and not_distinct_from works as != and = but handles
	 *     comparisons with NULL. (NULL is not distinct from NULL but NULL != NULL)
	 *   specials:
	 *     @order order by suplied column or columns.
	 *     @limit if one value; limit to that, if two values; limit the first
	 *       offset the second.
	 *     @join allows specifying join terms other then what foreign keys
	 *       define. Specify this prior to using the columns for other purposes.
	 *     @and Takes an array of clauses that all need to be true.
	 *     @or Takes an array of clauses that any one of them needs to be true.
	 *     @manual_query Array, lets you specify a custom whare clause it uses the
	 *       keys 'where' for the query text (use $1, $2 etc for parameters) and
	 *       'params' for the values to be used.
	 *     @custom_order Adds a query part to the order by part of the query.
	 * @return array of objects matching the filter.
	 */
	public static function selection($filter=[]) {
		global $db;
		$query = static::_build_query($filter, '*');
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

	/**
	 * Returns the sum of the field for all rows matching the filter.
	 *
	 * @param $field string|array the name or names of fields to be sumed up.
	 * @param $filter @see SnakeDruid::selection for details.
	 * @return numeric the sum of the field for all matching rows.
	 */
	public static function sum($field, $filter=[]) {
		global $db;
		$query = static::_build_query($filter, '*');

		$sum_this = '';
		if(is_array($field)) {
			if(count($field) % 2 != 1) {
				throw  new Exception('Invalid number of parameters for fields to summarize');
			}
			while($col = array_shift($field)) {
				static::_assert_in_table($col);
				$sum_this .= '"'.$col.'"';
				$operator = array_shift($field);
				if($operator) {
					static::_assert_valid_operator($operator);
					$sum_this .= $operator;
				}
			}
		} else {
			static::_assert_in_table($field);
			$sum_this = '"'.$field.'"';
		}
		$q = "
			SELECT SUM($sum_this) AS sum
			FROM (".$query->query().") AS s";
		$ret = $db->query($q, $query->params());
		return $ret[0]['sum'];
	}

	/**
	 * The number of rows matching the filter.
	 *
	 * @param filter $array @see SnakeDruid::selection for details.
	 * @return integer the number of rows matched by the filter.
	 */
	public static function count($filter=[]) {
		global $db;
		$query = static::_build_query($filter, '*');

		$q = "
			SELECT COUNT(*) AS count
			FROM (".$query->query().") AS s";
		$ret = $db->query($q, $query->params());
		return $ret[0]['count'];
	}

	public static function one($filter=[]) {
		$res = static::selection($filter);
		switch(count($res)) {
			case 0:
				return null;
			case 1:
				return $res[0];
			default:
				throw new ToManyMatchesException("Expected at most one match for query ".print_r($filter, true)." but got ".count($res));
		}
	}

	public static function first($filter=[]) {
		$res = static::selection(array_merge($filter, ['@limit' => 1]));
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

	protected function _update_primary_key() {
		if($this->_exists) {
			$this->_pk = [];
			foreach(static::_primary_key() as $key) {
				$this->_pk[$key] = $this->_data[$key];
			}
		} else {
			$this->_pk = null;
		}
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

	private static function _build_query($filter, $select) {
		$query = new QueryBuilder($select, static::table_name());
		self::_handle_params($query, $filter);
		return $query;
	}

	protected static function _in_table($column, $class=null) {
		return DBSchema::in(static::_class_to_table($class), $column);
	}

	protected static function _assert_in_table($column, $class=null) {
		$table = static::_class_to_table($class);
		if(!static::_in_table($column, $table)) {
			throw new NoColumnException("No such column '$column' in table '$table'");
		}
	}

	protected static function _assert_valid_operator($operator) {
		if(!in_array($operator, ['/', '*', '-', '+', '%'])) {
			throw new Exception("Invalid operator: '$operator'");
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

	private static function _handle_params(&$query, $filter, $glue='AND') {
		$table_name = static::table_name();
		foreach($filter as $column => $value) {
			if($column[0] == '@') {
				if(strpos($column, ':') !== false) {
					$column = strstr($column, ':', true);
				}
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
				case '@or':
					$query2 = new QueryBuilder(null, static::table_name(), 'OR', $query);
					self::_handle_params($query2, $value);
					$query2->update_master($query);
					break;
				case '@and':
					$query2 = new QueryBuilder(null, static::table_name(), 'AND', $query);
					self::_handle_params($query2, $value);
					$query2->update_master($query);
					break;
				default:
					throw new Exception("not implemented: '$column'");
				}
				continue;
			} else {
				$column = explode(':', $column);
				if(count($column) > 1) {
					$operator = $column[1];
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

class SnakeDruidException extends Exception {}
class NoConnectionException extends SnakeDruidException {}
class NoColumnException extends SnakeDruidException {}
class NoSuchTableException extends SnakeDruidException {}
class TypeMismatchException extends SnakeDruidException {}
class ToManyMatchesException extends SnakeDruidException {}
class ParameterException extends SnakeDruidException {}
