<?php
class DBSchema {
	private static $tables;
	private static $connections = [];

	private static function initialize() {
		global $db;
		$columns = $db->query("
			SELECT
				col.table_name,
				col.column_name,
				pk.constraint_type IS NOT DISTINCT FROM 'PRIMARY KEY' AS primary_key
			FROM information_schema.columns col
			LEFT JOIN (
				information_schema.key_column_usage AS k
				JOIN information_schema.table_constraints AS pk ON (
					k.constraint_name = pk.constraint_name
					AND k.constraint_schema = pk.constraint_schema
					AND constraint_type = 'PRIMARY KEY'
				)
			) ON (
				col.table_schema = k.table_schema
				AND col.table_name = k.table_name
				AND col.column_name = k.column_name
			)
			WHERE col.table_schema = 'public'
		");
		foreach($columns as $column) {
			if(!isset(self::$tables[$column['table_name']])) {
				self::$tables[$column['table_name']] = [
					'columns' => [],
					'primary_key' => []
				];
			}
			self::$tables[$column['table_name']]['columns'][] = $column['column_name'];
			if($column['primary_key'] == 't') {
				self::$tables[$column['table_name']]['primary_key'][] = $column['column_name'];
			}
		}
		$constraints = $db->query("
			SELECT
				f.table_name AS from_table,
				f.column_name AS from_column,
				t.table_name AS to_table,
				t.column_name AS to_column,
				f.constraint_name
			FROM information_schema.referential_constraints c
			NATURAL JOIN information_schema.key_column_usage f
			JOIN information_schema.key_column_usage t ON (
				c.unique_constraint_catalog = t.constraint_catalog
				AND c.unique_constraint_schema = t.constraint_schema
				AND c.unique_constraint_name = t.constraint_name
				AND f.ordinal_position = t.ordinal_position
			)
			WHERE f.constraint_schema = 'public'
			ORDER BY f.constraint_name
		");
		$old = null;
		foreach($constraints as $con) {
			if($old != $con['constraint_name']) {
				if(isset($from)) {
					self::$connections[$from][$to] = [
						'fields' => $f,
						'outgoing' => true,
					];
					self::$connections[$to][$from] = [
						'fields' => $t,
						'outgoing' => false,
					];
				}
				$old = $con['constraint_name'];
				$from = $con['from_table'];
				$to = $con['to_table'];
				$f = [];
				$t = [];
			}
			$f[$con['from_column']] = $con['to_column'];
			$t[$con['to_column']] = $con['from_column'];
		}
		if(isset($from)) {
			self::$connections[$from][$to] = [
				'fields' => $f,
				'outgoing' => true,
			];
			self::$connections[$to][$from] = [
				'fields' => $t,
				'outgoing' => false,
			];
		}
	}

	public static function in($table, $column) {
		return in_array($column, self::columns($table));
	}

	public static function columns($table) {
		if(!isset(self::$tables)) self::initialize();
		if(empty(self::$tables[$table])) {
			throw new NoSuchTableException("No such table $table");
		}
		return self::$tables[$table]['columns'];
	}

	public static function primary_key($table) {
		if(!isset(self::$tables)) self::initialize();
		return self::$tables[$table]['primary_key'];
	}

	public static function connection($from, $to, $assert=true) {
		if(array_key_exists($from, self::$connections) &&
			array_key_exists($to, self::$connections[$from])) {
			return self::$connections[$from][$to];
		}
		if($assert) {
			throw new NoConnectionException("No connection from '$from' to '$to'");
		}
		return null;
	}
}
