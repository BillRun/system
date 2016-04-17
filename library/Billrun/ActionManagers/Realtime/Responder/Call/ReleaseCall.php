<?php

/**
 * Response to ReleaseCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_ReleaseCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	public function getResponsApiName() {
		return 'release_call';
	}

	protected function getClearCause() {
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch ($this->row['granted_return_code']) {
				case ($returnCodes['no_subscriber']):
					return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.inactive_account');
			}
		}

		return "";
	}

	protected function getReturnCode() {
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch ($this->row['granted_return_code']) {
				case ($returnCodes['no_subscriber']):
					return Billrun_Factory::config()->getConfigValue("realtimeevent.returnCode.call_not_allowed");
			}
		}

		return Billrun_Factory::config()->getConfigValue("realtimeevent.returnCode.call_allowed");
	}

}
