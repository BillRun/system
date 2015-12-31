<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing u-test controller class
 *
 * @package  Controller
 * @since    0.5
 */
class TestController extends Yaf_Controller_Abstract {

	/**
	 * use for page title
	 *
	 * @var string
	 */

	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		Billrun_Factory::log('Start Unit testing');
		if (Billrun_Factory::config()->isProd()) {
			Billrun_Factory::log('Exit Unit testing. Unit testing not allowed on production');
			die();
		}
	}



	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function indexAction() {
		$type = (string)filter_input(INPUT_GET, 'type');
		if(!empty($type)){
			$function = $type . 'Action';
			$this->{$function}();
			return;
		}
	}
	
	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function dataAction() {
		var_dump($r);
		echo 'data controller';
	}
	
	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function callAction() {
		var_dump($r);
		echo 'data controller';
	}
	
	protected function sendRequest($data = array()){
		$data['XDEBUG_SESSION_START'] = 'netbeans-xdebug';
//		$query['usaget'] = $type;
//		$query['request'] = $data;
		$params = http_build_query ( $data );
		$api_url = 'http://billrun/api/realtimeevent';
		$URL = $api_url . '?' . $params;
		$ch = curl_init($URL);
	}


}
