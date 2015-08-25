<?php

class Billrun_ActionManagers_Realtime_Call_ReleaseCallResponder extends Billrun_ActionManagers_Realtime_Call_Responder {

	/**
	 * Returns balance leftovers to the current balance (if were taken due to prepaid mechanism)
	 */
	protected function rebalance() {
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$query = array(
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
		$rebalanceUsagev = $this->row['duration'] - $lines_coll->aggregate($query)[0]['sum'];
		if ($rebalanceUsagev < 0) {
			$rebalanceRow = new Mongodloid_Entity($this->row);
			unset($rebalanceRow['_id']);
			$rebalanceRow['prepaid_rebalance'] = true;
			$rebalanceRow['usagev'] = $rebalanceUsagev;
			$customerPricingCalc = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
			$rate = $customerPricingCalc->getRowRate($rebalanceRow);
			$customerPricingCalc->updateSubscriberBalance($rebalanceRow, $rebalanceRow['usaget'], $rate);
		}
	}

	public function getResponse() {
		$this->rebalance();
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $this->row['grantedReturnCode'],
		);
	}

}
