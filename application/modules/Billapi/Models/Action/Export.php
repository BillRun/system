<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is accounts unique get
 *
 * @package  Billapi
 * @since    5.5
 */
class Models_Action_Export extends Models_Action {

	public function execute() {
		$output = [];
		$query = $this->getExportQuery();
		$entries = $this->getDataToExport($query);
		$mapper = $this->getCsvMapper($entries);
		// add headers
		if (Billrun_Util::getIn($this->options, 'headers', true)) {
			$output[] = $this->getCsvHeaders($mapper);
		}
		// add rows
		foreach ($entries as $entry) {
			$output[] = $this->getRow($entry, $mapper);
		}
		return $output;
	}
	
	protected function getCollection() {
		return $this->request['collection'];
	}

	protected function getFieldsConfig() {
		$collection = $this->getCollection();
		return Billrun_Factory::config()->getConfigValue("{$collection}.fields", []);
	}

	protected function getMapperConfig() {
		$collection = $this->getCollection();
		return Billrun_Factory::config()->getConfigValue("billapi.{$collection}.export.mapper", []);
	}

	protected function setCsvOrder($mapper) {
		//ksort($mapper); // sort field_name by alphabet and digits
		return $mapper;
	}

	protected function createRecursionMapperKey($config, $configs = []) {
		$max_array_count = 1; // TODO:: TEMP need to find a way how to calculate it from DB
		$path = $config['field_name'];
		$title = isset($config['title']) ? $config['title'] : false;
		$matches = [];
		preg_match('/{[0-9]+}/', $path, $matches);
		if (empty($matches)) {
			$configs[$path] = $config;
			return $configs;
		}
		for ($idx = 0; $idx < $max_array_count; $idx++) {
			$config['field_name'] = str_replace($matches[0], $idx, $path);
			if ($title !== false) {
				$config['title'] = str_replace($matches[0], $idx, $title);
			}
			$configs = $this->createRecursionMapperKey($config, $configs);
		}
		return $configs;
	}

	protected function getCsvMapper() {
		$fields = $this->getFieldsConfig();
		$config = $this->getMapperConfig();
		$exportable_fields = array_replace_recursive(
			array_column($fields, null, 'field_name'),
			array_column($config, null, 'field_name')
		);
		$exportable_fields = array_filter($exportable_fields, function($field) {
			return Billrun_Util::getIn($field, 'exportable', true);
		});
		$mapper = array_reduce($exportable_fields, function ($acc, $field) {
			$acc = array_merge($acc, $this->createRecursionMapperKey($field));
			return $acc;
		}, []);
		$mapper = $this->setCsvOrder($mapper);
		return $mapper;
	}

	protected function getRowValue($data, $path, $params) {
		$defaultValue = Billrun_Util::getIn($params, 'default_value', null);
		$value = Billrun_Util::getIn($data, explode('.', $path), $defaultValue);
		$type = Billrun_Util::getIn($params, 'type', 'string');
		if (empty($value) && $type !== 'boolean' && !in_array($value, [0, '0']) ) {
			return '';
		}
		switch ($type) {
			case 'json':
				return $this->formatJson($value);
			case 'date':
			case 'datetime':
				return $this->formatDate($value);
			case 'daterange':
				$value = $this->formatDate($value);
				return $this->formatRanges($value);
			case 'range':
				return $this->formatRanges($value);
			case 'percentage':
				return $this->formatPercentage($value);
			case 'boolean':
				return $this->formatBoolean($value);
			default:
				if ($value instanceof MongoDate) {
					return $this->formatDate($value);
				}
				if (is_array($value) /*&& Billrun_Util::getIn($params, 'multiple', false)*/) {
					return $this->formatArray($value);
				}
				return $value;
		}
	}

	protected function getRow($data, $mapper) {
		$line = [];
		foreach ($mapper as $path => $map) {
			$line[$path] = $this->getRowValue($data, $path, $map);
		}
		return $line;
	}

	protected function getCsvHeaders($mapper) {
		$headers = [];
		foreach ($mapper as $key => $map) {
			$headers[] = !empty($map['title']) ? $map['title'] : $key;
		}
		return $headers;
	}

	protected function getExportQuery() {
		$query = [];
		foreach ($this->query as $key => $value) {
			if ($key === 'from') {
				$query['to'] = ['$gte' => $value];
			} else {
				$query[$key] = $value;
			}
		}
		return $query;
	}

	protected function getDataToExport($query) {
		$records = [];
		$results = $this->collectionHandler->query($query)->cursor();
		foreach ($results as $result) {
			$records[] = $result->getRawData();
		}
		return $records;
	}

	protected function formatJson($data) {
		if (empty($data)) {
			return '';
		}
		foreach ($data as $index => $value) {
			$data[$index] = Billrun_Utils_Mongo::recursiveConvertRecordMongoDatetimeFields($value, ['value']);
		}
		return json_encode($data);
	}

	protected function formatRanges($ranges) {
		return $this->formatArray(array_map(function ($range) {
			return $this->formatRange($range);
		}, $ranges));
	}

	protected function formatRange($range) {
		return "{$range['from']} - {$range['to']}";
	}

	protected function formatArray($values) {
		return implode(", ", $values);
	}

	protected function formatDate($value) {
		return Billrun_Utils_Mongo::convertMongoDatesToReadable($value);
	}

	protected function formatBoolean($value) {
		if (empty($value)) {
			return 'no';
		}
		return in_array($value, ['false', 'FALSE', '0', 'null', 'NULL']) ? 'no' : 'yes';
	}

	function formatPercentage($value) {
		return ($value * 100) . '%';
	}

}
