<?php
/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

// initiate libs
require_once "./libs/autoloader.php";

// load mongodb instance
$conn = Mongodloid_Connection::getInstance();
$db = $conn->getDB('billing');


if (isset($argv[1]))
{
	$type = $argv[1];
}
else
{
	$type = 'ilds';
}

if (isset($argv[2]))
{
	$stamp = $argv[2];
}
else
{
	$stamp = '201212ilds2';
}

$options = array(
	'type' => $type,
	'db' => $db,
	'stamp' => $stamp,
);

echo "<pre>";

$generator = generator::getInstance($options);

$generator->load();

$generator->generate();
