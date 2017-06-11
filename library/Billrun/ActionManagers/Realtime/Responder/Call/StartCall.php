<?php

/**
 * Response to StartCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_StartCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	public function getResponsApiName() {
		return 'start_call';
	}

	/**
	 * Gets the real dialed number
	 * @todo implement (maybe should be calculated during billing proccess)
	 * 
	 * @return the connect to number
	 */
	protected function getConnectToNumber() {
		return $this->row['connected_number'];
	}

	/**
	 * Gets acknowledge for freeCall request
	 * 
	 * @return int
	 */
	protected function getFreeCallAck() {
		return (isset($this->row['FreeCall']) && $this->row['FreeCall'] ? 1 : 0);
	}

	protected function getReservationTime() {
		if (isset($this->row['granted_return_code']) &&
			$this->row['granted_return_code'] !== Billrun_Factory::config()->getConfigValue('realtime.granted_code.ok', '')) {
			return 0;
		}
		return Billrun_Factory::config()->getConfigValue('realtimeevent.callReservationTime.default', 180) * 10;
	}

}
