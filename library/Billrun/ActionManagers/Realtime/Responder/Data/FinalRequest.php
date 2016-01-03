<?php

/**
 * Response to AnswerCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Data_FinalRequest extends Billrun_ActionManagers_Realtime_Responder_Data_Base {

	public function getResponsApiName() {
		return 'final_request';
	}
	
	public function isRebalanceRequired() {
		return true;
	}

}
