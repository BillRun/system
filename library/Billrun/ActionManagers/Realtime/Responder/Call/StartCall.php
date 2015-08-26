<?php

 class Billrun_ActionManagers_Realtime_Responder_Call_StartCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {
	public function getResponseData() {
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $this->row['grantedReturnCode'],
			'CallReservationTime' => $this->row['usagev'],
			'ConnectToNumber' => $this->getConnectToNumber(),
			'FreeCallAck' => (isset($this->row['FreeCall']) && $this->row['FreeCall'] ? 1 : 0),
			'ClearCause' => ($this->row['grantedReturnCode'] === 0 ? 1 : 0), //TODO: check if it's correct value
		);
	}
	
	/**
	 * Get's the real dialed number
	 * @todo implement (maybe should be calculated during billing proccess)
	 * 
	 * @return the connect to number
	 */
	protected function getConnectToNumber() {
		return "9999999999";
	}

}