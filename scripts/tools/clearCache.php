<?php

// Example: php scripts/tools/clearCache.php --env <env>
// docker exec -it -w /billrun/ billrun-app php scripts/tools/clearCache.php --env container

chdir(dirname(dirname(__DIR__)));

defined('APPLICATION_PATH') || define('APPLICATION_PATH', getcwd());
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);
$app->bootstrap();

if (!Billrun_Config::getInstance()->isConfigCacheEnabled()) {
	echo 'Config cache is disabled.' . PHP_EOL;
	exit(0);
}

$cache = Billrun_Factory::cache();
if (!$cache) {
	echo 'Cache is not configured.' . PHP_EOL;
	exit(1);
}

echo 'Clearing config cache...' . PHP_EOL;
$cache->remove('db_config', 'config');

if (function_exists('opcache_reset') && ini_get('opcache.enable')) {
	echo 'Resetting OPcache...' . PHP_EOL;
	opcache_reset();
}

exit(0);
