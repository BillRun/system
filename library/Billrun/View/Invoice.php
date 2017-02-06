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
		$typeMapping = array('flat' => 'name', 'service' => 'name');
		if(in_array($line['type'],array_keys($typeMapping))) {
			$usageName = $line[$typeMapping[$line['type']]];
		} else {
			$usageName = $line['arate_key'];
		}
		return ucfirst(strtolower(preg_replace('/_/', ' ', $usageName)));
	}
	
	public function buildSubscriptionListFromLines($lines) {
		$subscriptionList = array();
		$typeNames = array_flip($this->details_keys);
		foreach($lines as $subLines) {
			foreach($subLines as $line) {
				if(in_array($line['type'],$this->flat_line_types) && $line['aprice'] != 0) {
					if($line['type'] == 'credit') {
						$flatData = Billrun_Rates_Util::getRateByRef($line['arate'])['rates.call.BASE.rate'][0];
					} else {
						$flatRate = $line['type'] == 'flat' ? 
							new Billrun_Plan(array('name'=> $line['name'], 'time'=> $line['urt']->sec)) : 
							new Billrun_Service(array('name'=> $line['name'], 'time'=> $line['urt']->sec));
						$flatData = $flatRate->getData();
					}
					$line->collection(Billrun_Factory::db()->linesCollection());
					$name = $this->getLineUsageName($line);
					$subscriptionList[$name]['desc'] = $name;	
					$subscriptionList[$name]['type'] = $typeNames[$line['type']];
					//TODO : HACK : this is an hack to add rate to the highcomm invoice need to replace is  with the actual logic once the  pricing  process  will also add the  used rates to the line pricing information.
					$subscriptionList[$name]['rate'] = max(@$subscriptionList[$name]['rate'],(isset($flatData['price'][0]['price']) ? $flatData['price'][0]['price'] : $flatData['price']));
					@$subscriptionList[$name]['count']++;
					$subscriptionList[$name]['amount'] = Billrun_Util::getFieldVal($subscriptionList[$name]['amount'],0) + $line['aprice'];
				}
			}
		}
		return $subscriptionList;
	}
}
