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
		$additional = isset($params['request']['additional']) ? @json_decode($params['request']['additional'], TRUE) : array();
		list($this->query, $this->update) = $this->validateRequest($query, $update, $this->action, $this->config[$this->action], 999999);
		$this->additional = $this->validateAdditionalData($additional);
	}

	public function update() {
		$className = 'Billrun_Balance_Update_';
		if (isset($this->query['pp_includes_external_id']) || isset($this->query['pp_includes_external_name']) || isset($this->query['id']) || isset($this->query['_id'])) {
			$className .= 'Prepaidinclude';
			// load pp includes update balance
		} else if (isset($this->query['charging_plan']) || isset($this->query['charging_plan_name'])) {
			$className .= 'Chargingplan';
			// load charging plan update balance
		} else if (isset($this->query['secret'])) {
			$className .= 'Secret';
		} else {
			// throw an error
		}
		$params = array_merge($this->query, $this->update);
		if (!empty($this->additional)) {
			$params['additional'] = $this->additional;
		}
		$action = new $className($params);
		$ret = $action->execute();
		$this->after = $action->getAfter();
		$this->line = $action->getAffectedLine();
		return $ret;
	}

}
