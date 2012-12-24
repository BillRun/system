<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 012 class
 *
 * @package  Billing
 * @since    1.0
 */
class processor_binary_egsn extends processor_binary {

	protected $type = 'egsn';

	public function __construct($options) {
		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => array('C' => array(0)),
			'Served_IMSI' => array('imsi'=> array(1)),
			'ggsn_address' =>array('ip' => array(2,0)),
			'charging_id' => array('I' => array(2,0)),
			'sgsn_address' =>array('ip' => array(4,0)),
			'apnni' => array('string'=> array(5)),
			'pdp_type' => array('C' => array(6)),
			'served_pdp_address' => array('ip'=> array(7,0,0)),
			'dynamic_address_flag' => array('string'=> array(8)),
			'record_opening_time' => array('L'=> array(9)),
			'duration' => array('C*'=> array(10)),
			'cause_for_record_closing' => array('string'=> array(11)),
			'diagnostics' => array('ttr'=> array(12,0)),
			'record_sequence_number' => array('L*'=> array(13)),
			'node_id' => array('string'=> array(14)),
			'local_sequence_number' => array('L*'=> array(15)),
			'apn_selection_mode' => array('C*'=> array(16)),
			'record_sequence_number' => array('L*'=> array(17)),
		);
	}

}