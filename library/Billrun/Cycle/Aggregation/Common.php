<?php

trait Billrun_Cycle_Aggregation_Common {

	protected $exclusionQuery = [];
	protected $passthroughFields = [];
	protected $subsPassthroughFields = [];
	protected $generalOptions=[];

	public function __construct($options = array()) {
		$this->exclusionQuery = Billrun_Util::getFieldVal($options['exclusion_query'], $this->exclusionQuery);
		$this->passthroughFields = Billrun_Util::getFieldVal($options['passthrough_fields'], $this->passthroughFields);
		$this->subsPassthroughFields = Billrun_Util::getFieldVal($options['subs_passthrough_fields'], $this->subsPassthroughFields);
		$this->generalOptions = $options;
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
		$confirmedAids = $this->getConfirmedAids($mongoCycle);
		if($confirmedAids) {
			$match['$match']['$and'][] = array(
				'aid' => array(
					'$nin' => $confirmedAids,
				)
			);
		}
		return $match;
	}

	protected function getConfirmedAids($mongoCycle) {
		return Billrun_Billingcycle::getConfirmedAccountIds($mongoCycle->key());
	}

	/**
	 *
	 */
	protected function aggregatePipelines(array $pipelines, Mongodloid_Collection $collection) {
		$cursor = $collection->aggregateWithOptions($pipelines,['allowDiskUse'=> true]);
		$results = iterator_to_array($cursor);
		if (!is_array($results) || empty($results) ||
			(isset($results['success']) && ($results['success'] === FALSE))) {
			return array();
		}
		return $results;
	}

}
