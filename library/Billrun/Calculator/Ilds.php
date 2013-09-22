<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once __DIR__ . '/../../../application/golan/' . 'subscriber.php';

/**
 * Billing calculator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class Billrun_Calculator_Ilds extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "ilds";
	
	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
			->equals('source', static::$type)
			->notExists('price_customer');
//			->notExists('price_provider'); // @todo: check how to do or between 2 not exists		
	}
	
	/**
	 * Execute the calculation process
	 */
	public function calc() {

		Billrun_Factory::dispatcher()->trigger('beforeCalculateData', array('data' => $this->data));
		foreach ($this->data as $item) {
			$this->updateRow($item);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculateData', array('data' => $this->data));
	}
	
	/**
	 * update the billing line with stamp to avoid another aggregation
	 *
	 * @param int $subscriber_id the subscriber id to update
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillingLine($subscriber, $line) {
		if (isset($subscriber['id'])) {
			$subscriber_id = $subscriber['id'];
		} else {
			// todo: alert to log
			return false;
		}
		$current = $line->getRawData();
		$added_values = array(
			'account_id' => $subscriber['account_id'],
			'subscriber_id' => $subscriber_id,
		);
		
		if (isset($subscriber['account_id'])) {
			$added_values['account_id'] = $subscriber['account_id'];
		}

		$newData = array_merge($current, $added_values);
		$line->setRawData($newData);
		return true;
	}
	
	public function  update_subscriber_details() {
		foreach ($this->data as $item) {
			if($item['source'] == 'api' && $item['type'] == 'refund') {
				$time = date("YmtHis", $item->get('unified_record_time')->sec);
				$phone_number = $item->get('NDC_SN');
			} else  {
				$time = $item->get('call_start_dt');
				$phone_number = $item->get('caller_phone_no');				
			}
			// @TODO make it configurable
			$previous_month = date("Ymt235959", strtotime("previous month"));

			if ($time > $previous_month) {
				Billrun_Factory::log()->log("time frame is not till the end of previous month " . $time . "; continue to the next line", Zend_Log::INFO);
				continue;
			}
			
			if (!$item->get('account_id') || !$item->get('subscriber_id')) {
				// load subscriber
				$subscriber = golan_subscriber::get($phone_number, $time);
				if (!$subscriber) {
					Billrun_Factory::log()->log("subscriber not found. phone:" . $phone_number . " time: " . $time, Zend_Log::INFO);
					continue;
				}
			} else {
				Billrun_Factory::log()->log("subscriber " . $item->get('subscriber_id') . " already in line " . $item->get('stamp'), Zend_Log::INFO);
				$subscriber = array(
					'account_id' => $item->get('account_id'),
					'id' => $item->get('subscriber_id'),
				);
			}
			
			$subscriber_id = $subscriber['id'];
			
			// update billing line with billrun stamp
			if (!$this->updateBillingLine($subscriber, $item)) {
				Billrun_Factory::log()->log("subscriber " . $subscriber_id . " cannot update billing line", Zend_Log::INFO);
				continue;
			}
		}
	}

	/**
	 * Execute write down the calculation output
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $item) {
			$item->save($lines);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}

	/**
	 * Write the calculation into DB
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$current = $row->getRawData();
		$charge = $this->calcChargeLine($row->get('type'), $row->get('call_charge'));
		$added_values = array(
			'price_customer' => $charge,
			'price_provider' => $charge,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	/**
	 * Method to calculate the charge from flat rate
	 *
	 * @param string $type the type of the charge (depend on provider)
	 * @param double $charge the amount of charge
	 * @return double the amount to charge
	 *
	 * @todo: refactoring it by mediator or plugin system
	 */
	protected function calcChargeLine($type, $charge) {
		switch ($type):
			case '012':
			case '015':
				$rating_charge = round($charge / 1000, 3);
				break;

			case '013':
			case '018':
				$rating_charge = round($charge / 100, 2);
				break;
			case '014':
			case '019':	
				$rating_charge = round($charge, 3);
				break;
			default:
				$rating_charge = floatval($charge);
		endswitch;
		return $rating_charge;
	}

}
