<?php

 class Billrun_ActionManagers_Realtime_AnswerCallResponder extends Billrun_ActionManagers_Realtime_Responder {
	public function getResponse() {
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $this->row['grantedReturnCode'],
			'ClearCause' => '',
		);
	}

}