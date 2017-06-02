<?php
class SnakeFetcher {
	/**
	 * Fetch one object by value of one field
	 */
	public function from_field($class, $field, $value) {
		return $this->one($class, [$field => $value]);
	}

	/**
	 * Fetch one object by primary key
	 */
	public function from_id($class, $id) {
		return $this->from_field($class, $this->_id_name($class), $id);
	}

	/**
	 * Fetch one object. If the query returns more than one row, an exception is thrown.
	 */
	public function one($class, $filter=[]) {
		$res = $this->selection($class, $filter);
		switch(count($res)) {
			case 0:
				return null;
			case 1:
				return $res[0];
			default:
				throw new TooManyMatchesException("Expected at most one match for query ".print_r($filter, true)." but got ".count($res));
		}
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
	public function selection($class, $filter=[]) {
		global $db;
		$query = $this->_build_query($class::table_name(), $filter, '*');
		$query->default_order = null;

		$rows = $db->query($query->query(), $query->params());
		$ret = [];
		foreach($rows as $r) {
			$ret[] = new $class($r, true);
		}
		return $ret;
	}

	private function _build_query($table_name, $filter, $select) {
		$query = new QueryBuilder($select, $table_name);
		$this->_handle_params($table_name, $query, $filter);
		return $query;
	}

	private function _handle_params($table_name, &$query, $filter, $glue='AND') {
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
						$column = $this->_class_to_table(array_pop($path));
						$table = $table_name;
						if(count($path) > 0) {
							$table = $this->_join($table_name, $path, $query);
						}

						$query->join($table, $column, [
							'params' => $value,
							'operator' => $operator
						]);
					}
					break;
				case '@or':
					$query2 = new QueryBuilder(null, $table_name, 'OR', $query);
					self::_handle_params($table_name, $query2, $value);
					$query2->update_master($query);
					break;
				case '@and':
					$query2 = new QueryBuilder(null, $table_name, 'AND', $query);
					self::_handle_params($table_name, $query2, $value);
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
				$table = $this->_join($table_name, $path, $query);
			} else {
				$this->_assert_in_table($column, $table_name);
				$table = $table_name;
			}

			$query->where($table, $column, $operator, $value);
		}
	}

	protected function _in_table($column, $table_name) {
		return DBSchema::in($table_name, $column);
	}

	protected function _assert_in_table($column, $table_name) {
		if(!$this->_in_table($column, $table_name)) {
			throw new NoColumnException("No such column '$column' in table '$table_name'");
		}
	}

	private function _join($table_name, $path, &$query) {
		$prev = $table_name;
		while($curr = array_shift($path)) {
			$query->join($prev, $curr);
			$prev = $curr;
		}
		return $prev;
	}

	/**
	 * Get the name of the primary key column(s)
	 */
	protected function _id_name($class) {
		$pk = $this->_primary_key($class);
		switch(count($pk)) {
			case 0:
				return null;
			case 1:
				return $pk[0];
			default:
				return $pk;
		}
	}

	/**
	 * Get list of primary keys
	 */
	protected function _primary_key($class=null) {
		return DBSchema::primary_key($this->_class_to_table($class));
	}

	/**
	 * Get name of DB table from class
	 */
	protected function _class_to_table($class) {
		if(!class_exists($class)) {
			throw new \Exception('Class "'.$class.'" not found');
		}
		if(!is_subclass_of($class, 'SnakeDruid')) {
			throw new \Exception("Class ".$class." is not a subclass of SnakeDruid");
		}
		return $class::table_name();
	}
}
