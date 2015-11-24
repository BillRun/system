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
abstract class Billrun_ActionManagers_Realtime_Responder_Data_Base extends Billrun_ActionManagers_Realtime_Responder_Base {

	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.data.basic", array()),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.data.$this->responseApiName", array()));
	}
}
