<?php
class FetcherTest extends DatabaseTestCase {

	public function testFetchFromId() {
		global $db;
		$res = $db->query("
			INSERT INTO model_1 (int1, str1)
			VALUES ($1, $2)
			RETURNING id",
			[5, 'foobar']
		);
		if(!$res) {
			throw new Exception("Failed to insert model by manual query: ".$db->error);
		}
		$id = $res[0]['id'];

		$fetcher = new SnakeFetcher();
		$obj = $fetcher->from_id(Model1::class, $id);
		$this->assertNotNull($obj);
		$this->assertEquals(5, $obj->int1);
		$this->assertEquals("foobar", $obj->str1);
	}

	public function testChainedFetchFromId() {
		$m1 = Blueprint::make('Model1', "with_model2");

		$fetcher = new SnakeFetcher();
		$m1_ref = $fetcher->selection('Model1', ['model_2.int1' => $m1->Model2()->int1]);
		$this->assertCount(1, $m1_ref);
		$this->assertEquals($m1, $m1_ref[0]);
	}
}
