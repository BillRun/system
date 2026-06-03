<?php
ini_set('yaf.use_spl_autoload', 1); // to not interrupt Codeception
defined('APPLICATION_PATH') || define('APPLICATION_PATH', '.');
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
define('MOCKUP_URL', 'http://mockup:8081');
define('BILLRUN_URL', 'http://web');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);
$app->bootstrap();
// === Prevent running tests on production ===
if (\Billrun_Factory::config()->isProd()) {
    Billrun_Factory::log('ERROR: Running Codeception tests on production is forbidden!', Zend_Log::ERR);
    exit(1);
}
Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
Yaf_Loader::getInstance()->import(APPLICATION_PATH . '/vendor/autoload.php');