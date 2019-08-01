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
			$acc[$field['field_name']] = $field;
			return $acc;
		}, []);
		return $mapper;
	}

	protected function getRowValue($data, $path, $params) {
		$defaultValue = Billrun_Util::getIn($params, 'default_value', null);
		$value = Billrun_Util::getIn($data, explode('.', $path), $defaultValue);
		$type = Billrun_Util::getIn($params, 'type', 'string');
		if (empty($value) && $type !== 'boolean' && ![0, '0'].includes($value) ) {
			return '';
		}
		switch ($type) {
			case 'date':
			case 'datetime':
				return $this->formatDate($value);
			case 'daterange':
				$value = $this->formatDate($value);
				return $this->formatRanges($value);
			case 'range':
				return $this->formatRanges($value);
			case 'percentage':
				return ($value * 100) . '%';
			case 'boolean':
				return $this->formatBoolean($value);
			default:
				if (is_array($value) /*&& Billrun_Util::getIn($params, 'multiple', false)*/) {
					return $this->formatArray($value);
				}
				if ($value instanceof MongoDate) {
					return $this->formatDate($value);
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
				$query[$key] = ['$gte' => $value];
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
		return ['false', 'FALSE', '0', 'null', 'NULL'].includes($value) ? 'no' : 'yes';
	}

}
