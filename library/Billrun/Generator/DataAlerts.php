<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Generator_DataAlerts extends Billrun_Generator {
	/**
	 * The VAT value (TODO get from outside/config).
	 */

	const TIME_WINDOW = 10800; //3 hours in secs
	const THRESHOLD = 10000;
	const OVER_USAGE_THRESHOLD = 3;

	/**
	 * load the container the need to be generate
	 */
	public function load($initData = true) {
		$billrun = $this->db->getCollection(self::billrun_table);

		if ($initData) {
			$this->data = array();
		}

		$resource = $billrun->query()
			->equals('billrun', $this->getStamp());
//			->in('account_id', array('7024774','1218460', '8348358'))

		foreach ($resource as $entity) {
			$this->data[] = $entity;
		}

		print "aggregator entities loaded: " . count($this->data) . PHP_EOL;
	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		$subscribersOverUsage = array();
		foreach($this->data as $billrun_line) {
			$subscriberID = $billrun_line->get('subscriber_id');
			if(!isset($subscribersOverUsage[$subscriberID])) {
				$subscribersOverUsage[$subscriberID] = $billrun_line->get('data_alerts') ? $billrun_line->get('data_alerts') : 0;
			}
			if($billrun_line->get('data_usage.egsn.download') > THRESHOLD) {
				$subscribersOverUsage[$subscriberID] += $this->checkSubscriberOverUsage($subscriberID,$billrun_line);
			}
			if($subscribersOverUsage[$subscriberID] > OVER_USAGE_THRESHOLD) {
				$this->deactivateSubscriber($subscriberID);
			}
			$this->updateBillLine($billrun_line,$subscribersOverUsage[$subscriberID]);
		}

	}

	protected function checkSubscriberOverUsage($subscriberID,$BillrunLine) {
		$overUsageCount =0;
		$subscribersUsage = 0;
		foreach($this->getSubscriberLines($subscriberID) as $subscriberLine) {
			$subscribersUsage += $subscriberLine->get('fbc_downlink_volume');
			if($subscribersUsage > THRESHOLD) {
				$overUsageCount++;
				$this->notifyExcesiveUsage($subscriberID,$usage);
				$subscribersUsage -= THRESHOLD;
			}
		}
	}

	protected function notifyExcesiveUsage($subscriberID,$usage) {
		print("Over usage for subscriber : $subscriberID used : $usage");
		//TODO
	}

	protected function deactivateSubscriber($subscriberID) {
		print("Deactivating subscriber : $subscriberID");
		//TODO
	}
	protected function getSubscriberLines($subscriber_id) {
		$lines = $this->db->getCollection(self::lines_table);

		$ret = array();

		$resource = $lines->query()
			->greaterEq('last_save_time',time()-TIME_WINDOW)
			->equals('subscriber_id', "$subscriber_id");

		foreach ($resource as $entity) {
			$ret[] = $entity->getRawData();
		}

		return $ret;
	}

	protected function updateBillLine($billrunLine,$alertCount) {
			$data = $billrunLine->getRawData();
			if (!isset($data['data_alerts'])) {
				$data['data_alerts'] = $alertCount;
				$billrunLine->setRawData($data);
				$billrun_line->save();
			}
	}

}