<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CycleAggregatePipeline
 *
 * @author eran
 */
class Billrun_Cycle_Aggregation_CustomerRemote {
	use Billrun_Cycle_Aggregation_Common;
	
	/**
	 * Aggregate mongo with a query
	 * @param Billrun_DataTypes_MongoCycleTime $cycle - Current cycle time
	 * @param int $page - page
	 * @param int $size - size
	 * @param int $aids - Account ids, null by deafault
	 * @return array 
	 */
	public function getCustomerAggregationForPage($cycle, $page, $size, $aids = null, $invoicing_days = null) {
		if (is_null($page)) {
			$page = 0;
		}

		if (empty($size)) {
			$size = 100;
		}

		$result = Billrun_Factory::account()->getBillable($cycle, $page, $size, $aids, $invoicing_days);
		$billableResults = $this->filterConfirmedAccounts($result['data'], $cycle);
		usort($billableResults, function($a, $b){ return strcmp($a['from'],$b['from']);});
		$retResults = [];
		$customIDFields =Billrun_Factory::config()->getConfigValue('customer.aggregator.revision_identification_fields',[]);
		$idFields = array_merge($customIDFields, ['aid','sid','plan','play','first_name','last_name','type','email','address','services']);
		foreach($billableResults as $revision) {
			if(!in_array($revision['aid'],$this->exclusionQuery)) {
				$revStamp = @Billrun_Util::generateArrayStamp($revision, $idFields);
				if(empty($retResults[$revStamp])) {
					$retResults[$revStamp] = [];
				}
				if(empty($this->generalOptions['is_onetime_invoice'])) {
				if(!empty($revision['plan'])) {
					$planDate = [
						'from' => $revision['from'],
						'to' => $revision['to'],
					];

						$planDate['plan'] = $revision['plan'];
						$planDate['plan_activation'] = @$revision['plan_activation'];
						$planDate['plan_deactivation'] = @$revision['plan_deactivation'];
						foreach($customIDFields as $CIDF) {
							 if(!empty($revision[$CIDF]) ) {
								 $planDate[$CIDF] = $revision[$CIDF];
							 }
						}

					$retResults[$revStamp]['plan_dates'][] = $planDate;
				} else {
					$retResults[$revStamp]['plan_dates'][] = [
						'from' => $revision['from'],
						'to' => $revision['to']
					];
				}
				}
				$retResults[$revStamp]['id'] = array_filter($revision, function ($key) use ($idFields) { return in_array($key, $idFields); }, ARRAY_FILTER_USE_KEY);
				$passthroughFields = ($revision['type'] == 'account') ? $this->passthroughFields : $this->subsPassthroughFields;
				foreach ($passthroughFields as $passthroughField) {
					if(isset($revision[$passthroughField])) {
						$retResults[$revStamp]['passthrough'][$passthroughField] = $revision[$passthroughField];
					}
				}
			}
		}

		usort($retResults, function($a, $b){ return @$a['from'] - @$b['from'];});
		//usort($retResults, function($a, $b){ return $a['from']->sec - $b['from']->sec;});
		return ["data" => array_map(function($item){ return new Mongodloid_Entity($item);}, array_values($retResults)), "options" => $result['options']];

	}
	
	public function filterConfirmedAccounts($billableResults, $mongoCycle) {
		$confirmedAids = $this->getConfirmedAids($mongoCycle);
		return array_filter($billableResults, function($billableAccount) use($confirmedAids) {
			return !in_array($billableAccount['aid'], $confirmedAids);
		});
	}
	
	//--------------------------------------------------------------

}
