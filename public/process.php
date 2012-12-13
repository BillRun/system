<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
// initiate libs
// @todo make auto load
define('LIBS_PATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR);
require_once LIBS_PATH . 'parser.php';
require_once LIBS_PATH . 'processor.php';
define('MONGODLOID_PATH', LIBS_PATH . DIRECTORY_SEPARATOR . 'Mongodloid' . DIRECTORY_SEPARATOR);
require_once MONGODLOID_PATH . 'Connection.php';
require_once MONGODLOID_PATH . 'Exception.php';

// load mongodb instance
$conn = Mongodloid_Connection::getInstance();
$db = $conn->getDB('billing');

if (isset($argv[1]))
{
	$ilds_type = $argv[1];
}
else
{
	$ilds_type = '012';
}


if (isset($argv[2]))
{
	$file_path = $argv[2];
}
else
{
	$file_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'workspace' . DIRECTORY_SEPARATOR . 'INT_KVZ_GLN_MABAL_000001_201207311333.DAT';
	//$file_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'workspace' . DIRECTORY_SEPARATOR . 'SXFN_FINTL_ID000006_201209201634.DAT';
}

$options = array(
	'type' => $ilds_type,
	'file_path' => $file_path,
	'parser' => parser::getInstance('fixed'),
	'db' => $db,
);

$processor = processor::getInstance($options);
if ($processor)
{
	$ret = $processor->process();
}
else
{
	echo "error with loading processor" . PHP_EOL;
	exit();
}

echo "<pre>";
var_dump($ret);
print_R($processor->getData());
