<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Balance action class
 *
 * @package  Action
 * @since    0.5
 */
class Subscriber_UsageAction extends ApiAction {

	public function execute() {
		$request = $this->getRequest();
		$sid = $request->get("sid");
		if(empty($sid)) {
			$sid = $request->get("subscriber_id");
		}
		Billrun_Factory::log()->log("Execute subscriber_usage api call to SID: " . $sid, Zend_Log::INFO);
		if (!is_numeric($sid)) {
			return $this->setError("SID is not numeric", $request);
		} else {
			settype($sid, 'int');
		}
		$billrunKey = !empty($request->get("billrun")) ? $request->get("billrun") : Billrun_Util::getBillrunKey(time());
		if(!Billrun_util::isBillrunKey($billrunKey)){
			return $this->setError("billrun is not a valid billrun key", $request);
		}
		$offers = json_decode($request->get("offers"), TRUE);
		if (!is_array($offers) || json_last_error()) {
			return $this->setError('Illegal offers format', $request);
		}
		$addons_national = !empty($request->get('addons_national')) ? json_decode($request->get('addons_national'), TRUE): [];
		if (!is_array($addons_national) || json_last_error()) {
			return $this->setError('Illegal addons_national format', $request);
		}
		$addons = !empty($request->get('addons')) ? json_decode($request->get('addons'), TRUE): [];
		if (!is_array($addons) || json_last_error()) {
			return $this->setError('Illegal addons format', $request);
		}
		$cacheParams = array(
			'fetchParams' => array(
				'sid' => $sid,
				'billrun_key' => $billrunKey,
				'offers' => $offers,
				'addons_national' => $addons_national,
				'addons' => $addons
			),
		);

		$output = $this->cache($cacheParams);
		header('Content-type: application/json');
		if(!empty($output)){
			$this->getController()->setOutput(array(array(
				'status' => true,
				'msg' => '',
				'data' => $output
			), true)); // hack
		}
	}
	

	protected function fetchData($params) {
		$nationalPackages= array();
		$packages = array();
		$maxNationalUsage = array();
		$maxUsage = array();
		$actualUsage = array();
		$actualNationalUsage = array();
		$startTime = Billrun_Util::getStartTime($params['billrun_key']);
		//$endTime = Billrun_Util::getEndTime($params['billrun_key']);
		
		foreach ($params['offers'] as $offer){
			$plan = Billrun_Factory::plan(['name'=> $offer['plan'],'time'=> $startTime])->getData();
			if(empty($plan)) {
				return $this->setError('Couldn`t find the plan from request.', $offer);
			}
			//go though the  subscribers addons packages
			$this->getMaxUsagesOfPackages($params['addons'], $packages, $maxUsage, $plan);
			
			//go though the  subscribers addons national packages
			$this->getMaxUsagesOfPackages($params['addons_national'], $nationalPackages, $maxNationalUsage, $plan);
			if(!isset($nationalPackages[$plan['name']])){
				//Save the defualt plan group for addons national packages
				$this->getMaxUsagesOfPackages([["service_name" => $plan['name']]], $nationalPackages, $maxNationalUsage, $plan);
			}
		}
		//query subscriber balances active at the given billrun
		$mainBalances = iterator_to_array(Billrun_Balance::getCollection()->query(['sid' => $params['sid'], 'billrun_month' => $params['billrun_key']])->cursor());
		if(empty($mainBalances) ) {
			return $this->setError('Couldn`t retriver the subecriber balance from DB.', $mainBalances, $params);
		}
		//go though the  subscribers addons packages
		$this->getActualUsagesOfPackages($packages, $mainBalances, $actualUsage);
		//go though the  subscribers addons national packages
		$this->getActualUsagesOfPackages($nationalPackages, $mainBalances, $actualNationalUsage);
		
		 //merge results with saved group keys
		$output['usage_israel'] = [];
		foreach($maxNationalUsage as  $type => $usageVal) {
			$output['usage_israel']['current_'.$type.'_usage'] = 0;
			$output['usage_israel']['total_'.$type.'_max'] = $usageVal;
		}
		foreach($actualNationalUsage as  $type => $usageVal) {
			$output['usage_israel']['current_'.$type.'_usage'] = $usageVal;
		}
		$output['usage_abroad'] = [];
		foreach($maxUsage as  $type => $usageVal) {
			$output['usage_abroad']['current_'.$type.'_usage'] = 0;
			$output['usage_abroad']['total_'.$type.'_max'] = $usageVal;
		}
		foreach($actualUsage as  $type => $usageVal) {
			$output['usage_abroad']['current_'.$type.'_usage'] = $usageVal;
		}

		//do some beutyfing of the data
		return $output;
	}
	
	protected function getActualUsagesOfPackages($packages, $mainBalances, &$actualUsage) {
		foreach($packages as $pkg => $count) {
			// Sum the  usages  for the  packages / groups  the  subscriber has
			foreach ($mainBalances as $mainBalance){
				if(!empty($mainBalance['balance']['groups'][$pkg])) {
					foreach($mainBalance['balance']['groups'][$pkg] as  $type => $usageCounters ) {
						$actualUsage[$type] += $usageCounters['usagev'];
					}
				}
			}
		}
	}
	
	protected function getMaxUsagesOfPackages($addons, &$packages, &$maxUsage, $plan) {

		foreach($addons as  $addon) {
			// for each national group / package
			if(!empty($plan['include']['groups'][$addon['service_name']])) {
					foreach($plan['include']['groups'][$addon['service_name']] as $type => $value) {
						if(is_array($value)) { continue; }
						if ($maxUsage[$type] !== -1 && $plan['include']['groups'][$addon['service_name']][$type] !=='UNLIMITED'){
							$maxUsage[$type] += $plan['include']['groups'][$addon['service_name']][$type];
						}else {
							$maxUsage[$type] = -1;
						}
						$packages[$addon['service_name']] += 1 ;
					}
			}
		}
	}
}