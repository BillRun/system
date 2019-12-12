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
class Billrun_Cycle_AggregateRemote {
	
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
				'prorated_start' => 1,
				'prorated_end' => 1,
				'prorated_termination' => 1,
				'vatable' => 1,
				'price' => 1,
				'recurrence.periodicity' => 1,
				'plan_activation' => 1,
				'plan_deactivation' => 1,
				'include' => 1,
				'tax' => 1,
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

		if (empty($size)) {
			$size = 100;
		}
		return Billrun_Factory::account()->getBillable($cycle, $page, $size, $aids);
	}
	
	//--------------------------------------------------------------
	

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
