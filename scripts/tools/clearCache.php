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

$cache->remove('db_config', 'config');
echo 'Config cache cleared.' . PHP_EOL;

if (function_exists('opcache_reset') && ini_get('opcache.enable')) {
	opcache_reset();
	echo 'OPcache reset.' . PHP_EOL;
}

exit(0);
