<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for balance update
 *
 * @package  Billapi
 * @since    5.3
 */
abstract class Billrun_Balance_Update_Abstract {

	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Abstract';

	/**
	 * Flag to mark if balance is shared
	 * @var boolean
	 */
	protected $sharedBalance = false;

	/**
	 * the subscriber entry
	 * @var array
	 */
	protected $subscriber = array();
	
	/**
	 * additional parameters to be saved
	 * 
	 * @var array 
	 */
	protected $additional = array();
	
	/**
	 * The line saved to lines collection
	 * 
	 * @var array
	 */
	protected $line = null;

	public function __construct(array $params = array()) {
		if (!$this->sharedBalance && !isset($params['sid'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'Subscriber id (sid) is not define in input under prepaid include');
		} else if (!$this->sharedBalance) {
			$identifier = $params['sid'];
			$field = 'sid';
			$subscriber_type = 'subscriber';
		}
		
		if ($this->sharedBalance && !isset($params['aid'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'On shared balance account id (aid) must be defined in the input');
		} else if ($this->sharedBalance) {
			$identifier = $params['aid'];
			$field = 'aid';
			$subscriber_type = 'account';
		}

		$this->loadSubscriber((int) $identifier, $field, $subscriber_type);
		
		if (!empty($params['additional'])) {
			$this->additional= $params['additional'];
		}
	}

	abstract protected function init();

	/**
	 * get the update method type
	 * @return string
	 */
	public function getUpdateType() {
		return $this->updateType;
	}

	public function execute() {
		if ($this->preValidate() === false) {
			return false;
		}

		$this->update();

		if ($this->postValidate() === false) {
			return false;
		}

		$this->line = $this->createBillingLines();
		$this->trackChanges();

		return true;
	}

	/**
	 * method to trigger the update
	 * 
	 * @return boolean true on success else false
	 */
	abstract protected function update();

	/**
	 * create row to track the balance update
	 */
	abstract protected function createBillingLines($chargingData = array());

	/**
	 * method to track change in audit trail
	 * 
	 * @return true on success log change else false
	 */
	abstract protected function trackChanges();

	public function preValidate() {
		$ret = true;
		Billrun_Factory::dispatcher()->trigger('BillApiBalancePreValidate', array($this, &$ret));
		return $ret;
	}

	public function postValidate() {
		$ret = true;
		Billrun_Factory::dispatcher()->trigger('BillApiBalancePostValidate', array($this, &$ret));
		return $ret;
	}

	/**
	 * method to load subscriber details
	 * @param type $sid
	 * @throws Billrun_Exceptions_Api
	 * @todo add connection type (limit to prepaid)
	 * @return array subscriber details
	 */
	protected function loadSubscriber($identifier, $field, $subscriber_type) {
		$subQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$subQuery[$field] = $identifier;
		$subQuery['type'] = $subscriber_type;
		
		$sub = Billrun_Factory::subscriber()->load($subQuery)->current(); // todo add revision from/to support
		if ($sub->isEmpty()) {
			throw new Billrun_Exceptions_Api(0, array(), ucfirst($field) . ' not found on prepaid include update');
		}
		$this->subscriber = $sub->getRawData();
	}
	
	/**
	 * Gets the line saved in lines collection
	 * 
	 * @return array
	 */
	public function getAffectedLine() {
		return $this->line;
	}
	
	public function getAfter() {
		return null;
	}
	
	/**
	 * method to add property to additional info
	 * 
	 * @param string $key
	 * @param mixed $val
	 */
	public function addAdditional($key, $val) {
		$this->additional[$key] = $val;
	}

}
