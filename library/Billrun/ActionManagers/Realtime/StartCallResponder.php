<?php

 class Billrun_ActionManagers_Realtime_StartCallResponder extends Billrun_ActionManagers_Realtime_Responder {
	public function getResponse() {
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $this->row['grantedReturnCode'],
			'CallReservationTime' => $this->row['usagev'],
			'ConnectToNumber' => '',
			'FreeCallAck' => (isset($this->row['FreeCall']) && $this->row['FreeCall']),
			'ClearCause' => '',
		);
	}

}