<?php

/**
 * Response to AnswerCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_AnswerCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	public function getResponseData() {
		$ret = $this->getResponseBasicData();
		return $ret;
	}

}
