<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) != 'cli') {
	die("This script is CLI only.");
}

$options = getopt('', array('file:','output:'));
$filePath = $options['file'];
if (empty($filePath)) {
	print('file path must be supplied (--file)' . PHP_EOL);
	return;
}

$mapping = array();
$applicationIds = array();
$baseTokenTypes = array(
	'OCTET STRING' => 'octetstring',
	'INTEGER' => 'integer',
	'VisibleString' => 'visible_string',
	'NumericString' => 'numeric_string',
	'SEQUENCE' => 'sequence',
	'CHOICE' => 'choice',
	'SEQUENCE OF' => 'sequence_of',
);
$output = convert($filePath);

foreach ($output as $outputLine) {
	print (print_R($outputLine, 1) . PHP_EOL);
}

function convert($filePath) {
	global $mapping, $applicationIds, $baseTokenTypes;
	$fp = fopen($filePath, 'r');
	if (!$fp) {
		print ('unable to open "' . $filePath . '"' . PHP_EOL);
		return;
	}
	
	$output = array();
	$linesToConvert = array();
	
	while ($line = fgets($fp)) {
		if (shouldIgnoreLine($line)) {
			continue;
		}
		$matches = array();
		preg_match('/\[APPLICATION (.*?)\]/', $line, $matches);
		$appId = isset($matches[1]) ? $matches[1] : '';
		parseLine($line, $appId);
		if (!isMappingLine($line, $appId)) {
			$linesToConvert[] = $line;
		}
	}
	fclose($fp);
	
	for ($i = 0; $i < count($linesToConvert); $i++) {
		$line = $linesToConvert[$i];
		$lineArr = explode(' ::= ', $line);
		if (count($lineArr) < 2) {
			print ("Cannot convert line '{$linesToConvert[$i]}'" . PHP_EOL);
			continue;
		}
		$varible = $lineArr[0];
		$type = strtolower($lineArr[1]);
		switch ($type) {
			case 'sequence':
			case 'choice':
					$c = 1000; // to avoid infinite loop
					while ($linesToConvert[$i] !== '{' && $c-- > 0) { // skip '{' line
						$i++;
					}
					$i++;
					$c = 1000; // to avoid infinite loop
					while ($linesToConvert[$i] !== '}' && $c-- > 0) {
						$sequenceLineArr = explode(' ', $linesToConvert[$i]);
						if (count($sequenceLineArr) < 2) {
							print ("Cannot convert line '{$linesToConvert[$i]}'" . PHP_EOL);
						} else {
							$sequenceVar = $sequenceLineArr[0];
							$sequenceType = $sequenceLineArr[1];
							if ($sequenceType) {
								$output[] = "{$varible}.{$type}.{$sequenceVar}={$sequenceType}";
								$mapping[$sequenceVar] = $sequenceType;
							} else {
								print ("Cannot find type for line '{$linesToConvert[$i]}'" . PHP_EOL);
							}
						}
						$i++;
					}
				break;
			case (preg_match('/sequence of*/', $type) ? true : false):
				$sequenceLineArr = explode(' ', $lineArr[1]);
				if (count($sequenceLineArr) > 2) {
					$sequenceType = $sequenceLineArr[2];
				} else { // type is in new line
					$sequenceType = $linesToConvert[++$i];
				}
				$output[] = "{$varible}.sequence_of={$sequenceType}";
				break;
			default:
				print ("Cannot convert line '{$line}'" . PHP_EOL);
		}
	}
	
	foreach ($mapping as $token => $type) {
		if (in_array($type, array('SEQUENCE OF', 'SEQUENCE', 'CHOICE'))) {
			continue;
		}
		$baseType = $type;
		$output[] = "{$token}={$baseType}";
	}
	
	foreach ($baseTokenTypes as $token => $type) {
		$output[] = "{$token}={$type}";
	}
	
	foreach ($applicationIds as $token => $appId) {
		$output[] = "application_id.{$token}={$appId}";
	}
	
	return $output;
}

function shouldIgnoreLine($line) {
	if (empty($line)) {
		return true;
	}
	$Ignoreregexes = array(
		'/^--/',
		'/^\.\.\./',
		'/^GSM Association Confidential/',
		'/^Official Document TD/',
		'/^V([\d.]*) Page (\d*) of (\d*)/',
		"/^" . PHP_EOL . "/",
		'/^BEGIN/',
		'/^END/',
		'/DEFINITIONS IMPLICIT TAGS/',
	);
	
	foreach ($Ignoreregexes as $Ignoreregex) {
		if (preg_match($Ignoreregex, $line)) {
			return true;
		}
	}
	return false;
}

function parseLine(&$line) {
	$searches = array(
		' OPTIONAL',
		',',
		'-- *m.m.',
		'  ',
		PHP_EOL,
	);
	$replaces = array(
		'',
		'',
		'',
		' ',
		'',
	);
	$line = preg_replace('/\[(\w|\s|\d)*\]/i','', $line); // removes "[APPLICATION XXX]" from line
	$line = preg_replace('/\s(-|–|)\(SIZE(\s|)\(\d*[.]*\d*\)\)/i','',$line); // removes "-(SIZE(X))" from line
	$line = str_replace($searches, $replaces, $line); // removes unused text from line
}

function isMappingLine($line, $appId) {
	global $mapping, $applicationIds;
	if (strpos($line, '::=')) {
		$isSequenceOf = strpos($line, 'SEQUENCE OF') !== false;
		$isSequence = !$isSequenceOf &&  strpos($line, 'SEQUENCE') !== false;
		$isChoice = strpos($line, 'CHOICE') !== false;
		if ($isSequenceOf || $isSequence || $isChoice) {
			$lineArr = explode(' ::= ', $line);
			$token = $lineArr[0];
			if ($isSequenceOf) {
				$type = 'SEQUENCE OF';
			} else if ($isSequence) {
				$type = 'SEQUENCE';
			} else if ($isChoice) {
				$type = 'CHOICE';
			} else {
				$type = 'UNKNOWN';
			}
			$mapping[$token] = $type;
			if (!empty($appId)) {
				$applicationIds[$token] = $appId;
			}
			return false;
		}
		$lineArr = explode(' ::= ', $line);
		$token = $lineArr[0];
		$type = $lineArr[1];
		$mapping[$token] = $type;
		if (!empty($appId)) {
			$applicationIds[$token] = $appId;
		}
		return true;
	}
	return false;
}
