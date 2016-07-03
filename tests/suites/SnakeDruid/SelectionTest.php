<?php

class SelectionTest extends DatabaseTestCase {
	public function testAutomaticJoin() {
		$m1 = Blueprint::make('Model1', "with_model2");

		$m1_ref = Model1::selection(['model2.int1' => $m1->Model2()->int1]);
		$this->assertCount(1, $m1_ref);
		$this->assertEquals($m1, $m1_ref[0]);
	}

	public function testManualJoin() {
		$m1 = Blueprint::make('Model1', ['int1' => 5000]);
		$m2 = Blueprint::make('Model2', ['int1' => 5000, 'str1' => 'derp']);

		$m1_ref = Model1::selection([ '@join' => [ 'model2:using' => 'int1']]	);
		$this->assertCount(1, $m1_ref);
		$this->assertEquals($m1, $m1_ref[0]);
	}

	public function testOrder() {
		$key = "ordertest";
		$m1 = Blueprint::make('Model1', ['int1' => 1, 'str1' => $key]);
		$m2 = Blueprint::make('Model1', ['int1' => 2, 'str1' => $key]);

		$selection = Model1::selection(['str1' => $key, '@order' => 'int1']);
		$this->assertEquals($m1->id, $selection[0]->id);
		$this->assertEquals($m2->id, $selection[1]->id);

		$selection = Model1::selection(['str1' => $key, '@order' => 'int1:desc']);
		$this->assertEquals($m2->id, $selection[0]->id);
		$this->assertEquals($m1->id, $selection[1]->id);
	}

	public function testIn() {
		$key = "testin";
		$m1 = Blueprint::make('Model1', ['int1' => 1, 'str1' => $key]);
		$m2 = Blueprint::make('Model1', ['int1' => 2, 'str1' => $key]);
		$m3 = Blueprint::make('Model1', ['int1' => 3, 'str1' => $key]);
		$m4 = Blueprint::make('Model1', ['int1' => 4, 'str1' => $key]);
		$m5 = Blueprint::make('Model1', ['int1' => 5, 'str1' => $key]);

		$match_array = [1, 2, 3];

		$selection = Model1::selection(['str1' => $key, 'int1:in' => [1, 2, 3]]);
		$sorted_result = array_map(function($v) {
			return $v->int1;
		}, $selection);
		sort($sorted_result);

		$this->assertEquals($match_array, $sorted_result);
	}

	public function testNotIn() {
		$key = "testnotin";
		$m1 = Blueprint::make('Model1', ['int1' => 1, 'str1' => $key]);
		$m2 = Blueprint::make('Model1', ['int1' => 2, 'str1' => $key]);
		$m3 = Blueprint::make('Model1', ['int1' => 3, 'str1' => $key]);
		$m4 = Blueprint::make('Model1', ['int1' => 4, 'str1' => $key]);
		$m5 = Blueprint::make('Model1', ['int1' => 5, 'str1' => $key]);

		$match_array = [1, 2, 3];

		$selection = Model1::selection(['str1' => $key, 'int1:not_in' => [4, 5]]);
		$sorted_result = array_map(function($v) {
			return $v->int1;
		}, $selection);
		sort($sorted_result);

		$this->assertEquals($match_array, $sorted_result);
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage No such column 'foobar' in table 'model_1'
	 */
	public function testUnknowColumn() {
		Model1::selection(['foobar' => 'bar']);
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage No connection from 'model_1' to 'foobar'
	 */
	public function testInvalidJoin() {
		Model1::selection(['foobar.foo' => 'a']);
	}

	/**
	 * @expectedException Exception
	 * @expectedExceptionMessage Invalid operator: '!'
	 */
	public function testInvalidOperatorForSum() {
		Model1::sum(['id', '!', 'int1']);
	}

	/**
	 */
	public function testSum() {
		Blueprint::make('Model1', ['int1' => 100, 'str1' => 'testSum']);
		Blueprint::make('Model1', ['int1' => 10,  'str1' => 'testSum']);
		$this->assertEquals(220, Model1::sum(['int1', '+', 'int1'], ['str1' => 'testSum']));
	}

	/* TODO: Add much more tests */
}
