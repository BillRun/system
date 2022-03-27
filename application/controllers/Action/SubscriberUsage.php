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

		$sid = @$body["sid"];
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
		$nonPackagedRoamingAddons= Billrun_Factory::config()->getConfigValue('subscriber_usage.non_packaged_roaming_addons',[]);
		$nonPackagedAddons= Billrun_Factory::config()->getConfigValue('subscriber_usage.non_packaged_addons',[]);
		$nationalPackages= array();
		$packages = array();
		$maxNationalUsage = array();
		$maxUsage = array();
		$actualUsage = array();
		$actualNationalUsage = array();
		$startTime = Billrun_Util::getStartTime($params['billrun_key']);
		$endTime = Billrun_Util::getEndTime($params['billrun_key']);
		$horizion = $endTime > time() ? time() : FALSE;

		//Prepare addons with open (allways on) pacakge included
		$roamingAddons=array_merge($this->generateFakeAddons($nonPackagedRoamingAddons,$params['billrun_key']), $params['addons']);
		$nationalAddons=array_merge($this->generateFakeAddons($nonPackagedAddons,$params['billrun_key']), $params['addons_national']);

		//query subscriber balances active at the given billrun
		$mainBalances = iterator_to_array(Billrun_Balance::getCollection()->query(['sid' => $params['sid'], 'billrun_month' => $params['billrun_key']])->cursor());
// 		if(empty($mainBalances) ) {
// 			return $this->setError('Couldn`t retriver the subecriber balance from DB.', $params);
// 		}
		$hasIRPQualification = array_filter($roamingAddons,function($i){return  $i['service_name'] == 'IRP_PREMIUM_QUALIFICATION';});
		if(!empty($hasIRPQualification)) {
			$roamingAddons = array_values(array_filter($roamingAddons, function($pkg){ return $pkg['service_name'] != 'VF';}));
		}
		$vfMax = 0;
		//days_max  will be set by  the getMaxUsagesOfPackages but there is no balance logic for usage so will query the lines
		$vfResults = $this->countDays($params['sid'], date('Y',$startTime), array_column($sortedOffers,'plan'));
		$sortedOffers = $params['offers'];
		usort($sortedOffers,function($a,$b){return strcmp($b['end_date'],$a['end_date']); });
		$lastOffer = reset($sortedOffers);

		//If this is a current balance check don't use max usage for expired
		foreach($roamingAddons as $idx => $roamingAddon) {
			if($horizion && strtotime($roamingAddon['to_date']) < $horizion ) {
				$packageBalances = $this->getPackageBalances($params['sid'],$roamingAddon);
				$this->getActualUsagesOfPackagesFromPackageBalance([$roamingAddon['service_name']=> 1], $packageBalances, $maxUsage);
				@$packages[$roamingAddon['service_name']]['count'] += 1;
				@$packages[$roamingAddon['service_name']]['packages'] = [$roamingAddon];
				;
				array_splice($roamingAddons,$idx,1);
			}
		}
		//If this is a current balance check don't use max usage for expired
		foreach($nationalAddons as $idx => $nationalAddon) {
			if($horizion && strtotime($nationalAddon['to_date']) < $horizion ) {
				$this->getActualUsagesOfPackages([$nationalAddon['service_name']=> 1], $mainBalances, $maxNationalUsage);
				@$nationalPackages[$nationalAddon['service_name']]['count'] += 1;
				@$nationalPackages[$nationalAddon['service_name']]['packages']= [$nationalAddon];
				array_splice($roamingAddons,$idx,1);

			}
		}
		foreach ($sortedOffers as $offer){
			$plan = Billrun_Factory::plan(['name'=> $offer['plan'],'time'=> $startTime])->getData();
			if(empty($plan)) {
				return $this->setError('Couldn`t find the plan from request.', $offer);
			}
			//go though the  subscribers addons packages

			$this->getMaxUsagesOfPackages($roamingAddons, $packages, $maxUsage, $plan);

			//go though the  subscribers addons national packages
			$this->getMaxUsagesOfPackages($nationalAddons, $nationalPackages, $maxNationalUsage, $plan);

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
			if(isset($plan['includes']['groups']['VF']) && empty($packages['IRP_PREMIUM_QUALIFICATION'])) {
				$vfMax = max($vfMax,$plan['includes']['groups']['VF']['limits']['days']);
			}
		}
		//go though the  subscribers addons packages
		$this->getActualUsagesOfPackages($packages, $mainBalances, $actualUsage);
		//go though the  subscribers addons national packages
		$this->getActualUsagesOfPackages($nationalPackages, $mainBalances, $actualNationalUsage);
		
		 //merge results with saved group keys
		$this->initializeUsagesTypes($output);
