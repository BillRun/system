<?php

 class Billrun_ActionManagers_Realtime_Call_ReservationTimeResponder extends Billrun_ActionManagers_Realtime_Call_Responder {
	public function getResponseData() {
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $this->row['grantedReturnCode'],
			'CallReservationTime' => $this->row['usagev'],
			'ClearCause' => ($this->row['grantedReturnCode'] === 0 ? 1 : 0), //TODO: check if it's correct value
		);
	}

}