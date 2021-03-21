<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for auto-renew
 *
 * @package         Tests
 * @subpackage      Auto-renew
 * @since           4.4
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

class Tests_Wallet extends UnitTestCase {
	
	protected $prepaid_record = array("pp_includes_external_id" => 1, "pp_includes_name" => "CORE BALANCE");
	
    protected $tests = array(
//			// PLANS - Call
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("usagev" => '0')), 'value' =>0, 'field_name' => 'call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("cost" => '0')), 'value' => 0, 'field_name' => 'call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("usagev" => '1')), 'value' =>1, 'field_name' => 'call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("cost" => '1')), 'value' => 1, 'field_name' => 'call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("usagev" => '4.9')), 'value' => 4.9, 'field_name' => 'call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("cost" => '4.9')), 'value' => 4.9, 'field_name' => 'call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("usagev" => '100')), 'value' => 100, 'field_name' => 'call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("cost" => '100')), 'value' => 100, 'field_name' => 'call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("usagev" => '-1')), 'value' => -1, 'field_name' => 'call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("cost" => '-1')), 'value' => -1, 'field_name' => 'call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("usagev" => '-4.9')), 'value' => -4.9, 'field_name' => 'call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("cost" => '-4.9')), 'value' => -4.9, 'field_name' => 'call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("usagev" => '-100')), 'value' => -100, 'field_name' => 'call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("call" => array("cost" => '-100')), 'value' => -100, 'field_name' => 'call' ), //00
//			
//			// PLANS - SMS
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("usagev" => '0')), 'value' =>0, 'field_name' => 'sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("cost" => '0')), 'value' => 0, 'field_name' => 'sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("usagev" => '1')), 'value' =>1, 'field_name' => 'sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("cost" => '1')), 'value' => 1, 'field_name' => 'sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("usagev" => '4.9')), 'value' => 4.9, 'field_name' => 'sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("cost" => '4.9')), 'value' => 4.9, 'field_name' => 'sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("usagev" => '100')), 'value' => 100, 'field_name' => 'sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("cost" => '100')), 'value' => 100, 'field_name' => 'sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("usagev" => '-1')), 'value' => -1, 'field_name' => 'sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("cost" => '-1')), 'value' => -1, 'field_name' => 'sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("usagev" => '-4.9')), 'value' => -4.9, 'field_name' => 'sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("cost" => '-4.9')), 'value' => -4.9, 'field_name' => 'sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("usagev" => '-100')), 'value' => -100, 'field_name' => 'sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("sms" => array("cost" => '-100')), 'value' => -100, 'field_name' => 'sms' ), //00
//			
//			// PLANS - DATA
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("usagev" => '0')), 'value' =>0, 'field_name' => 'data' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("cost" => '0')), 'value' => 0, 'field_name' => 'data' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("usagev" => '1')), 'value' =>1, 'field_name' => 'data' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("cost" => '1')), 'value' => 1, 'field_name' => 'data' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("usagev" => '4.9')), 'value' => 4.9, 'field_name' => 'data' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("cost" => '4.9')), 'value' => 4.9, 'field_name' => 'data' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("usagev" => '100')), 'value' => 100, 'field_name' => 'data' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("cost" => '100')), 'value' => 100, 'field_name' => 'data' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("usagev" => '-1')), 'value' => -1, 'field_name' => 'data' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("cost" => '-1')), 'value' => -1, 'field_name' => 'data' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("usagev" => '-4.9')), 'value' => -4.9, 'field_name' => 'data' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("cost" => '-4.9')), 'value' => -4.9, 'field_name' => 'data' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("usagev" => '-100')), 'value' => -100, 'field_name' => 'data' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("data" => array("cost" => '-100')), 'value' => -100, 'field_name' => 'data' ), //00
//			
//			// PLANS - incoming_call
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("usagev" => '0')), 'value' =>0, 'field_name' => 'incoming_call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("cost" => '0')), 'value' => 0, 'field_name' => 'incoming_call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("usagev" => '1')), 'value' =>1, 'field_name' => 'incoming_call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("cost" => '1')), 'value' => 1, 'field_name' => 'incoming_call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("usagev" => '4.9')), 'value' => 4.9, 'field_name' => 'incoming_call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("cost" => '4.9')), 'value' => 4.9, 'field_name' => 'incoming_call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("usagev" => '100')), 'value' => 100, 'field_name' => 'incoming_call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("cost" => '100')), 'value' => 100, 'field_name' => 'incoming_call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("usagev" => '-1')), 'value' => -1, 'field_name' => 'incoming_call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("cost" => '-1')), 'value' => -1, 'field_name' => 'incoming_call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("usagev" => '-4.9')), 'value' => -4.9, 'field_name' => 'incoming_call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("cost" => '-4.9')), 'value' => -4.9, 'field_name' => 'incoming_call' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("usagev" => '-100')), 'value' => -100, 'field_name' => 'incoming_call' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_call" => array("cost" => '-100')), 'value' => -100, 'field_name' => 'incoming_call' ), //00
//		
//			// PLANS - Incoming SMS
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("usagev" => '0')), 'value' =>0, 'field_name' => 'incoming_sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("cost" => '0')), 'value' => 0, 'field_name' => 'incoming_sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("usagev" => '1')), 'value' =>1, 'field_name' => 'incoming_sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("cost" => '1')), 'value' => 1, 'field_name' => 'incoming_sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("usagev" => '4.9')), 'value' => 4.9, 'field_name' => 'incoming_sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("cost" => '4.9')), 'value' => 4.9, 'field_name' => 'incoming_sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("usagev" => '100')), 'value' => 100, 'field_name' => 'incoming_sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("cost" => '100')), 'value' => 100, 'field_name' => 'incoming_sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("usagev" => '-1')), 'value' => -1, 'field_name' => 'incoming_sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("cost" => '-1')), 'value' => -1, 'field_name' => 'incoming_sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("usagev" => '-4.9')), 'value' => -4.9, 'field_name' => 'incoming_sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("cost" => '-4.9')), 'value' => -4.9, 'field_name' => 'incoming_sms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("usagev" => '-100')), 'value' => -100, 'field_name' => 'incoming_sms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("incoming_sms" => array("cost" => '-100')), 'value' => -100, 'field_name' => 'incoming_sms' ), //00
//			
//			// PLANS - MMS
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("usagev" => '0')), 'value' =>0, 'field_name' => 'mms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("cost" => '0')), 'value' => 0, 'field_name' => 'mms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("usagev" => '1')), 'value' =>1, 'field_name' => 'mms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("cost" => '1')), 'value' => 1, 'field_name' => 'mms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("usagev" => '4.9')), 'value' => 4.9, 'field_name' => 'mms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("cost" => '4.9')), 'value' => 4.9, 'field_name' => 'mms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("usagev" => '100')), 'value' => 100, 'field_name' => 'mms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("cost" => '100')), 'value' => 100, 'field_name' => 'mms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("usagev" => '-1')), 'value' => -1, 'field_name' => 'mms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("cost" => '-1')), 'value' => -1, 'field_name' => 'mms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("usagev" => '-4.9')), 'value' => -4.9, 'field_name' => 'mms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("cost" => '-4.9')), 'value' => -4.9, 'field_name' => 'mms' ), //00
//		
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("usagev" => '-100')), 'value' => -100, 'field_name' => 'mms' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => array("mms" => array("cost" => '-100')), 'value' => -100, 'field_name' => 'mms' ), //00
			//
			// PREPAID - MMS
            array('charging_by' => 'mms', 'charging_by_value' => array("usagev" => '0'), 'value' =>0, 'field_name' => 'mms' ), //00
            array('charging_by' => 'mms', 'charging_by_value' => array("cost" => '0'), 'value' => 0, 'field_name' => 'mms' ), //00
		
            array('charging_by' => 'mms', 'charging_by_value' => array("usagev" => '1'), 'value' =>1, 'field_name' => 'mms' ), //00
            array('charging_by' => 'mms', 'charging_by_value' => array("cost" => '1'), 'value' => 1, 'field_name' => 'mms' ), //00
		
            array('charging_by' => 'mms', 'charging_by_value' => array("usagev" => '4.9'), 'value' => 4.9, 'field_name' => 'mms' ), //00
            array('charging_by' => 'mms', 'charging_by_value' => array("cost" => '4.9'), 'value' => 4.9, 'field_name' => 'mms' ), //00
		
            array('charging_by' => 'mms', 'charging_by_value' => array("usagev" => '100'), 'value' => 100, 'field_name' => 'mms' ), //00
            array('charging_by' => 'mms', 'charging_by_value' => array("cost" => '100'), 'value' => 100, 'field_name' => 'mms' ), //00
		
            array('charging_by' => 'mms', 'charging_by_value' => array("usagev" => '-1'), 'value' => -1, 'field_name' => 'mms' ), //00
            array('charging_by' => 'mms', 'charging_by_value' => array("cost" => '-1'), 'value' => -1, 'field_name' => 'mms' ), //00
		
            array('charging_by' => 'mms', 'charging_by_value' => array("usagev" => '-4.9'), 'value' => -4.9, 'field_name' => 'mms' ), //00
            array('charging_by' => 'mms', 'charging_by_value' => array("cost" => '-4.9'), 'value' => -4.9, 'field_name' => 'mms' ), //00
		
            array('charging_by' => 'mms', 'charging_by_value' => array("usagev" => '-100'), 'value' => -100, 'field_name' => 'mms' ), //00
            array('charging_by' => 'mms', 'charging_by_value' => array("cost" => '-100'), 'value' => -100, 'field_name' => 'mms' ), //00
			//
			// PREPAID - data
            array('charging_by' => 'data', 'charging_by_value' => array("usagev" => '0'), 'value' =>0, 'field_name' => 'data' ), //00
            array('charging_by' => 'data', 'charging_by_value' => array("cost" => '0'), 'value' => 0, 'field_name' => 'data' ), //00
		
            array('charging_by' => 'data', 'charging_by_value' => array("usagev" => '1'), 'value' =>1, 'field_name' => 'data' ), //00
            array('charging_by' => 'data', 'charging_by_value' => array("cost" => '1'), 'value' => 1, 'field_name' => 'data' ), //00
		
            array('charging_by' => 'data', 'charging_by_value' => array("usagev" => '4.9'), 'value' => 4.9, 'field_name' => 'data' ), //00
            array('charging_by' => 'data', 'charging_by_value' => array("cost" => '4.9'), 'value' => 4.9, 'field_name' => 'data' ), //00
		
            array('charging_by' => 'data', 'charging_by_value' => array("usagev" => '100'), 'value' => 100, 'field_name' => 'data' ), //00
            array('charging_by' => 'data', 'charging_by_value' => array("cost" => '100'), 'value' => 100, 'field_name' => 'data' ), //00
		
            array('charging_by' => 'data', 'charging_by_value' => array("usagev" => '-1'), 'value' => -1, 'field_name' => 'data' ), //00
            array('charging_by' => 'data', 'charging_by_value' => array("cost" => '-1'), 'value' => -1, 'field_name' => 'data' ), //00
		
            array('charging_by' => 'data', 'charging_by_value' => array("usagev" => '-4.9'), 'value' => -4.9, 'field_name' => 'data' ), //00
            array('charging_by' => 'data', 'charging_by_value' => array("cost" => '-4.9'), 'value' => -4.9, 'field_name' => 'data' ), //00
		
            array('charging_by' => 'data', 'charging_by_value' => array("usagev" => '-100'), 'value' => -100, 'field_name' => 'data' ), //00
            array('charging_by' => 'data', 'charging_by_value' => array("cost" => '-100'), 'value' => -100, 'field_name' => 'data' ), //00
			//
			// PREPAID - sms
            array('charging_by' => 'sms', 'charging_by_value' => array("usagev" => '0'), 'value' =>0, 'field_name' => 'sms' ), //00
            array('charging_by' => 'sms', 'charging_by_value' => array("cost" => '0'), 'value' => 0, 'field_name' => 'sms' ), //00
		
            array('charging_by' => 'sms', 'charging_by_value' => array("usagev" => '1'), 'value' =>1, 'field_name' => 'sms' ), //00
            array('charging_by' => 'sms', 'charging_by_value' => array("cost" => '1'), 'value' => 1, 'field_name' => 'sms' ), //00
		
            array('charging_by' => 'sms', 'charging_by_value' => array("usagev" => '4.9'), 'value' => 4.9, 'field_name' => 'sms' ), //00
            array('charging_by' => 'sms', 'charging_by_value' => array("cost" => '4.9'), 'value' => 4.9, 'field_name' => 'sms' ), //00
		
            array('charging_by' => 'sms', 'charging_by_value' => array("usagev" => '100'), 'value' => 100, 'field_name' => 'sms' ), //00
            array('charging_by' => 'sms', 'charging_by_value' => array("cost" => '100'), 'value' => 100, 'field_name' => 'sms' ), //00
		
            array('charging_by' => 'sms', 'charging_by_value' => array("usagev" => '-1'), 'value' => -1, 'field_name' => 'sms' ), //00
            array('charging_by' => 'sms', 'charging_by_value' => array("cost" => '-1'), 'value' => -1, 'field_name' => 'sms' ), //00
		
            array('charging_by' => 'sms', 'charging_by_value' => array("usagev" => '-4.9'), 'value' => -4.9, 'field_name' => 'sms' ), //00
            array('charging_by' => 'sms', 'charging_by_value' => array("cost" => '-4.9'), 'value' => -4.9, 'field_name' => 'sms' ), //00
		
            array('charging_by' => 'sms', 'charging_by_value' => array("usagev" => '-100'), 'value' => -100, 'field_name' => 'sms' ), //00
            array('charging_by' => 'sms', 'charging_by_value' => array("cost" => '-100'), 'value' => -100, 'field_name' => 'sms' ), //00
			//
			// PREPAID - call
            array('charging_by' => 'call', 'charging_by_value' => array("usagev" => '0'), 'value' =>0, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => array("cost" => '0'), 'value' => 0, 'field_name' => 'call' ), //00
		
            array('charging_by' => 'call', 'charging_by_value' => array("usagev" => '1'), 'value' =>1, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => array("cost" => '1'), 'value' => 1, 'field_name' => 'call' ), //00
		
            array('charging_by' => 'call', 'charging_by_value' => array("usagev" => '4.9'), 'value' => 4.9, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => array("cost" => '4.9'), 'value' => 4.9, 'field_name' => 'call' ), //00
		
            array('charging_by' => 'call', 'charging_by_value' => array("usagev" => '100'), 'value' => 100, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => array("cost" => '100'), 'value' => 100, 'field_name' => 'call' ), //00
		
            array('charging_by' => 'call', 'charging_by_value' => array("usagev" => '-1'), 'value' => -1, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => array("cost" => '-1'), 'value' => -1, 'field_name' => 'call' ), //00
		
            array('charging_by' => 'call', 'charging_by_value' => array("usagev" => '-4.9'), 'value' => -4.9, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => array("cost" => '-4.9'), 'value' => -4.9, 'field_name' => 'call' ), //00
		
            array('charging_by' => 'call', 'charging_by_value' => array("usagev" => '-100'), 'value' => -100, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => array("cost" => '-100'), 'value' => -100, 'field_name' => 'call' ), //00
			//
			// PREPAID - total_cost
            array('charging_by' => 'total_cost', 'charging_by_value' => array("usagev" => '0'), 'value' =>0, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => array("cost" => '0'), 'value' => 0, 'field_name' => 'total_cost' ), //00
		
            array('charging_by' => 'total_cost', 'charging_by_value' => array("usagev" => '1'), 'value' =>1, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => array("cost" => '1'), 'value' => 1, 'field_name' => 'total_cost' ), //00
		
            array('charging_by' => 'total_cost', 'charging_by_value' => array("usagev" => '4.9'), 'value' => 4.9, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => array("cost" => '4.9'), 'value' => 4.9, 'field_name' => 'total_cost' ), //00
		
            array('charging_by' => 'total_cost', 'charging_by_value' => array("usagev" => '100'), 'value' => 100, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => array("cost" => '100'), 'value' => 100, 'field_name' => 'total_cost' ), //00
		
            array('charging_by' => 'total_cost', 'charging_by_value' => array("usagev" => '-1'), 'value' => -1, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => array("cost" => '-1'), 'value' => -1, 'field_name' => 'total_cost' ), //00
		
            array('charging_by' => 'total_cost', 'charging_by_value' => array("usagev" => '-4.9'), 'value' => -4.9, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => array("cost" => '-4.9'), 'value' => -4.9, 'field_name' => 'total_cost' ), //00
		
            array('charging_by' => 'total_cost', 'charging_by_value' => array("usagev" => '-100'), 'value' => -100, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => array("cost" => '-100'), 'value' => -100, 'field_name' => 'total_cost' ), //00
		
            array('charging_by' => 'total_cost', 'charging_by_value' => 0, 'value' =>0, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => 1,'value' =>1, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => 4.9, 'value' => 4.9, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => 100, 'value' => 100, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => -1, 'value' => -1, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => -4.9, 'value' => -4.9, 'field_name' => 'total_cost' ), //00
            array('charging_by' => 'total_cost', 'charging_by_value' => -100, 'value' => -100, 'field_name' => 'total_cost' ), //00
		
