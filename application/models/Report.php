<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class is to hold the logic for the Cards module.
 *
 * @package  Models
 * @subpackage Table
 * @since    4.0
 */
class ReportModel {
	
	protected $config = null;

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) { 
		$this->config = Billrun_Factory::config()->getConfigValue('api.config.aggregate');
	}
	
	
	/**
	 * applyFilter
	 * 
	 * @param type $query
	 * @param type $page
	 * @param type $size
	 * @return type
	 */
	public function applyFilter($report, $page, $size) {
		$collection = Billrun_Factory::db()->{$this->getCollection($report) . "Collection"}();
		
		$aggregate = array();
		
		$match = $this->getMatch($report);
		if(!empty($match)) {
			$aggregate[] = array('$match' => $match);
		}

		$group = $this->getGroup($report);
		if(!empty($group)) {
			$aggregate[] = array('$group' => $group);
		}
		
		$skip = $this->getSkip($size, $page);
		if($skip !== -1) {
			$aggregate[] = array('$skip' => $skip);
		}
		
		$limit = $this->getLimit($size);
		if($limit !== -1) {
			$aggregate[] = array('$limit' => $limit);
		}
		
		$project = $this->getProject($report);
		if(!empty($project)) {
			$aggregate[] = array('$project' => $project);
		}
		
		$sort = $this->getSort($report);
		if(!empty($sort)) {
			$aggregate[] = array('$sort' => $sort);
		}
		
		$results = $collection->aggregate($aggregate);	
		$rows = [];
		foreach ($results as $result) {
			$row = $result->getRawData();
			$rows[] = $this->formatOutputRow($row);
		}
		return $rows;
	}
	
	protected function formatOutputRow($row) {
		$output = array();
		foreach ($row as $key => $value) {
			if(is_array($value)) {
				// array result like addToSet
				if(count(array_filter(array_keys($value), 'is_string'))  === 0){
					$formatedValues = array();
					foreach ($value as $val) {
						if($val != ""){ // ignore empty values						
							$formatedValues[] = $this->formatOutputValue($val, $key);
						}
					}
					$output[$key] = implode(', ',$formatedValues);
				} else { // is associative array like _id or subfields
					foreach ($value as $value_key => $val) {
						$formatedKey = ($key == '_id') ? $value_key : $key . '.' . $value_key;
						$output[$formatedKey] = $this->formatOutputValue($val, $key);
					}
				}
			} else {
				$output[$key] = $this->formatOutputValue($value, $key);
			}
		}
		return $output;
	}
	
	protected function formatOutputValue($value, $key) {
		return $value;
	}
	
	protected function formatInputMatchOp($op, $field, $value) {
		if($field === 'billrun') {
			switch ($value) {
				case 'confirmed':
					return 'lte';
				default:
					return $op;
			}
		}
		return $op;
	}
	
	protected function formatInputMatchValue($value, $field, $type) {	
		if($field === 'billrun') {
			switch ($value) {
				case 'current':
					return Billrun_Billrun::getActiveBillrun();
				case 'first_unconfirmed':
					$last = Billrun_Billingcycle::getLastConfirmedBillingCycle();
					return Billrun_Billingcycle::getFollowingBillrunKey($last);
				case 'last_confirmed':
					return Billrun_Billingcycle::getLastConfirmedBillingCycle();
				case 'confirmed':
					return Billrun_Billingcycle::getLastConfirmedBillingCycle();
				default:
					return $value;
			}
		}
		return $value;
	}
	
	protected function formatInputMatchField($field) {				
		switch ($field) {
			case 'billrun_status':
				return 'billrun';
			default:
				return $field;
		}
	}
	
	protected function getDefaultEntityMatch($entity) {
		$defaultEntityMatch = array();
		switch ($entity) {
			case 'subscription':
				$defaultEntityMatch[] = array("type" => "subscriber");
				return $defaultEntityMatch;
			case 'customer':
				$defaultEntityMatch[] = array("type" => "account");
				return $defaultEntityMatch;
			default:
				return $defaultEntityMatch;
		}
	}
	
	protected function getCollection($report) {
		if(empty($report['entity'])) {
			throw new Exception("Report entity is empty");
		}
		switch ($report['entity']) {
			case 'usage':
				return 'lines';
			case 'subscription':
				return 'subscribers';
			case 'customer':
				return 'subscribers';
			default:
				throw new Exception("Invalid entity type");
		}
	}
	
	protected function getGroup($report) {
		$group = array();
		if ($report['type'] === 1) {
			foreach ($report['columns'] as $column) {
				$op = $column['op'];
				$field = $column['field_name'];
				// (FIX for Error: the group aggregate field name 'xx.yy' cannot be used because $group's field names cannot contain '.')
				$field_key = str_replace(".", "__", $field);
				switch ($op) {
					case 'count':
						$group[$field_key] = array('$sum' => 1);
						break;
					case 'sum':
					case 'avg':
					case 'first':
					case 'last':
					case 'max':
					case 'min':
					case 'push':
					case 'addToSet':
						$group[$field_key] = array("\${$op}" => "\$$field");
						break;
					case 'group':
						$group['_id'][$field_key] = "\$$field";
						break;
					default:
						throw new Exception("Invalid group by operator $op");
						break;
				}
			}
			if (empty($group['_id'])) {
				$group['_id'] = null;
			}
		}
		return $group;
	}
	
	protected function getMatch($report) {
		$matchs = $this->getDefaultEntityMatch($report['entity']);
		foreach ($report['conditions'] as $condition) {
			$type = $condition['type'];
			$field = $this->formatInputMatchField($condition['field']);
			$op = $this->formatInputMatchOp($condition['op'], $field, $condition['value']);
			$value = $this->formatInputMatchValue($condition['value'], $field, $type);		
			switch ($op) {
				case 'like':
					$formatedExpression = array(
						'$regex' => "^{$value}$",
						'$options' => 'i'
					);
					break;
				case 'starts_with':
					$formatedExpression = array(
						'$regex' => "^{$value}",
						'$options' => 'i'
					);
					break;
				case 'ends_with':
					$formatedExpression = array(
						'$regex' => "{$value}$",
						'$options' => 'i'
					);
					break;
				case 'in':
				case 'nin':
					//TODO: add support for dates
					if ($type === 'number') {
						$values = array_map('floatval', explode(',', $value));
					} else {
						$values = explode(',',$value);
					}
					$formatedExpression = array(
						"\${$op}" => $values
					);
					break;
				case 'ne':
				case 'eq':
					if ($type === 'date') {
						$date = strtotime(substr($value, 0, 10));
						$beginOfDay = strtotime("midnight", $date);
						$endOfDay = strtotime("tomorrow", $date) - 1;
						$gteDate = ($op === 'eq') ? $beginOfDay : $endOfDay;
						$ltDate = ($op === 'eq') ? $endOfDay : $beginOfDay;
						$formatedExpression = array(
							'$gte' => new MongoDate($gteDate),
							'$lt' => new MongoDate(),
						);
					} elseif ($type === 'number') {
						$formatedExpression = array(
							'$eq' => floatval($value)
						);
					} elseif ($type === 'boolean') {
						$formatedExpression = array(
							'$ne' => (bool)$value
						);
					} else {
						$formatedExpression = array(
							'$eq' => $value
						);
					}
					break;
				case 'lt':
				case 'lte':
				case 'gt':
				case 'gte':
					if (get_class($value) === 'MongoDate') {
						$date = strtotime(substr($value, 0, 10));
						$queryDate = ($op === 'lt' || $op === 'lte')
							? strtotime("tomorrow", $date) - 1
							: strtotime("midnight", $date);
						$formatedExpression = array(
							"\${$op}" => new MongoDate($queryDate),
						);
					} elseif ($type === 'number') {
						$formatedExpression = array(
							"\${$op}" => floatval($value)
						);
					} else {
						$formatedExpression = array(
							"\${$op}" => $value
						);
					}
				break;	
				case 'exists':
					$formatedExpression = array(
						"\${$op}" => (bool)$value
					);
					break;
				default:
					throw new Exception("Invalid filter operator $op");
					break;
			}
			$matchs[][$field] = $formatedExpression;
		}
		return !empty($matchs) ? array('$and' => $matchs) : array();
	}
	
	protected function getSkip($size = -1, $page = -1) {
		if ($size === -1 && $page === -1) {
			return 0;
		}
		return intval($page) * intval($size);
	}
	
	protected function getLimit($size = -1) {
		return intval($size);
	}
	
	protected function getProject($report) {
		$project = array('_id' => 0);
		$isReportGrouped = $report['type'] === 1;
		if(empty($report['columns'])) {
			throw new Exception("Columns list is empty, nothing to display");
		}
		foreach ($report['columns'] as $column) {
			$field_name = $column['field_name'];
			if ($isReportGrouped) {
				// (FIX for Error: the group aggregate field name 'xx.yy' cannot be used because $group's field names cannot contain '.')
				$field_name = str_replace('.', '__', $column['field_name']);
				if($column['op'] === 'group') {
					// fix mongoDB group by _id if exist
					$field_name = '_id.' . $field_name;
				}
			}
			$project[$column['key']] = array(
				'$ifNull' => array("\${$field_name}", '')
			);

		}
		return $project;
	}
	
	protected function getSort($report) {
		$sorts = array();
		if(!empty($report['sorts'])) {
			foreach ($report['sorts'] as $sort) {
				$sorts[$sort['field']] = $sort['op'] > 0 ? 1 : -1 ;
			}
		}
		return $sorts;
	}
}
