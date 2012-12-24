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


$options = array(
	'type' => $type,
);

$calculator = calculator::getInstance($options);

$calculator->load();

$calculator->calc();

$calculator->write();
