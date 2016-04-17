<?php

/**
 * Response to AnswerCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Data_FinalRequest extends Billrun_ActionManagers_Realtime_Responder_Data_Base {

	public function getResponsApiName() {
		return 'final_request';
	}

	protected function getReturnCode() {
		return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
	}

}
