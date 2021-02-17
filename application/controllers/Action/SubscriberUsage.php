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
		$body = json_decode(file_get_contents('php://input'),true);
		
		$sid = $body["sid"];
		if(empty($sid)) {
			$sid = $body["subscriber_id"];
		}
		Billrun_Factory::log()->log("Execute subscriber_usage api call to SID: " . $sid, Zend_Log::INFO);
		if (!is_numeric($sid)) {
			return $this->setError("SID is not numeric", $body);
		} else {
			settype($sid, 'int');
		}
		$billrunKey = !empty($body["billrun"]) ? strval($body["billrun"]) : Billrun_Util::getBillrunKey(time());
		if(!Billrun_util::isBillrunKey($billrunKey)){
			return $this->setError("billrun is not a valid billrun key", $body);
		}
		$offers = $body["offers"];
		if (!is_array($offers)) {
			return $this->setError('Illegal offers format', $body);
		}
		$addons_national = !empty($body['addons_national']) ? $body['addons_national']: [];
		if (!is_array($addons_national)) {
			return $this->setError('Illegal addons_national format', $body);
		}
		$addons = !empty($body['addons']) ? $body['addons']: [];
		if (!is_array($addons)) {
			return $this->setError('Illegal addons format', $body);
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
				'data' => $output,
				'input' => $body,
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

		//query subscriber balances active at the given billrun
		$mainBalances = iterator_to_array(Billrun_Balance::getCollection()->query(['sid' => $params['sid'], 'billrun_month' => $params['billrun_key']])->cursor());
		if(empty($mainBalances) ) {
			return $this->setError('Couldn`t retriver the subecriber balance from DB.', $params);
		}

		//$endTime = Billrun_Util::getEndTime($params['billrun_key']);
		$sortedOffers = $params['offers'];
		usort($sortedOffers,function($a,$b){return strcmp($b['end_date'],$a['end_date']); });
		$lastOffer = reset($sortedOffers);
		foreach ($sortedOffers as $offer){
			$plan = Billrun_Factory::plan(['name'=> $offer['plan'],'time'=> $startTime])->getData();
			if(empty($plan)) {
				return $this->setError('Couldn`t find the plan from request.', $offer);
			}
			//go though the  subscribers addons packages
			$this->getMaxUsagesOfPackages($params['addons'], $packages, $maxUsage, $plan);
			
			//go though the  subscribers addons national packages
			$this->getMaxUsagesOfPackages($params['addons_national'], $nationalPackages, $maxNationalUsage, $plan);

			//Save the defualt plan group for as if it`s a  national packages
			if(!isset($nationalPackages[$plan['name']])){
				//For the plan either....
				if($lastOffer == $offer) {
					//If it`s the last offer get its limits
					$this->getMaxUsagesOfPackages([["service_name" => $plan['name']]], $nationalPackages, $maxNationalUsage, $plan);
				} else {
					//if it`s not the last offer get the amount that was actaully used and add it to the max usage
					$currBalances = array_filter($mainBalances,function($bl) use ($plan){ return isset($bl['balance']['groups'][$plan['name']]);} );
					$this->getActualUsagesOfPackages(["{$plan['name']}" => 1], $currBalances, $maxNationalUsage);
					$nationalPackages[$plan['name']] += 1;
				}
			}
		}
		//go though the  subscribers addons packages
		$this->getActualUsagesOfPackages($packages, $mainBalances, $actualUsage);
		//go though the  subscribers addons national packages
		$this->getActualUsagesOfPackages($nationalPackages, $mainBalances, $actualNationalUsage);
		
		 //merge results with saved group keys
		$this->initializeUsagesTypes($output);
		foreach($maxNationalUsage as  $type => $usageVal) {
			$output['usage_israel'][$type.'_usage'] = 0;
			$output['usage_israel'][$type.'_max'] = $usageVal;
		}
		foreach($actualNationalUsage as  $type => $usageVal) {
			$output['usage_israel'][$type.'_usage'] = $usageVal;
		}
		foreach($maxUsage as  $type => $usageVal) {
			$output['usage_abroad'][$type.'_usage'] = 0;
			$output['usage_abroad'][$type.'_max'] = $usageVal;
		}
		foreach($actualUsage as  $type => $usageVal) {
			$output['usage_abroad'][$type.'_usage'] = $usageVal;
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
						@$actualUsage[$type] += $usageCounters['usagev'];
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
						if (@$maxUsage[$type] !== -1 && $plan['include']['groups'][$addon['service_name']][$type] !=='UNLIMITED'){
							@$maxUsage[$type] += $plan['include']['groups'][$addon['service_name']][$type];
						}else {
							@$maxUsage[$type] = -1;
						}
						@$packages[$addon['service_name']] += 1 ;
					}
			}
		}
	}
	
	
	protected function initializeUsagesTypes(&$output) {
		$usage_israel_types = ['data', 'call', 'sms', 'mms'];
		$usage_abroad_types = ['data', 'call', 'sms', 'mms', 'days'];
		foreach ($usage_israel_types as $type){
			$output['usage_israel'][$type.'_usage'] = 0;
			$output['usage_israel'][$type.'_max'] = 0;
		}
		foreach ($usage_abroad_types as $type){
			$output['usage_abroad'][$type.'_usage'] = 0;
			$output['usage_abroad'][$type.'_max'] = 0;
		}
		
	}
}
