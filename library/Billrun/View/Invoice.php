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
	
	public $lines = array();
	
	/*
	 * get and set lines of the account
	 */
	public function add_lines() {
		$lines_collection = Billrun_Factory::db()->linesCollection();
		$this->lines = array();
		$aid = $this->data['aid'];
		$billrun_key = $this->data['billrun_key'];
		$query = array('aid' => $aid, 'billrun' => $billrun_key);
		$accountLines = $lines_collection->query($query);
		foreach ($accountLines as $line) {
			$sid = (string)$line['sid'];
			if (empty($this->lines[$sid])) {
				$this->lines[$sid] = array();
			}
			array_push($this->lines[$sid], $line);
		}
	}
	
	public function getLineUsageName($line) {
		$usageName = '';
		$rate = $this->getRateForLine($line);
		$typeMapping = array('flat' => array('rate'=> 'description','line'=>'name'), 
							 'service' => array('rate'=> 'description','line' => 'name'));
		
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
	
	public function buildSubscriptionListFromLines($lines) {
		$subscriptionList = array();
		$typeNames = array_flip($this->details_keys);
		foreach($lines as $subLines) {
			foreach($subLines as $line) {
				if(in_array($line['type'],$this->flat_line_types) && $line['aprice'] != 0) {
					$rate = $this->getRateForLine($line);
					$flatData =  ($line['type'] == 'credit') ? $rate['rates']['call']['BASE']['rate'][0] : $rate;
					
					$line->collection(Billrun_Factory::db()->linesCollection());
					$name = $this->getLineUsageName($line);
					$key = $this->getLineAggregationKey($line, $rate, $name);
					$subscriptionList[$key]['desc'] = $name;	
					$subscriptionList[$key]['type'] = $typeNames[$line['type']];
					//TODO : HACK : this is an hack to add rate to the highcomm invoice need to replace is  with the actual logic once the  pricing  process  will also add the  used rates to the line pricing information.
					$subscriptionList[$key]['rate'] = max(@$subscriptionList[$key]['rate'],(isset($flatData['price'][0]['price']) ? $flatData['price'][0]['price'] : $flatData['price']));
					@$subscriptionList[$key]['count']+= Billrun_Util::getFieldVal($line['usagev'],1);
					$subscriptionList[$key]['amount'] = Billrun_Util::getFieldVal($subscriptionList[$key]['amount'],0) + $line['aprice'];
				}
			}
		}
		return $subscriptionList;
	}
	
	protected function getRateForLine($line) {
		$rate = FALSE;
		if(!empty($line['arate'])) {
			$rate = @Billrun_Rates_Util::getRateByRef($line['arate'])->getRawData();
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
		if($line['type'] == 'service' && $rate['quantitative']) {
			$key .= $line['usagev']. $line['sid'];
		}
		return $key;
	}
}
