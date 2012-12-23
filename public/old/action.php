<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
// initiate auto loader
require_once __DIR__ . "/../libs/autoloader.php";

try {
	$opts = new Zend_Console_Getopt(
			array(
				'p|P|process' => 'Process files into database',
				'c|C|calc|calculate' => 'Calculate lines in database',
				'a|A|aggregate' => 'Aggregate lines for billrun',
				'g|G|generate' => 'Generate xml and csv files of specific billrun',
				'g|G|generate' => 'Generate xml and csv files of specific billrun',
				'h|H|help' => 'Displays usage information.',
				'ild-s' => 'Process: Ild to use',
				'path-s' => 'Process: Path of the process file',
				'parser-s' => 'Process: Parser type (default fixed)',
			)
	);

	$opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
	exit($e->getMessage() . "\n\n" . $e->getUsageMessage());
}

/**
 * Action : process
 */
if (isset($opts->process)) {
	$ild = $opts->getOption('ild');
	$path = $opts->getOption('path');
	$parserType = $opts->getOption('parser');
	if (empty($parserType)) {
		$parserType = 'fixed';
	}

	$options = array(
		'type' => $ild,
		'file_path' => $path,
		'parser' => parser::getInstance(array('type' => $parserType)),
	);

	$processor = processor::getInstance($options);

	if ($processor) {
		$processor->process();
	} else {
		echo "Processor not found" . PHP_EOL;
	}

	exit();
}

/**
 * Action : calculate
 */
if (isset($opts->calculate)) {
	// do something
	echo "calculate" . PHP_EOL;
	exit();
}

/**
 * Action : aggregate
 */
if (isset($opts->aggregate)) {
	// do something
	echo "aggregate" . PHP_EOL;
	exit();
}

/**
 * Action : generate
 */
if (isset($opts->generate)) {
	// do something
	echo "generate" . PHP_EOL;
	exit();
}



if (isset($opts->help)) {
	echo $opts->getUsageMessage();
	exit;
}
