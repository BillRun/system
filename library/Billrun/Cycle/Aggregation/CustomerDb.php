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
		$pipelines=[];

		$pipelines[] = $this->getMatchPipeline($cycle);
		if ($aids) {
			$pipelines[count($pipelines) - 1]['$match']['$and'][] = array('aid' => array('$in' => $aids));
		}
		$addedPassthroughFields = $this->getAddedPassthroughValuesQuery();
		$mainAggregationLogic = $this->getCycleAggregationPipeline($addedPassthroughFields,$page,$size, $invoicing_days);
		if(!empty($this->generalOptions['is_onetime_invoice'])) {
			$mainAggregationLogic = $this->alterMainLogicForOnetime($mainAggregationLogic);
		}

		$pipelines = array_merge($pipelines,array_values($mainAggregationLogic));


		$pipelines[] = $this->getSortPipeline();

		$pipelines[] = $this->getFinalProject($addedPassthroughFields);

		$collection = Billrun_Factory::db()->subscribersCollection();
		return ["data" => $this->aggregatePipelines($pipelines,$collection), "options" => Billrun_Factory::config()->getConfigValue("customer.aggregator.options", [])];
	}

	//--------------------------------------------------------------------------------------------

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
		$pipelines[] = array(
			'$group' => array_merge($addedPassthroughFields['group'],array(
				'_id' => array(
					'aid' => '$aid',
				),
				'sub_plans' => array(
					'$push' => array_merge($addedPassthroughFields['sub_push'],array(
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
				'_id' => array(
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
				),
				'plan_dates' => array(
					'$push' => array(
						'plan' => '$sub_plans.plan',
						'from' => '$sub_plans.from',
						'to' => '$sub_plans.to',
						'plan_activation' => '$sub_plans.plan_activation',
						'plan_deactivation' => '$sub_plans.plan_deactivation',
					),
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
			$srcField = is_array($subscriberField) ? $subscriberField['value'] : $subscriberField;
			$sub_push[$srcField] =  '$' . $srcField;
			$group2[$srcField] = array('$first' => '$sub_plans.' . $srcField);
			$project[$srcField] ='$' . $srcField;
		}
		if (!$project) {
			$project = 1;
		}
		return array('group' => $group, 'project' => $project, 'second_group' => $group2,'sub_push' => $sub_push );
	}
	
}
