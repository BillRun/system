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
			$aggregate[] = array('$match' => array('$and' => $match));
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
		

//		error_log(__FILE__ . '(' . __FUNCTION__ . ":" . __LINE__ . ") " . "\n" . "aggregate" . " :\n" . print_r($aggregate, 1) . "\n");
		
		$results = $collection->aggregate($aggregate);
		$rows = [];
		foreach ($results as $result) {
			$row = $result->getRawData();
			$rows[] = $this->formatRow($row);
		
		}
//		error_log(__FILE__ . '(' . __FUNCTION__ . ":" . __LINE__ . ") " . "\n" . "row" . " :\n" . print_r($rows, 1) . "\n");
		return $rows;
	}
	
	protected function formatRow($row) {
		$output = array();
		foreach ($row as $key => $value) {
			if(is_array($value)) {
				// array result like addToSet
				if(count(array_filter(array_keys($value), 'is_string'))  === 0){
					$formatedValues = array();
					foreach ($value as $val) {
						$formatedValues[] = $this->formatValue($val);
					}
					$output[$key] = implode(',',$formatedValues);
				} else { // is associative array like _id or subfields
					foreach ($value as $value_key => $val) {
						$formatedKey = ($key == '_id') ? $value_key : $key . '.' . $value_key;
						$output[$formatedKey] = $this->formatValue($val);
					}
				}
			} else {
				$output[$key] = $value;
			}
		}
		return $output;
	}
	
	protected function formatValue($value) {
		if(isset($value->sec)){
			return Billrun_Utils_Mongo::convertMongoDatesToReadable($value);
		}
		return $value;
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

					default:
						$formatedExpression = array("\${$op}" => "\$$value");
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
				foreach ($expression as $op => $value) {
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

						default:
							$formatedExpression = array(
								"\${$op}" => $value
							);
							break;
					}
					$matchs[][$field] = $formatedExpression;
				}
			}
		}
		return $matchs;
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
		if (!empty($project) && !isset($project['_id'])) {
//				$project['_id'] = 0;
		}
		return $project;
	}
}
