<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 */
class Billrun_ActionManagers_Subscribers_Create extends Billrun_ActionManagers_Subscribers_Action {

	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	
	/**
	 * Keeps entity's type (account/subscriber/...)
	 * @var type 
	 */
	protected $type;
	
	protected $fields;

	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success creating subscriber"));
	}

	/**
	 * Get the query to run to get a subscriber from the db.
	 * @return array Query to run in the mongo.
	 */
	protected function getSubscriberQuery() {
		$subscriberQuery = array_merge(Billrun_Util::getDateBoundQuery(), array('type' => $this->type));
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if (isset($field['unique']) && $field['unique']) {
				if (is_array($this->query[$fieldName])) {
					$subscriberQuery['$or'][][$fieldName] = array('$in' => Billrun_Util::array_remove_compound_elements($this->query[$fieldName]));
				}
				$subscriberQuery['$or'][][$fieldName] = $this->query[$fieldName];
			}
		}
		return $subscriberQuery;
	}

	/**
	 * Check if the subscriber to create already exists.
	 * @return boolean - true if the subscriber exists.
	 */
	protected function subscriberExists() {
		// Check if the subscriber already exists.
		$subscriberQuery = $this->getSubscriberQuery();

		$subscribers = $this->collection->query($subscriberQuery);

		// TODO: Use the subscriber class.
		if ($subscribers->count() > 0) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base");
			$parameters = http_build_query($this->query, '', ', ');
			$this->reportError($errorCode, Zend_Log::NOTICE, array($parameters));
			return true;
		}

		return false;
	}
	
	protected function setSubscriberType($data) {
		$subscriberTypes = Billrun_Factory::config()->getConfigValue('subscribers.types', array());
		if (empty($this->type = $data['type']) ||
			!in_array($this->type, $subscriberTypes)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 7;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}		
		return true;
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		try {
			if (!$this->subscriberExists()) {
				$entity = new Mongodloid_Entity($this->query);
				$this->collection->save($entity, 1);
			}
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 1;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Billrun_Log::WARN);
		}

		$outputResult = array(
			'status' => $this->errorCode == 0 ? 1 : 0,
			'desc' => $this->error,
			'error_code' => $this->errorCode,
		);

		if (isset($entity)) {
			$outputResult['details'] = $entity->getRawData();
		}
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if (!$this->setQueryRecord($input)) {
			return false;
		}

		return true;
	}

	/**
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonData = null;
		$query = $input->get('subscriber');
		if (empty($query) || (!($jsonData = json_decode($query, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		if (!$this->setSubscriberType($jsonData)) {
			return false;
		}
		
		$invalidFields = $this->setQueryFields($jsonData);

		// If there were errors.
		if (!empty($invalidFields)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 3;
			$this->reportError($errorCode, Zend_Log::NOTICE, array(implode(',', $invalidFields)));
			return false;
		}
		
		$this->setAdditionalFields();

		return $this->validate();
	}
	
	protected function setGeneratedFields() {
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if (isset($field['generated']) && $field['generated']) {
				$this->query[$fieldName] = Billrun_Factory::db()->subscribersCollection()->createAutoInc($fieldName);
			}
		}
	}
	
	protected function setAdditionalFields() {
		// Set the from and to values.
		$this->query['from'] = new MongoDate();
		$this->query['to'] = new MongoDate(strtotime('+100 years'));
		
		$this->setGeneratedFields();
	}

	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
		$this->fields = $this->getFields();

		// Array of errors to report if any error occurs.
		$invalidFields = array();

		// Get only the values to be set in the update record.
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if ((isset($field['mandatory']) && $field['mandatory']) &&
				(!isset($queryData[$fieldName]) || empty($queryData[$fieldName]))) {
				$invalidFields[] = $fieldName;
			} else if (isset($queryData[$fieldName])) {
				$this->query[$fieldName] = $queryData[$fieldName];
			}
		}

		return $invalidFields;
	}
	
	protected function getFields() {
		return array_merge(
			Billrun_Factory::config()->getConfigValue("subscribers." . $this->type . ".fields", array()),
			Billrun_Factory::config()->getConfigValue("subscribers.fields", array())
		);
	}
	
	protected function validate() {
		if ($this->type === 'subscriber') {
			return $this->isAccountExists($this->query['aid']);
		}
		
		return true;
	}
	
	protected function isAccountExists($aid) {
		$query = array_merge(
			Billrun_Util::getDateBoundQuery(), 
			array("type" => "account", "aid" => $aid)
		);
		if (Billrun_Factory::db()->subscribersCollection()->query($query)->cursor()->count() === 0) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 8;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($aid));
			return false;
		}
		return true;
	}

}
