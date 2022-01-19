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
	protected $report = null;
	protected $cacheFormatStyle = [];
	protected $cacheEntityFields = [];
	protected $currentTime = null;
	protected $aggregateOptions = [
		'allowDiskUse' => true,
	];
	
	/**
	 *  Array of entity join map keys
	 */
	protected $mapJoin = array(
		'usage' => array(
			'subscription' => array(
				'source_field' => 'sid',
				'target_field' => 'sid',
			),
			'customer' => array(
				'source_field' => 'aid',
				'target_field' => 'aid',
			),
		),
		'usage_archive' => array(
			'subscription' => array(
				'source_field' => 'sid',
				'target_field' => 'sid',
			),
			'customer' => array(
				'source_field' => 'aid',
				'target_field' => 'aid',
			),
		),
		'subscription' => array(
			'usage' => array(
				'source_field' => 'sid',
				'target_field' => 'sid',
			),
			'customer' => array(
				'source_field' => 'aid',
				'target_field' => 'aid',
			),
		)
	);
	
	/**
	 * Array of entities with revision
	 */
	protected $entityWithRevisions = array('subscription', 'customer');
	
	/**
	 * Fields that are complex object
	 */
	protected $pluckFields = array(
		array(
			'key' => 'name',
			'fields' => array('$subscription.services', 'services'),
		),
	);

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct($report = null) { 
		$this->config = Billrun_Factory::config()->getConfigValue('api.config.aggregate');
		$this->setReport($report);
	}
	
	public function setReport($report = null) { 
		$this->report = $report;
	}
	
	/**
	 * getReportByKey
	 * 
	 * @param type $key
	 * @return type report
	 */
	public static function getReportByKey($key) {
		return Billrun_Factory::db()->reportsCollection()->query(array('key' => $key))->cursor()->current()->getRawData();
	}
	
	/**
	 * applyFilter
	 * 
	 * @param type $query
	 * @param type $page
	 * @param type $size
	 * @return type
	 */
	public function applyFilter($page, $size) {
		$collection = Billrun_Factory::db()->{$this->getCollection() . "Collection"}();
		$report_entity = $this->getReportEntity();
		
		$aggregate = array();
		
		$addFields = $this->getAddFields($report_entity);
		if(!empty($addFields)) {
			$aggregate[] = array('$addFields' => $addFields);
		}
		
		$match = $this->getMatch($report_entity);
		if(!empty($match)) {
			$aggregate[] = array('$match' => $match);
		}
		
		$join_entities = $this->getReportJoinEntities();
		if(!empty($join_entities)) {
			if(!$this->isValidJoin($report_entity, $join_entities)) {
				$report_entities = implode(", ", $join_entities);
				throw new Exception("No support to join {$report_entity} with those entities: {$report_entities}");
			}
			foreach ($join_entities as $join_entity) {
				$lookup = $this->getLookup($join_entity);
				if(!empty($lookup)) {
					$aggregate[] = array('$lookup' => $lookup);
				}
				// filter by account type beacuse subscribers collection is mixed 
				if($join_entity === 'customer' ) {
					$filterByType = $this->getFilterByType($join_entity, 'type', 'account');
					if(!empty($filterByType)) {
						$aggregate[] = array('$addFields' => $filterByType);
					}
				}
				// filter by subscriber type beacuse subscribers collection is mixed 
				if($join_entity === 'subscription' ) {
					$filterByType = $this->getFilterByType($join_entity, 'type', 'subscriber');
					if(!empty($filterByType)) {
						$aggregate[] = array('$addFields' => $filterByType);
					}
				}
				if(in_array($join_entity, $this->entityWithRevisions)) {
					$filterByRevision = $this->getFilterByRevision($join_entity);
					if(!empty($filterByRevision)) {
						$aggregate[] = array('$addFields' => $filterByRevision);
					}
				}
				$unwind = $this->getUnwind($join_entity);
				if(!empty($unwind)) {
					$aggregate[] = array('$unwind' => $unwind);
				}

				$match = $this->getMatch($join_entity);
				if(!empty($match)) {
					$aggregate[] = array('$match' => $match);
				}
			}
		}

		$group = $this->getGroup();
		if(!empty($group)) {
			$aggregate[] = array('$group' => $group);
		}
		
		$project = $this->getProject();
		if(!empty($project)) {
			$aggregate[] = array('$project' => $project);
		}
		
		$sort = $this->getSort();
		if(!empty($sort)) {
			$aggregate[] = array('$sort' => $sort);
		}
		
		$skip = $this->getSkip($size, $page);
		if($skip !== -1) {
			$aggregate[] = array('$skip' => $skip);
		}
		
		$limit = $this->getLimit($size);
		if($limit !== -1) {
			$aggregate[] = array('$limit' => $limit);
		}
		
		$results = $collection->aggregateWithOptions($aggregate, $this->aggregateOptions);
		$rows = [];
		$formatters = $this->getFieldFormatters();
		foreach ($results as $result) {
			$row = $result->getRawData();
			$rows[] = $this->formatOutputRow($row, $formatters);
		}
		return $rows;
	}
	
	protected function isValidJoin($report_entity, $join_entities) {
		if (empty($this->mapJoin[$report_entity])) {
			return false;
		}
		$allowd_join_entities = array_keys($this->mapJoin[$report_entity]);
		return count(array_intersect($join_entities, $allowd_join_entities)) == count($join_entities);
	}
	
	protected function getRowsFormattersByKey($key, $formatters) {
		$formats = array();
		foreach ($formatters as $formatter) {
			if ($formatter['field'] === $key) {
				$formats[] = $formatter;
			}
		}
//		$field_names = array_column($this->report['columns'], 'field_name', 'key');
//		// if field is subscriber.play forse add default empty value formater to be default Play
//		if($field_names[$key] === 'subscriber.play') {
//			$defaultPlay = Billrun_Utils_Plays::getDefaultPlay();
//			if(!empty($defaultPlay['name'])) {
//				$defaultEmptyPlayformat = [
//					'field' => $key,
//					'op' => 'default_empty',
//					'value' => $defaultPlay['name'],
//				];
//				array_unshift($formats, $defaultEmptyPlayformat);
//			}
//		}
		return $formats;
	}
	
	protected function formatOutputRow($row, $formatters) {
		$output = array();
		foreach ($row as $key => $value) {
			$formats = $this->getRowsFormattersByKey($key, $formatters);
			if(is_array($value)) {
				// array result like addToSet
				if(count(array_filter(array_keys($value), 'is_string'))  === 0){
					$formatedValues = array();
					foreach ($value as $val) {
						if($val != ""){ // ignore empty values						
							$formatedValues[] = $this->formatOutputValue($val, $key, $formats);
						}
					}
					$output[$key] = implode(', ',$formatedValues);
				} else { // is associative array like _id or subfields
					foreach ($value as $value_key => $val) {
						$formatedKey = ($key == '_id') ? $value_key : $key . '.' . $value_key;
						$output[$formatedKey] = $this->formatOutputValue($val, $key, $formats);
					}
				}
			} else {
				$output[$key] = $this->formatOutputValue($value, $key, $formats);
			}
		}
		return $output;
	}
	
	protected function formatOutputValue($value, $key, $formats) {
		if(!is_scalar($value) && (is_array($value) || get_class($value) !== 'Mongodloid_Date')){
			// array result like addToSet
			if(count(array_filter(array_keys($value), 'is_string')) === 0){
				$values = array();
				foreach ($value as $val) {
					$values[] = $this->formatOutputValue($val, $key, $formats);
				}
				return implode(', ', $values);
			}
			$value = $this->pluckOutputValue($value, $key, $formats);
		}
		if(!empty($formats)) {
			foreach ($formats as $format) {
				$value = $this->applyValueformat($value, $format);
			}
		}
		if(gettype($value) == 'boolean') {
			return $value ? 'TRUE' : 'FALSE';
		}
		return $value;
	}
	
	protected function pluckOutputValue($value, $key, $formats) {
		$field_names = array_column($this->report['columns'], 'field_name', 'key');
		//If value is object where value is at key 'NAME' -> pop the value
		foreach ($this->pluckFields as $pluckField) {
			if(in_array($field_names[$key], $pluckField['fields'])){
				return $value[$pluckField['key']];
			}
		}
		
		$columns = array_column($this->report['columns'], null, 'key');
		$field = $columns[$key];
		$field_conf = $this->getEntityCustomFields($field['entity'], $field['field_name']);
		switch ($field_conf['type']) {
			case 'ranges':
				return "{$value['from']}-{$value['to']}";
			default:
				return $value;
		}
	}
	
	protected function applyValueformat($value, $format) {
		$cacheKey = (string)$value;
		if(!empty($this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey])) {
			return $this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey];
		}
		switch ($format['op']) {
			case 'date_override': {
				if (!empty($value->sec) || is_numeric($value)) {
					$styledValue = new Mongodloid_Date(strtotime("+{$format['value']}", $value->sec));
				} elseif (is_string($value) && $value !== ""){
					$styledValue = new Mongodloid_Date(strtotime("{$value} {$format['value']}" ));
				} else {
					$styledValue = $value;
				}
				$this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey] = $styledValue;
				return $styledValue;
			}
			case 'billing_cycle': {
				if (!Billrun_Util::isBillrunKey($value)) {
					$this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey] = $value;
					return $value;
				} else if ($format['value'] === 'start') {
					$styledValue = new Mongodloid_Date(Billrun_Billingcycle::getStartTime($value));
					$this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey] = $styledValue;
					return $styledValue;
				}
				$styledValue = new Mongodloid_Date(Billrun_Billingcycle::getEndTime($value));
				$this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey] = $styledValue;
				return $styledValue;
			}
			case 'time_format': 
			case 'datetime_format': 
			case 'date_format': {
				$time = (!empty($value->sec)) ? $value->sec :  strtotime($value);
				return $time !== false ? date($format['value'], $time) : $value;
			}
			case 'vat_format': {
				if (is_numeric($value) && $value != 0 ) {
					$taxCalc = Billrun_Calculator::getInstance(array('autoload' => false, 'type' => 'tax'));
					$styledValue = ($format['value'] === 'remove_tax') ? $taxCalc->removeTax($value) : $taxCalc->addTax($value);
					$this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey] = $styledValue;
					return $styledValue;
				}
				return $value;
			}
			case 'currency_format': {
				$currencySymbol = Billrun_Rates_Util::getCurrencySymbol(Billrun_Factory::config()->getConfigValue('pricing.currency','USD'));
				if ($format['value'] === 'prefix') {
					return $currencySymbol.$value;
				}
				return $value.$currencySymbol;
			}
			case 'multiplication':
				return (is_numeric($value) && is_numeric($format['value'])) ? $value * $format['value'] : $value;
			case 'default_empty': {
				if ($value !== "" && !is_null($value)){
					$styledValue = $value;
				} else {
					$condition = array();
					if (strpos($format['value'], 'start') != false) {
						$condition['start_end'] = 'start';
					} else if (strpos($format['value'], 'end') != false) {
						$condition['start_end'] = 'end';
					}
					switch($format['value']) {
						case 'current_time':
							$styledValue = $this->currentTime();
							break;
						case 'current_start':
						case 'current_end':
							$condition['value'] = 'current';
							break;
						case 'first_unconfirmed_start':
						case 'first_unconfirmed_end':
							$condition['value'] = 'first_unconfirmed';
							break;
						case 'last_confirmed_start':
						case 'last_confirmed_end':
							$condition['value'] = 'last_confirmed';
							break;
						default: $styledValue = $format['value'];
					}
					if (isset($condition['start_end'])) {
						if (!isset($this->cacheFormatStyle[$condition['value'] .'_' .$condition['start_end']])) {
							$styledValue = $condition['start_end'] == 'start' ? 
								Billrun_Billingcycle::getStartTime($this->formatInputMatchValue($condition, 'billrun', null)):
								Billrun_Billingcycle::getEndTime($this->formatInputMatchValue($condition, 'billrun', null));
							$this->cacheFormatStyle[$condition['value'] .$condition['start_end']] = date('m/d/Y H:i:s', $styledValue);
						}
						$styledValue = $this->cacheFormatStyle[$condition['value'] .$condition['start_end']];
					}
					$this->cacheFormatStyle[$format['op']][$format['value']][$cacheKey] = $styledValue;
					return $styledValue;
				}
			}
			default:
				return $value;
		}
	}
	
	protected function currentTime() {
		if(!$this->currentTime) {
			$this->currentTime = date('m/d/Y H:i:s');
		}
		return $this->currentTime;
	}

	protected function formatInputMatchOp($condition, $field) {
		$op = $condition['op'];
		$value = $condition['value'];
		// search by op
		switch ($op) {
			case 'last_hours':
			case 'last_days_include_today':
				return 'gte';
			case 'last_days':
				return 'between';
		}
		// search by field_name
		if($field === 'billrun') {
			switch ($value) {
				case 'confirmed':
					return 'in';
				default:
					return $op;
			}
		}
		// If subscriber.play doesn't exists in line we need to check for default play
		if($condition['entity'] === 'usage' && $field === 'subscriber.play') {
			$values = explode(',', $value);
			$defaultPlay = Billrun_Utils_Plays::getDefaultPlay();
			if ($op === 'nin' || ($op === 'in' && in_array($defaultPlay['name'], $values))) {
				return 'and';
			}
		}
		if($condition['field'] === 'logfile_status') {
			switch ($value) {
				case 'processed':
				case 'not_processed':
					return 'exists';
				case 'crashed':
				case 'processing':
					return 'and';
				default:
					return $op;
			}
		}
		return $op;
	}
	
	protected function formatInputMatchValue($condition, $field, $type) {
		$value = $condition['value'];
		$op = $condition['op'];
		if ($type === 'daterange') {
			return new Mongodloid_Date(strtotime($value));
		}
		// search by op
		switch ($op) {
			case 'last_hours':
				$hours = -1 * intval($value);
				return date("c", strtotime("{$hours} hours"));
			case 'last_days_include_today':
				$days = -1 * intval($value);
				return date("c", strtotime("{$days} day midnight"));
			case 'last_days':
				$days = -1 * (intval($value) + 1);
				return array(
					'from' => date("c", strtotime("{$days} day midnight")),
					'to' => date("c", strtotime("today") - 1)
				);
		}
		// If subscriber.play doesn't exists in line we need to check for default play
		if($condition['entity'] === 'usage' && $field === 'subscriber.play') {
			$values = explode(',', $value);
			$defaultPlay = Billrun_Utils_Plays::getDefaultPlay();
			$withDefault = in_array($defaultPlay['name'], $values);
			// IN + DEFAULT
			if ($op === 'in' && $withDefault) {
				return [
					['subscriber' => [
						'$exists' => true,
					]],
					['$or' => [
						['subscriber.play' => ['$exists' => false]],
						['subscriber.play' => ['$in' => $values]],
					]]
				];
			}
			// NIN + DEFAULT
			if ($op === 'nin' && $withDefault) {
				return [
					['subscriber' => [
						'$exists' => true,
					]],
					['$and' => [
						['subscriber.play' => ['$exists' => true]],
						['subscriber.play' => ['$nin' => $values]],
					]]
				];
			}
			// NIN + NO DEFAULT
			if ($op === 'nin' && !$withDefault) {
				return [
					['subscriber' => [
						'$exists' => true,
					]],
					['subscriber.play' => ['$nin' => $values]],
				];
			}
			// IN + NO DEFAULT
			// Nornal case return only [] value
		}
		// search by field_name
		if($field === 'billrun') {
			switch ($value) {
				case 'current':
					return Billrun_Billrun::getActiveBillrun();
				case 'first_unconfirmed':
					if (($last = Billrun_Billingcycle::getLastConfirmedBillingCycle()) != Billrun_Billingcycle::getFirstTheoreticalBillingCycle()) {
						return Billrun_Billingcycle::getFollowingBillrunKey($last);
					}
					if (is_null($lastStarted = Billrun_Billingcycle::getFirstStartedBillingCycle())) {
						return $last;
					}
					return $lastStarted;
				case 'last_confirmed':
					return Billrun_Billingcycle::getLastConfirmedBillingCycle();
				case 'confirmed':
					$confirmed = Billrun_Billingcycle::getConfirmedCycles();
					return implode(',', $confirmed);
				default:
					return $value;
			}
		}
		if($field === 'calc_name' && $value === 'false') {
			return false;
		}
		if($condition['field'] === 'logfile_status') {
			switch ($value) {
				case 'processed':
					return true;
				case 'not_processed':
					return false;
				case 'crashed':
					return array(
						array('start_process_time' =>array('$exists' => true)),
						array('start_process_time' => array('$lt' => new Mongodloid_Date(strtotime("-6 hours")))),
						array('process_time' => array('$exists' => false)),
					);
				case 'processing':
					return array(
						array('start_process_time' =>array('$exists' => true)),
						array('start_process_time' => array('$gt' => new Mongodloid_Date(strtotime("-6 hours")))),
						array('process_time' => array('$exists' => false)),
					);
				default:
					return $value;
			}
		}
		return $value;
	}
	
	protected function formatInputMatchField($condition, $entity) {
		$field = $condition['field'];
		if  ($this->isRatesTariffCategoryField($field)) {
			$parts = explode(".", $field);
			$tariff = $parts[1];
			$category_name = $parts[2];
			$field_name = array_slice($parts, 3);
			return implode(".", array(implode("_", array('rate', $tariff, $category_name)), implode(".", $field_name)));
		}
		switch ($field) {
			case 'logfile_status':
				switch ($condition['value']) {
					case 'crashed':
					case 'processing':
						return '';
					case 'processed':
					case 'not_processed':
						return 'process_time';
					default:
						return $field;
				}
			case 'billrun_status':
				return 'billrun';
			default:
				$needle = '$' . $entity;
				$length = strlen($needle);
				if (substr($field, 0, $length) === $needle){
					return substr_replace($field, $entity, 0, $length);
				}
				return $field;
		}
	}

	protected function getFilterByType($field, $by_field, $by_value){
		$path = '$$raw.' . $by_field;
		$filter[$field] = array(
			'$filter' => array(
				'input' => "\$$field",
				'as' =>  "raw",
				'cond' => array(
					'$eq' => array($path, $by_value)
				)
			)
		);
		return $filter;
	}
	
	protected function getFilterByRevision($field){
		$filter[$field] = array(
			'$filter' => array(
				'input' => "\$$field",
				'as' =>  "raw",
				'cond' => array(
					'$eq' => array('$$raw.to', array(
						'$max' => "\$$field".'.to'
					))
				)
			)
		);
		return $filter;
	}

	protected function getLookup($entity) {
		$report_entity = $this->getReportEntity();
		$join_entity = $this->entityMapper($entity);
		$lookup = array(
			'from' => $join_entity,
			'localField' => $this->mapJoin[$report_entity][$entity]['source_field'],
			'foreignField' => $this->mapJoin[$report_entity][$entity]['target_field'],
			'as' => $entity
		);
		return $lookup;
	}
	
	protected function getUnwind($entity) {
		return array(
			'path' => "\$$entity",
			'preserveNullAndEmptyArrays' => true
		);
	}
	
	/**
	 * get unique list of all join entityes expect report entity.
	 * @param type $report
	 * @return type
	 */
	protected function getReportJoinEntities() {
		$joinEntities = array();
		if(!empty($this->report['columns'])) {
			foreach ($this->report['columns'] as $column) {
				$joinEntities[] = $this->getFieldEntity($column);
			}
		}
		if(empty(!$this->report['conditions'])) {
			foreach ($this->report['conditions'] as $condition) {
				$joinEntities[] = $this->getFieldEntity($condition);
			}
		}
		return array_diff(array_unique($joinEntities), [$this->getReportEntity()]);
	}
	
	protected function getReportEntity() {
		return $this->report['entity'];
	}
	
	protected function getFieldFormatters() {
		return $this->report['formats'];
	}
	
	protected function getFieldEntity($field) {
		if(!empty($field['entity'])) {
			return $field['entity'];
		}
		return $this->getReportEntity();
	}
	
	protected function getDefaultEntityMatch() {
		$defaultEntityMatch = array();
		switch ($this->getReportEntity()) {
			case 'subscription':
				$defaultEntityMatch[]['type'] = "subscriber";
				$activeQuery = Billrun_Utils_Mongo::getDateBoundQuery();
				$defaultEntityMatch[]['to'] = $activeQuery['to'];
				$defaultEntityMatch[]['from'] = $activeQuery['from'];
				return $defaultEntityMatch;
			case 'customer':
				$defaultEntityMatch[]['type'] = "account";
				$activeQuery = Billrun_Utils_Mongo::getDateBoundQuery();
				$defaultEntityMatch[]['to'] = $activeQuery['to'];
				$defaultEntityMatch[]['from'] = $activeQuery['from'];
				return $defaultEntityMatch;
			case 'logFile':
				$defaultEntityMatch[]['file_name'] = [
					"\$exists" => true,
				];
				$defaultEntityMatch[]['type'] = [
					"\$ne" => 'custom_payment_gateway',
				];
				return $defaultEntityMatch;
			case 'paymentsTransactionsRequest':
				$defaultEntityMatch[]['type'] = 'custom_payment_gateway';
				$defaultEntityMatch[]['payments_file_type'] = 'transactions_request';
				return $defaultEntityMatch;
			case 'paymentsTransactionsResponse':
				$defaultEntityMatch[]['type'] = 'custom_payment_gateway';
				$defaultEntityMatch[]['payments_file_type'] = 'transactions_response';
				return $defaultEntityMatch;
			case 'paymentDenials':
				$defaultEntityMatch[]['type'] = 'custom_payment_gateway';
				$defaultEntityMatch[]['payments_file_type'] = 'denials';
				return $defaultEntityMatch;
			case 'paymentsFiles':
				$defaultEntityMatch[]['type'] = 'custom_payment_gateway';
				$defaultEntityMatch[]['payments_file_type'] = 'payments';
				return $defaultEntityMatch;
			default:
				return $defaultEntityMatch;
		}
	}

	protected function getCollection() {
		$entity = $this->getReportEntity();
		if(empty($entity)) {
			throw new Exception("Report entity is empty");
		}
		return $this->entityMapper($entity);
	}
	
	/**
	 * Map entity name to collection
	 * 
	 * @param type $entity name 
	 * @return string collection name
	 * @throws Exception validate for only allowd collections
	 */
	protected function entityMapper($entity) {
		switch ($entity) {
			case 'usage':
				return 'lines';
			case 'usage_archive':
				return 'archive';
			case 'subscription':
				return 'subscribers';
			case 'customer':
				return 'subscribers';
			case 'queue':
				return 'queue';
			case 'event':
				return 'events';
			case 'logFile':
			case 'paymentsTransactionsRequest':
			case 'paymentsTransactionsResponse':
			case 'paymentDenials':
			case 'paymentsFiles':
				return 'log';
			case 'bills':
				return 'bills';
			default:
				throw new Exception("Invalid entity type");
		}
	}

	/**
	 * Map entity custom fields
	 * 
	 * @param type $entity name 
	 * @return string path to custom fields
	 */
	protected function entityCustomFieldsMapper($entity) {
		switch ($entity) {
			case 'subscription':
				return 'subscribers.subscriber.fields';
			case 'customer':
				return 'subscribers.account.fields';
			default: {
				$collection = $this->entityMapper($entity);
				return "{$collection}.fields";
			}
		}
	}
	
	protected function getEntityCustomFields($entity, $fieldName) {		
		if(!empty($this->cacheEntityFields[$entity])) {
			return $this->cacheEntityFields[$entity][$fieldName];
		}
		$entityFieldConfig = array_column(Billrun_Factory::config()->getConfigValue($this->entityCustomFieldsMapper($entity), []), null, "field_name");
		$this->cacheEntityFields[$entity] = $entityFieldConfig;
		return $this->cacheEntityFields[$entity][$fieldName];
	}
	
	protected function getGroup() {
		$group = array();
		if ($this->isReportGrouped()) {
			foreach ($this->report['columns'] as $column) {
				if  (substr($column['field_name'], 0, strlen('rate_tariff_category_')) === 'rate_tariff_category_') {
					$column['field_name'] = implode(".", array($column['field_name'], implode(".", $column['field_key'])));
				}
				$op = $column['op'];
				$field = $column['field_name'];
				//remove JOIN entity name prefix
				$field = str_replace('$', '', $field);
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
	
	protected function getAddFields($entity) {
		$newFields = array();
		foreach ($this->report['columns'] as $key => $column) {
			if  ($this->isRatesTariffCategoryField($column['field_name'])) {
				$parts = explode(".", $column['field_name']);
				$rates = $parts[0]; // rates
				$tariff = $parts[1]; // tariff_category
				$category_name = $parts[2];
				$field_name = array_slice($parts, 3);
			
				$new_field_name = "rate_tariff_category_" . $category_name;
				$filter = array(
						'$filter' => array(
						"input" => '$rates', 
						"as" => "rate", 
						"cond" => array( '$eq'=> array( '$$rate.tariff_category', $category_name ) )
					)
				);
				$newFields[$new_field_name] = array(
					'$arrayElemAt' => array($filter, 0)
				);
				// Change the field name of this filed in report settings for other operations
				$this->report['columns'][$key]['field_name'] = $new_field_name;
				$this->report['columns'][$key]['field_key'] = $field_name;
			}
		}
		return $newFields;
	}
	
	protected function getMatch($entity) {
		$matchs = $this->getDefaultEntityMatch();
		foreach ($this->report['conditions'] as $condition) {
			$condition_entity = $this->getFieldEntity($condition);
			if($condition_entity !== $entity) {
				return array();
			}
			$parsedCondition = $this->parseMatchCondition($condition);
			$matchs[][$parsedCondition['field']] = $parsedCondition['query'];
		}
		return !empty($matchs) ? array('$and' => $matchs) : array();
	}
	
	protected function parseMatchCondition($condition) {
		$condition_entity = $this->getFieldEntity($condition);
		$type = $condition['type'];
		$field = $this->formatInputMatchField($condition, $condition_entity);
		$op = $this->formatInputMatchOp($condition, $field);
		$value = $this->formatInputMatchValue($condition, $field, $type);
		switch ($op) {
			case 'in_range':
				$formatedExpression = [
					'$elemMatch' => [
						'from' => ['$lte' => $value],
						'to' => ['$gte' => $value],
					],
				];
				break;
			case 'nin_range':
				$formatedExpression = [
					'$not' => [
						'$elemMatch' => [
							'from' => ['$lte' => $value],
							'to' => ['$gte' => $value],
						]
					]
				];
				break;
			case 'like':
				$formatedExpression = array(
					'$regex' => "{$value}",
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
					$values = explode(',', $value);
				}
				if ($field == 'paid' && in_array('0', $values)) {
					$values[] = false;
				}
				$formatedExpression = array(
					"\${$op}" => $values
				);
				break;
			case 'ne':
			case 'eq':
				if ($type === 'date') {
					$date = strtotime($value);
					$beginOfDay = strtotime("midnight", $date);
					$endOfDay = strtotime("tomorrow", $date) - 1;
					$gteDate = ($op === 'eq') ? $beginOfDay : $endOfDay;
					$ltDate = ($op === 'eq') ? $endOfDay : $beginOfDay;
					$formatedExpression = array(
						'$gte' => new Mongodloid_Date($gteDate),
						'$lt' => new Mongodloid_Date($ltDate),
					);
				} elseif ($type === 'datetime') {
					$date = strtotime($value);
					$gteDate = ($op === 'eq') ? $date : $date + 59;
					$ltDate = ($op === 'eq') ? $date + 59 : $date;
					$formatedExpression = array(
						'$gte' => new Mongodloid_Date($gteDate),
						'$lt' => new Mongodloid_Date($ltDate),
					);
				} elseif ($type === 'number') {
					$formatedExpression = array(
						"\${$op}" => floatval($value)
					);
				} elseif ($type === 'boolean') {
					$formatedExpression = array(
						"\${$op}" => (bool) $value
					);
				} else {
					$formatedExpression = array(
						"\${$op}" => $value
					);
				}
				break;
			case 'between':
				if (in_array($type, ['date', 'datetime'])) {
					$formatedExpression = array(
						'$gte' => new Mongodloid_Date(strtotime($value['from'])),
						'$lt' => new Mongodloid_Date(strtotime($value['to'] + 60)), // to last minute second
					);
				} elseif ($type === 'number') {
					$formatedExpression = array(
						'$gte' => floatval($value['from']),
						'$lt' => floatval($value['to']),
					);
				} else {
					$formatedExpression = array(
						'$gte' => $value['from'],
						'$lte' => $value['to'],
					);
				}
				break;
			case 'lt':
			case 'lte':
			case 'gt':
			case 'gte':
				if ($type === 'date') {
					$date = strtotime($value);
					$queryDate = ($op === 'gt' || $op === 'lte') ? strtotime("tomorrow", $date) - 1 : strtotime("midnight", $date);
					$formatedExpression = array(
						"\${$op}" => new Mongodloid_Date($queryDate),
					);
				} elseif ($type === 'datetime') {
					$date = strtotime($value);
					$queryDate = ($op === 'gt' || $op === 'lte') ? $date + 59 : $date;
					$formatedExpression = array(
						"\${$op}" => new Mongodloid_Date($queryDate),
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
					"\${$op}" => (bool) $value
				);
				break;
			case 'and':
			case 'or':
				// for complex queries
				$field = "\${$op}";
				$formatedExpression = $value;
				break;
			default:
				throw new Exception("Invalid filter operator $op");
				break;
		}
		return array(
			'field' => $field,
			'query' => $formatedExpression
		);
	}

	protected function getSkip($size = -1, $page = -1) {
		if ($size === -1 && $page === -1) {
			return 0;
		}
		// Size has addition 1 item to check if next page exists 
		return intval($page) * intval($size - 1);
	}
	
	protected function getLimit($size = -1) {
		return intval($size);
	}

	protected function getProject() {
		$project = array('_id' => 0);
		$isReportGrouped = $this->isReportGrouped();
		if(empty($this->report['columns'])) {
			throw new Exception("Columns list is empty, nothing to display");
		}
		foreach ($this->report['columns'] as $column) {
			// special care for rates.tariff.category
			if (substr($column['field_name'], 0, strlen('rate_tariff_category_')) === 'rate_tariff_category_') {
				$column['field_name'] = implode(".", array($column['field_name'], implode(".", $column['field_key'])));
			}
			$field_name = str_replace('$', '', $column['field_name']);
			if ($isReportGrouped) {
				// (FIX for Error: the group aggregate field name 'xx.yy' cannot be used because $group's field names cannot contain '.')
				$field_name = str_replace('.', '__', $field_name);
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
	
	protected function getSort() {
		$sorts = array();
		if(!empty($this->report['sorts'])) {
			foreach ($this->report['sorts'] as $sort) {
				$sorts[$sort['field']] = $sort['op'] > 0 ? 1 : -1 ;
			}
		}
		return $sorts;
	}
	
	protected function isRatesTariffCategoryField($field) {
		return (substr($field, 0, strlen('rates.tariff_category.')) === 'rates.tariff_category.');
	}
	
	protected function isReportGrouped() {
		return $this->report['type'] == 1;
	}
}
