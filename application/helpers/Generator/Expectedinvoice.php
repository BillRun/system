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
	 * the balance date
	 * @var string a formatted date string
	 */
	protected $billrunStamp = null;
	
	/**
	 * the billing method
	 * @var string prepaid or postpaid
	 */
	protected $billing_method = null;
	
	/**
	 * Account's invoicing day (relevant for multi day cycle mode)
	 * @var string - invoicing day (between 1 to 28)
	 */
	protected $invoicing_day = null;

	
	public function __construct($options) {
		$options['auto_create_dir'] = false;
		parent::__construct($options);
		$this->aid = Billrun_Util::getFieldVal($options['aid'], 0);
		$this->now = time();
	}

	// Theres nothing  to load  in this  generator  ans it`s data  is based on the  customer aggregator logic	
	public function load() {}

	public function generate() {
		$config = Billrun_Factory::config();
		$options = array(
			'type' => 'customer',
			'force_accounts' => array($this->aid),
			'stamp' => $this->stamp,
			'fake_cycle' => true,
		);
		$generator = Billrun_Aggregator::getInstance($options);
		$generator->load();
		if ($config->isMultiDayCycle()) {
			$generator->setInvoicing_days();
		}
		if($generator->aggregate()) {
			return Generator_WkPdf::getTempDir($this->stamp) . "/pdf/{$this->stamp}_{$this->aid}_0.pdf";
		} else {
			throw new Exception("Couldn't generate invoice for {$this->aid} for {$this->stamp} billing cycle.",0);
		}
		return FALSE;
	}

	protected function setAccountId($aid) {
		$this->aid = intval($aid);
	}


}
