<?php

/**
 * Response to MMS request
 */
class Billrun_ActionManagers_Realtime_Responder_Mms_Mms extends Billrun_ActionManagers_Realtime_Responder_Base {
	
	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.mms.basic", array()),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.mms.$this->responseApiName", array()));
	}

	public function getResponsApiName() {
		return 'mms';
	}

}
