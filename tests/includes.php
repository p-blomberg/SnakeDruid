<?php

error_reporting(E_STRICT|E_ALL);

include realpath(dirname(__FILE__)) . "/database.php";
include realpath(dirname(__FILE__)) . "/helpers.php";

include realpath(dirname(__FILE__)) . "/../SnakeDruid.php";
include realpath(dirname(__FILE__)) . "/SnakeDruidTest.php";
include realpath(dirname(__FILE__)) . "/Blueprint.php";
include realpath(dirname(__FILE__)) . "/DatabaseTestCase.php";

foreach(glob(realpath(dirname(__FILE__)) . "/models/*.php") as $filename) {
	require_once $filename;
}
