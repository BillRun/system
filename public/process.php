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
define('MONGODLOID_PATH', LIBS_PATH . DIRECTORY_SEPARATOR . 'Mongodloid'.  DIRECTORY_SEPARATOR);
require_once MONGODLOID_PATH . 'Connection.php';
require_once MONGODLOID_PATH . 'Exception.php';

// load mongodb instance
$conn = Mongodloid_Connection::getInstance();
$db = $conn->getDB('billing');

// retreive file
$file_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'workspace' . DIRECTORY_SEPARATOR . 'SXFN_FINTL_ID000006_201209201634.DAT';

$options = array(
	'type' => '018',
	'file_path' => $file_path,
	'parser' => parser::getInstance('fixed'),
	'db' => $db,
);

$processor = processor::getInstance($options);

$processor->process();

echo "<pre>"; print_R($processor->getData());
