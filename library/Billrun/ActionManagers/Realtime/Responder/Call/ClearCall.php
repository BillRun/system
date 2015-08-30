<?php

/**
 * Response to ClearCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_ClearCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	public function getResponseData() {
		$ret = $this->getResponseBasicData();
		unset($ret['ReturnCode']);
		$ret['TimeDate'] = $this->row['time_date'];
		$ret['TimeZone'] = $this->row['time_zone'];
		return $ret;
	}

}
