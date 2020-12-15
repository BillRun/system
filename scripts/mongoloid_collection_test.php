<?php

//php mongoloid_collection_test.php --env dev appDir='/home/user1/projects/billrun'
parse_str(implode('&', array_slice($argv, 1)), $_GET);
$appDir = $_GET['appDir'];

// Code to let script work with Billrun project
defined('APPLICATION_PATH') || define('APPLICATION_PATH', $appDir);
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);
$app->bootstrap();
Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");


try{
	///monogloid update
	$res = Billrun_Factory::db()->linesCollection()->update(["stamp"  => "5ee82af1d26c4004eb179b0679f0cd1f"],['type' => 'blala']);

	print_r($res);
}catch(\PhpOffice\PhpSpreadsheet\Exception $ex){
	echo $ex->getMessage();
}