<?php
ini_set('yaf.use_spl_autoload', 1); // to not interrupt Codeception
defined('APPLICATION_PATH') || define('APPLICATION_PATH', '.');
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);
$app->bootstrap();
Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
Yaf_Loader::getInstance()->import(APPLICATION_PATH . '/vendor/autoload.php');