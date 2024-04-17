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
class Billrun_Cycle_Aggregation_CustomerDb {
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
		$billableResults = $this->filterConfirmedAccounts($result, $cycle);
		usort($billableResults, function($a, $b){ return strcmp($a['from'],$b['from']);});
		$retResults = [];
		$customIDFields =Billrun_Factory::config()->getConfigValue('customer.aggregator.revision_identification_fields',[]);
		$idFields = array_merge($customIDFields, ['aid','sid','plan','play','first_name','last_name','type','email','address','services']);
		foreach($billableResults as $revision) {
			$revision = $revision instanceof Mongodloid_Entity ? $revision->getRawData() : $revision;
			$fieldMapping = ['firstname' => 'first_name', 'lastname' => 'last_name'];
			foreach($fieldMapping as $srcField => $dstField) {
				if(isset($revision[$srcField])) {
					$revision[$dstField] = $revision[$srcField];
				}
			}
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
		return ["data" => array_map(function($item){ return new Mongodloid_Entity($item);}, array_values($retResults)), "options" => Billrun_Factory::config()->getConfigValue("customer.aggregator.options", [])];
	}

	//--------------------------------------------------------------------------------------------

	public function filterConfirmedAccounts($billableResults, $mongoCycle) {
		$confirmedAids = $this->getConfirmedAids($mongoCycle);
		return array_filter(iterator_to_array($billableResults), function($billableAccount) use($confirmedAids) {
			return !in_array($billableAccount['aid'], $confirmedAids);
		});
	}

	/**
	 * Retrive the final projection of the  aggregate.
	 */
	protected function getFinalProject($addedPassthroughFields) {
		return empty($this->generalOptions['is_onetime_invoice']) ?
			[
				'$project' => [
					'_id' => 0,
					'id' => '$_id',
					'plan_dates' => 1,
					'card_token' => 1,
					'passthrough' => $addedPassthroughFields['project'],
				]
			] :
			[
				'$project' => [
					'_id' => 0,
					'id' => '$_id',
					'card_token' => 1,
					'passthrough' => $addedPassthroughFields['project'],
				]
			];
	}

	/**
	 *
	 */
	protected function getSortPipeline() {
		return array(
			'$sort' => array(
				'_id.aid' => 1,
				'_id.sid' => 1,
				'_id.type' => -1,
				'_id.plan' => 1,
				'plan_dates.from' => 1,
			),
		);
	}

	/**
	 * get the main  aggreation and paging logic query that is to be sent to the DB
	 */
	protected function getCycleAggregationPipeline($addedPassthroughFields, $page, $size, $invoicing_days = null) {
		$customIDFields = $this->getCustomIDFieldsGrouping();
		$pipelines[] = array(
			'$group' => array_merge($addedPassthroughFields['group'],array(
				'_id' => array(
					'aid' => '$aid',
				),
				'sub_plans' => array(
					'$push' => array_merge($customIDFields['sub_push'],$addedPassthroughFields['sub_push'],array(
						'type' => '$type',
						'sid' => '$sid',
						'plan' => '$plan',
						'play' => '$play',
						'from' => '$from',
						'to' => '$to',
						'plan_activation' => '$plan_activation',
						'plan_deactivation' => '$plan_deactivation',
						'first_name' => '$firstname',
						'last_name' => '$lastname',
						'email' => '$email',
						'address' => '$address',
						'services' => '$services'
					)),
				),
				'card_token' => array(
					'$first' => '$card_token'
				),
				'invoicing_day' => array(
					'$first' => '$invoicing_day'
				)
			)),
		);
		if (!empty($invoicing_days)) {
			$config = Billrun_Factory::config();
			/*if one of the searched "invoicing_day" is the default one, then we'll search for all the accounts with "invoicing_day"
			field that is different from all the undeclared invoicing_days. */
			if (in_array(strval($config->getConfigChargingDay()), $invoicing_days)) {
				$nin = array_diff(array_map('strval', range(1, 28)), $invoicing_days);
				$pipelines[] = array(
					'$match' => [
						'invoicing_day' => ['$nin' => array_values($nin)]
					]
				);
			} else {
				$pipelines[] = array(
					'$match' => [
						'invoicing_day' => ['$in' => $invoicing_days]
					]
				);
			}
		}
		$pipelines[] = array(
			'$sort' => array(
				'_id.aid' => 1
			)
		);
		$pipelines[] = array(
			'$skip' => $page * $size,
		);
		$pipelines[] = array(
			'$limit' => intval($size),
		);
		
		// If the accounts should not be overriden, filter the existing ones before.
		if ($this->exclusionQuery) {
			$pipelines[] = ['$match' => ['aid' => $this->exclusionQuery ] ];
		}
		
		$pipelines[] = array(
			'$unwind' => '$sub_plans',
		);
		
		$pipelines[] = array(
			'$sort' => array(
				'_id.aid' => 1,
				'sub_plans.sid' => 1,
				'sub_plans.from' => -1,
			)
		);
		$pipelines[] = array(
			'$group' => array_merge($addedPassthroughFields['second_group'], array(
				'_id' => array_merge($customIDFields['second_group'],array(
					'aid' => '$_id.aid',
					'sid' => '$sub_plans.sid',
					'plan' => '$sub_plans.plan',
					'play' => '$sub_plans.play',
					'first_name' => '$sub_plans.first_name',
					'last_name' => '$sub_plans.last_name',
					'type' => '$sub_plans.type',
					'email' => '$sub_plans.email',
					'address' => '$sub_plans.address',
					'services' => '$sub_plans.services',
					'activation_date' => '$sub_plans.activation_date'
				)),
				'plan_dates' => array(
					'$push' => array_merge($customIDFields['second_group'],array(
						'plan' => '$sub_plans.plan',
						'from' => '$sub_plans.from',
						'to' => '$sub_plans.to',
						'plan_activation' => '$sub_plans.plan_activation',
						'plan_deactivation' => '$sub_plans.plan_deactivation',
					)),
				),
				'card_token' => array(
					'$first' => '$card_token'
				),
			)),
		);
		
		return $pipelines;
	}
	
