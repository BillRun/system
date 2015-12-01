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
	 * 
	 * @return type
	 */
	protected function getRealUsagev() {
		return $this->row['duration'];
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
	 * Gets the Line that needs to be updated
	 * @todo Implement function when we will have spec
	 */	
	protected function getLineToUpdate() {
		return null;
	}

}
