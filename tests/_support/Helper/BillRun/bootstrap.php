<?php
ini_set('yaf.use_spl_autoload', 1); // to not interrupt Codeception
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(__DIR__ . '/../../../..'));
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
// Register Models_* classes from the Billapi module without clobbering Yaf's main library path.
spl_autoload_register(function ($class) {
    if (strpos($class, 'Models_') === 0) {
        $file = APPLICATION_PATH . '/application/modules/Billapi/Models/' . substr($class, 7) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});
Yaf_Loader::getInstance()->import(APPLICATION_PATH . '/vendor/autoload.php');