<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing invoice view - helper for html template for invoice
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_View_Invoice extends Yaf_View_Simple {

	/*
	 * get and set lines of the account
	 */
	public function setLines($accountLines) {
		$this->lines = $accountLines;
		$this->subServices = [];
	}

	public function loadLines() {
		$lines_collection = Billrun_Factory::db()->linesCollection();
		$this->lines = array();
		$billrun_key = $this->data['billrun_key'];
		// BRCD-785: a consolidated invoice may contain subscribers from sub-accounts (each subs entry
		// carries its own aid), so load lines for every contributing aid - not just the invoicing account.
		// For a flat account this degenerates to the single own aid.
		$query = array('aid' => array('$in' => $this->getContributingAids()), 'billrun' => $billrun_key);
		$accountLines = $lines_collection->query($query);
		$this->setLines($accountLines);
	}

	/**
	 * BRCD-785: the distinct account ids that contribute lines to this invoice - the invoicing account
	 * plus any rolled-up sub-accounts present in the subs array.
	 *
	 * @return int[]
	 */
	protected function getContributingAids() {
		$aids = array(intval($this->data['aid']));
		if (!empty($this->data['subs'])) {
			foreach ($this->data['subs'] as $sub) {
				if (isset($sub['aid'])) {
					$aids[] = intval($sub['aid']);
				}
			}
		}
		return array_values(array_unique($aids));
	}

	/**
	 * BRCD-2826: per-sub-account breakdown for a hierarchical (consolidated) invoice. Each entry carries a
	 * textual hierarchy breadcrumb (root > ... > account) and the subtotal rolled up from that account's
	 * subscribers. Templates can iterate this to render breadcrumbs and per-sub-account subtotals; a flat
	 * invoice returns a single entry for its own account.
	 *
	 * @return array list of ['aid','name','breadcrumb','level','totals'=>['before_vat','after_vat'],'sids'=>int[]]
	 */
	public function getSubAccountsBreakdown() {
		if (empty($this->data['subs'])) {
			return array();
		}
		// Per-account breadcrumb metadata is persisted on the invoice during aggregation, so no live DB
		// queries are needed at render time.
		$hierarchyMeta = Billrun_Util::getIn($this->data, array('attributes', 'hierarchy'), array());
		$byAid = array();
		foreach ($this->data['subs'] as $sub) {
			$aid = intval(Billrun_Util::getIn($sub, 'aid', $this->data['aid']));
			if (!isset($byAid[$aid])) {
				$byAid[$aid] = array('before_vat' => 0, 'after_vat' => 0, 'sids' => array());
			}
			// Each subs entry carries its own per-subscriber totals; summing them per aid yields the
			// sub-account subtotal.
			$byAid[$aid]['before_vat'] += Billrun_Util::getIn($sub, array('totals', 'before_vat'), 0);
			$byAid[$aid]['after_vat'] += Billrun_Util::getIn($sub, array('totals', 'after_vat'), 0);
			if (isset($sub['sid'])) {
				$byAid[$aid]['sids'][] = intval($sub['sid']);
			}
		}
		$result = array();
		foreach ($byAid as $aid => $entry) {
			$breadcrumb = $this->buildHierarchyBreadcrumb($aid, $hierarchyMeta);
			$result[] = array(
				'aid' => $aid,
				'name' => empty($breadcrumb) ? ('Account ' . $aid) : end($breadcrumb),
				'breadcrumb' => implode(' > ', $breadcrumb),
				'level' => count($breadcrumb),
				'totals' => array('before_vat' => $entry['before_vat'], 'after_vat' => $entry['after_vat']),
				'sids' => $entry['sids'],
			);
		}
		return $result;
	}

	/**
	 * Ordered display names from the invoice's top account down to $aid (BRCD-2826 breadcrumb segments),
	 * built from the metadata persisted on the invoice during aggregation - no live DB queries.
	 *
	 * @param int $aid
	 * @param array $hierarchyMeta aid => ['name'=>..., 'parent_aid'=>...]
	 * @return string[]
	 */
	protected function buildHierarchyBreadcrumb($aid, $hierarchyMeta) {
		if (empty($hierarchyMeta)) {
			$name = $this->getInvoiceAccountName();
			return $name === '' ? array() : array($name);
		}
		$names = array();
		$visited = array();
		$current = intval($aid);
		$maxDepth = Billrun_Cycle_Hierarchy::getMaxDepth();
		while ($current && !isset($visited[$current]) && isset($hierarchyMeta[$current]) && count($names) <= $maxDepth) {
			$visited[$current] = true;
			array_unshift($names, Billrun_Util::getIn($hierarchyMeta, array($current, 'name'), 'Account ' . $current));
			$parent = Billrun_Util::getIn($hierarchyMeta, array($current, 'parent_aid'), null);
			$current = is_null($parent) ? null : intval($parent);
		}
		if (empty($names)) {
			$names[] = 'Account ' . intval($aid);
		}
		return $names;
	}

	/**
	 * Display name of the invoice's own account, from its persisted attributes.
	 */
	protected function getInvoiceAccountName() {
		$attributes = Billrun_Util::getIn($this->data, 'attributes', array());
		foreach (array('company_name', 'fullname') as $field) {
			if (!empty($attributes[$field])) {
				return $attributes[$field];
			}
		}
		return trim(Billrun_Util::getFieldVal($attributes['firstname'], '') . ' ' . Billrun_Util::getFieldVal($attributes['lastname'], ''));
	}
	
	
	
	public function getLineUsageName($line) {
		$usageName = '';
		$rate = $this->getRateForLine($line);
		$typeMapping = array('flat' => array('rate'=> 'description','line'=>'name'), 
							 'service' => array('rate'=> 'description','line' => 'name'));
		
		if (isset($rate['invoice_description'])) {
			return $rate['invoice_description'];
		}
		
		if(in_array($line['type'],array_keys($typeMapping))) {
			$usageName = isset($typeMapping[$line['type']]['rate']) ? 
								$rate[$typeMapping[$line['type']]['rate']] :
								ucfirst(strtolower(preg_replace('/_/', ' ',$line[$typeMapping[$line['type']]['line']])));
		} else {
			$usageName = !empty($line['description']) ?
							$line['description'] : 
							(!empty($rate['description']) ? 
								$rate['description'] :
								ucfirst(strtolower(preg_replace('/_/', ' ',$line['arate_key']))) );
		}
		return $usageName;
	}
	
	public function getAllDiscount($lines) {
		$discounts = array('lines' => array(), 'total'=> 0);
		foreach($lines as $line) {
			if($line['usaget'] == 'discount') {
				@$discounts['lines'][$this->getLineUsageName($line)] += $line['aprice'];
				@$discounts['total'] +=$line['aprice'];
			}
		}
		return $discounts;
	}
	
	public function getLineUsageVolume($line) {
		$usagev = Billrun_Utils_Units::convertInvoiceVolume($line['usagev'], $line['usaget']);
		$unit = Billrun_Utils_Units::getInvoiceUnit($line['usaget']);
		$unitDisplay = Billrun_Utils_Units::getUnitLabel($line['usaget'], $unit);
		return (is_numeric($usagev) ? number_format($usagev, 2) : $usagev) . ' ' . $unitDisplay;
	}


	public function buildSubscriptionListFromLines($lines) {
		$subscriptionList = array();
		$typeNames = array_flip($this->details_keys);
		foreach($lines as $line) {
			if(in_array($line['type'],$this->flat_line_types) && $line['aprice'] != 0 && $line['usaget'] != 'discount') {
				$rate = $this->getRateForLine($line);
				$flatData =  ($line['type'] == 'credit') ? $rate['rates']['call']['BASE']['rate'][0] : $rate;
				if ($line instanceof Mongodloid_Entity) {
					$line->collection(Billrun_Factory::db()->linesCollection());
				}
				$name = $this->getLineUsageName($line);
				$key = $this->getLineAggregationKey($line, $rate, $name);
				$subscriptionList[$key]['desc'] = $name;	
				$subscriptionList[$key]['type'] = $typeNames[$line['type']];
				//TODO : HACK : this is an hack to add rate to the highcomm invoice need to replace is  with the actual logic once the  pricing  process  will also add the  used rates to the line pricing information.
				$subscriptionList[$key]['rate'] = max(@$subscriptionList[$key]['rate'],$this->getLineRatePrice($flatData, $line));
				@$subscriptionList[$key]['count']+= Billrun_Util::getFieldVal($line['usagev'],1);
				$subscriptionList[$key]['amount'] = Billrun_Util::getFieldVal($subscriptionList[$key]['amount'],0) + $line['aprice'];
				$subscriptionList[$key]['start'] = empty($line['start']) ? @$subscriptionList[$key]['start'] : $line['start'] ;
				$subscriptionList[$key]['end'] = empty($line['end']) ? @$subscriptionList[$key]['end'] : $line['end'] ;
				$subscriptionList[$key]['span'] = $this->getListItemSpan($subscriptionList[$key]);
			}
		}
		return $subscriptionList;
	}
	
	public function currencySymbol() {
		return Billrun_Rates_Util::getCurrencySymbol(Billrun_Factory::config()->getConfigValue('pricing.currency','USD'));
	}
	
	protected function getLineRatePrice($rate, $line) {
		$pricePerUsage = 0;		
		if(isset($rate['price'][0]['price'])) {
			$priceByCycle = Billrun_Util::mapArrayToStructuredHash($rate['price'], array('from'));
			$pricePerUsage = $priceByCycle[empty($line['cycle']) ? 0 : $line['cycle']]['price'];
		} else if( isset($rate['rates'][$line['usaget']]['BASE']['rate'][0]['price']) ) {
			$pricePerUsage = $rate['rates'][$line['usaget']]['BASE']['rate'][0]['price'];
		} else {
			$pricePerUsage = $rate['price'];
		}
		return $pricePerUsage;
	}
	
	protected function getRateForLine($line) {
		$rate = FALSE;
		if(!empty($line['arate'])) {
			$rate = Mongodloid_Ref::isRef($line['arate']) ? Billrun_Rates_Util::getRateByRef($line['arate']) : $line['arate'];
			$rate = $rate->getRawData();
		} else {
			$flatRate = $line['type'] == 'flat' ? 
				new Billrun_Plan(array('name'=> $line['name'], 'time'=> $line['urt']->sec)) : 
				new Billrun_Service(array('name'=> $line['name'], 'time'=> $line['urt']->sec));
			$rate = $flatRate->getData();
		}
		return $rate;
	}
	
	protected function getLineAggregationKey($line,$rate,$name) {
		$key = $name;
		if(!empty($this->render_detailed_quantitative_services) && $line['type'] == 'service' && $rate['quantitative']) {
			$key .= $line['usagev']. $line['sid'];
		}
		if(!empty($line['start'])) {
			$key .= date('ymd',$line['start']->sec);
		}
		if(!empty($line['end'])) {
			$key .=  '_'.date('ymd',$line['end']->sec);
		}
		if(!empty($line['cycle'])) {
			$key .= $line['cycle'];
		}
		return $key;
	}
	
	protected function getListItemSpan($item) {
		return (empty($item['start']) ? '' : 'Starting '.date(date($this->date_format,$item['start']->sec))) .
				(empty($item['start']) || empty($item['end']) ? '' : ' - ') .
				(empty($item['end'])   ? '' : 'Ending '.date(date($this->date_format,$item['end']->sec)));
	}

	public function getFormatedPrice($price,$precision = 2, $priceSymbol = '₪') {
		return "{$priceSymbol} ". number_format((isset($price) ? floatval($price): 0), $precision)  ;
	}
	
	public function getFormatedUsage($usage, $usaget, $showUnits = false, $precision = 0) {
		$usage = empty($usage) ? 0 :$usage;
		$unit = Billrun_Utils_Units::getInvoiceUnit($usaget);
		$volume = Billrun_Utils_Units::convertVolumeUnits( $usage , $usaget,  $unit);
		return (preg_match('/^[\d.]+$/', $volume) && $volume ?  number_format($volume,$precision) : $volume )." ". ($showUnits ? Billrun_Utils_Units::getUnitLabel($usaget, $unit) : '');
	}
	/**
	* Get usage traiff based on the usage type  , rate ,plan, services  the subscriber had.
	*/
	public function getRateTariff($rateName, $usaget,$planName = FALSE, $services = [], $addTax = FALSE ) {
		if(!empty($rateName)) {
			$rate = Billrun_Rates_Util::getRateByName($rateName, $this->data['end_date']->sec);
			if(!empty($rate)) {
				$serviceInstances = [];
				if(is_array($services)) {
					foreach( $services as $service ) {
						$serviceInstances[] = Billrun_Factory::service(['name' => $service['name'],'time' => $service['to']->sec-1]);
					}
				}
				
				$tariff = Billrun_Rates_Util::getTariff($rate, $usaget, $planName, $serviceInstances);
			
			}
		}
		$retTariff = (empty($tariff) ? 0 : Billrun_Tariff_Util::getTariffForVolume($tariff, 0))  * Billrun_Util::getFieldVal($this->tariffMultiplier[$usaget], 1);
		if($addTax) {
			$taxCalc = Billrun_Calculator::getInstance(['type'=>'tax']);
			$retTariff = $taxCalc->addTax($retTariff);
		}
		return $retTariff;
	}

	public function getPlanDescription($subscriberiptionData) {
		if(!empty($subscriberiptionData['plan'])) {
			$plan = Billrun_Factory::plan(array('name'=>$subscriberiptionData['plan'],'time'=>$this->data['end_date']->sec));
			return str_replace('[[NextPlanStage]]', date(Billrun_Base::base_dateformat, Billrun_Util::getFieldVal($subscriberiptionData['next_plan_price_tier'],new Mongodloid_Date())->sec), $plan->get('invoice_description'));
		}
		return "";
	}
	
	public function getBillrunKey() {
		return $this->data['billrun_key'];
	}
	
	public function shouldProvideDetails() {
		return !empty($this->data['attributes']['invoice_details']) || in_array($this->data['aid'],  Billrun_Factory::config()->getConfigValue('invoice_export.aid_with_detailed_invoices',array()));
	}
	
	public function getInvoicePhonenumber($rawNumber) {
		$retNumber = $rawNumber;
		foreach($this->destinationsNumberTransforms as $regex => $transform) {
			$retNumber = preg_replace($regex,$transform,$retNumber);
		}
		
		return $retNumber;
	}
	
	public function shouldRatebeDisplayed($usageData,$section='all') {
		return static::shouldRatebeDisplayedByKey($usageData['rate'],$section);
	}
        
        public function shouldRatebeDisplayedForLine($line,$section='all') {
		return static::shouldRatebeDisplayedByKey($line['arate_key'],$section) && !in_array($line['type'],Billrun_Factory::config()->getConfigValue('invoice_export.hide_rates_by_type.'.$section,[]));
	}
        
        public function shouldRatebeDisplayedByKey($rateKey,$section='all') {
		return !Billrun_Util::regexArrMatch(Billrun_Factory::config()->getConfigValue('invoice_export.hide_rates.'.$section,array()),$rateKey);
	}
	
	public function getSubscriberServices($sid) {
		if(!isset($this->subServices[$sid])) {
			$this->subServices[$sid] = [];
			//Get  only relevent subscriber revisions
			$query = Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $this->data['start_date']->sec,$this->data['end_date']->sec);
			$query['sid'] = $sid;
			//Get only services relevent to the  current billrun
			$filterServices = Billrun_Utils_Mongo::getOverlappingWithRange('services.from', 'services.to', $this->data['start_date']->sec,$this->data['end_date']->sec);
			$aggrgatePipeline = [['$match'=>$query],
								['$unwind'=>'$services'],
								['$match'=>$filterServices],
								['$group'=>['_id'=>null,'services'=>['$addToSet'=>'$services']]]];
			$subservs = Billrun_Factory::db()->subscribersCollection()->aggregate($aggrgatePipeline)->current();
			foreach($subservs['services'] as $service) {
				$this->subServices[$sid][] = $service;
			}
		}
		return $this->subServices[$sid];
	}
	
	public function getSubscriberMessages($sid) {
		$query['time'] = date(Billrun_Base::base_datetimeformat, $this->data['invoice_date']->sec);
		$query['sid'] = $sid;
		$subData = Billrun_Factory::subscriber()->loadSubscriberForQuery($query);
		$msgs = !empty($subData) && !$subData->isEmpty() && !empty($subData['invoice_messages']) ? $subData['invoice_messages'] : [];
		$retMsgs = [];
		foreach($msgs as $msg) {
			$entryTime = is_object($msg['entry_time']) ? $msg['entry_time']->sec :
							(is_array($msg['entry_time']) ? $msg['entry_time']['sec'] :
								strtotime( $msg['entry_time']));
			if( $this->data['start_date']->sec <= $entryTime && $entryTime < $this->data['end_date']->sec ) {
				$retMsgs [] = $msg;
			}
		}
		return $retMsgs;
	}
	
	public function createSubscriberInvoiceTables($lines, $flatTypes = [], $usageTypes = [], $details_keys = []) {
		$config = Billrun_Factory::config();
		$invoice_display = $config->getInvoiceDisplayConfig();
		if (is_a($lines, 'Traversable')) {
			$lines = iterator_to_array($lines);
		}
		$lines_filtered = array_filter($lines, function($line) {
			return $line['sid'] != 0;
		});
		$this->buildNotCustomTabels($lines_filtered, $flatTypes, false, $details_keys);
		if (!empty($tabels_config = $invoice_display['usage_details']['tables'] ?? null)) {
			foreach ($lines_filtered as $index => $line) {
				if (in_array($line['type'], $usageTypes)) {
					$this->associateLineToTable($line, $tabels_config, $details_keys);
				}
			}
		} else {
			$this->buildNotCustomTabels($lines_filtered, $usageTypes, true, $details_keys);
		}
	}

	public function associateLineToTable($line, $tabels_config, $details_keys = []) {
		$meetConditions = 0;
		foreach ($tabels_config as $tabel_index => $tabel_config) {
			foreach ($tabel_config['conditions'] as $condition) {
				if (Billrun_Util::isConditionMet($line, $condition)) {
					$meetConditions = 1;
				}
			}
			if ($meetConditions) {
				$this->invoice_usage_tabels[$line['sid']][$tabel_index][] = $this->getTableRow($line, $tabel_config['columns'], $details_keys);
				return;
			}
		}
	}

	public function getTableRow($line, $columns, $details_keys = [], $is_flat_type = false) {
		$row = [];
		$datetime_format = Billrun_Factory::config()->getConfigValue('invoice_export.datetime_format', 'd/m/Y H:i:s');
		$flippedKeys = array_flip($details_keys);
		foreach ($columns as $index => $column) {
			switch ($column['field_name']) {
				case 'urt':
					$row['DATE_TIME'] = date($datetime_format, $line['urt']->sec - ($is_flat_type ? 1 : 0));
					break;
				case 'usaget':
					$row['TYPE'] = (!empty($flippedKeys[$line['usaget']]) ? $flippedKeys[$line['usaget']] : (empty($flippedKeys[$line['type']]) ? $line['type'] : $flippedKeys[$line['type']]));
					break;
				case 'arate_key':
					$row['RATE'] = $this->getLineUsageName($line);
					break;
				case 'usagev':
					$row['VOLUME'] = $this->getLineUsageVolume($line);
					break;
				case 'aprice':
					$row['AMOUNT'] = number_format($line['aprice'], 2);
					break;
				default:
					$row[$column['label']] = Billrun_Util::getIn($line, $column['field_name'], "");
					break;
			}
		}
		return $row;
	}

	public function buildNotCustomTabels($lines, $types, $is_usage_types = false, $details_keys = []) {
		$fields = ['DATE_TIME' => 'urt',
			'TYPE' => 'usaget',
			'RATE' => 'arate_key',
			'VOLUME' => 'usagev',
			'AMOUNT' => 'aprice'
		];
		$columns = [];
		foreach ($fields as $label => $field_name) {
			$columns[] = ['field_name' => $field_name, 'label' => $label];
		}
		foreach ($lines as $index => $line) {
			if (in_array($line['type'], $types)) {
				if (!$is_usage_types) {
					$this->invoice_flat_tabels[$line['sid']][0][] = $this->getTableRow($line, $columns, $details_keys, true);
				} else {
					$this->invoice_usage_tabels[$line['sid']][0][] = $this->getTableRow($line, $columns, $details_keys);
				}
			}
		}
	}


	/**
	 * TODO implement
	 */
	public function  getFormatedDate($date,$formatType = '' ) {
		return date('Y-m-d' ,$date);
	}
}
