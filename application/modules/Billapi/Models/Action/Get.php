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

	/**
	 * Flag to indicate if revision info should be processed.
	 * Set by prepareProjection() and used by processResults().
	 * @var bool
	 */
	protected $revisionInfoRequested = false;
	
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

		$project = $this->prepareProjection();

		Billrun_Factory::log("Billapi get runs query: " . json_encode($this->query), Zend_Log::DEBUG);
		$cursor = $this->collectionHandler->find($this->query, $project);

		if ($this->size != 0) {
			$cursor->limit($this->size + 1);
		}
		if ($this->page != 0) {
			$cursor->skip($this->page * $this->size);
		}
		if (!empty($this->sort)) {
			$cursor->sort((array) $this->sort);
		}

		return $this->processResults($cursor);
	}
	
	/**
	 * gets the date fields names to be converted before retured
	 * 
	 * @return array
	 */
	protected function getDateFields() {
		$default = ['from', 'to', 'creation_time'];
		$config_date_fields = [];
		if (!empty($this->request['collection'])){
			$data['collection'] = $this->request['collection'];
			$data['no_init'] = true;
			$entityModel = Models_Entity::getInstance($data);
			$config = Billrun_Factory::config()->getConfigValue("billapi.{$this->request['collection']}", array());
			$config_fields = array_merge(array('fields' => Billrun_Factory::config()->getConfigValue($entityModel->getCustomFieldsPath(), [])), $config[$this->request['action']]);
			$config_date_fields = array_column(array_filter($config_fields['fields'], function($field) {
				return in_array(@$field['type'], ['date', 'daterange']);
			}), 'field_name');
		}
		$date_fields_names = array_unique(array_merge($default, $config_date_fields));
		return $date_fields_names;
	}

	/**
	 * gets the names of the fields that are stored encrypted and need to be
	 * decrypted before being returned. Mirrors getDateFields(): collects from
	 * both the custom fields config (keyed by 'field_name') and the action
	 * update_parameters config (keyed by 'name').
	 *
	 * @return array
	 */
	protected function getEncryptedFields() {
		$encrypted_fields = array();
		if (!empty($this->request['collection'])) {
			$data['collection'] = $this->request['collection'];
			$data['no_init'] = true;
			$entityModel = Models_Entity::getInstance($data);
			$config = Billrun_Factory::config()->getConfigValue("billapi.{$this->request['collection']}", array());
			$custom_fields = Billrun_Factory::config()->getConfigValue($entityModel->getCustomFieldsPath(), []);
			$encrypted_custom = array_column(array_filter($custom_fields, function($field) {
				return Billrun_Util::getFieldVal($field['type'], 'string') === 'encrypted';
			}), 'field_name');
			$update_parameters = Billrun_Util::getIn($config, array($this->request['action'], 'update_parameters'), array());
			$encrypted_params = array_column(array_filter($update_parameters, function($field) {
				return Billrun_Util::getFieldVal($field['type'], 'string') === 'encrypted';
			}), 'name');
			$encrypted_fields = array_values(array_unique(array_merge($encrypted_custom, $encrypted_params)));
		}
		return $encrypted_fields;
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
				$isEncrypted = (Billrun_Util::getFieldVal($field['type'], 'string') === 'encrypted');
				$ret [] = array(
					'name' => $field['field_name'],
					// encrypted fields are matched by blind index (exact match);
					// others use array to allow search from UI by regex
					'type' => $isEncrypted ? 'encrypted' : 'array', //$this->getCustomFieldType($field),
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

	protected function prepareProjection() {
		if (isset($this->request['project'])) {
			$project = (array) json_decode($this->request['project'], true);
			$this->revisionInfoRequested = !empty($project['revision_info']);
			unset($project['revision_info']);
			if ($this->revisionInfoRequested) {
				$uniqueFields = Billrun_Factory::config()->getConfigValue("billapi.{$this->request['collection']}.duplicate_check", array());
				foreach ($uniqueFields as $fieldName) {
					$project[$fieldName] = 1;
				}
			}
		} else {
			$this->revisionInfoRequested = true;
			$project = array();
		}
		return $project;
	}

	protected function processResults(Mongodloid_Cursor $cursor) {
		$records = array_values(iterator_to_array($cursor));
		Billrun_Factory::log('Billapi get received ' . count($records) . " results", Zend_Log::DEBUG);
		$encryptedFields = $this->getEncryptedFields();
		foreach($records as  &$record) {
			if (isset($record['invoice_id'])) {
				$record['invoice_id'] = (int)$record['invoice_id'];
			}
			if ($this->revisionInfoRequested && isset($record['from'], $record['to'])) {
				$record = Models_Entity::setRevisionInfo($record, $this->getCollectionName(), $this->request['collection']);
			}
			$record = Billrun_Utils_Mongo::recursiveConvertRecordMongodloidDatetimeFields($record, $this->getDateFields());
			if (!empty($encryptedFields)) {
				$record = Billrun_Utils_Mongo::recursiveDecryptRecordFields($record, $encryptedFields);
			}
		}
		return $records;
	}

}
