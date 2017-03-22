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
abstract class Models_Action_Update_Balance_Abstract {

	/**
	 * the update method type
	 * @var string
	 */
	protected $updateType = 'Abstract';

	/**
	 * the subscriber entry
	 * @var array
	 */
	protected $subscriber = array();

	public function __construct(array $params = array()) {
		if (!isset($params['sid'])) {
			throw new Billrun_Exceptions_Api(0, array(), 'Subscriber id is not define in input under prepaid include');
		}

		$this->loadSubscriber((int) $params['sid']);
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

		$this->createBillingLines();
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
	abstract protected function createBillingLines();

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
	protected function loadSubscriber($sid) {
		$subQuery = array(
			'$or' => array(
				array(
					'type' => array(
						'$exists' => false,
					)), // backward compatibility (type not exists)
				array('type' => 'subscriber'),
			),
			'sid' => $sid,
		);
		$sub = Billrun_Factory::db()->subscribersCollection()->query($subQuery)->cursor()->current();
		if ($sub->isEmpty()) {
			throw new Billrun_Exceptions_Api(0, array(), 'Subscriber not found on prepaid include update');
		}
		$this->subscriber = $sub->getRawData();
	}

}
