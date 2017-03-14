<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi balances model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Balances extends Models_Entity {
	
	public function __construct($params) {
		parent::__construct($params);
	}
	
	protected function init($params) {
		$query = isset($params['request']['query']) ? @json_decode($params['request']['query'], TRUE) : array();
		$update = isset($params['request']['update']) ? @json_decode($params['request']['update'], TRUE) : array();
		list($this->query, $this->update) = $this->validateRequest($query, $update, $this->action, $this->config[$this->action], 999999);
		if (isset($this->query['charging_plan_name'])) {
			Billrun_Factory::log(print_R($params['request']['query']['charging_plan_name'], 1));
		} else {
			
		}
//		parent::init($params);
	}
	
	public function update() {
		$className = 'Models_Action_Update_Balance_';
		if (isset($this->query['pp_includes_external_id']) || isset($this->query['pp_includes_external_name']) 
			|| isset($this->query['id']) || isset($this->query['_id'])) {
			$className .= 'Prepaidinclude';
			// load pp includes update balance
		} else if (isset($this->query['charging_plan'])) {
			$className .= 'Chargingplan';
			// load charging plan update balance
		} else if (isset($this->query['secret'])) {
			$className .= 'Secret';
		} else {
			// throw an error
		}
		$params = array_merge($this->query, $this->update);
		$action = new $className($params);
		$ret = $action->execute();
		return $ret;
	}
	
}
