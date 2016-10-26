<?php

$CONFIG_FILE = '/home/tomfeigin/projects/billrun_tests/CustomerAggregator.ini';
$CONFIGURATION = loadConfigurations();

// connect to mongodb
$m = new MongoClient();
$subColl = getSubscriberCollection($CONFIGURATION, $m);
$planColl = getPlansCollection($CONFIGURATION, $m);
$invoiceColl = getInvoiceCollection($CONFIGURATION, $m);
$cycleColl = getCycleCollection($CONFIGURATION, $m);

// Get the subscribers before the test.
$plansBefore = getBefore($planColl);
$subsBefore = getBefore($subColl);
$invoiceBefore = getBefore($invoiceColl);
$cycleBefore = getBefore($cycleColl);

// Erase the subscribers and plans collections.
$planColl->remove(array());
$subColl->remove(array());
$invoiceColl->remove(array());
$cycleColl->remove(array());

handlePlans($planColl, $CONFIGURATION);
handleSubscribers($subColl, $invoiceColl, $cycleColl, $CONFIGURATION);

// Erase the subscribers and plans collections.
$planColl->remove(array());
$subColl->remove(array());
$invoiceColl->remove(array());
$cycleColl->remove(array());

// Put them all back
if(!empty($subsBefore)) {
	$subColl->batchInsert($subsBefore);
}

if(!empty($plansBefore)) {
	$planColl->batchInsert($plansBefore);
}


if(!empty($invoiceBefore)) {
	$invoiceColl->batchInsert($invoiceBefore);
}

if(!empty($cycleBefore)) {
	$cycleColl->batchInsert($cycleBefore);
}

function handleSubscribers(MongoCollection $subColl, MongoCollection $invoiceColl,MongoCollection $cycleColl, $config) {
	// Get the subscriber data files
	$subData = $config['subscribers']['data'];
	foreach ($subData as $dataFile) {
		$currData = file_get_contents($dataFile);
		$jsonData = json_decode($currData, true);
		if($jsonData === null) {
			echo("Skipping " . $dataFile . ". Cannot decode json.\n");
			continue;
		}
		
		if(!isset($jsonData['test'])) {
			echo("Skipping " . $dataFile . ". No test name provided.\n");
			continue;
		}
		
		$dates = $jsonData['dates'];
		$testName = $jsonData['test'];
		echo("Running test: " . $testName);
		$testRawData = $jsonData['data'];
		$testData = translateDates($testRawData, $dates);
		
		// If it is empty
		if(empty($testData)) {
			echo("Skipping " . $dataFile . ". No data.\n");
			continue;
		}
		
		// Erase the collection.
		$subColl->remove();
		$invoiceColl->remove(array());
		$cycleColl->remove(array());
		$subColl->batchInsert($testData);
		
		aggregate($config);
		
		generate($config, $testName);

		// Get the invoice data
		$invoice = $invoiceColl->find()->getNext();
		if(!$invoice) {
			echo("INVOICE WAS NOT CREATED!\n");
			continue;
		}
		
		$subs = isset($invoice['subs']) ? (count($invoice['subs'])) : 0;
		if($subs != $jsonData['subs']) {
			echo($subs . "SUBSCRIBERS LISTED ISNTEAD OF " . $jsonData['subs'] . "!\n");
			continue;
		}
		
		echo "TEST SUCCESSFUL!!!!\n";
	}
}

function aggregate($config) {
	$command = buildAggregateCommand($config);
	$output = array();
	exec($command, $output);
	print_r($output);
}

function generate($config, $testName) {
	$commnad = buildWkpdfCommand($config, $testName);
	$output = array();
	exec($commnad, $output);
	print_r($output);
}

function buildAggregateCommand($config) {
	$cycle = $config['cycle'];
	return buildCommand($cycle, 'aggregate', $cycle['aggregate_type']);
}

