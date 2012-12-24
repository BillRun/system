<?php
/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

// initiate libs
require_once __DIR__ . "/../libs/autoloader.php";

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
	'stamp' => $stamp,
);

echo "<pre>";

$generator = generator::getInstance($options);

$generator->load();

$generator->generate();
