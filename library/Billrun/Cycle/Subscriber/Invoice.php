<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregated subscriber's invoice container
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Subscriber_Invoice {
	
	use Billrun_Traits_ConditionsCheck;
	
	public $aggrResults = null;
	
	/**
	 *
	 * @var array
	 */
	protected $data;
	
	protected $rates = array();
	
	protected $invoicedLines = array();
	protected $invoiceGrouping = [];
        
        protected $totalGroupHashMap = array();

        protected $shouldKeepLinesinMemory = true;
	protected $shouldAggregateUsage = true;
        
        protected $groupingExtraFields = array();
        protected $groupingEnabled = true;
		protected $groupingSumExtraFields = array();

        /**
	 * 
	 * @param array $data - Subscriber data
	 * @param integer $sid
	 * @param integer $aid
	 */
	public function __construct(&$rates, $data, $sid = 0, $aid = 0) {
		$this->rates = &$rates;
		if(!$data) {
			$this->data = $this->createClosedSubscriber($sid, $aid);
		} else {
			$this->data = $data;
		}
		$this->invoiceGrouping = $this->getInvoiceGrouping();
		$this->groupingExtraFields = Billrun_Factory::config()->getConfigValue('billrun.grouping.fields', array()); 
                $this->groupingEnabled = Billrun_Factory::config()->getConfigValue('billrun.grouping.enabled', true); 
				$this->groupingSumExtraFields = Billrun_Factory::config()->getConfigValue('billrun.grouping.sum_fields', array()); 
	}

	/**
	 * Function to get the grouping configuration
	 * @return array
	 */
	public function getInvoiceGrouping() {
		$fields = Billrun_Factory::config()->getConfigValue('billrun.grouping.fields', []);
		if (!empty($fields)) {
			return array(['conditions' => [], 'name' => 'default_grouping', 'fields' => array_map(function ($field) {
                    return ['field_name' => $field, 'op' => 'group'];
                }, $fields)]);
		} else {
			return Billrun_Factory::config()->getConfigValue('billrun.grouping', []);
		}
	}
	
	/**
	 * Create a closed subscriber record
	 * @param type $sid
	 * @param type $aid
	 * @return type
	 */
	protected function createClosedSubscriber($sid, $aid) {
		Billrun_Factory::log("Subscriber $sid is not active, yet has lines", Zend_Log::ALERT);
		$subscriber = array(
			'aid' => $aid, 
			'sid' => $sid, 
			'plan' => null, 
			'next_plan' => null,
			'subscriber_status' => 'closed'
		);
		return $subscriber;
	}

	public function setShouldKeepLinesinMemory($newValue) {
        $this->shouldKeepLinesinMemory = $newValue;
	}

	public function setShouldAggregateUsage($newValue) {
        $this->shouldAggregateUsage = $newValue;
	}
	
	/**
	 * Set data
	 * @param type $key
	 * @param type $value
	 */
	public function setData($key, $value) {
		$this->data[$key] = $value;
	}
	
	/**
	 * Gets the subscriber data.
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Updates the billrun costs
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param boolean $vatable is the row vatable
	 */
	public function updateCosts($pricingData, $row, $vatable) {
		$vat_key = ($vatable ? "vatable" : "vat_free");
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			if (!isset($this->data['costs']['over_plan'][$vat_key])) {
				$this->data['costs']['over_plan'][$vat_key] = $pricingData['aprice'];
				} else {
				$this->data['costs']['over_plan'][$vat_key] += $pricingData['aprice'];
				}
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			if (!isset($this->data['costs']['out_plan'][$vat_key])) {
				$this->data['costs']['out_plan'][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['out_plan'][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'flat') {
			if (!isset($this->data['costs']['flat'][$vat_key])) {
				$this->data['costs']['flat'][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['flat'][$vat_key] += $pricingData['aprice'];
		}
		} else if ($row['type'] == 'credit') {
			if (!isset($this->data['costs']['credit'][$row['usaget']][$vat_key])) {
				$this->data['costs'][$row['usaget']][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs'][$row['usaget']][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'service') {
			if (!isset($this->data['costs']['service'][$vat_key])) {
				$this->data['costs']['service'][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['service'][$vat_key] += $pricingData['aprice'];
			}
		} else if(!in_array($row['type'] , Billrun_Factory::config()->getFileTypes())){
			Billrun_Factory::log("Updating unknown type: " . $row['type']);
		}
	}

	
	protected function updateBreakdown($breakdownKey, $rate, $cost, $usagev, $taxData, $addedData = array(), $overridePreviouslyAggregatedResults = false) {
		if (!isset($this->data['breakdown'][$breakdownKey])) {
			$this->data['breakdown'][$breakdownKey] = array();
		}
		$rate_key = $rate['key'];
		foreach ($this->data['breakdown'][$breakdownKey] as &$breakdowns) {
			if (($breakdowns['name'] === $rate_key) || (!$rate_key && ($breakdowns['name'] == 'Plans and Services'))) {
				Billrun_Factory::log("Found relevant billrun breakdown for rate " . $rate_key,Zend_Log::DEBUG);
				$breakdowns['cost'] = !$overridePreviouslyAggregatedResults ? $breakdowns['cost'] + $cost : $cost;
				$breakdowns['usagev'] = !$overridePreviouslyAggregatedResults ? $breakdowns['usagev'] + $usagev : $usagev;
				$breakdowns['count'] += 1;
				foreach($taxData as $tax ) {
					if(empty($tax['description'])) {
                                                Billrun_Factory::log('Received tax with an empty description. Skipping...',Zend_log::DEBUG);
						continue;
					}
					@$breakdowns['taxes'][$tax['description']] = $overridePreviouslyAggregatedResults ? @$breakdowns['taxes'][$tax['description']] + $tax['amount'] : $tax['amount'];
				}
				if(!empty($addedData)) {
					$breakdowns = array_merge($breakdowns,$addedData);
				}
				return;
			}
		}
		
		if(!$rate_key) {
			Billrun_Factory::log("Didn't find rate data to update. Updating " . $row['stamp'] . " data under 'Plans and Services' breakdown",Zend_Log::DEBUG);
			$rate_key = "Plans and Services";
		}
		
		Billrun_Factory::log("Creating new breakdown object for " . $rate_key,Zend_Log::DEBUG);
		$newBrkDown =  array('name' => $rate_key, 'count' => 1 , 'usagev' => $usagev, 'cost' => $cost);
		if(!empty($addedData)) {
			$newBrkDown = array_merge( $newBrkDown, $addedData);
		}
		$this->data['breakdown'][$breakdownKey][] = $newBrkDown;
	}

		/**
	 * Returns the breakdown key for the row
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	
	 * @return breakdown key
	 */
	protected function getBreakdownKey($row) {
		if (in_array($row['type'], array('flat', 'service'))) {
			return $row['type'];
		}
		
		$fileTypes = Billrun_Factory::config()->getFileTypes();
		if (in_array($row['type'], $fileTypes)) {
			return 'usage';
		}
		
		
		if (in_array($row['type'], array('credit'))) {
			return $row['usaget'];
		}
		
		Billrun_Factory::log("Cannot get type for line. Details: " . print_R($row, 1), Zend_Log::ALERT);
		return FALSE;
	}
	
	/**
	 * HACK TO MAKE THE BILLLRUN FASTER
	 * Get a rate from the row
	 * @param Mongodloid_Entity the row to get rate from
	 * @return Mongodloid_Entity the rate of the row
	 */
	protected function getRowRate($row) {
		if(!isset($row['arate'])) {
			if(!empty($row['name'])) {
				return array('key' => $row['name']);
			}
			return null;
		}
		
		$raw_rate = $row['arate'];
		$id_str = strval($raw_rate['$id']);
		$col_str = strval($raw_rate['$ref']);
		if(!isset($this->rates[$col_str][$id_str])) {
			if (isset($this->rates[$id_str])) {
				Billrun_Factory::log("Found rate " . $row['arate_key'] . " in cycle rates cache, using ref " . $id_str ,Zend_Log::DEBUG);
				return $this->rates[$id_str];
			} else {
				Billrun_Factory::log("Didn't find rate " . $row['arate_key'] . " in rates cache. Searching relevant rate by Db ref " . $id_str ,Zend_Log::DEBUG);
				$rate = Billrun_Rates_Util::getRateByRef($raw_rate, true)->getRawData();
				if (empty($rate)) {
					Billrun_Factory::log("Didn't find rate " . $row['arate_key'] . " using db ref " . $id_str . ". Searching relevant rate by time" ,Zend_Log::DEBUG);
					$rate = Billrun_Rates_Util::getRateByName($row['arate_key'], $row['urt']->sec);
				} else {
					Billrun_Factory::log("Found rate " . $row['arate_key'] . " using ref " . $id_str ,Zend_Log::DEBUG);
			}
				$res = $rate;
		}
		} else {
			$res = $this->rates[$col_str][$id_str];
		}
			
		return $res;
	}
	
	/**
	 * Add pricing and usage counters to the subscriber billrun breakdown.
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param boolean $vatable is the line vatable or not
	 * @param string $billrun_key the billrun_key of the billrun
	 * @todo remove billrun_key parameter
	 */
	public function addLine($counters, $row, $pricingData, $vatable) {
		Billrun_Factory::log("Adding line " . $row['stamp'] . " to billrun breakdown" ,Zend_Log::DEBUG);
		if (!$breakdownKey = $this->getBreakdownKey($row)) {
			return;
		}
		Billrun_Factory::log("Searching rate for row " . $row['stamp'] ,Zend_Log::DEBUG);
		$rate = $this->getRowRate($row);
                if($this->groupingEnabled){
                        $this->addGroupToTotalGrouping($row);
                }
		$addedData = [];
		if(!empty($row['start'])) {
			$addedData['start'] = $row['start'];
		}
		if(!empty($row['end'])) {
			$addedData['end'] = $row['end'];
		}
		Billrun_Factory::log("Updating billrun breakdown with row " . $row['stamp'] . " data",Zend_Log::DEBUG);
		$this->updateBreakdown($breakdownKey, $rate, $pricingData['aprice'], $row['usagev'],$row['tax_data']['taxes'], $addedData);
		// TODO: apply arategroup to new billrun object
		if (isset($row['arategroup'])) {
			$this->addLineGroupData($counters, $row);
		}
		
		if (!isset($this->data['totals'][$breakdownKey])) {
			$this->data['totals'][$breakdownKey] = array();
		}

		$priceAfterVat = $pricingData['aprice'];
		if ($vatable) {
			$priceAfterVat = $this->addLineVatableData($pricingData, $breakdownKey, Billrun_Util::getFieldVal($row['tax_data'],array()));
			if(!empty($row['tax_data']['taxes'])) {
				foreach ($row['tax_data']['taxes'] as $tax) {
					if(empty($tax['description'])) {
						Billrun_Factory::log("Received Tax with empty decription on row {$row['stamp']} , Skipping...",Zend_log::DEBUG);
						continue;
					}
					//TODO change to a generic optional tax configuration  (taxation.CSI.apply_optional_charges)
					if( $tax['pass_to_customer'] == 1 
						 ||
						Billrun_Factory::config()->getConfigValue('taxation.CSI.apply_optional_charges',FALSE) && $tax['pass_to_customer'] == 0 && $row['tax_data']['total_amount'] !== 0 ) {
						$prevAmount = Billrun_Util::getFieldVal($this->data['totals']['taxes'][$tax['description']],0);
						$this->data['totals']['taxes'][$tax['description']] = $prevAmount + $tax['amount'];
					}
				}
			}
		}
		
		
		$this->data['totals']['before_vat'] = Billrun_Util::getFieldVal($this->data['totals']['before_vat'], 0) + $pricingData['aprice'];
		$this->data['totals']['after_vat'] = Billrun_Util::getFieldVal($this->data['totals']['after_vat'], 0) + $priceAfterVat;
		$this->data['totals'][$breakdownKey]['before_vat'] = Billrun_Util::getFieldVal($this->data['totals'][$breakdownKey]['before_vat'], 0) + $pricingData['aprice'];
		$this->data['totals'][$breakdownKey]['after_vat'] = Billrun_Util::getFieldVal($this->data['totals'][$breakdownKey]['after_vat'], 0) + $priceAfterVat;
		
		if ($this->shouldKeepLinesinMemory) {
			$this->invoicedLines[$row['stamp']] = $row;
		}
	}

	/**
	 * Add group data
	 * @param type $counters
	 * @param type $row
	 */
	protected function addLineGroupData($counters, $row) {
		if (isset($row['in_plan'])) {
			$usagev = Billrun_Util::getFieldVal($this->data['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'], 0) + $row['in_plan'];
			$this->data['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'] = $usagev;
		}
		if (isset($row['over_plan'])) {
			$usagev = Billrun_Util::getFieldVal($this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'], 0) + $row['over_plan'];
			$this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'] = $usagev;
			$cost = Billrun_Util::getFieldVal($this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'], 0) + $row['aprice'];
			$this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'] = $cost;
		}
	}
	
	/**
	 * Add the line vatable data to the totals
	 * @return integer Price after vat.
	 */
	protected function addLineVatableData($pricingData, $breakdownKey,$taxData = array()) {
		Billrun_Factory::dispatcher()->trigger('beforeAddLineVatableData', array($this, $pricingData, $breakdownKey,&$taxData));
		if (isset($taxData['add_to_breakdown']) && !$taxData['add_to_breakdown']) {
			Billrun_Factory::log('Tax data should not be added to billrun ' . $breakdownKey . ' breakdown', Zend_Log::DEBUG);
			return $pricingData['aprice'];
		}
		if(!empty($taxData['total_amount']) ) {
			$this->data['totals']['vatable'] = Billrun_Util::getFieldVal($this->data['totals']['vatable'], 0) + $pricingData['aprice'];
			$this->data['totals'][$breakdownKey]['vatable'] = Billrun_Util::getFieldVal($this->data['totals'][$breakdownKey]['vatable'], 0) + $pricingData['aprice'];
			$newPrice = $pricingData['aprice'];
			//Add flat taxes (nonprecentage taxes)
			foreach(Billrun_Util::getFieldVal($taxData['taxes'], array()) as $tax) {
				if( $tax['amount'] != 0) {
					$newPrice += $tax['amount'];
				}
			}
			return $newPrice;
		} else if( empty($taxData) ) {
			Billrun_Factory::log('addLineVatableData failed: Tax data missing. account: ' . $this->data['aid'] . ', subscriber: ' . $this->data['sid'] . ', billrun: ' . $this->data['key'] . ', breakdown key: ' . $breakdownKey, Zend_Log::CRIT);
		}
		//else 
		return $pricingData['aprice'];
				
	}
	
	/**
	 * Add a single subscriber record to the array of totals
	 * @param type $newTotals
	 * @return type
	 */
	public function updateTotals($newTotals) {
		$totalsKeys = array('flat','service','refund','charge','usage','discount');
		foreach($totalsKeys as $totalsKey) {
			$newTotals[$totalsKey]['before_vat'] += Billrun_Util::getFieldVal($this->data['totals'][$totalsKey]['before_vat'], 0);
			$newTotals[$totalsKey]['after_vat'] += Billrun_Util::getFieldVal($this->data['totals'][$totalsKey]['after_vat'], 0);
			$newTotals[$totalsKey]['vatable'] += Billrun_Util::getFieldVal($this->data['totals'][$totalsKey]['vatable'], 0);
		}
		$newTotals['before_vat'] += Billrun_Util::getFieldVal($this->data['totals']['before_vat'], 0);
		$newTotals['after_vat'] += Billrun_Util::getFieldVal($this->data['totals']['after_vat'], 0);
		$newTotals['vatable'] += Billrun_Util::getFieldVal($this->data['totals']['vatable'], 0);
		$newTotals['after_vat_rounded'] = round($newTotals['after_vat'], 2);

		if(!empty($this->data['totals']['taxes'])) {
			foreach($this->data['totals']['taxes'] as $key => $taxAmount) {
				$newTotals['taxes'][$key] = Billrun_Util::getFieldVal($newTotals['taxes'][$key], 0);
				$newTotals['taxes'][$key] += $taxAmount; 
			}
		}
		return $newTotals;
	}
	
	/**
	 * Add all lines of the account to the billrun object
	 * @param array $aggregated array of aggregated subscribers.
	 * @return array the stamps of the lines used to create the billrun
	 */
	public function addLines($lines) {
		$sid = $this->data['sid'];
		$aid = $this->data['aid'];
		
		Billrun_Factory::log("Processing account Lines $aid:$sid" . " lines: " . count($lines), Zend_Log::DEBUG);

		$updatedLines = $this->processLines(array_values($lines));
		Billrun_Factory::log("Finished processing account $aid:$sid lines. Total: " . count($updatedLines), Zend_Log::DEBUG);
		return $updatedLines;
	}

	/**
	 * Process an array of lines.
	 * @param type $subLines
	 * @return type
	 */
	protected function processLines($subLines) {
		$updatedLines = array();
		
		foreach ($subLines as $line) {
			
			// the check fix 2 issues:
			// 1. temporary fix for https://jira.mongodb.org/browse/SERVER-9858
			// 2. avoid duplicate lines
			if (isset($updatedLines[$line['stamp']])) {
				Billrun_Factory::log("Skipping duplicate line");
				continue;
			}
			
			// Process a single line.
			if(!$this->processLine($line)) {
				continue;
			}
			
			//Billrun_Factory::log("Done Processing account Line for $sid : ".  microtime(true));
			$updatedLines[$line['stamp']] = $line;
		}
		if ($this->shouldAggregateUsage) {
			$this->aggregateLinesToBreakdown($subLines);
		} else {
			Billrun_Factory::log('Skipping subscriber '. $this->data['sid'].' usage aggrergation for AID :'. $this->data['aid'],Zend_Log::INFO);
		}

		return $updatedLines;
	}
	
	/**
	 * 
	 * @param type $subLines
	 */
	public function aggregateLinesToBreakdown($subLines, $overridePreviouslyAggregatedResults = false) {
		$subLines = array_map(function($subLine) {
			return ($subLine instanceof Mongodloid_Entity) ? $subLine->getRawData() : $subLine;
		}, $subLines);
		$untranslatedAggregationConfig = Billrun_Factory::config()->getConfigValue('billrun.invoice.aggregate.pipelines', Billrun_Factory::config()->getConfigValue('billrun.invoice.aggregate.subscriber.final_data',array()));
		$translations = array('BillrunKey' => $this->data['key']);
		$aggregationConfig  = json_decode(Billrun_Util::translateTemplateValue(json_encode($untranslatedAggregationConfig),$translations),JSON_OBJECT_AS_ARRAY);
		Billrun_Factory::log('Updating billrun object with aggregated lines for SID : ' . $this->data['sid']);
		$aggregate = new Billrun_Utils_Arrayquery_Aggregate();
		foreach($aggregationConfig as $brkdwnKey => $brkdownConfigs) {
			foreach($brkdownConfigs['pipelines'] as $breakdownConfig) {
				$pipeline_stamp = Billrun_Util::generateArrayStamp(array('pipeline' => $breakdownConfig));
				$this->aggrResults[$pipeline_stamp] = $aggregate->aggregate($breakdownConfig, $subLines, $this->aggrResults[$pipeline_stamp]);
				if($this->aggrResults[$pipeline_stamp]) {
					foreach($this->aggrResults[$pipeline_stamp] as $aggregateValue) {
						
						//$this->data['breakdown'][$brkdwnKey] = array();
						$key = ( empty($aggregateValue['name']) ? $aggregateValue['_id'] : $aggregateValue['name'] );
						$this->updateBreakdown($brkdwnKey, array('key'=> $key), $aggregateValue['price'], $aggregateValue['usagev'], array(),  array_merge(array_diff_key($aggregateValue,array('_id'=>1,'price'=>1,'usagev'=>1)),
						array('conditions' =>json_encode($breakdownConfig[0]['$match']))), $overridePreviouslyAggregatedResults);
					}
				}
			}
		}
		Billrun_Factory::log('Finished aggregating into billrun object for SID : ' . $this->data['sid']);
	}

	/**
	 * Process a single line.
	 * @param type $line
	 * @return boolean
	 */
	protected function processLine($line) {
		$pricingData = $this->getPricingData($line);

		if ($line['type'] == 'flat') {
			if(!$this->processFlatLine($line)) {
				return false;
			}
		} else {
			$rate = $this->getRowRate($line);
			$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable)) || isset($line['tax_data']);
			$this->updateInvoice(array($line['usaget'] => $line['usagev']), $pricingData, $line, $vatable);
		} 
		
		return true;
	}
	
	/**
	 * Get the pricing data array for a line
	 * @param type $line
	 * @return type
	 */
	protected function getPricingData($line) {
		$pricingData = array('aprice' => $line['aprice']);
		if (isset($line['over_plan'])) {
			$pricingData['over_plan'] = $line['over_plan'];
		} else if (isset($line['out_plan'])) {
			$pricingData['out_plan'] = $line['out_plan'];
		}
		return $pricingData;
	}
	
	/**
	 * Process a flat line
	 * @param arary $line
	 * @return boolean true if successful.
	 */
	protected function processFlatLine($line) {
		$vatable = empty($line['vatable']);
		$this->updateInvoice(array(), array('aprice' => $line['aprice']), $line, !$vatable);
		return true;
	}

		/**
	 * Updates the billrun costs, lines & breakdown with the input line if the line is not already included in it
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the input line
	 * @param boolean $vatable is the line vatable or not
	 */
	public function updateInvoice($counters, $pricingData, $row, $vatable) {
		$this->addLine($counters, $row, $pricingData, $vatable);
		$this->updateCosts($pricingData, $row, $vatable);
	}
     
	//--------------------------------------------------------------------------
	
	public function getTotals() {
		return $this->data['totals'];
	}
	
	public function getInvoicedLines() {
		return $this->invoicedLines;
	}

	protected function getGroupingKeysforRow($row, $custom_grouping_fields = []) {
		$groupingKeys = array();
		switch ($row['type']) {
			case 'flat':
				$groupingKeys['entity_key'] = Billrun_Util::getIn($row, 'plan', null);
				$groupingKeys['source'] = 'plan';
				break;
			case 'service':
				$groupingKeys['entity_key'] = Billrun_Util::getIn($row, 'service', null);
				$groupingKeys['source'] = 'service';
				break;
			case 'credit':
				switch ($row['usaget']) {
					case 'discount':
						$groupingKeys['entity_key'] = Billrun_Util::getIn($row, 'key', null);
						break;
					case 'refund':
					case 'charge':
						$groupingKeys['entity_key'] = Billrun_Util::getIn($row, 'arate_key', null);
						break;
				}
				$groupingKeys['source'] = Billrun_Util::getIn($row, 'usaget', null);
				break;
			default:
				$fileTypes = Billrun_Factory::config()->getFileTypes();
				if (in_array($row['type'], $fileTypes)) {
					$groupingKeys['entity_key'] = Billrun_Util::getIn($row, 'arate_key', null);
					$groupingKeys['source'] = 'rate';
				} else {
					Billrun_Factory::log("Updating unknown type: " . $row['type'], Zend_Log::NOTICE);
				}
		}
		$taxes = Billrun_Util::getIn($row, 'tax_data.taxes', array());
		foreach ($taxes as $tax) {
			$tax_key = isset($tax['key']) ? $tax['key'] : "";
			$tax_type = isset($tax['type']) ? $tax['type'] : "";
			$groupingKeys['tax_key'][$tax_key][] = $tax_type;
		}


		foreach ($custom_grouping_fields as $field) {
			if ($field['op'] == 'group') {
				$value = Billrun_Util::getIn($row, $field['field_name'], null);
				if (isset($value)) {
					if (!empty($field['format'])) {
						$translation_array[$field['field_name']] = [
							'value' => $field['field_name'],
							'type' =>$field['type'], 
							'format' => $field['format']
						];
					}
					$value = !empty($field['format']) ? Billrun_Util::translateFields($row, $translation_array)[$field['field_name']] : $value;
					Billrun_Util::setIn($groupingKeys, $field['field_name'], $value);
				}
			}
		}
		return $groupingKeys;
	}

	protected function createNewTotalsGrouping($groupingKeys, $row, $index, $row_grouping_options = []) {
		foreach ($groupingKeys as $field => $value) {
			$this->data['totals']['grouping'][$index][$field] = $value;
		}
		if(isset($row_grouping_options['name'])) {
			$this->data['totals']['grouping'][$index]['group_name'] = $row_grouping_options['name'];
		}
		$this->updateTotalsGrouping($row, $index, $row_grouping_options['fields']);
	}

	protected function updateTotalsGrouping($row, $index, $row_grouping_fields = []) {
		$this->data['totals']['grouping'][$index]['usagev'] = Billrun_Util::getFieldVal($this->data['totals']['grouping'][$index]['usagev'], 0) + Billrun_Util::getIn($row, 'usagev', 0);;
		$this->data['totals']['grouping'][$index]['count'] =  Billrun_Util::getFieldVal($this->data['totals']['grouping'][$index]['count'], 0) + 1;
		$this->data['totals']['grouping'][$index]['before_taxes'] = Billrun_Util::getFieldVal($this->data['totals']['grouping'][$index]['before_taxes'], 0) + Billrun_Util::getIn($row, 'aprice', 0);
		$this->data['totals']['grouping'][$index]['taxes'] = Billrun_Util::getFieldVal($this->data['totals']['grouping'][$index]['taxes'], 0) + Billrun_Util::getIn($row, 'tax_data.total_amount', 0);
		$this->data['totals']['grouping'][$index]['after_taxes'] = Billrun_Util::getFieldVal($this->data['totals']['grouping'][$index]['after_taxes'], 0) + Billrun_Util::getIn($row, 'final_charge', 0);
		
		foreach ($row_grouping_fields as $field) {
			if($field['op'] == 'sum') {
				$this->data['totals']['grouping'][$index][$field['field_name']] = Billrun_Util::getFieldVal($this->data['totals']['grouping'][$index][$field['field_name']], 0) + Billrun_Util::getIn($row, $field['field_name'], 0);
			}
		}
	}

	protected function addGroupToTotalGrouping($row) {
		if($row_grouping_options = $this->getRowGroupOptions($row)) {
			$groupingKeys = $this->getGroupingKeysforRow($row, $row_grouping_options['fields']);
			if (isset($groupingKeys['tax_key'])) {
				foreach ($groupingKeys['tax_key'] as $key => $types) {
					foreach ($types as $type) {
						$uniqeGroupingKeys = $groupingKeys;
						$uniqeGroupingKeys['tax_key'] = !empty($key) ? $key : null;
						$uniqeGroupingKeys['tax_type'] = !empty($type) ? $type : null;
						$this->addGroup($uniqeGroupingKeys, $row, $row_grouping_options);
					}
				}
			} else {
				$this->addGroup($groupingKeys, $row, $row_grouping_options);
			}
		}
	}
	
	/**
	 * Returns row's relevant grouping configuration
	 * @param type $row
	 * @return array or false - if the line doesnt meet any of the conditions
	 */
	protected function getRowGroupOptions($row) {
		foreach ($this->invoiceGrouping as $grouping_object) {
			if ($this->isConditionsMeet($row, $grouping_object['conditions'])) {
				return ['fields' => $grouping_object['fields'], 'name' => $grouping_object['name']];
			}
		}
		return false;
	}

	protected function addGroup($uniqeGroupingKeys, $row, $row_grouping_options) {
		$result = $this->findGroupTotalByGroupingKey($uniqeGroupingKeys, $row_grouping_options);
		//if allready have group for this $uniqeGroupingKeys update this group
		if ($result['status']) {
			$this->updateTotalsGrouping($row, $result['index'], $row_grouping_options['fields']);
		} else {
			//if dont have group for this $uniqeGroupingKeys creat new one.
			$this->createNewTotalsGrouping($uniqeGroupingKeys, $row, $result['index'], $row_grouping_options);
		}
	}

	/**
	 * This function find if for this subscriber already exist group (in the sub.totals of the billrun object)
	 * for the $groupingkeys, if so return the index of the group
	 * otherwise return the next index of the array and save it in $totalGroupHashMap for this new $groupingkeys.
	 * @param $groupingkeys - the keys that distinguish a group.
	 * @return if exist group return status=true and the index otherwise status=false and the the new index.
	 */
	protected function findGroupTotalByGroupingKey($uniqeGroupingKeys, $groupingkeys) {
		$result = array();
		$stamp = Billrun_Util::generateArrayStamp(array_merge($uniqeGroupingKeys, $groupingkeys));
		$index = Billrun_Util::getIn($this->totalGroupHashMap, $stamp, null);
		if (isset($index)) {
			$result['status'] = true;
			$result['index'] = $index;
		} else {
			$result['status'] = false;
			$result['index'] = count($this->totalGroupHashMap);
			$this->totalGroupHashMap[$stamp] = $result['index'];
		}
		return $result;
	}

}