function buildWkpdfCommand($config, $testName) {
	$cycle = $config['cycle'];
	$command = buildCommand($cycle, 'generate', $cycle['generate_type']);
	return $command;
}

function buildCommand($cycleData, $action, $type) {
	$command = "php ";
	$command .= $cycleData['index'] . ' ';
	$command .= '--env ' . $cycleData['env'] . ' ';
	$command .= '--' . $action . ' --type ' . $type . ' ';
	$command .= '--stamp ' . $cycleData['stamp'] . ' ';
	$command .= '--page ' . $cycleData['page'] . ' ';
	$command .= '--size ' . $cycleData['size'] . ' ';
	return $command;
}

function handlePlans(MongoCollection $planColl, $config) {
	// Get the plan data files
	$planData = $config['plans']['data'];
	
	foreach ($planData as $dataFile) {
		$currData = file_get_contents($dataFile);
		$jsonData = json_decode($currData, true);
		if($jsonData === null) {
			echo("Skipping " . $dataFile . ". Cannot decode json.\n");
			continue;
		}
		
		// Get date fields
		$dates = $jsonData['dates'];
		$rawData = $jsonData['data'];
		$translated = translateDates($rawData, $dates);
		
		// If it is empty
		if(empty($translated)) {
			echo("Skipping " . $dataFile . ". No data.\n");
			continue;
		}
		
		// Insert the data.
		$planColl->batchInsert($translated);
	}
}

function translateDates($data, $dates) {
	foreach ($data as &$record) {
		$record = translateRecordDates($record, $dates);
	}
	return $data;
}

function translateRecordDates($record, $dates) {
	foreach ($record as $key => &$value) {
		if(in_array($key, $dates)) {
			$value = new MongoDate($value);
		}
	}
	return $record;
}

function getCollection($configuration, MongoClient $m, $collName) {
	// The database name.
	$dbName = $configuration['db']['name'];
	
	// The collection name
	$dbCollName = $configuration['db'][$collName];
	
	// select a database
	$db = $m->$dbName;

	// Get the subscribers collection.
	return $db->$dbCollName;
}

function getInvoiceCollection($configuration, MongoClient $m) {
	return getCollection($configuration, $m, 'invoice');
}

function getPlansCollection($configuration, MongoClient $m) {
	return getCollection($configuration, $m, 'plans');
}

function getSubscriberCollection($configuration, MongoClient $m) {
	return getCollection($configuration, $m, 'subscribers');
}

function getCycleCollection($configuration, MongoClient $m) {
	return getCollection($configuration, $m, 'cycle');
}

function getBefore(MongoCollection $coll) {
	// Get the records before the test.
	$cursor = $coll->find();
	$recordsBefore = iterator_to_array($cursor);
	return $recordsBefore;
}

function loadConfigurations() {
	// Declare globals
	global $CONFIG_FILE;
	
	// Check that the file exists.
	if(!file_exists($CONFIG_FILE)) {
		throw new Exception("No configuration file found!");
	}
	
	// Process ini file.
	$config = parse_ini_file($CONFIG_FILE, true);
	validateConfigurations($config);
	return $config;
}

function validateConfigurations($config) {
	if(!isset($config['plans'], $config['subscribers'], $config['db'], $config['cycle'])) {
		throw new Exception("Config file missing sections.");
	}
	
	$plansData = $config['plans']['data'];
	$plansError = validateFiles($plansData);
	$subsData = $config['subscribers']['data'];
	$subsError = validateFiles($subsData);
	$errors = $plansError + $subsError;
	if(!empty($errors)) {
		$errorData = print_r(implode(';', $errors),1);
		throw new Exception("Invalid data files: " . $errorData);
	}
}

function validateFiles($files) {
	$invalidFiles = array();
	foreach ($files as $currentFile) {
		if(!file_exists($currentFile)) {
			$invalidFiles[] = $currentFile;
		}
	}
	return $invalidFiles;
}

?>
