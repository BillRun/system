<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
class Billrun_ActionManagers_Realtime_Responder_Call_HealthcheckIn extends Billrun_ActionManagers_Realtime_Responder_Base {

	public function getResponsApiName() {
		return 'healthcheck_in';
	}
	
	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(), Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.call.$this->responseApiName", array()));
	}
}
