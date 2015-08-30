<?php

/**
 * Response to ReleaseCall request
 */
class Billrun_ActionManagers_Realtime_Responder_Call_ReleaseCall extends Billrun_ActionManagers_Realtime_Responder_Call_Base {

	/**
	 * Returns balance leftovers to the current balance (if were taken due to prepaid mechanism)
	 */
	protected function rebalance() {
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$query = $this->getRebalanceQuery();
		$rebalanceUsagev = $this->row['duration'] - $lines_coll->aggregate($query)[0]['sum'];
		if ($rebalanceUsagev < 0) {
			$this->handleRebalanceRequired($rebalanceUsagev);
		}
	}

	/**
	 * In case balance is in over charge (due to prepaid mechanism), 
	 * adds a refund row to the balance.
	 * 
	 * @param type $rebalanceUsagev amount of balance (usagev) to return to the balance
	 */
	protected function handleRebalanceRequired($rebalanceUsagev) {
		$rebalanceRow = new Mongodloid_Entity($this->row);
		unset($rebalanceRow['_id']);
		$rebalanceRow['prepaid_rebalance'] = true;
		$rebalanceRow['usagev'] = $rebalanceUsagev;
		$customerPricingCalc = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		$rate = $customerPricingCalc->getRowRate($rebalanceRow);
		$customerPricingCalc->updateSubscriberBalance($rebalanceRow, $rebalanceRow['usaget'], $rate);
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

	public function getResponse() {
		$this->rebalance();
		parent::getResponse();
	}

	public function getResponseData() {
		$ret = $this->getResponseBasicData();
		unset($ret['ClearCause']);
		return $ret;
	}

}
