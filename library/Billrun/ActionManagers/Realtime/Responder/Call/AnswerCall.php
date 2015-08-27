<?php

/**
 * Response to AnswerCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_AnswerCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	public function getResponseData() {
		$grantedReturnCode = $this->row['grantedReturnCode'];
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $grantedReturnCode,
			'ClearCause' => ($grantedReturnCode === 0 ? 1 : 0), //TODO: check if it's correct value
		);
	}

}
