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
		$mapper = $this->getCsvMapper();
		if (Billrun_Util::getIn($this->options, 'headers', true)) {
			$output[] = $this->getCsvHeaders($mapper);
		}
		$entries = $this->getDataToExport($this->getExportQuery());
		foreach ($entries as $entry) {
			$output[] = $this->getRow($entry, $mapper);
		}
		return $output;
	}
	
	protected function getCollection() {
		return $this->$this->request['collection'];
	}

	protected function getFieldsConfig() {
		$collection = $this->getCollection();
		return Billrun_Factory::config()->getConfigValue("{$collection}.fields", []);
	}

	protected function getCsvMapper() {
		$fields = $this->getFieldsConfig();
		$exportable_fields = array_filter($fields, function($field) {
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
		switch ($type) {
			case 'date':
			case 'datetime':
				return Billrun_Utils_Mongo::convertMongoDatesToReadable($value);
			case 'daterange':
				$values = [];
				if (empty($value)) {
					return '';
				}
				foreach ($value as $range) {
					$from = Billrun_Utils_Mongo::convertMongoDatesToReadable($range['from']);
					$to = Billrun_Utils_Mongo::convertMongoDatesToReadable($range['to']);
					$values[] = "{$from} - $to";
				}
				return implode(" ,", $values);
			case 'range':
				$values = [];
				if (!empty($value)) {
					return '';
				}
				foreach ($value as $range) {
					$values[] = "{$range['from']} - {$range['to']}";
				}
				return implode(" ,", $values);
			case 'percentage':
				return ($value * 100) . '%';
			case 'boolean':
				return $value ? 'yes' : 'no';
			default:
				if (!empty($value) && Billrun_Util::getIn($params, 'multiple', false)) {
					return implode(" ,", $value);
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

}
