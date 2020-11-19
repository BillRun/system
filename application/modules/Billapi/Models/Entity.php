<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for operations on BillRun entities
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Entity {

	use Models_Verification;

	/**
	 * Entirty available statuses values
	 */
	const FUTURE = 'future';
	const EXPIRED = 'expired';
	const ACTIVE = 'active';
	const UNLIMITED_DATE = '+149 years';

	/**
	 * The entity name
	 * @var string
	 */
	protected $entityName;
	/**
	 * The DB collection name
	 * @var string
	 */
	protected $collectionName;

	/**
	 * The DB collection
	 * @var Mongodloid_Collection
	 */
	protected $collection;

	/**
	 * The entity billapi configuration
	 * @var array
	 */
	protected $config;

	/**
	 * The wanted query
	 * @var array
	 */
	protected $query = array();

	/**
	 * The new data
	 * @var array
	 */
	protected $update = array();
	
	/**
	 * The update data from request without changes
	 * @var array
	 */
	protected $originalUpdate = array();
	
	/**
	 * Additional data to save
	 * @var array
	 */
	protected $additional = array();

	/**
	 * The update options
	 * @var array
	 */
	protected $queryOptions = array();

	/**
	 * The wanted sort (for get operations)
	 * @var array
	 */
	protected $sort = array();

	/**
	 * Page number for get operations
	 * @var int
	 */
	protected $page = 0;

	/**
	 * Page size for get operations
	 * @var int
	 */
	protected $size = 10;

	/**
	 * the entity before the change
	 * 
	 * @var array
	 */
	protected $before = null;

	/**
	 * the entity after the change
	 * 
	 * @var array
	 */
	protected $after = null;

	/**
	 * the previous revision of the entity
	 * 
	 * @var array
	 */
	protected $previousEntry = null;

	/**
	 * the line that was added as part of the action
	 * 
	 * @var array
	 */
	protected $line = null;

	/**
	 * the change action applied on the entity
	 * 
	 * @var string
	 */
	protected $action = 'change';

	/**
	 * Gets the minimum date for moving entities in time (unix timestamp)
	 * 
	 * @var int
	 */
	protected static $minUpdateDatetime = null;

	/**
	 * the change action applied on the entity
	 * 
	 * @var string
	 */
	protected $availableOperations = array('query', 'update', 'sort');

	public static function getInstance($params) {
		$modelPrefix = 'Models_';
		$className = $modelPrefix . ucfirst($params['collection']);
		if (!@class_exists($className)) {
			$className = $modelPrefix . 'Entity';
		}
		return new $className($params);
	}

	public function __construct($params) {
		$this->entityName = $params['collection'];
		if ($params['collection'] == 'accounts') { //TODO: remove coupling on this condition
			$this->collectionName = 'subscribers';
		} else {
			$this->collectionName = $params['collection'];
		}
		$this->collection = Billrun_Factory::db()->{$this->collectionName . 'Collection'}();
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/modules/billapi/' . $params['collection'] . '.ini');
		$this->config = Billrun_Factory::config()->getConfigValue('billapi.' . $params['collection'], array());
		if (isset($params['no_init']) && $params['no_init']) {
			return;
		}
		if (isset($params['request']['action'])) {
			$this->action = $params['request']['action'];
		}
		$this->init($params);
	}

	protected function init($params) {
		$query = isset($params['request']['query']) ? @json_decode($params['request']['query'], TRUE) : array();
		$update = isset($params['request']['update']) ? @json_decode($params['request']['update'], TRUE) : array();
		$this->originalUpdate = $update;
		$options = isset($params['request']['options']) ? @json_decode($params['request']['options'], TRUE) : array();
		if (json_last_error() != JSON_ERROR_NONE) {
			throw new Billrun_Exceptions_Api(0, array(), 'Input parsing error');
		}

		$customFields = $this->getCustomFields($update);
		$duplicateCheck = isset($this->config['duplicate_check']) ? $this->config['duplicate_check'] : array();
		$config = array_merge(array('fields' => Billrun_Factory::config()->getConfigValue($this->getCustomFieldsPath(), [])), $this->config[$this->action]);
		list($translatedQuery, $translatedUpdate, $translatedQueryOptions) = $this->validateRequest($query, $update, $this->action, $config, 999999, true, $options, $duplicateCheck, $customFields);
		$this->setQuery($translatedQuery);
		$this->setUpdate($translatedUpdate);
		$this->setQueryOptions($translatedQueryOptions);
		foreach ($this->availableOperations as $operation) {
			if (isset($params[$operation])) {
				$this->{$operation} = $params[$operation];
			}
		}
		$page = Billrun_Util::getFieldVal($params['page'], 0);
		$this->page = Billrun_Util::IsIntegerValue($page) ? $page : 0;
		$size = Billrun_Util::getFieldVal($params['size'], 10);
		$this->size = Billrun_Util::IsIntegerValue($size) ? $size : 10;
		if (isset($this->query['_id'])) {
			$this->setBefore($this->loadById($this->query['_id']));
		}
		if (isset($this->config[$this->action]['custom_fields']) && $this->config[$this->action]['custom_fields']) {
			$this->addCustomFields($this->config[$this->action]['custom_fields'], $update);
		}

		//transalte all date fields
		Billrun_Utils_Mongo::convertQueryMongoDates($this->update);
	}

	/** 
	 * method to retrieve entity name that we are running on
	 * 
	 * @return string
	 */
	public function getEntityName() {
		return $this->entityName;
	}
	
	/** 
	 * method to retrieve action that we are running
	 * 
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * method to add entity custom fields values from request
	 * 
	 * @param array $fields array of field settings
	 */
	protected function addCustomFields($fields, $originalUpdate) {
//		$ad = $this->getCustomFields();
		$customFields = $this->getCustomFields($this->update);
		$additionalFields = array_column($customFields, 'field_name');
		$mandatoryFields = array();
		$uniqueFields = array();
		$defaultFieldsValues = array();
		$fieldTypes = array();

		foreach ($customFields as $customField) {
			$fieldName = $customField['field_name'];
			$mandatoryFields[$fieldName] = Billrun_Util::getFieldVal($customField['mandatory'], false);
			$uniqueFields[$fieldName] = Billrun_Util::getFieldVal($customField['unique'], false);
			$defaultFieldsValues[$fieldName] = Billrun_Util::getFieldVal($customField['default_value'], null);
			$fieldTypes[$fieldName] = Billrun_Util::getFieldVal($customField['type'], 'string');
		}

		$defaultFields = array_column($this->config[$this->action]['update_parameters'], 'name');
		if (is_null($defaultFields)) {
			$defaultFields = array();
		}
		$customFields = array_diff($additionalFields, $defaultFields);
//		print_R($customFields);
		foreach ($customFields as $field) {
			if ($this->action == 'create' && $mandatoryFields[$field] && (Billrun_Util::getIn($originalUpdate, $field, '') === '')) {
				throw new Billrun_Exceptions_Api(0, array(), "Mandatory field: $field is missing");
			}
			$val = Billrun_Util::getIn($originalUpdate, $field, null);
			$uniqueVal = Billrun_Util::getIn($originalUpdate, $field, Billrun_Util::getIn($this->before, $field, false));
			if ($uniqueVal !== FALSE && $uniqueFields[$field] && $this->hasEntitiesWithSameUniqueFieldValue($originalUpdate, $field, $uniqueVal, $fieldTypes[$field])) {
				throw new Billrun_Exceptions_Api(0, array(), "Unique field: $field has other entity with same value $uniqueVal");
			}
			if (!is_null($val)) {
				Billrun_Util::setIn($this->update, $field, $val);
			} else if ($this->action === 'create' && !is_null($defaultFieldsValues[$field])) {
				Billrun_Util::setIn($this->update, $field, $defaultFieldsValues[$field]);
			}
		}
//		print_R($this->update);die;
	}

	protected function hasEntitiesWithSameUniqueFieldValue($data, $field, $val, $fieldType = 'string') {
		$nonRevisionsQuery = $this->getNotRevisionsOfEntity($data);
		if ($fieldType == 'ranges') {
			$uniqueQuery = Api_Translator_RangesModel::getOverlapQuery($field, $val);
		} else if (is_array($val)) {
			$uniqueQuery = array($field => array('$in' => $val)); // not revisions of same entity, but has same unique value
		} else {
			$uniqueQuery = array($field => $val); // not revisions of same entity, but has same unique value
		}
		$startTime = strtotime(isset($data['from'])? $data['from'] : $this->getDefaultFrom());
		$endTime = strtotime(isset($data['to'])? $data['to'] : $this->getDefaultTo());
		$overlapingDatesQuery = Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $startTime, $endTime);
		$query = array('$and' => array($uniqueQuery, $overlapingDatesQuery));
		if ($nonRevisionsQuery) {
			$query['$and'][] = $nonRevisionsQuery;
		}

		return $this->collection->query($query)->count() > 0;
	}

	/**
	 * builds a query that gets all entities that are not revisions of the current entity
	 * 
	 * @param type $data
	 */
	protected function getNotRevisionsOfEntity($data) {
		$query = array();
		foreach (Billrun_Util::getFieldVal($this->config['collection_subset_query'], []) as $fieldName => $fieldValue) {
			$query[$fieldName] = $fieldValue;
		}
		$query['$or'] = array();
		foreach (Billrun_Util::getFieldVal($this->config['duplicate_check'], []) as $fieldName) {
			if (!isset($data[$fieldName])) {
				$dupFieldVal = Billrun_Util::getIn($data, $fieldName, Billrun_Util::getIn($this->before, $fieldName, false));
				$data[$fieldName] = $dupFieldVal;
			}
			$query['$or'][] = array(
				$fieldName => array('$ne' => $data[$fieldName]),
			);
		}

		if (empty($query['$or'])) {
			unset($query['$or']);
		}

		return $query;
	}

	protected function getCustomFields($update = array()) {
		return array_filter(Billrun_Factory::config()->getConfigValue($this->collectionName . ".fields", array()), function($customField) {
			return !Billrun_Util::getFieldVal($customField['system'], false);
		});
	}

	public function getCustomFieldsPath() {
		return $this->collectionName . ".fields";
	}

	/**
	 * Create a new entity
	 * @param type $data the entity to create
	 * @return boolean
	 * @throws Billrun_Exceptions_Api
	 */
	public function create() {
		$this->action = 'create';
		unset($this->update['_id']);
		if (empty($this->update['from'])) {
			$this->update['from'] = new MongoDate();
		}
		if (empty($this->update['to'])) {
			$this->update['to'] = new MongoDate(strtotime(self::UNLIMITED_DATE));
		}
		if (empty($this->update['creation_time'])) {
			$this->update['creation_time'] = $this->update['from'];
		}
		if ($this->duplicateCheck($this->update)) {
			$status = $this->insert($this->update);
			$this->fixEntityFields($this->before);
			$this->trackChanges($this->update['_id']);
			return isset($status['ok']) && $status['ok'];
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Entity already exists');
		}
	}

	/**
	 * Performs the update action by a query and data to update
	 * @param array $query
	 * @param array $data
	 */
	public function update() {
		$this->action = 'update';
		$this->checkUpdate();
		$this->fixEntityFields($this->before);
		$this->trackChanges($this->query['_id']);
		return true;
	}

	/**
	 * Performs the permanentchange action by a query.
	 */
	public function permanentChange() {
		$this->action = 'permanentchange';
		if (!$this->query || empty($this->query) || !isset($this->query['_id'])) {
			return;
		}
		if ($this->update['from']->sec < $this->before['from']->sec || $this->update['from']->sec > $this->before['to']->sec) {
			throw new Billrun_Exceptions_Api(1, array(), 'From field must be between ' . date('Y-m-d', $this->before['from']->sec) . ' to ' . date('Y-m-d', $this->before['to']->sec));
		}
		$this->protectKeyField();
		$permanentQuery = $this->getPermanentChangeQuery();
		$permanentUpdate = $this->getPermanentChangeUpdate();
		$this->checkMinimumDate($this->update, 'from', 'Revision update');
		if ($this->update['from']->sec != $this->before['from']->sec && $this->update['from']->sec != $this->before['to']->sec) {
			$res = $this->collection->update($this->query, array('$set' => array('to' => $this->update['from'])));
			if (!isset($res['nModified']) || !$res['nModified']) {
				return false;
			}
			$prevEntity = $this->before->getRawData();
			unset($prevEntity['_id']);
			$prevEntity['from'] = $this->update['from'];
			$this->insert($prevEntity);
		}
		$beforeChangeRevisions = $this->collection->query($permanentQuery)->cursor();
		$oldRevisions = iterator_to_array($beforeChangeRevisions);
		$this->collection->update($permanentQuery, $permanentUpdate, array('multiple' => true));
		$afterChangeRevisions = $this->collection->query($permanentQuery)->cursor();
		$this->fixEntityFields($this->before);
		$field = $this->getKeyField();
		foreach ($afterChangeRevisions as $newRevision) {
			$currentId = $newRevision['_id']->getMongoId()->{'$id'};
			$oldRevision = $oldRevisions[$currentId];
			
			$key = $oldRevision[$field];
			Billrun_AuditTrail_Util::trackChanges($this->action, $key, $this->entityName, $oldRevision->getRawData(), $newRevision->getRawData());
		}
		return true;
	}

	protected function getPermanentChangeQuery() {
		$duplicateCheck = isset($this->config['duplicate_check']) ? $this->config['duplicate_check'] : array();
		foreach ($duplicateCheck as $fieldName) {
			$query[$fieldName] = $this->before[$fieldName];
		}
		$query['from'] = array('$gte' => $this->update['from']);
		return $query;
	}

	protected function getPermanentChangeUpdate() {
		$update = $this->update;
		unset($update['from']);
		return $this->generateUpdateParameter($update, $this->queryOptions);
	}
	
	/**
	 * Performs the changepassword action by a query and data to update
	 * @param array $query
	 * @param array $data
	 */
	public function changePassword() {
		$this->action = 'changepassword';

		$this->checkUpdate();
		Billrun_Factory::log("Password changed successfully for " . $this->before['username'], Zend_Log::INFO);
		return true;
	}

	protected function checkUpdate() {
		if (!$this->query || empty($this->query) || !isset($this->query['_id'])) {
			return;
		}

		$this->protectKeyField();

		if ($this->preCheckUpdate() !== TRUE) {
			return false;
		}
		$status = $this->dbUpdate($this->query, $this->update);
		if (!isset($status['nModified']) || !$status['nModified']) {
			return false;
		}
	}

	/**
	 * method to check if the update is valid
	 * actual for update and closeandnew methods
	 * 
	 * @throws Billrun_Exceptions_Api
	 */
	protected function preCheckUpdate($time = null) {
		$ret = $this->checkDateRangeFields($time);
		Billrun_Factory::dispatcher()->trigger('beforeBillApiUpdate', array($this->before, &$this->query, &$this->update, &$ret));
		return $ret;
	}

	/**
	 * method to check date range fields
	 * by default checking only to field (not in the past)
	 * 
	 * @param int $time (optional) unix timestamp for minimum to value
	 * 
	 * @return true if check success else false
	 */
	protected function checkDateRangeFields($time = null) {
		if (is_null($time)) {
			$time = time();
		}
		if (isset($this->before['to']->sec) && $this->before['to']->sec < self::getMinimumUpdateDate()) {
			return false;
		}
		return true;
	}

	/**
	 * method to close the current entity and open a new one (for in-advance changes of entities)
	 * 
	 * @return mixed array of insert status, on failure false
	 * 
	 * @todo avoid overlapping of entities
	 */
	public function closeandnew() {
		$this->action = 'closeandnew';
		if (!isset($this->update['from'])) {
			$this->update['from'] = new MongoDate();
		}
		if (!is_null($this->before)) {
			$prevEntity = $this->before->getRawData();
			unset($prevEntity['_id']);
			$this->update = array_merge($prevEntity, $this->update);
		}
		if ($this->preCheckUpdate() !== TRUE) {
			return false;
		}

		$this->protectKeyField();
		$this->checkMinimumDate($this->update, 'from', 'Revision update');
		$this->verifyLastEntry();

		if ($this->before['from']->sec >= $this->update['from']->sec) {
			throw new Billrun_Exceptions_Api(1, array(), 'Revision update minimum date is ' . date('Y-m-d', $this->before['from']->sec));
			return false;
		}

		$closeAndNewPreUpdateOperation = $this->getCloseAndNewPreUpdateCommand();

		$res = $this->collection->update($this->query, $closeAndNewPreUpdateOperation);
		if (!isset($res['nModified']) || !$res['nModified']) {
			return false;
		}

//		$oldId = $this->query['_id'];
		unset($this->update['_id']);
		$status = $this->insert($this->update);
		$newId = $this->update['_id'];
		$this->fixEntityFields($this->before);
		$this->trackChanges($newId);
		return isset($status['ok']) && $status['ok'];
	}

	/**
	 * method to get the db command that run on close and new operation
	 * 
	 * @return array db update command
	 */
	protected function getCloseAndNewPreUpdateCommand() {
		return array(
			'$set' => array(
				'to' => new MongoDate($this->update['from']->sec)
			)
		);
	}

	/**
	 * method to protect key field update
	 * used on update & closeandnew operation
	 */
	protected function protectKeyField() {
		$keyField = $this->getKeyField();
		if (isset($this->update[$keyField]) && $this->update[$keyField] != $this->before[$keyField]) {
			$this->update[$keyField] = $this->before[$keyField];
		}
	}

	/**
	 * method get the minimum time to update
	 * 
	 * @return unix timestamp
	 */
	public static function getMinimumUpdateDate() {
		if (is_null(self::$minUpdateDatetime)) {
			self::$minUpdateDatetime = ($billrunKey = Billrun_Billingcycle::getLastNonRerunnableCycle()) ? Billrun_Billingcycle::getEndTime($billrunKey) : 0;
		}
		return self::$minUpdateDatetime;
	}
	
	public static function isAllowedChangeDuringClosedCycle() {
		return Billrun_Factory::config()->getConfigValue('system.closed_cycle_changes', false);
	}

	/**
	 * Gets an entity by a query
	 * @param array $query
	 * @param array $data
	 * @return array the entities found
	 */
	public function get() {
		if (isset($this->config['active_documents']) && $this->config['active_documents']) {
			$add_query = Billrun_Utils_Mongo::getDateBoundQuery();
			$this->query = array_merge($add_query, $this->query);
		}
		$ret = $this->runQuery($this->query, $this->sort);
		if (isset($this->config['get']['columns_filter_out']) && count($this->config['get']['columns_filter_out'])) {
			$filter_columns = $this->config['get']['columns_filter_out'];
			array_walk($ret, function(&$item) use ($filter_columns) {
				$item = array_diff_key($item, array_flip($filter_columns));
			});
		}
		return $ret;
	}

	/**
	 * Verify that an entity can be deleted.
	 * 
	 * @return boolean
	 */
	protected function canEntityBeDeleted() {
		return true;
	}

	/**
	 * method to check if the current query allocate the last entry
	 * 
	 * @return boolean true if the last entry else false
	 */
	protected function verifyLastEntry() {
		$entry = $this->collection->query($this->query)->cursor()->sort(array('_id' => 1))->current();
		if (isset($entry['_id']) && $this->before['_id'] != $entry['_id']) {
			throw new Billrun_Exceptions_Api(1500, array(), "Cannot remove old entries, but only the last created entry that exists");
		}
		return true;
	}

	/**
	 * method to check minimum date by the last billing cycle
	 * 
	 * @param array $params the parameters the field exists
	 * @param string $field the field to check
	 * @param string $action the action that is checking
	 * 
	 * @return true on success else false
	 * 
	 * @throws Billrun_Exceptions_Api
	 */
	protected function checkMinimumDate($params, $field = 'to', $action = null) {
		if (static::isAllowedChangeDuringClosedCycle()) {
			return true;
		}
		if (is_null($action)) {
			$action = $this->action;
		}

		$fromMinTime = self::getMinimumUpdateDate();
		if (isset($params[$field]->sec) && $params[$field]->sec < $fromMinTime) {
			throw new Billrun_Exceptions_Api(1, array(), ucfirst($action) . ' minimum date is ' . date('Y-m-d', $fromMinTime));
			return false;
		}
		return true;
	}

	/**
	 * Deletes an entity by a query
	 * @param array $query
	 * @param array $update
	 * @return type
	 */
	public function delete() {
		$this->action = 'delete';
		if (!$this->canEntityBeDeleted()) {
			throw new Billrun_Exceptions_Api(2, array(), 'entity cannot be deleted');
		}

		if (!$this->validateQuery()) {
			return false;
		}
		if (isset($this->config['collection_subset_query'])) {
			foreach ($this->config['collection_subset_query'] as $key => $value) {
				$this->query[$key] = $value;
			}
		}

		$this->verifyLastEntry();
		$this->checkMinimumDate($this->before, 'from');

		$status = $this->remove($this->query); // TODO: check return value (success to remove?)
		if (!isset($status['ok']) || !$status['ok']) {
			return false;
		}
		$this->trackChanges(null); // assuming remove by _id

		if ($this->shouldReopenPreviousEntry()) {
			return $this->reopenPreviousEntry();
		}
		$this->fixEntityFields($this->before);
		return true;
	}

	/**
	 * validates that the query is legitimate
	 * 
	 * @return boolean
	 */
	protected function validateQuery() {
		if (!$this->query || empty($this->query) || !isset($this->query['_id']) || !isset($this->before) && $this->before->isEmpty()) { // currently must have some query
			return false;
		}
		return true;
	}

	/**
	 * make entity expired by setting to field with datetime of now
	 * 
	 * @return boolean true on success else false
	 */
	public function close() {
		$this->action = 'close';
		if (!$this->query || empty($this->query)) { // currently must have some query
			return;
		}

		if (!isset($this->update['to'])) {
			$this->update = array(
				'to' => new MongoDate()
			);
		}

		$this->checkMinimumDate($this->update);

		$status = $this->dbUpdate($this->query, $this->update);
		if (!isset($status['nModified']) || !$status['nModified']) {
			return false;
		}
		$this->fixEntityFields($this->before);
		$this->trackChanges($this->query['_id']);
		return true;
	}

	public function move() {
		$this->action = 'move';
		if (!$this->query || empty($this->query)) { // currently must have some query
			return;
		}

		if (!isset($this->update['from']) && !isset($this->update['to'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'Move operation must have from or to input');
		}

		if (isset($this->update['from'])) { // default is move from
			$ret = $this->moveEntry('from');
			$this->fixEntityFields($this->before);
			return $ret;
		}
		$ret = $this->moveEntry('to');
		$this->fixEntityFields($this->before);
		return $ret;
	}
	
	public function reopen() {
		$this->action = 'reopen';

		if (!$this->query || empty($this->query) || !isset($this->query['_id']) || !isset($this->before) || $this->before->isEmpty()) { // currently must have some query
			return false;
		}

		if (!isset($this->update['from'])) {
			throw new Billrun_Exceptions_Api(2, array(), 'reopen "from" field is missing');
		}

		$lastRevision = $this->getLastRevisionOfEntity($this->before, $this->collectionName);
		if (!$lastRevision || !isset($lastRevision['to']) || !self::isItemExpired($lastRevision) || $lastRevision['to']->sec > $this->update['from']->sec) {
			throw new Billrun_Exceptions_Api(3, array(), 'cannot reopen entity - reopen "from" date must be greater than last revision\'s "to" date');
		}
		
		$changeDuringClosedCycle = static::isAllowedChangeDuringClosedCycle();
		if (!$changeDuringClosedCycle && $this->update['from']->sec < self::getMinimumUpdateDate()) {
			throw new Billrun_Exceptions_Api(3, array(), 'cannot reopen entity in a closed cycle');
		}

		$prevEntity = $this->before->getRawData();
		$this->update = array_merge($prevEntity, $this->update);
		unset($this->update['_id']);
		$this->update['to'] = new MongoDate(strtotime(self::UNLIMITED_DATE));
		$status = $this->insert($this->update);
		$newId = $this->update['_id'];
		$this->fixEntityFields($this->before);
		$this->trackChanges($newId);
		return isset($status['ok']) && $status['ok'];
	}

	/**
	 * move from date of entity including change the previous entity to field
	 * 
	 * @return boolean true on success else false
	 */
	protected function moveEntry($edge = 'from') {
		if ($edge == 'from') {
			$otherEdge = 'to';
		} else { // $current == 'to'
			$otherEdge = 'from';
		}
		if (!isset($this->update[$edge])) {
			$this->update = array(
				$edge => new MongoDate()
			);
		}

		if (($edge == 'from' && $this->update[$edge]->sec >= $this->before[$otherEdge]->sec) || ($edge == 'to' && $this->update[$edge]->sec <= $this->before[$otherEdge]->sec)) {
			throw new Billrun_Exceptions_Api(0, array(), 'Requested start date greater than or equal to end date');
		}

		$this->checkMinimumDate($this->update, $edge);

		$keyField = $this->getKeyField();

		if ($edge == 'from') {
			$query = array(
				$keyField => $this->before[$keyField],
				$otherEdge => array(
					'$lte' => $this->before[$edge],
				)
			);

			$sort = -1;
			$rangeError = 'Requested start date is less than previous end date';
		} else {
			$query = array(
				$keyField => $this->before[$keyField],
				$otherEdge => array(
					'$gte' => $this->before[$edge],
				)
			);
			$sort = 1;
			$rangeError = 'Requested end date is greater than next start date';
		}

		// previous entry on move from, next entry on move to
		$followingEntry = $this->collection->query($query)->cursor()
			->sort(array($otherEdge => $sort))
			->current();

		if (!empty($followingEntry) && !$followingEntry->isEmpty() && (
			($edge == 'from' && $followingEntry[$edge]->sec > $this->update[$edge]->sec) ||
			($edge == 'to' && $followingEntry[$edge]->sec < $this->update[$edge]->sec)
			)
		) {
			throw new Billrun_Exceptions_Api(0, array(), $rangeError);
		}

		$status = $this->dbUpdate($this->query, $this->update);
		if (!isset($status['nModified']) || !$status['nModified']) {
			return false;
		}
		if ($edge == 'from') {
			$this->updateCreationTime($keyField, $edge);
		}
		$this->trackChanges($this->query['_id']);

		if (!empty($followingEntry) && !$followingEntry->isEmpty() && ($this->before[$edge]->sec === $followingEntry[$otherEdge]->sec)) {
			$this->setQuery(array('_id' => $followingEntry['_id']->getMongoID()));
			$this->setUpdate(array($otherEdge => new MongoDate($this->update[$edge]->sec)));
			$this->setBefore($followingEntry);
			return $this->update();
		}
		return true;
	}

	/**
	 * Convert keys that was received as dot annotation back to dot annotation
	 */	
	protected function dataToDbUpdateFormat(&$data, $originalUpdate) {
		foreach ($this->originalUpdate as $update_key => $value) {
			$keys = explode('.', $update_key);
			if (count($keys) > 1) {
				$val = Billrun_Util::getIn($originalUpdate, $update_key);
				$data[$update_key] = $val;
				Billrun_Util::unsetInPath($data, $keys, true);
			}
		}
	}
	
	protected function generateUpdateParameter($data, $options = array()) {
		$update = array();
		unset($data['_id']);
		if(!empty($data)) {
			$this->dataToDbUpdateFormat($data, $this->originalUpdate);
			$update = array(
				'$set' => $data,
			);
		}
		if(!empty($options)) {
			$update = array_merge($update, $options);
		}
		return $update;
	}

	/**
	 * DB update currently limited to update of one record
	 * @param type $query
	 * @param type $data
	 */
	protected function dbUpdate($query, $data) {
		$update = $this->generateUpdateParameter($data, $this->queryOptions);
		return $this->collection->update($query, $update);
	}

	/**
	 * Run a DB query against the current collection
	 * @param array $query
	 * @return array the result set
	 */
	protected function runQuery($query, $sort) {
		$res = $this->collection->find($query);

		if ($this->page != -1) {
			$res->skip($this->page * $this->size);
		}

		if ($this->size != -1) {
			$res->limit($this->size);
		}

		if ($sort) {
			$res = $res->sort($sort);
		}

		$records = array_values(iterator_to_array($res));
		foreach ($records as &$record) {
			$record = Billrun_Utils_Mongo::recursiveConvertRecordMongoDatetimeFields($record);
		}
		return $records;
	}

	/**
	 * Performs a delete from the DB by a query
	 * @param array $query
	 */
	protected function remove($query) {
		return $this->collection->remove($query);
	}

	/**
	 * gets the previous revision of the entity
	 * 
	 * @return Mongodloid_Entity
	 */
	protected function getPreviousEntity() {
		$key = $this->getKeyField();
		$previousEntryQuery = array(
			$key => $this->before[$key],
		);
		$previousEntrySort = array(
			'_id' => -1
		);
		return $this->collection->query($previousEntryQuery)->cursor()
				->sort($previousEntrySort)->limit(1)->current();
	}

	/**
	 * future entity was removed - checks if needs to reopen the previous entity
	 * 
	 * @return boolean - is reopen required
	 */
	protected function shouldReopenPreviousEntry() {
		if (!(isset($this->before['from']->sec) && $this->before['from']->sec >= self::getMinimumUpdateDate())) {
			return false;
		}
		$this->previousEntry = $this->getPreviousEntity();
		return !$this->previousEntry->isEmpty() &&
			($this->before['from'] == $this->previousEntry['to']);
	}

	/**
	 * future entity was removed - need to update the to of the previous change
	 */
	protected function reopenPreviousEntry() {
		if (!$this->previousEntry->isEmpty()) {
			$this->setQuery(array('_id' => $this->previousEntry['_id']->getMongoID()));
			$this->setUpdate(array('to' => $this->before['to']));
			$this->setBefore($this->previousEntry);
			return $this->update();
		}
		return TRUE;
	}

	/**
	 * method to update the update instruct
	 * @param array $u mongo update instruct
	 */
	public function setUpdate($u) {
		$this->update = $u;
	}
	
	/**
	 * method to update the update options instruct
	 * @param array $o mongo update options instruct
	 */
	public function setQueryOptions($o) {
		$queryOptions = array();
		if (isset($o['push_fields'])) {
			foreach ($o['push_fields'] as $push_field) {
				$queryOptions['$push'][$push_field['field_name']] = array(
					'$each' => $push_field['field_values']
				);
			}
			
		}
		if (isset($o['pull_fields'])) {
			foreach ($o['pull_fields'] as $pull_field) {
				if(isset($pull_field['pull_by_key'])) {
					$queryOptions['$pull'][$pull_field['field_name']][$pull_field['pull_by_key']]['$in'] = $pull_field['field_values'];
				} else {
					$queryOptions['$pull'][$pull_field['field_name']]['$in'] = $pull_field['field_values'];
				}
			}
			
		}
		$this->queryOptions = $queryOptions;
	}

	/**
	 * method to update the query instruct
	 * @param array $q mongo query instruct
	 */
	public function setQuery($q) {
		$this->query = $q;
	}

	/**
	 * method to update the before entity
	 * @param array $b the before entity
	 */
	public function setBefore($b) {
		$this->before = $b;
	}

	/**
	 * method to return the before state of the entity
	 *
	 * @return array $b the before state entity
	 */
	public function getBefore() {
		return $this->before;
	}

	/**
	 * method to return the after state of the entity
	 *
	 * @return array $b the after state entity
	 */
	public function getAfter() {
		return $this->after;
	}

	/**
	 * method to return the affected line
	 *
	 * @return array
	 */
	public function getAffectedLine() {
		return $this->line;
	}

	/**
	 * method to track changes with audit trail
	 * 
	 * @param MongoId $newId the new id; if null take from update array _id field
	 * @param MongoId $oldId the old id; if null this is new document (insert operation)
	 * 
	 * @return boolean true on success else false
	 */
	protected function trackChanges($newId = null) {
		$field = $this->getKeyField();
		if (is_null($newId) && isset($this->update['_id'])) {
			$newId = $this->update['_id'];
		}

		if ($newId) {
			$this->after = $this->loadById($newId);
		}

		$old = !is_null($this->before) ? $this->before->getRawData() : null;
		$new = !is_null($this->after) ? $this->after->getRawData() : null;
		$key = isset($this->update[$field]) ? $this->update[$field] :
			(isset($this->before[$field]) ? $this->before[$field] : null);
		return Billrun_AuditTrail_Util::trackChanges($this->action, $key, $this->entityName, $old, $new);
	}

	/**
	 * method to load the entity from DB by _id
	 * 
	 * @param mixed $id MongoId or id (string) of the entity
	 * 
	 * @return array the entity loaded
	 */
	protected function loadById($id) {
		$fetchQuery = array('_id' => ($id instanceof MongoId) ? $id : new MongoId($id));
		return $this->collection->query($fetchQuery)->cursor()->current();
	}

	/**
	 * Inserts a document to the DB, as is
	 * @param array $data
	 */
	protected function insert(&$data) {
		$ret = $this->collection->insert($data, array('w' => 1, 'j' => true));
		return $ret;
	}

	/**
	 * Returns true if current record does not overlap with existing records in the DB
	 * @param array $data
	 * @param array $ignoreIds
	 * @return boolean
	 */
	protected function duplicateCheck($data, $ignoreIds = array()) {
		$query = array();
		foreach (Billrun_Util::getFieldVal($this->config['duplicate_check'], []) as $fieldName) {
			$query[$fieldName] = $data[$fieldName];
		}
		if ($ignoreIds) {
			$query['_id'] = array(
				'$nin' => $ignoreIds,
			);
		}
		return $query ? !$this->collection->query($query)->count() : TRUE;
	}

	/**
	 * Return the key field by collection
	 * 
	 * @return String
	 */
	protected function getKeyField() {
		switch ($this->collectionName) {
			case 'users':
				return 'username';
			case 'discounts':
			case 'reports':
			case 'taxes':
			case 'rates':
				return 'key';
			default:
				return 'name';
		}
	}

	/**
	 * Add revision info (status, early_expiration) to record
	 * 
	 * @param array $record - Record to set revision info.
	 * @param string $collection - Record collection
	 * 
	 * @return The record with revision info.
	 */
	public static function setRevisionInfo($record, $collection, $entityName) {
		$status = self::getStatus($record, $collection);
		$isLast = self::getIsLast($record, $collection, $entityName);
		$earlyExpiration = self::isEarlyExpiration($record, $status, $isLast);
		$isCurrentCycle = $record['from']->sec >= self::getMinimumUpdateDate();
		$record['revision_info'] = array(
			"status" => $status,
			"is_last" => $isLast,
			"early_expiration" => $earlyExpiration,
			"updatable" => $isCurrentCycle,
			"closeandnewable" => $isCurrentCycle,
			"movable" => $isCurrentCycle,
			"removable" => $isCurrentCycle,
			"movable_from" => self::isDateMovable($record['from']->sec),
			"movable_to" => self::isDateMovable($record['to']->sec)
		);
		return $record;
	}

	protected static function isDateMovable($timestamp) {
		if (static::isAllowedChangeDuringClosedCycle()) {
			return true;
		}
		return self::getMinimumUpdateDate() <= $timestamp;
	}

	/**
	 * Calculate record status
	 * 
	 * @param array $record - Record to set revision info.
	 * @param string $collection - Record collection name
	 * 
	 * @return string Status, available values are: "future", "expired", "active"
	 */
	static function getStatus($record, $collection) {
		if ($record['to']->sec < time()) {
			return self::EXPIRED;
		}
		if ($record['from']->sec > time()) {
			return self::FUTURE;
		}
		return self::ACTIVE;
	}

	/**
	 * Calculate record status
	 * 
	 * @param array $record - Record to set revision info.
	 * @param string $collection - Record collection name
	 * @param string $entityName - Record entity name
	 * 
	 * @return string Status, available values are: "future", "expired", "active"
	 */
	static function getIsLast($record, $collection, $entityName) {
		// For active records, check if it has furure revisions
		$query = Billrun_Utils_Mongo::getDateBoundQuery($record['to']->sec, true, $record['to']->usec);
		$uniqueFields = Billrun_Factory::config()->getConfigValue("billapi.{$entityName}.duplicate_check", array());
		foreach ($uniqueFields as $fieldName) {
			$query[$fieldName] = $record[$fieldName];
		}
		$recordCollection = Billrun_Factory::db()->{$collection . 'Collection'}();
		return $recordCollection->query($query)->count() === 0;
	}

	/**
	 * Check if record was closed by close action.
	 * true if the "to" field is less than 50 years from record "from" date.
	 * 
	 * @param array $record - Record to set revision info.
	 * @param string $status - Record status, available values are: "expired", "active", "future"
	 * 
	 * @return bool
	 */
	protected static function isEarlyExpiration($record, $status, $isLast) {
		if ($status === self::FUTURE || ($status === self::ACTIVE && $isLast)) {
			return self::isItemExpired($record);
		}
		return false;
	}

	public function getCollectionName() {
		return $this->collectionName;
	}

	public function getCollection() {
		return $this->collection;
	}

	public function getMatchSubQuery() {
		$query = array();
		foreach (Billrun_Util::getFieldVal($this->config['collection_subset_query'], []) as $fieldName => $fieldValue) {
			$query[$fieldName] = $fieldValue;
		}

		return $query;
	}

	protected function updateCreationTime($keyField, $edge) {
		if(isset($this->update['_id'])) {
			$queryCreation = array(
				$keyField => $this->before[$keyField],
			);
			$firstRevision = $this->collection->query($queryCreation)->cursor()->sort(array($edge => 1))->limit(1)->current();
			if ($this->update['_id'] == strval($firstRevision->getId())) {
				$this->collection->update($queryCreation, array('$set' => array('creation_time' => $this->update[$edge])), array('multiple' => 1));
			}
		}
	}

	/**
	 * checks if item is not "unlimited", which means it has an expiration date
	 * 
	 * @param array $item
	 * @param string $expiredField
	 * @return boolean
	 */
	protected static function isItemExpired($item, $expiredField = 'to') {
		return $item[$expiredField]->sec < strtotime("+10 years");
	}

	/**
	 * gets the last revision of the entity (might be expired, active, future)
	 * 
	 * @param array $entity
	 */
	public function getLastRevisionOfEntity($entity) {
		$query = array();
		foreach (Billrun_Util::getFieldVal($this->config['duplicate_check'], []) as $fieldName) {
			$query[$fieldName] = $entity[$fieldName];
		}
		$sort = array('_id' => -1);
		return $this->collection->find($query)->sort($sort)->limit(1)->getNext();
	}

	protected function fixEntityFields($entity) {
		return;
	}
	
	protected function getDefaultFrom() {
		switch ($this->action) {
			case 'permanentchange':
				return '1970-01-02 00:00:00';
			case 'create':
				return Billrun_Util::generateCurrentTime();
			
			default:
				return $this->before['from'];
		}
	}
	
	protected function getDefaultTo() {
		switch ($this->action) {
			case 'permanentchange':
			case 'create':
				return '+100 years';
			
			default:
				return $this->before['to'];
		}
	}

	protected function validateAdditionalData($additional) {
		if (!is_array($additional)) {
			return [];
		}
		return $additional;
	}
}
