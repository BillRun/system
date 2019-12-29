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

	protected $exclusionQuery = array();
	protected $passthroughFields = array();
	protected $subsPassthroughFields = array();
	
	public function __construct($options = array()) {
		$this->exclusionQuery = Billrun_Util::getFieldVal($options['exclusion_query'], $this->exclusionQuery);
		$this->passthroughFields = Billrun_Util::getFieldVal($options['passthrough_fields'], $this->passthroughFields);
		$this->subsPassthroughFields = Billrun_Util::getFieldVal($options['subs_passthrough_fields'], $this->subsPassthroughFields);
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
		$billableResults = Billrun_Factory::account()->getBillable($cycle, $page, $size, $aids);
		usort($billableResults, function($a, $b){ return strcmp($a['from'],$b['from']);});
		$retResults = [];
		$idFields = ['aid','sid','plan','play','first_name','last_name','type','email','address','services'];
		foreach($billableResults as $revision) {
			if(!in_array($revision['aid'],$this->exclusionQuery)) {
				$revStamp = @Billrun_Util::generateArrayStamp($revision, $idFields);
				if(empty($retResults[$revStamp])) {
					$retResults[$revStamp] = [];
				}
				if(!empty($revision['plan'])) {
					$retResults[$revStamp]['plan_dates'][] = [
						'plan' => $revision['plan'],
						'from' => $revision['from'],
						'to' => $revision['to'],
						'plan_activation' => @$revision['plan_activation'],
						'plan_deactivation' => @$revision['plan_deactivation'],
					];
				} else {
					$retResults[$revStamp]['plan_dates'][] = [
						'from' => $revision['from'],
						'to' => $revision['to']
					];
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

		usort($billableResults, function($a, $b){ return $a['from']->sec - $b['from']->sec;});
		//usort($retResults, function($a, $b){ return $a['from']->sec - $b['from']->sec;});
		return array_map(function($item){ return new Mongodloid_Entity($item);}, array_values($retResults));

	}
	
	//--------------------------------------------------------------

}