	/**
	 * Remove fields from main aggreation  that are not needed for onetime invoice
	 */
	protected function alterMainLogicForOnetime($mainAggregationLogic) {
		unset($mainAggregationLogic[0]['$group']['sub_plans']['$push']['plan']);
		unset($mainAggregationLogic[0]['$group']['sub_plans']['$push']['plan_activation']);
		unset($mainAggregationLogic[0]['$group']['sub_plans']['$push']['plan_deactivation']);
		$finalGroupIdx = ($mainAggregationLogic[5]['$group']['plan_dates']['$push']['plan']) ? 5 : 6;
		unset($mainAggregationLogic[$finalGroupIdx]['$group']['plan_dates']['$push']['plan']);

		return $mainAggregationLogic;
	}
	
	protected function getAddedPassthroughValuesQuery() {
		$group = array();
		$group2 = array();
		$project = array();
		$sub_push = array();
		$passthroughFields = array_merge($this->subsPassthroughFields, $this->passthroughFields);
		
		foreach ($passthroughFields as $subscriberField) {
			if (!is_array($subscriberField) && strpos($subscriberField, ".") !== false) {
				$project_val = '$' . $subscriberField;
				foreach($reversed = array_reverse(explode(".", $subscriberField)) as $sub_key) {
					$project_val = [$sub_key => $project_val];
				}
				$srcField = end($reversed);
				$project = array_merge($project, $project_val);
			} else {
				$srcField = is_array($subscriberField) ? $subscriberField['value'] : $subscriberField;
				$project[$srcField] ='$' . $srcField;
			}
			$sub_push[$srcField] =  '$' . $srcField;
			$group2[$srcField] = array('$first' => '$sub_plans.' . $srcField);
		}
		if (!$project) {
			$project = 1;
		}
		return array('group' => $group, 'project' => $project, 'second_group' => $group2,'sub_push' => $sub_push );
	}

	protected function getCustomIDFieldsGrouping() {
		$customIDFields = Billrun_Factory::config()->getConfigValue('customer.aggregator.revision_identification_fields',[]);
		$retGroupingFields = ['sub_push'=> [], 'second_group'=>[]];
		if(!empty($customIDFields)) {
			foreach($customIDFields as $CIDF) {
				$retGroupingFields['sub_push'][$CIDF] = '$'.$CIDF;
				$retGroupingFields['second_group'][$CIDF] = '$sub_plans.'.$CIDF;
			}
		}
		return $retGroupingFields;
	}
	
}
