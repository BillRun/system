<?php

/**
 * Response to ReleaseCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_ReleaseCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	public function getResponsApiName() {
		return 'release_call';
	}

	public function isRebalanceRequired() {
		return true;
	}
	
	/**
	 * Gets the real usagev of the user (known only on the next API call)
	 * Given in 10th of a second
	 * 
	 * @return type
	 */
	protected function getRealUsagev() {
		$duration = (!empty($this->row['duration']) ? $this->row['duration'] : 0);
		return $duration / 10;
	}

	/**
	 * Gets a query to find amount of balance (usagev) calculated for a prepaid call
	 * 
	 * @return array
	 */
	protected function getRebalanceQuery() {
		return array(
			array(
				'$match' => array(
					"call_reference" => $this->row['call_reference']
				)
			),
			array(
				'$group' => array(
					'_id' => '$call_reference',
					'sum' => array('$sum' => '$usagev')
				)
			)
		);
	}
	
	/**
	 * Gets the Line that needs to be updated (on rebalance)
	 */	
	protected function getLineToUpdate() {
		$findQuery = array(
			"sid" => $this->row['sid'],
			"call_reference" => $this->row['call_reference'],
			"api_name" => array('$ne' => "release_call"),
		);
		
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$line = $lines_coll->query($findQuery)->cursor()->sort(array('_id' => -1))->limit(1);
		return $line;
	}
	
	/**
	 * See Billrun_ActionManagers_Realtime_Responder_Base->getChargedUsagev description
	 */
	protected function getChargedUsagev($lineToRebalance) {
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$query = $this->getRebalanceQuery();
		$line = $lines_coll->aggregate($query)->current();
		return $line['sum'];
	}
	
	protected function getClearCause() {
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch($this->row['granted_return_code']) {
				case ($returnCodes['no_subscriber']):
					return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.inactive_account');
			} 
		}

		return "";
	}
	
	protected function getReturnCode() {
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch($this->row['granted_return_code']) {
				case ($returnCodes['no_subscriber']):
					return Billrun_Factory::config()->getConfigValue("realtimeevent.returnCode.call_not_allowed");
			} 
		}
		
		return Billrun_Factory::config()->getConfigValue("realtimeevent.returnCode.call_allowed");
	}

}
