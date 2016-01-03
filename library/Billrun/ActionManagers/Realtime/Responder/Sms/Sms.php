<?php

/**
 * Response to AnswerCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Sms_Sms extends Billrun_ActionManagers_Realtime_Responder_Base {
	
	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.sms.basic", array()),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.sms.$this->responseApiName", array()));
	}

	public function getResponsApiName() {
		return 'sms';
	}

}
