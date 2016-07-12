<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Reset model class to reset subscribers balance & lines for a billrun month
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class ResetLinesModel {

	/**
	 *
	 * @var array
	 */
	protected $sids;

	/**
	 *
	 * @var string
	 */
	protected $billrun_key;

	/**
	 * Don't get newly stuck lines because they might have not been inserted yet to the queue
	 * @var string
	 */
	protected $process_time_offset;

	public function __construct($sids, $billrun_key) {
		$this->sids = $sids;
		$this->billrun_key = strval($billrun_key);
		$this->process_time_offset = Billrun_Config::getInstance()->getConfigValue('resetlines.process_time_offset', '15 minutes');
	}

	public function reset() {
		Billrun_Factory::log('Reset subscriber activated', Zend_Log::INFO);
		$ret = $this->resetLines();
		return $ret;
	}

	/**
	 * Removes the balance doc for each of the subscribers
	 */
	public function resetBalances($sids) {
		$ret = true;
		$balances_coll = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
		if (!empty($this->sids) && !empty($this->billrun_key)) {
			$query = array(
				'billrun_month' => $this->billrun_key,
				'sid' => array(
					'$in' => $sids,
				),
			);
			$ret = $balances_coll->remove($query); // ok ==1 && n>0
		}
		return $ret;
	}

	/**
	 * Get the reset lines query.
	 * @param array $update_sids - Array of sid's to reset.
	 * @return array Query to run in the collection for reset lines.
	 */
	protected function getResetLinesQuery($update_sids) {
		return array(
			'$or' => array(
				array(
					'billrun' => $this->billrun_key
				),
				array(
					'billrun' => array(
						'$exists' => FALSE,
					),
					'urt' => array(// resets non-billable lines such as ggsn with rate INTERNET_VF
						'$gte' => new MongoDate(Billrun_Billrun::getStartTime($this->billrun_key)),
						'$lte' => new MongoDate(Billrun_Billrun::getEndTime($this->billrun_key)),
					)
				),
			),
			'sid' => array(
				'$in' => $update_sids,
			),
			'type' => array(
				'$ne' => 'credit',
			),
			'process_time' => array(
				'$lt' => date(Billrun_Base::base_dateformat, strtotime($this->process_time_offset . ' ago')),
			),
		);
	}

	/**
	 * Reset lines for subscribers based on input array of SID's
	 * @param array $update_sids - Array of subscriber ID's to reset.
	 * @param array $advancedProperties - Array of advanced properties.
	 * @param Mongodloid_Collection $lines_coll - The lines collection.
	 * @param Mongodloid_Collection $queue_coll - The queue colection.
	 * @return boolean true if successful false otherwise.
	 */
	protected function resetLinesForSubscribers($update_sids, $advancedProperties, $lines_coll, $queue_coll) {
		$query = $this->getResetLinesQuery($update_sids);
		$lines = $lines_coll->query($query);
		$stamps = array();
		$queue_lines = array();
		$queue_line = array(
			'calc_name' => false,
			'calc_time' => false,
			'skip_fraud' => true,
		);

		// Go through the collection's lines and fill the queue lines.
		foreach ($lines as $line) {
			$stamps[] = $line['stamp'];
			$this->buildQueueLine($queue_line, $line, $advancedProperties);
			$queue_lines[] = $queue_line;
		}

		// If there are stamps to handle.
		if ($stamps) {
			// Handle the stamps.
			if (!$this->handleStamps($stamps, $queue_coll, $queue_lines, $lines_coll, $update_sids)) {
				return false;
			}
		}
	}

	/**
	 * Removes lines from queue, reset added fields off lines and re-insert to queue first stage
	 * @todo support update/removal of credit lines
	 */
	protected function resetLines() {
		$lines_coll = Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY');
		$queue_coll = Billrun_Factory::db()->queueCollection()->setReadPreference('RP_PRIMARY');
		if (empty($this->sids) || empty($this->billrun_key)) {
			// TODO: Why return true?
			return true;
		}

		$offset = 0;
		$configFields = array('imsi', 'msisdn', 'called_number', 'calling_number');
		$advancedProperties = Billrun_Factory::config()->getConfigValue("queue.advancedProperties", $configFields);

		while ($update_count = count($update_sids = array_slice($this->sids, $offset, 10))) {
			Billrun_Factory::log('Resetting lines of subscribers ' . implode(',', $update_sids), Zend_Log::INFO);
			$this->resetLinesForSubscribers($update_sids, $advancedProperties, $lines_coll, $queue_coll);
			$offset += 10;
		}

		return TRUE;
	}

	/**
	 * Construct the queue line based on the input line from the collection.
	 * @param array $queue_line - Line to construct.
	 * @param array $line - Input line from the collection.
	 * @param array $advancedProperties - Advanced config properties.
	 */
	protected function buildQueueLine($queue_line, $line, $advancedProperties) {
		$queue_line['stamp'] = $line['stamp'];
		$queue_line['type'] = $line['type'];
		$queue_line['urt'] = $line['urt'];

		foreach ($advancedProperties as $property) {
			if (isset($line[$property]) && !isset($queue_line[$property])) {
				$queue_line[$property] = $line[$property];
			}
		}
	}

	/**
	 * Get the query to update the lines collection with.
	 * @return array - Query to use to update lines collection.
	 */
	protected function getUpdateQuery() {
		return array(
			'$unset' => array(
				//						'aid' => 1,
				//						'sid' => 1,
				'apr' => 1,
				'aprice' => 1,
				'arate' => 1,
				'arategroup' => 1,
				'billrun' => 1,
				'in_arate' => 1,
				'in_group' => 1,
				'in_plan' => 1,
				'out_plan' => 1,
				'over_arate' => 1,
				'over_group' => 1,
				'over_plan' => 1,
				'plan' => 1,
				'usagesb' => 1,
				'usagev' => 1,
			),
			'$set' => array(
				'rebalance' => new MongoDate(),
			),
		);
	}

	/**
	 * Get the query to return all lines including the collected stamps.
	 * @param $stamps - Array of stamps to query for.
	 * @return array Query to run for the lines collection.
	 */
	protected function getStampsQuery($stamps) {
		return array(
			'stamp' => array(
				'$in' => $stamps,
			),
		);
	}

	/**
	 * Handle stamps for reset lines.
	 * @param array $stamps
	 * @param type $queue_coll
	 * @param type $queue_lines
	 * @param type $lines_coll
	 * @param type $update_sids
	 * @return boolean
	 */
	protected function handleStamps($stamps, $queue_coll, $queue_lines, $lines_coll, $update_sids) {
		$update = $this->getUpdateQuery();
		$stamps_query = $this->getStampsQuery($stamps);

		$ret = $queue_coll->remove($stamps_query); // ok == 1, err null
		if (isset($ret['err']) && !is_null($ret['err'])) {
			return FALSE;
		}

		$ret = $this->resetBalances($update_sids); // err null
		if (isset($ret['err']) && !is_null($ret['err'])) {
			return FALSE;
		}
		if (Billrun_Factory::db()->compareServerVersion('2.6', '>=') === true) {
			$ret = $queue_coll->batchInsert($queue_lines); // ok==true, nInserted==0 if w was 0
			if (isset($ret['err']) && !is_null($ret['err'])) {
				return FALSE;
			}
		} else {
			foreach ($queue_lines as $qline) {
				$ret = $queue_coll->insert($qline); // ok==1, err null
				if (isset($ret['err']) && !is_null($ret['err'])) {
					return FALSE;
				}
			}
		}
		$ret = $lines_coll->update($stamps_query, $update, array('multiple' => 1)); // err null
		if (isset($ret['err']) && !is_null($ret['err'])) {
			return FALSE;
		}

		return true;
	}

}
