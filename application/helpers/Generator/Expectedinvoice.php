<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Generator
 * @copyright  Copyright (C) 2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Balance generator
 *
 * @package    Generator
 * @subpackage Balance
 * @since      1.0
 */
class Generator_Expectedinvoice extends Billrun_Generator {

	/**
	 * Account for which to get the current balance
	 * @var int 
	 */
	protected $aid = null;


	/**
	 *
	 * @var array the updated account data received from the CRM
	 */
	protected $account_data = array();

	/**
	 * the balance date
	 * @var string a formatted date string
	 */
	protected $billrunStamp = null;
	
	/**
	 * the billing method
	 * @var string prepaid or postpaid
	 */
	protected $billing_method = null;

	
	public function __construct($options) {
		$options['auto_create_dir'] = false;
		parent::__construct($options);
		self::$type = 'balance';
		$this->aid = Billrun_Util::getFieldVal($options['aid'], 0);
		$this->now = time();
	}

	public function load() {
//		foreach ($this->account_data as $subscriber) {
//			if (!Billrun_Factory::db()->rebalance_queueCollection()->query(array('sid' => $subscriber->sid), array('sid' => 1))
//					->cursor()->current()->isEmpty()) {
//				$subscriber_status = "REBALANCE";
//				$billrun->addSubscriber($subscriber, $subscriber_status);
//				continue;
//			}
//		}


	}

	public function generate() {
		$options = array(
			'type' => 'customer',
			'force_accounts' => array($this->aid),
			'stamp' => $this->stamp,
			'fake_cycle' => true,
		);
		$generator = Billrun_Aggregator::getInstance($options);
		$generator->load();
		if($generator->aggregate()) {
			return  "/tmp/{$this->stamp}/pdf/{$this->stamp}_{$this->aid}_0.pdf";
		}
		return FALSE;
	}

	protected function setAccountId($aid) {
		$this->aid = intval($aid);
	}


}
