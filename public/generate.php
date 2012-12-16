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

$options = array(
	'type' => 'ilds',
	'db' => $db,
	'stamp' => '201212ilds1',
);

echo "<pre>";

$generator = generator::getInstance($options);

$generator->load();

$generator->generate();
