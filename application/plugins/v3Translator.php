<?php

/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to keep API's backward compatible with V3
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.9
 */
class v3TranslatorPlugin extends Billrun_Plugin_BillrunPluginBase {
	
	protected $loadedConfigs = [];
	
	protected $configs = [];
	
	public function shouldTranslate($request) {
		if ($request instanceof Yaf_Request_Http) {
			return $request->get('translate', $request->getParam('translate', false));
		}
		return Billrun_Util::getIn($request, 'translate', false);
	}


	public function beforeBillApiRunOperation($collection, $action, &$request) {
		if (!$this->shouldTranslate($request)) {
			return;
		}
		
		if (in_array($action, ['get', 'uniqueget'])) {
			$this->translateGetRequest($collection, $request);
		}
	}
	
	public function afterBillApi($collection, $action, $request, &$output) {
		if (!$this->shouldTranslate($request)) {
			return;
		}
		
		$strip = $this->getCompundParam($request->get('strip', false), false);
		if ($strip) {
			$output = $this->stripResults($output, $params['strip']);
		}
	}
	
	protected function translateGetRequest($collection, &$request) {
		if (in_array($collection, ['rates', 'plans'])) {
			$query = json_decode($request['query'], JSON_OBJECT_AS_ARRAY);
			$query['hidden_from_api'] = false;
			$request['query'] = json_encode($query);
		}
	}
	
	/**
	 * copied from Plans/Rates API for BC
	 * 
	 * @param type $results
	 * @param type $strip
	 * @return type
	 * TODO: This function is found in the project multiple times, should be moved to a better location.
	 */
	protected function stripResults($results, $strip) {
		$stripped = array();
		foreach ($strip as $field) {
			foreach ($results as $result) {
				if (isset($result[$field])) {
					if (is_array($result[$field])) {
						$stripped[$field] = array_merge(isset($stripped[$field]) ? $stripped[$field] : array(), $result[$field]);
					} else {
						$stripped[$field][] = $result[$field];
					}
				}
			}
		}
		return $stripped;
	}

	/**
	 * process a compund http parameter (an array)
	 * @param type $param the parameter that was passed by the http;
	 * @return type
	 */
	protected function getCompundParam($param, $retParam = array()) {
		if (isset($param)) {
			$retParam = $param;
			if ($param !== FALSE) {
				if (is_string($param)) {
					$retParam = json_decode($param, true);
				} else {
					$retParam = (array) $param;
				}
			}
		}
		return $retParam;
	}
	
	public function beforeGetLinesData($request, &$linesRequestQueries) {
		$find = &$linesRequestQueries['find'];
		$options = &$linesRequestQueries['options'];
		
		if (!empty($request['zone_grouping_translate'])) {
			$zone_groupings = Billrun_Util::findInArray($find, '$or.*.zone_grouping.$in.*', null, true);
//			Billrun_Factory::log(print_R($zone_groupings, 1));
			foreach ($zone_groupings as $zone_grouping) {
				foreach ($zone_grouping as $key => $value) {
					$in = array('arate' => array('$in' => $this->getRatesBDRefByZoneGrouping($value['zone_grouping']['$in'])));
					unset($find['$or'][$key]['zone_grouping']);
					$find['$or'][$key] = array_merge($find['$or'][$key], $in);
				}
			}
			$options['rate_fields'] = array('zone_grouping');
		}
	}
	
	protected function getRatesBDRefByZoneGrouping($zone_grouping) {
		$ratesIds = Billrun_Factory::db()->ratesCollection()->query(array('zone_grouping' => array('$in' => $zone_grouping)))->cursor()->fields(array('_id' => 1))->setRawReturn(true);
		$retRates = array();
		foreach ($ratesIds as $id) {
			$retRates[] = Mongodloid_Ref::create('rates', $id['_id']);
		}
		return $retRates;
	}
	
	public function afterTranslateCustomerAggregatorData($aggregator, &$translatedData) {
		$ret = [];
		$data = $aggregator->getData();
		Billrun_Utils_Mongo::convertQueryMongodloidDates($data);
		$aid = $aggregator->getAid() ?: -1;
		$passthrough = $data['services'];
		$passthrough['aid'] = $aid;
		
		foreach ($data['services'] as $sub) {
			$planDates = $sub['plan_dates'] ?: [];
			$services = $sub['id']['services'] ?: [];
			$id = [
				'aid' => $aid,
				'first_name' => $sub['id']['first_name'],
				'last_name' => $sub['id']['last_name'],
				'sid' => $sub['id']['sid'] ? $sub['id']['sid'] : 0,
				'plan' => $sub['next_plan'] ?: $sub['curr_plan'],
				'type' => !empty($sub['id']['sid']) ? 'subscriber' : 'account',
				'address' => $sub['address'] ?: '',
				'services' => $services,
			];
			
			$ret[] = [
				'plan_dates' => $planDates,
				'card_token' => null,
				'id' => $id,
				'passthrough' => $passthrough,
			];
		}
		
		$translatedData = $ret;
	}

}
