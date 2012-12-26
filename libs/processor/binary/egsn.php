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
			'Served_IMSI' => array('BCDencode'=> array(1)),
			'ggsn_address' =>array('ip' => array(2,0)),
			'charging_id' => array('number' => array(3)),
			'sgsn_address' =>array('ip' => array(4,0)),
			'apnni' => array('string'=> array(5)),
			'pdp_type' => array('C' => array(6)),
			'served_pdp_address' => array('ip'=> array(7,0,0)),
			'dynamic_address_flag' => array('C'=> array(8)),
			'record_opening_time' => array('datetime'=> array(9)),
			'duration' => array('C*'=> array(10)),
			'cause_for_record_closing' => array('C'=> array(11)),
			'diagnostics' => array('C*'=> array(12,0)),
			'record_sequence_number' => array('L*'=> array(13)),
			'node_id' => array('string'=> array(13)),
			'local_sequence_number' => array('number'=> array(14)),
			'apn_selection_mode' => array('C*'=> array(15)),
			'Served_msisdn'	=>	array('C*'=> array(16)),
			'charging_characteristics'	=>	array('C*'=> array(17)),
			'charging_characteristics_selection_mode'	=>	array('C*'=> array(18,0,0)),
			'sgsn_plmn_id'	=>	array('number'=> array(19)),
			'served_imeisv'	=>	array('BCDencode'=> array(24,0,6)),
			'rat_type'	=>	array('H*'=> array(24,0,1)),
			'ms_timezone'	=>	array('datetime'=> array(24,0,2)),
			'user_location_information'	=>	array('datetime'=> array(24,0,3)),
			'list_of_service_data'	=>	array('C*'=> array(24,0,4)),
			'record_extensions'	=>	array('C*'=> array(24)),
		);
	}

}