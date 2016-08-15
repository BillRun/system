<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 */
class Billrun_ActionManagers_Subscribers_Create extends Billrun_ActionManagers_Subscribers_Action {

	use Billrun_ActionManagers_Subscribers_Validator {
		validate as baseValidate;
	}
	use Billrun_ActionManagers_Subscribers_Servicehandler;
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();

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
		if (!parent::parse($input) || !$this->setQueryRecord($input)) {
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
		$this->query['type'] = $this->type;
		// Set the from and to values.
		$this->query['from'] = new MongoDate();
		$this->query['plan_activation'] = new MongoDate();
		$this->query['to'] = new MongoDate(strtotime('+100 years'));

		$this->setGeneratedFields();
	}

	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
		// Array of errors to report if any error occurs.
		$invalidFields = array();

		// Get only the values to be set in the update record.
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if ((isset($field['mandatory']) && $field['mandatory']) &&
				(!isset($queryData[$fieldName]) || empty($queryData[$fieldName]))) {
				$invalidFields[] = $fieldName;
			} else if (!isset($queryData[$fieldName])) {
				continue;
			}
			
			// TODO: Create some sort of polymorphic behaviour to correctly handle
			// the updating fields.
			if($fieldName === 'services') {
				$toSet = $this->getSubscriberServices($queryData['services']);
			} else {
				$toSet = $queryData[$fieldName];
			}
			
			if(empty($toSet)) {
				continue;
			}
			
			$this->query[$fieldName] = $toSet;
		}

		return $invalidFields;
	}

	protected function validate() {
		if (($this->type === 'subscriber') && (!$this->isAccountExists($this->query['aid']))) {
				return false;
		}
		
		return $this->baseValidate();
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

	protected function getSubscriberData() {
		return $this->query;
	}

}