//            array('charging_by' => 'usagev', 'charging_by_value' => '4.9', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => '100', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => '-1', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => '-4.9','value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'usagev', 'charging_by_value' => '-100','value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//		
//            array('charging_by' => 'cost', 'charging_by_value' => '1', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'cost', 'charging_by_value' => '4.9', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'cost', 'charging_by_value' => '100', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'cost', 'charging_by_value' => '-1', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'cost', 'charging_by_value' => '-4.9', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'cost', 'charging_by_value' => '-100', 'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//		
//            array('charging_by' => 'total_cost', 'charging_by_value' => '1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '-1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '-4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '-100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
		
			// PREPAID INCLUDES
            array('charging_by' => 'call', 'charging_by_value' => 1, 'value' => 1, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => 4.9,'value' => 4.9, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => 100,'value' => 100, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => -1, 'value' => -1, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => -4.9, 'value' => -4.9, 'field_name' => 'call' ), //00
            array('charging_by' => 'call', 'charging_by_value' => -100, 'value' => -100, 'field_name' => 'call' ), //00
		
//            array('charging_by' => 'sms', 'charging_by_value' => '1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'sms', 'charging_by_value' => '4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'sms', 'charging_by_value' => '100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'sms', 'charging_by_value' => '-1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'sms', 'charging_by_value' => '-4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'sms', 'charging_by_value' => '-100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//		
//            array('charging_by' => 'data', 'charging_by_value' => '1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'data', 'charging_by_value' => '4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'data', 'charging_by_value' => '100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'data', 'charging_by_value' => '-1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'data', 'charging_by_value' => '-4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'data', 'charging_by_value' => '-100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//		
//            array('charging_by' => 'mms', 'charging_by_value' => '1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'mms', 'charging_by_value' => '4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'mms', 'charging_by_value' => '100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'mms', 'charging_by_value' => '-1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'mms', 'charging_by_value' => '-4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'mms', 'charging_by_value' => '-100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//		
//            array('charging_by' => 'total_cost', 'charging_by_value' => '1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '-1', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '-4.9', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
//            array('charging_by' => 'total_cost', 'charging_by_value' => '-100', 'pp_pair' => 1,  'value' => '2016-01-29 00:00:01', 'field_name' => '2016-02-10 00:00:00' ), //00
    );

    function testConstructWallet() {
		$testCase = 0;
		foreach ($this->tests as $test) {
			$result = new Billrun_DataTypes_Wallet($test['charging_by'], $test['charging_by_value'], $this->prepaid_record);
			
			$this->assertEqual($result->getValue(), $test['value'], '[' . $testCase . '] Value mismatch');
			$this->assertEqual($result->getChargingByUsaget(), $test['field_name'], '[' . $testCase . '] Fieldname mismatch:' . $result->getChargingByUsaget() . " - " . $test['field_name']);
			
			$testCase++;
		}
    }
	
}