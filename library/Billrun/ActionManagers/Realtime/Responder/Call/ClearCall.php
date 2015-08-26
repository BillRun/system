<?php

 class Billrun_ActionManagers_Realtime_Responder_Call_ClearCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {
	public function getResponseData() {
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'TimeDate' => $this->row['time_date'],
			'TimeZone' => $this->row['time_zone'],
			'ClearCause' => ($this->row['grantedReturnCode'] === 0 ? 1 : 0), //TODO: check if it's correct value
		);
	}

}