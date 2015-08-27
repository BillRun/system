<?php

/**
 * Response to ReservationTime request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_ReservationTime extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	public function getResponseData() {
		$grantedReturnCode = $this->row['granted_return_code'];
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $grantedReturnCode,
			'CallReservationTime' => $this->row['usagev'],
			'ClearCause' => ($grantedReturnCode === 0 ? 1 : 0), //TODO: check if it's correct value
		);
	}

}
