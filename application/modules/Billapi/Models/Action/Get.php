<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi get operation
 * Retrieve list of entities
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Get extends Models_Action {

	/**
	 * the size of page
	 * @var int
	 */
	protected $size = 0;

	/**
	 * the page index
	 * @var int
	 */
	protected $page = 0;

	/**
	 * the query
	 * @var array
	 */
	protected $query = array();

	/**
	 * the sort
	 * @var array
	 */
	protected $sort = array();
	
	protected function __construct(array $params = array()) {
		if (isset($params['request'])) {
			$this->request = $params['request'];
		}
		
		if (isset($params['settings']) && isset($params['settings']['query_parameters'])) {
			$params['settings']['query_parameters'] = array_merge($params['settings']['query_parameters'], $this->getQueryCustomFields());
		}
		return parent::__construct($params);
	}

	public function execute() {
//		if (!empty($this->request['query'])) {
//			$this->query = (array) json_decode($this->request['query'], true);
//		}

		if (isset($this->request['page']) && is_numeric($this->request['page'])) {
			$this->page = (int) $this->request['page'];
		}

		if (isset($this->request['size']) && is_numeric($this->request['size'])) {
			$this->size = (int) $this->request['size'];
		}

		if (isset($this->request['sort'])) {
			$this->sort = (array) json_decode($this->request['sort'], true);
		}

		return $this->runQuery();
	}

	/**
	 * Run a DB query against the current collection
	 * @return array the result set
	 */
	protected function runQuery() {

		if (isset($this->request['project'])) {
			$project = (array) json_decode($this->request['project'], true);
			$revision_info = !empty($project['revision_info']);
			unset($project['revision_info']);
			// if revision_info requested, all entity unique fields are required for query
			if($revision_info){
				$uniqueFields = Billrun_Factory::config()->getConfigValue("billapi.{$this->request['collection']}.duplicate_check", array());
				foreach ($uniqueFields as $fieldName) {
					$project[$fieldName] = 1;
				}
			}
		} else {
			$revision_info = true;
			$project = array();
		}

		$ret = $this->collectionHandler->find($this->query, $project);

		if ($this->size != 0) {
			$ret->limit($this->size + 1); // the +1 is to indicate if there is next page
		}

		if ($this->page != 0) {
			$ret->skip($this->page * $this->size);
		}

		if (!empty($this->sort)) {
			$ret->sort((array) $this->sort);
		}

		$records = array_values(iterator_to_array($ret));
		foreach($records as  &$record) {
			if (isset($record['invoice_id'])) {
				$record['invoice_id'] = (int)$record['invoice_id'];
			}
			if ($revision_info && isset($record['from'], $record['to'])) {
				$record = Models_Entity::setRevisionInfo($record, $this->getCollectionName(), $this->request['collection']);
			}
			$record = Billrun_Utils_Mongo::recursiveConvertRecordMongoDatetimeFields($record, $this->getDateFields());
		}
		return $records;
	}
	
	/**
	 * gets the date fields names to be converted before retured
	 * 
	 * @return array
	 */
	protected function getDateFields() {
		return array('from', 'to', 'creation_time');
	}
	
	/**
	 * add option to query also by custom fields
	 */
	protected function getQueryCustomFields() {
		$ret = array();
		$customFieldsKey = $this->getCustomFieldsKey();
		$customFields = Billrun_Factory::config()->getConfigValue("$customFieldsKey.fields", array());
		foreach ($customFields as $field) {
			if (Billrun_Util::getFieldVal($field['searchable'], false)) {
				$ret [] = array(
					'name' => $field['field_name'],
					'type' => $this->getCustomFieldType($field),
				);
			}
		}
		
		return $ret;
	}
	
	protected function getCustomFieldType($field) {
		$type = Billrun_Util::getIn($field, 'type', 'string');
		switch ($type) {
			case 'ranges':
			case 'boolean':
				return $type; 
			default:
				return 'string';
		}
	}
	
	protected function getCustomFieldsKey() {
		return $this->getCollectionName();
	}

	/**
	 * method to get page size
	 * @return type
	 */
	public function getSize() {
		return $this->size;
	}

}
