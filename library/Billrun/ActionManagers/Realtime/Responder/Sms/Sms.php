<?php

/**
 * Response to AnswerCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Sms_Sms extends Billrun_ActionManagers_Realtime_Responder_Base {
	
	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.sms.basic", array()),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.sms.$this->responseApiName", array()));
	}

	public function getResponsApiName() {
		return 'sms';
	}
	
	protected function getErrorCode() {
		if ($this->row['usagev'] === 0) {
			return Billrun_Factory::config()->getConfigValue("realtime_error_base") + 2;
		}

		return parent::getErrorCode();
	}

	protected function getStatus() {
		if ($this->row['usagev'] === 0) {
			return 0;
		}

		return parent::getStatus();
	}

	protected function getDesc() {
		if ($this->row['usagev'] === 0) {
			return 0;
		}

		return parent::getDesc();
	}
	
	protected function getStatus() {
		if ($this->row['usagev'] === 0) {
			return "Not enough balance";
		}

		return parent::getStatus();
	}
}
