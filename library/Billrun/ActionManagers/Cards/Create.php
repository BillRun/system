<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Parser to be used by the cards action
 *
 * @package  cards
 * @since    4.0
 */
class Billrun_ActionManagers_Cards_Create extends Billrun_ActionManagers_Cards_Action {

	use Billrun_FieldValidator_ServiceProvider;

	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $cards = array();
	protected $secrets = array();
	protected $inner_hash;

	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success creating cards"));
	}

	/**
	 * Get the array of fields to be inserted in the create record from the user input.
	 * @return array - Array of fields to be inserted.
	 */
	protected function getCreateFields() {
		return Billrun_Factory::config()->getConfigValue('cards.create_fields', array());
	}

	/**
	 * Get the array of initial statuses.
	 * @return array - Array of initial statuses.
	 */
	protected function getInitialStatus() {
		return Billrun_Factory::config()->getConfigValue('cards.initialStatus', array());
	}

	/**
	 * Check if one of the secrets in the batch already exists in CARDS table.
	 * @return boolean - true or false.
	 */
	protected function secretExists() {
		$query = array('secret' => array('$in' => $this->secrets));
		return !Billrun_Factory::db()->cardsCollection()->query($query)->cursor()->limit(1)->current()->isEmpty();
	}

	/**
	 * This function builds the create for the Cards creation API after 
	 * validating existance of field and that they are not empty.
	 * @param array $input - fields for insertion in Jason format. 
	 * @return Return false (and writes errLog) when fails to loocate 
	 * all needed field and/or values for insertion and true when success.
	 */
	protected function createProcess($input) {
		$createFields = $this->getCreateFields();
		$initialStatus = $this->getInitialStatus();
		$jsonCreateDataArray = null;
		$create = $input->get('cards');

		if (empty($create) || (!($jsonCreateDataArray = json_decode($create, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("cards_error_base");
			$error = "There is no create tag or create tag is empty!";
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		if ($jsonCreateDataArray !== array_values($jsonCreateDataArray)) {
			$jsonCreateDataArray = array($jsonCreateDataArray);
		}

		$this->inner_hash = md5(serialize($jsonCreateDataArray));
		foreach ($jsonCreateDataArray as $jsonCreateData) {
			$oneCard = array();
			foreach ($createFields as $field) {
				if (!isset($jsonCreateData[$field]) || empty($jsonCreateData[$field])) {
					$errorCode = Billrun_Factory::config()->getConfigValue("cards_error_base") + 1;
					$this->reportError($errorCode, Zend_Log::NOTICE, array($field));
					return false;
				}
				$oneCard[$field] = $jsonCreateData[$field];
			}

			// Initial status validity check (Initial status should be "Idle")
			if ($initialStatus && !in_array($oneCard['status'], $initialStatus)) {
				$errorCode = Billrun_Factory::config()->getConfigValue("cards_error_base") + 3;
				$this->reportError($errorCode, Zend_Log::NOTICE, array($oneCard['status']));
				return false;
			}

			// service provider validity check
			if (!$this->validateServiceProvider($oneCard['service_provider'])) {
				$errorCode = Billrun_Factory::config()->getConfigValue("cards_error_base") + 4;
				$this->reportError($errorCode, Zend_Log::NOTICE, array($oneCard['service_provider']));
				return false;
			}

			$oneCard['secret'] = hash('sha512', $oneCard['secret']);
			$oneCard['from'] = new MongoDate();
			$oneCard['to'] = new MongoDate(strtotime($oneCard['to']));
			$oneCard['creation_time'] = new MongoDate(strtotime($oneCard['creation_time']));
			$oneCard['inner_hash'] = $this->inner_hash;

			$this->secrets[] = $oneCard['secret'];
			$this->cards[] = $oneCard;
		}

		return true;
	}

	/**
	 * Clean the inner hash from the cards in the mongo
	 * @param type $bulkOptions - Options for bulk insert in mongo db.
	 * @return type
	 */
	protected function cleanInnerHash($bulkOptions) {
		$updateQuery = array('inner_hash' => $this->inner_hash);
		$updateValues = array('$unset' => array('inner_hash' => 1));
		$updateOptions = array_merge($bulkOptions, array('multiple' => 1));
		return Billrun_Factory::db()->cardsCollection()->update($updateQuery, $updateValues, $updateOptions);
	}

	/**
	 * Remove the created cards due to error.
	 * @param type $bulkOptions - Options used for bulk insert to the mongo db.
	 * @return type
	 */
	protected function removeCreated($bulkOptions) {
		$removeQuery = array('inner_hash' => $this->inner_hash);
		return Billrun_Factory::db()->cardsCollection()->remove($removeQuery, $bulkOptions);
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$bulkOptions = array(
			'continueOnError' => true,
			'socketTimeoutMS' => 300000,
			'wTimeoutMS' => 300000,
		);
		$exception = null;
		try {
			if (!$this->secretExists()) {
				$res = Billrun_Factory::db()->cardsCollection()->batchInsert($this->cards, $bulkOptions);
			} else {
				$errorCode = Billrun_Factory::config()->getConfigValue("cards_error_base") + 5;
				$this->reportError($errorCode, Zend_Log::NOTICE);
			}
		} catch (\Exception $e) {
			$exception = $e;
			$errorCode = Billrun_Factory::config()->getConfigValue("cards_error_base") + 2;
			$error = 'failed storing in the DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			$this->reportError($errorCode, Zend_Log::NOTICE);
			Billrun_Factory::log('failed saving request :' . print_r($this->cards, 1), Zend_Log::NOTICE);
			$res = $this->removeCreated($bulkOptions);
		}

		// Error code 0 is success
		if (!$this->errorCode) {
			$res = $this->cleanInnerHash($bulkOptions);
		}

		array_walk($this->cards, function (&$card, $idx) {
			unset($card['secret']);
		});

		$outputResult = array(
			'status' => $this->errorCode == 0 ? 1 : 0,
			'desc' => $this->error,
			'error_code' => $this->errorCode,
			'details' => (!$this->errorCode) ?
				(json_encode($this->cards)) :
				('Batch Cancelled')
		);
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {

		if (!$this->createProcess($input)) {
			return false;
		}

		return true;
	}

}
