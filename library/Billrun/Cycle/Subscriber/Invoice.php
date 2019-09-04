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
	
	/**
	 *
	 * @var array
	 */
	protected $data;
	
	protected $rates = array();
	
	protected $invoicedLines = array();
	
	protected $shouldKeepLinesinMemory = true;
	protected $shouldAggregateUsage = true;
        
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

	
	protected function updateBreakdown($breakdownKey, $rate, $cost, $usagev, $taxData, $addedData = array() ) {
		if (!isset($this->data['breakdown'][$breakdownKey])) {
			$this->data['breakdown'][$breakdownKey] = array();
		}
		$rate_key = $rate['key'];
		foreach ($this->data['breakdown'][$breakdownKey] as &$breakdowns) {
			if ($breakdowns['name'] === $rate_key) {
				$breakdowns['cost'] += $cost;
				$breakdowns['usagev'] += $usagev;
				$breakdowns['count'] += 1;
				foreach($taxData as $tax ) {
					if(empty($tax['description'])) {
						Billrun_Factory::log('Received Tax  with empty  decription Skiping...',Zend_log::DEBUG);
						continue;
					}
					@$breakdowns['taxes'][$tax['description']] += $tax['amount'];
				}
				if(!empty($addedData)) {
					$breakdowns = array_merge($breakdowns,$addedData);
				}
				return;
			}
		}
		
		if(!$rate_key) {
			$rate_key = "Plans and Services";
		}
		
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
		if(!isset($this->rates[$id_str])) {
			return null;
		}
		return $this->rates[$id_str];
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
		if (!$breakdownKey = $this->getBreakdownKey($row)) {
			return;
		}
		$rate = $this->getRowRate($row);

		$addedData = [];
		if(!empty($row['start'])) {
			$addedData['start'] = $row['start'];
		}
		if(!empty($row['end'])) {
			$addedData['end'] = $row['end'];
		}
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
						Billrun_Factory::log("Received Tax with empty decription on row {$row['stamp']} , Skiping...",Zend_log::DEBUG);
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
			Billrun_Factory::log('addLineVatableData failed: Tax data missing. data: ' . print_R($this->data, 1), Zend_Log::CRIT);
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
	public function aggregateLinesToBreakdown($subLines) {
		$untranslatedAggregationConfig = Billrun_Factory::config()->getConfigValue('billrun.invoice.aggregate.pipelines',array());
		$translations = array('BillrunKey' => $this->data['key']);
		$aggregationConfig  = json_decode(Billrun_Util::translateTemplateValue(json_encode($untranslatedAggregationConfig),$translations),JSON_OBJECT_AS_ARRAY);
		Billrun_Factory::log('Updating billrun object with aggregated lines for SID : ' . $this->data['sid']);
		$aggregate = new Billrun_Utils_Arrayquery_Aggregate();
		foreach($aggregationConfig as $brkdwnKey => $brkdownConfigs) {
			foreach($brkdownConfigs as $breakdownConfig) {
				$aggrResults = $aggregate->aggregate($breakdownConfig, $subLines);
				if($aggrResults) {
					foreach($aggrResults as $aggregateValue) {
						
						//$this->data['breakdown'][$brkdwnKey] = array();
						$key = ( empty($aggregateValue['name']) ? $aggregateValue['_id'] : $aggregateValue['name'] );
						$this->updateBreakdown($brkdwnKey, array('key'=> $key), $aggregateValue['price'], $aggregateValue['usagev'], array(),  array_merge(array_diff_key($aggregateValue,array('_id'=>1,'price'=>1,'usagev'=>1)),
						array('conditions' =>json_encode($breakdownConfig[0]['$match']))) );
					}
				}
			}
		}
		Billrun_Factory::log('Finished aggreating into billrun object for SID : ' . $this->data['sid']);
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
}
