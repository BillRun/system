<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
// initiate libs
require_once __DIR__ . "/../libs/autoloader.php";

if (isset($argv[1])) {
	$path = $argv[1];
} else {
	$path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'workspace';
}

$options = array(
	'type' => 'files',
	'workspace' => $path,
);

$receiver = receiver::getInstance($options);
if ($receiver) {
	$ret = $receiver->receive();
} else {
	echo "error with loading receiver" . PHP_EOL;
	exit();
}
