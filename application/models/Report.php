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
		$config = Billrun_Factory::config()->getConfigValue('api.config.aggregate');
	}
	
	
	/**
	 * applyFilter
	 * 
	 * @param type $query
	 * @param type $page
	 * @param type $size
	 * @return type
	 */
	public function applyFilter($query, $page, $size) {
		$collection = Billrun_Factory::db()->{$this->getCollection($query) . "Collection"}();
		
		$aggregate = array();
		
		$match = $this->getMatch($query);
		if(!empty($match)) {
			$aggregate[] = array('$match' => $match);
		}

		$group = $this->getGroup($query);
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
		
		$project = $this->getProject($query);
		if(!empty($project)) {
			$aggregate[] = array('$project' => $project);
		}
		
		$sort = $this->getSort($query);
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
						$formatedValues[] = $this->formatOutputValue($val, $key);
					}
					$output[$key] = implode(',',$formatedValues);
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
	
	protected function formatInputValue($value, $key) {				
		$arrayToConvert = array($value);
		Billrun_Utils_Mongo::convertQueryMongoDates($arrayToConvert);
		return $arrayToConvert[0];
	}
	
	protected function getCollection($query) {
		return $query['collection'];
	}
	
	protected function getGroup($query) {
		$group = array();
		foreach ($query['groupByFields'] as $idFiled) {
			$group['_id'][$idFiled] = "\$$idFiled";
		}
		  	  
		foreach ($query['groupBy'] as $fieldLabel => $expression) {
			foreach ($expression as $op => $value) {
				switch ($op) {
					case 'count':
						$formatedExpression = array('$sum' => 1);
						break;
					case 'sum':
					case 'avg':
					case 'first':
					case 'last':
					case 'max':
					case 'min':
					case 'push':
					case 'addToSet':
						$formatedExpression = array("\${$op}" => "\$$value");
						break;
					default:
						throw new Exception("Invalid group by operator $op");
						break;
				}
				$group[$fieldLabel] = $formatedExpression;
			}
		}
		return $group;
	}
	
	protected function getMatch($query) {
		$matchs = array();
		foreach ($query['query'] as $match) {
			foreach ( $match as $field => $expression) {
				foreach ($expression as $op => $inputValue) {
					$value = $this->formatInputValue($inputValue, $field);		
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
							$formatedExpression = array(
								'$in' => explode(',',$value)
							);
							break;
						case 'eq':
							if (get_class($value) === 'MongoDate') {
								$date = strtotime(substr($inputValue, 0, 10));
								$beginOfDay = strtotime("midnight", $date);
								$endOfDay   = strtotime("tomorrow", $date) - 1;
								$formatedExpression = array(
									'$gte' => new MongoDate($beginOfDay),
									'$lt' => new MongoDate($endOfDay),
								);
							} else {
								$formatedExpression = array(
									'$eq' => $value
								);
							}
							break;
						case 'lt':
						case 'lte':
							if (get_class($value) === 'MongoDate') {
								$date = strtotime(substr($inputValue, 0, 10));
								$endOfDay   = strtotime("tomorrow", $date) - 1;
								$formatedExpression = array(
									"\${$op}" => new MongoDate($endOfDay),
								);
							} else {
								$formatedExpression = array(
									"\${$op}" => $value
								);
							}
							break;
						case 'gt':
						case 'gte':
							if (get_class($value) === 'MongoDate') {
								$date = strtotime(substr($inputValue, 0, 10));
								$beginOfDay = strtotime("midnight", $date);
								$formatedExpression = array(
									"\${$op}" => new MongoDate($beginOfDay),
								);
							} else {
								$formatedExpression = array(
									"\${$op}" => $value
								);
							}
						break;	
						case 'ne':
						case 'exists':
							$formatedExpression = array(
								"\${$op}" => $value
							);
							break;
						default:
							throw new Exception("Invalid filter operator $op");
							break;
					}
					$matchs[][$field] = $formatedExpression;
				}
			}
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
	
	protected function getProject($query) {
		$project = $query['project'];
		// required project value to be set or the table will be empty.
		if(empty($project)) {
			throw new Exception("Empty display fields list");
		}
		// fix mongoDB group by _id if exist
		foreach ($project as $fieldName => $show) {
			if(in_array($fieldName, $query['groupByFields'])) {
				$project[$fieldName] = '$_id.' . $fieldName;
			}
		}
			
		if (!isset($project['_id'])) {
				$project['_id'] = 0;
		}
		return $project;
	}
	
	protected function getSort($query) {
		$sort = $query['sort'];
		if(empty($sort)) {
			return array();
		}
		
		$output = array();
		foreach ($sort as $fieldName => $sortType) {
			$output[$fieldName] = $sortType > 0 ? 1 : -1 ;
		}
		return $output;
	}
}
