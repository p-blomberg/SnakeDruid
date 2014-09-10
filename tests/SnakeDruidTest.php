<?php
class SnakeDruidTest extends SnakeDruid {
	public static function test_id_name($class=null) {
		return static::_id_name($class);
	}

	public static function test_columns($class) {
		return static::_columns($class);
	}

	public static function test_class_to_table($class=null) {
		return static::_class_to_table($class);
	}

	public static function test_in_table($column, $class) {
		return static::_in_table($column, $class);
	}
}
