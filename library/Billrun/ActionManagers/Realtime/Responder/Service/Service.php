<?php

/**
 * Response to AnswerCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Service_Service extends Billrun_ActionManagers_Realtime_Responder_Base {
	
	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.service.basic", array()),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.service.$this->responseApiName", array()));
	}

	public function getResponsApiName() {
		return 'service';
	}

}
