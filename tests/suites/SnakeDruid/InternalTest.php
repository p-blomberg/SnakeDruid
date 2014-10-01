<?php

class InternalTest extends DatabaseTestCase {
	public function testIdName() {
		$this->assertEquals('id', Model1::test_id_name());
	}

	public function testIdNameWithClass() {
		$this->assertEquals('id', Model1::test_id_name('Model1'));
	}

	public function testIdNameWithTable() {
		$this->assertEquals('id', Model1::test_id_name('model_1'));
	}

	public function testColumns() {
		$this->assertEquals(
			['id', 'int1', 'str1', 'model2_id'],
			Model2::test_columns('Model1')
		);
	}

	public function testColumnsWithTableName() {
		$this->assertEquals(
			['id', 'int1', 'str1', 'model2_id'],
			Model2::test_columns('model_1')
		);
	}

	public function testClassToTableWithClass() {
		$this->assertEquals('model_1', Model2::test_class_to_table('Model1'));
	}

	public function testClassToTableWithTable() {
		$this->assertEquals('model_1', Model2::test_class_to_table('model_1'));
	}

	public function testClassToTableWithoutParam() {
		$this->assertEquals('model_1', Model1::test_class_to_table());
	}

	public function testInTableSuccess() {
		$this->assertTrue(Model1::test_in_table('int1', 'Model1'));
	}

	public function testInTableSuccessWithTable() {
		$this->assertTrue(Model1::test_in_table('int1', 'model_1'));
	}

	public function testInTableFalse() {
		$this->assertFalse(Model1::test_in_table('not_a_column', 'Model1'));
	}

	public function testInTableFalseWithTable() {
		$this->assertFalse(Model1::test_in_table('not_a_column', 'model_1'));
	}
}
