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
class Billrun_Cycle_Onetime_AggregatePipeline {
	
	protected $exclusionQuery = array();
	protected $passthroughFields = array();
	protected $subsPassthroughFields = array();
	
	public function __construct($options = array()) {
		$this->exclusionQuery = Billrun_Util::getFieldVal($options['exclusion_query'], $this->exclusionQuery);
		$this->passthroughFields = Billrun_Util::getFieldVal($options['passthrough_fields'], $this->passthroughFields);
		$this->subsPassthroughFields = Billrun_Util::getFieldVal($options['subs_passthrough_fields'], $this->subsPassthroughFields);
	}
	
	/**
	 * 
	 * @param Billrun_DataTypes_MongoCycleTime $cycle
	 * @return type
	 */
	// TODO: Move this function to a "collection aggregator class"
	public function getCycleDateMatchPipeline($cycle) {
		$mongoCycle = new Billrun_DataTypes_MongoCycleTime($cycle);
		return  array('$match' => Billrun_Utils_Mongo::getOverlappingWithRange('from','to',$mongoCycle->start(),$mongoCycle->end()));
	}
	
	// TODO: Move this function to a "collection aggregator class"
	public function getPlansProjectPipeline() {
		return array(
			'$project' => array(
				'plan' => '$name',
				'upfront' => 1,
				'prorated' => 1,
				'vatable' => 1,
				'price' => 1,
				'recurrence.periodicity' => 1,
				'plan_activation' => 1,
				'plan_deactivation' => 1,
				'include' => 1
			)
		);
	}
	
	/**
	 * Aggregate mongo with a query
	 * @param Billrun_DataTypes_MongoCycleTime $cycle - Current cycle time
	 * @param int $page - page
	 * @param int $size - size
	 * @param int $aids - Account ids, null by deafault
	 * @return array 
	 */
	public function getCustomerAggregationForPage($cycle, $page, $size, $aids = null) {
		if (is_null($page)) {
			$page = 0;
		}
		$pipelines[] = $this->getMatchPipeline($cycle);
		if ($aids) {
			$pipelines[count($pipelines) - 1]['$match']['$and'][] = array('aid' => array('$in' => $aids));
		}
		$addedPassthroughFields = $this->getAddedPassthroughValuesQuery();
		$pipelines[] = array(
			'$group' => array_merge($addedPassthroughFields['group'],array(
				'_id' => array(
					'aid' => '$aid',
				),
				'sub_plans' => array(
					'$push' => array_merge($addedPassthroughFields['sub_push'],array(
						'type' => '$type',
						'sid' => '$sid',
						//'plan' => '$plan',
						'from' => '$from',
						'to' => '$to',
						//'plan_activation' => '$plan_activation',
						///'plan_deactivation' => '$plan_deactivation',
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
		$pipelines[] = array(
			'$skip' => $page * $size,
		);
		$pipelines[] = array(
			'$limit' => intval($size),
		);
		$pipelines[] = array(
			'$unwind' => '$sub_plans',
		);
		$pipelines[] = array(
			'$group' => array_merge($addedPassthroughFields['second_group'], array(
				'_id' => array(
					'aid' => '$_id.aid',
					'sid' => '$sub_plans.sid',
					//'plan' => '$sub_plans.plan',
					'first_name' => '$sub_plans.first_name',
					'last_name' => '$sub_plans.last_name',
					'type' => '$sub_plans.type',
					'email' => '$sub_plans.email',
					'address' => '$sub_plans.address',
					'services' => '$sub_plans.services'
				),
				'card_token' => array(
					'$first' => '$card_token'
				),
			)),
		);
		
		$pipelines[] = $this->getSortPipeline();

		$pipelines[] = array(
			'$project' => array(
				'_id' => 0,
				'id' => '$_id',
//				'plan_dates' => 1,
				'card_token' => 1,
				'passthrough' => $addedPassthroughFields['project'],
			)
		);
		
		return $pipelines;
	}
	
	//--------------------------------------------------------------
	
	protected function getAddedPassthroughValuesQuery() {
		$group = array();
		$group2 = array();
		$project = array();
		$sub_push = array();
		foreach ($this->passthroughFields as $accountField) {
			$group[$accountField] = array('$addToSet' => '$' . $accountField);
			$group2[$accountField] = array('$first' => '$' . $accountField);
			$project[$accountField] = array('$arrayElemAt' => array('$' . $accountField, 0));
		}
		
		foreach ($this->subsPassthroughFields as $subscriberField) {
			$srcField = is_array($subscriberField) ? $subscriberField['value'] : $subscriberField;
			$sub_push[$srcField] =  '$' . $srcField;
			$group2[$srcField] = array('$first' => '$sub_plans.' . $srcField);
			$project[$srcField] ='$' . $srcField;;
		}
		if (!$project) {
			$project = 1;
		}
		return array('group' => $group, 'project' => $project, 'second_group' => $group2,'sub_push' => $sub_push );
	}
	
		/**
	 * 
	 * @param Billrun_DataTypes_MongoCycleTime $mongoCycle
	 * @return type
	 */
	protected function getMatchPipeline($mongoCycle) {
		$match = array(
			'$match' => array(
				'$or' => array(
					array_merge( // Account records
						array('type' => 'account'), Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $mongoCycle->start()->sec, $mongoCycle->end()->sec)
					),
					array(// Subscriber records
						'type' => 'subscriber',
						'plan' => array(
							'$exists' => 1
						),
						'$or' => array(
							Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $mongoCycle->start()->sec, $mongoCycle->end()->sec),
							array(// searches for a next plan. used for plans paid upfront
								'from' => array(
									'$lte' => $mongoCycle->end(),
								),
								'to' => array(
									'$gt' => $mongoCycle->end(),
								),
							),
						)
					)
				)
			)
		);

		// If the accounts should not be overriden, filter the existing ones before.
		if ($this->exclusionQuery) {
			$match['$match']['aid'] = $this->exclusionQuery;
		}

		$confirmedAids = Billrun_Billingcycle::getConfirmedAccountIds($mongoCycle->key());
		if ($confirmedAids) {
			$match['$match']['$and'][] = array(
				'aid' => array(
					'$nin' => $confirmedAids,
				)
			);
		}

		return $match;
	}
	
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
}
