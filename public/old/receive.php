<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
// initiate libs
require_once __DIR__ . "/../libs/autoloader.php";

// load mongodb instance
$conn = Mongodloid_Connection::getInstance();
$db = $conn->getDB('billing');


if (isset($argv[1])) {
	$path = $argv[1];
} else {
	$path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'workspace';
}

$options = array(
	'type' => 'files',
	'db' => $db,
	'workspace' => $path,
);

$receiver = receiver::getInstance($options);
if ($receiver) {
	$ret = $receiver->receive();
} else {
	echo "error with loading receiver" . PHP_EOL;
	exit();
}