// 		foreach($maxNationalUsage as  $type => $usageVal) {
// 			$output['usage_israel'][$type.'_usage'] = 0;
// 			$output['usage_israel'][$type.'_max'] = $usageVal;
// 		}
// 		foreach($actualNationalUsage as  $type => $usageVal) {
// 			$output['usage_israel'][$type.'_usage'] = $usageVal;
		if($vfMax) {
			$maxUsage['days'] += $vfMax;
		}
		foreach($maxUsage as  $type => $usageVal) {
			$output['usage_abroad'][$type.'_usage'] = 0;
			$output['usage_abroad'][$type.'_max'] = $usageVal;
		}
		foreach($actualUsage as  $type => $usageVal) {
			$output['usage_abroad'][$type.'_usage'] = $usageVal;
		}

		$output['usage_abroad']['days_usage'] = 0 + @$vfResults;

		//do some beutyfing of the data
		return $output;
	}
	
	protected function getActualUsagesOfPackages($packages, $mainBalances, &$actualUsage) {
		foreach($packages as $pkg => $data) {
			if(!empty($data['packages']) &&  !empty(reset($data['packages'])['id']) ) {
				foreach($data['packages'] as $addon) {
					$packageBalances = $this->getPackageBalances(reset($mainBalances)['sid'],$addon);
					$this->getActualUsagesOfPackagesFromPackageBalance([$pkg => 1], $packageBalances, $actualUsage);
				}
				continue;
			}
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

	protected function getActualUsagesOfPackagesFromPackageBalance($packages, $packageBalances, &$actualUsage) {
		$releventTypes = ['data','call','sms','mms'];
		foreach($packages as $pkg => $count) {
			// Sum the  usages  for the  packages / groups  the  subscriber has in it`s psecific  package balance
			foreach ($packageBalances as $packageBalance){
				if( $packageBalance['service_name'] == $pkg ) {
					foreach($releventTypes as  $type ) {
						if(!empty($packageBalance['balance']['totals'][$type]['usagev']) ) {
							@$actualUsage[$type] += $packageBalance['balance']['totals'][$type]['usagev'];
						}
					}
				}
			}
		}
	}

	
	protected function getMaxUsagesOfPackages($addons, &$packages, &$maxUsage, $plan) {
		$vfMapping = ['data' => 6442451000];
		foreach($addons as  $addon) {
// 			Billrun_Factory::log($addon['service_name']);
			// for each national group / package
			if(!empty($plan['include']['groups'][$addon['service_name']]) && !((isset($packages[$addon['service_name']]['ids']) && in_array( $addon['id'], $packages[$addon['service_name']]['ids'] ))) ) {

					foreach($plan['include']['groups'][$addon['service_name']] as $type => $value) {
						if(	is_array($value) ) {	continue;	}
						if (@$maxUsage[$type] !== -1 && $plan['include']['groups'][$addon['service_name']][$type] !=='UNLIMITED'){
							@$maxUsage[$type] += $plan['include']['groups'][$addon['service_name']][$type];
						} else {
							if($addon['service_name']=== 'VF' && $vfMapping[$type]) {
									@$maxUsage[$type] += $vfMapping[$type];
							} else {
									@$maxUsage[$type] = -1;
							}
						}

					}
					@$packages[$addon['service_name']] = [
																'count' => Billrun_Util::getFieldVal($packages[$addon['service_name']]['count'],0) + 1,
																'ids' => array_merge([$addon['id']],Billrun_Util::getFieldVal($packages[$addon['service_name']]['ids'],[])),
																'packages' => array_merge([$addon],Billrun_Util::getFieldVal($packages[$addon['service_name']]['packages'],[]))

						];
					if(!empty($plan['include']['groups'][$addon['service_name']]['limits']['days'])) {
						@$maxUsage['days'] += $plan['include']['groups'][$addon['service_name']]['limits']['days'];
					}

			}
		}
	}
	
	protected function initializeUsagesTypes(&$output) {
		$usage_israel_types = ['data', 'call', 'sms', 'mms'];
		$usage_abroad_types = ['data', 'call', 'sms', 'mms', 'days'];
// 		foreach ($usage_israel_types as $type){
// 			$output['usage_israel'][$type.'_usage'] = 0;
// 			$output['usage_israel'][$type.'_max'] = 0;
// 		}
		foreach ($usage_abroad_types as $type){
			$output['usage_abroad'][$type.'_usage'] = 0;
			$output['usage_abroad'][$type.'_max'] = 0;
		}
		
	}

	protected function generateFakeAddons($fakeAddonsList, $billrunKey) {
		$startTime = Billrun_Util::getStartTime($billrunKey);
		$endTime = Billrun_Util::getEndTime($billrunKey);
		$generatedAddons = [];
		foreach($fakeAddonsList as  $fAddon) {
			$generatedAddons[] = [
				'service_name' => $fAddon,
				'start_date' => date('Y-m-d H:i:s',$startTime),
				'end_date'  => date('Y-m-d H:i:s',$endTime),
				'from_date' => date('Y-m-d H:i:s',$startTime),
				'to_date'  => date('Y-m-d H:i:s',$endTime),
			];
		}
		return $generatedAddons;

	}

	protected function getPackageBalances($sid,$package) {
		$from = empty($package['balance_from_date']) ? strtotime($package['from_date']) : $package['balance_from_date'];
		$to = empty($package['balance_to_date']) ? strtotime($package['to_date']) : $package['balance_to_date'];
		$billrunKey = $package['service_name'] . '_' . date("Ymd", $from) . '_' . date("Ymd", $to) . '_' . $package['id'];

		return Billrun_Balance::getCollection()->query(['sid' => $sid, 'billrun_month' => $billrunKey])->cursor();
	}

	public function countDays($sid, $year = null, $plans = []) {
		$fraudResult = $this->countDaysFraud($sid, $year);
		$fraudCount = 0 + @$fraudResult['VF']['count'] + @$fraudResult['IRP_VF_10_DAYS']['count'];
		$billingResult = $this->countDaysBilling($sid, $year, $plans);
		$billingCount = 0 + ( empty($billingResult['VF']["day_sum"]) ? 0 : $billingResult['VF']["day_sum"] ) +  max(0,@$billingResult['IRP_VF_10_DAYS']["day_sum"]);
		return max($billingCount,$fraudCount);
	}

	public function countDaysBilling($sid, $year = null, $plans = []) {
		$max_datetime = null;
		try {
			$vfRateGroups = Billrun_Factory::config()->getConfigValue('vfdays.fraud.groups.vodafone',['VF','IRP_VF_10_DAYS']);
			$from = strtotime($year . '-01-01' . ' 00:00:00');
			if (is_null($max_datetime)) {
				$to = strtotime($year . '-12-31' . ' 23:59:59');
			} else {
				$to = !is_numeric($max_datetime) ? strtotime($max_datetime) : $max_datetime;
			}

			$start_of_year = new MongoDate($from);
			$end_date = new MongoDate($to);
			$isr_transitions = Billrun_Util::getIsraelTransitions();
			if (Billrun_Util::isWrongIsrTransitions($isr_transitions)) {
				Billrun_Log::getInstance()->log("The number of transitions returned is unexpected", Zend_Log::ALERT);
			}
			$transition_dates = Billrun_Util::buildTransitionsDates($isr_transitions);
			$transition_date_summer = new MongoDate($transition_dates['summer']->getTimestamp());
			$transition_date_winter = new MongoDate($transition_dates['winter']->getTimestamp());
			$summer_offset = Billrun_Util::getTransitionOffset($isr_transitions, 1);
			$winter_offset = Billrun_Util::getTransitionOffset($isr_transitions, 2);


			$match = array(
				'$match' => array(
					'sid' => $sid,
					'$or' => array(
						array('type' => 'tap3'),
						array('type' => 'smsc'),
					),
					'plan' => array('$in' => $plans),
					'arategroup' => ['$in'=> $vfRateGroups ],
					'billrun' => array(
						'$exists' => true,
					),
				),
			);

			$project = array(
				'$project' => array(
					'sid' => 1,
					'urt' => 1,
					'type' => 1,
					'plan' => 1,
					'arategroup' => 1,
					'billrun' => 1,
					'isr_time' => array(
						'$cond' => array(
							'if' => array(
								'$and' => array(
									array('$gte' => array('$urt', $transition_date_summer)),
									array('$lt' => array('$urt', $transition_date_winter)),
								),
							),
							'then' => array(
								'$add' => array('$urt', $summer_offset * 1000)
							),
							'else' => array(
								'$add' => array('$urt', $winter_offset * 1000)
							),
						),
					),
				),
			);

			$match2 = array(
				'$match' => array(
					'urt' => array(
						'$gte' => $start_of_year,
						'$lte' => $end_date,
					),
				),
			);
			$group = array(
				'$group' => array(
					'_id' => array(
						'plan'=> '$plan',
						'day_key' => array(
							'$dayOfMonth' => array('$isr_time'),
						),
						'month_key' => array(
							'$month' => array('$isr_time'),
						),
						'arategroup' => '$arategroup'
					),
				),
			);
			$group2 = array(
				'$group' => array(
					'_id' => [
						'arategroup' =>'$_id.arategroup',
						'plan'=>'$_id.plan'
					],
					'max_date' => ['$max'=>['$add'=>[['$multiply'=>['$_id.month_key',100]],'$_id.day_key']] ],
					'day_sum' => array(
						'$sum' => 1,
					),
				),
			);
			$sortPlans = [
				'$sort' => ['max_date'=> -1]
			];

			$group3 = array(
				'$group' => array(
					'_id' => '$_id.arategroup',
					'day_sum' => array(
						'$sum' => '$day_sum',
					),
				),
			);
			$billing_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('billing.db'))->linesCollection();
			Billrun_Factory::log("vfdays tap3 aggregate query : ".json_encode([$match, $project, $match2, $group, $group2,$sortPlans,$group3]));
			$results = $billing_connection->aggregate($match, $project, $match2, $group, $group2,$sortPlans,$group3);
		} catch (Exception $ex) {
			Billrun_Factory::log('Error to fetch to billing from fraud system. ' . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::ERR);
			Billrun_Factory::log('We will skip the billing fetch for this call.', Zend_Log::WARN);
		}
		$associatedResults = [];
		foreach($results as $res) {
			$associatedResults[$res['_id']] = $res;
		}
		return $associatedResults;
	}

		/**
	 * for subscriber with LARGE_PREIUM (?KOSHER) counts the number of days he used he's phone abroad
	 * in the current year based on fraud lines
	 * @param type $sid
	 * @return number of days
	 */
	public function countDaysFraud($sid, $year = null) {

		$vfrateGroups = Billrun_Factory::config()->getConfigValue('vfdays.fraud.groups.vodafone',['VF','IRP_VF_10_DAYS']);

		$match1 = array(
			'$match' => array(
				'$or' => array(
					array('subscriber_id' => $sid),
					array('sid' => $sid),
				)
			),
		);
		$match2 = array(
			'$match' => array(
				'arategroup' => [ '$in' => $vfrateGroups],
				'$or' => [
					['record_opening_time' => new MongoRegex("/^$year/")],
					['callEventStartTimeStamp' => new MongoRegex("/^$year/")]
				],
			),
		);
// max_datetime

		$group = array(
			'$group' => array(
				'_id' => [
							'plan'=> '$plan',
							'date' =>['$substr' => [
								['$ifNull' => ['$record_opening_time','$callEventStartTimeStamp'] ],
								4,
								4
							]],
							'arategroup' => '$arategroup'
				],
				'count' => array('$sum' => 1),
			),
		);

		$group2 = array(
			'$group' => array(
				'_id' => [
						'arategroup' =>'$_id.arategroup',
						'plan'=>'$_id.plan'
					],
				'max_date' => ['$max'=>'$_id.date' ],
				'count' => array('$sum' => 1),
			),
		);
		$sortPlans = [
				'$sort' => ['max_date'=> -1]
			];
		//$limitRes = ['$limit'=> 1];
		$group3 = array(
			'$group' => array(
				'_id' => '$_id.arategroup',
				'count' => array('$sum' => '$count'),
			),
		);
		Billrun_Factory::log("vfdays fraud aggregate query : ".json_encode([$match1, $match2, $group, $group2,$sortPlans,$group3]));
		$results = Billrun_Factory::db()->linesCollection()->aggregate($match1, $match2, $group, $group2,$sortPlans,$group3);
		$associatedResults = [];
		foreach($results as $res) {
			$associatedResults[$res['_id']] = $res;
		}
		return $associatedResults;
	}
}
