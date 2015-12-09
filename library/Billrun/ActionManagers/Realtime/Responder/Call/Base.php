<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
abstract class Billrun_ActionManagers_Realtime_Responder_Call_Base extends Billrun_ActionManagers_Realtime_Responder_Base {

	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.call.basic", array()),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.call.$this->responseApiName", array()));
	}

	/**
	 * Gets the clear casue value, based on $this->row data
	 * 
	 * @return int clear cause value
	 */
	protected function getClearCause() {
		if ($this->row['granted_return_code'] === 0) {
			return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.no_balance');
		}

		return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.normal_release');
	}

}
