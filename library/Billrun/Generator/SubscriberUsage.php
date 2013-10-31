<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing call generator class
 * Make and  receive call  base on several  parameters
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Generator_SubscriberUsage extends Billrun_Generator {


	/**
	 * The script to generate calls by.
	 */
	protected $from = false;
	protected $to = false;

	/**
	 * The test identification.
	 */
	protected $subscriberId =false;
	

	public function __construct($options) {

		parent::__construct($options);
		if (isset($options['subscriber_id'])) {
			$this->subscriberId = $options['subscriber_id'];
		}
		if (isset($options['from'])) {
			$this->from = new MongoDate(strtotime($options['from']));
		}
		if (isset($options['to'])) {
			$this->to = new MongoDate(strtotime($options['to']));
		}

	}

	/**
	 * Generate the calls as defined in the configuration.
	 */
	public function generate() {
		//Billrun_Factory::log(print_r($this->subscriberId,1));

		$subscriberLines = array();
		foreach ($this->lines as $value) {
			$subscriberLines[] = $value->getRawData();
		}
		Billrun_Factory::log(print_r($subscriberLines,1));
		//Billrun_Factory::log(print_r($subscriberLines,1));
		return array('lines' => $subscriberLines );
	}
	
	/**
	 * Load the script
	 */
	public function load() {

		$this->lines = Billrun_Factory::db()->linesCollection()->query(array(
																'sid'=> (int) $this->subscriberId, 
																'urt' => array(
																		'$gte' => $this->from,
																		'$lte' => $this->to,
																	)
													))->cursor();
		
		
	}

}
