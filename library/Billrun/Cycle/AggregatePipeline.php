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
class Billrun_Cycle_AggregatePipeline {
	
	protected $exclusionQuery = array();
	protected $passthroughFields = array();
	
	public function __construct($options = array()) {
		$this->exclusionQuery = Billrun_Util::getFieldVal($options['exclusion_query'], $this->exclusionQuery);
		$this->passthroughFields = Billrun_Util::getFieldVal($options['passthrough_fields'], $this->exclusionQuery);
	}
	
	/**
	 * 
	 * @param Billrun_DataTypes_MongoCycleTime $cycle
	 * @return type
	 */
	// TODO: Move this function to a "collection aggregator class"
	public function getCycleDateMatchPipeline($cycle) {
		$mongoCycle = new Billrun_DataTypes_MongoCycleTime($cycle);
		return array(
			'$match' => array(
				'from' => array(
					'$lt' => $mongoCycle->end()
					),
				'to' => array(
					'$gt' => $mongoCycle->start()
					)
				)
			);
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
				'plan_deactivation' => 1
			)
		);
	}
	
	/**
	 * Aggregate mongo with a query
	 * @param Billrun_DataTypes_MongoCycleTime $cycle - Current cycle time
	 * @param int $page - page
	 * @param int $size - size
	 * @param int $aid - Account id, null by deafault
	 * @return array 
	 */
	public function getCustomerAggregationForPage($cycle, $page, $size, $aid = null) {
		if ($aid) {
			$page = 0;
			$size = 1;
		}
		if (is_null($page)) {
			$page = 0;
		}
		$pipelines[] = $this->getMatchPipeline($cycle);
		if ($aid) {
			$pipelines[count($pipelines) - 1]['$match']['aid'] = intval($aid);
		}
		$addedPassthroughFields = $this->getAddedPassthroughValuesQuery();
		$pipelines[] = array(
			'$group' => array_merge($addedPassthroughFields['group'],array(
				'_id' => array(
					'aid' => '$aid',
				),
				'sub_plans' => array(
					'$push' => array(
						'type' => '$type',
						'sid' => '$sid',
						'plan' => '$plan',
						'from' => '$from',
						'to' => '$to',
						'plan_activation' => '$plan_activation',
						'plan_deactivation' => '$plan_deactivation',
						'first_name' => '$firstname',
						'last_name' => '$lastname',
						'address' => '$address',
						'services' => '$services'
					),
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
					'plan' => '$sub_plans.plan',
					'first_name' => '$sub_plans.first_name',
					'last_name' => '$sub_plans.last_name',
					'type' => '$sub_plans.type',
					'address' => '$sub_plans.address',
					'services' => '$sub_plans.services'
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
		
		$pipelines[] = $this->getSortPipeline();

		$pipelines[] = array(
			'$project' => array(
				'_id' => 0,
				'id' => '$_id',
				'plan_dates' => 1,
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
		foreach ($this->passthroughFields as $subscriberField) {
			$group[$subscriberField] = array('$addToSet' => '$' . $subscriberField);
			$group2[$subscriberField] = array('$first' => '$' . $subscriberField);
			$project[$subscriberField] = array('$arrayElemAt' => array('$' . $subscriberField, 0));
		}
		if (!$project) {
			$project = 1;
		}
		return array('group' => $group, 'project' => $project, 'second_group' => $group2);
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

		return $match;
	}
	
	protected function getSortPipeline() {
		return array(
			'$sort' => array(
				'_id.aid' => 1,
				'_id.sid' => 1,
				'_id.type' => -1,
				'_id.plan' => 1,
				
				// TODO: We might want to uncomment this
//				'plan_dates.from' => 1,
			),
		);
	}
}
