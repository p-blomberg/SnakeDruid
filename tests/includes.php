<?php

error_reporting(E_STRICT|E_ALL);

require dirname(__DIR__)."/vendor/autoload.php";

include realpath(dirname(__FILE__)) . "/database.php";
include realpath(dirname(__FILE__)) . "/helpers.php";

include realpath(dirname(__FILE__)) . "/SnakeDruidTest.php";
include realpath(dirname(__FILE__)) . "/Blueprint.php";
include realpath(dirname(__FILE__)) . "/DatabaseTestCase.php";

foreach(glob(realpath(dirname(__FILE__)) . "/models/*.php") as $filename) {
	require_once $filename;
}
