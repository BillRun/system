<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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

	public function __construct($sids, $billrun_key) {
		$this->sids = $sids;
		$this->billrun_key = $billrun_key;
	}

	public function reset() {
		Billrun_Factory::log()->log('Reset subscriber activated', Zend_Log::INFO);
		$this->resetBalances();
		$this->resetLines();
		return true;
	}

	/**
	 * Removes the balance doc for each of the subscribers
	 */
	public function resetBalances() {
		$balances_coll = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection();
		if (!empty($this->sids) && !empty($this->billrun_key)) {
			$query = array(
				'billrun_month' => $this->billrun_key,
				'sid' => array(
					'$in' => $this->sids,
				),
			);
			$balances_coll->remove($query);
		}
	}

	/**
	 * Removes lines from queue, reset added fields off lines and re-insert to queue first stage
	 * @todo support update/removal of credit lines
	 */
	public function resetLines() {
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$queue_coll = Billrun_Factory::db()->queueCollection();
		if (!empty($this->sids) && !empty($this->billrun_key)) {
			$offset = 0;
			while ($update_count = count($update_sids = array_slice($this->sids, $offset, 10))) {
				Billrun_Factory::log()->log('Resetting lines of subscribers ' . implode(',', $update_sids), Zend_Log::INFO);
				$query = array(
					'billrun' => $this->billrun_key,
					'sid' => array(
						'$in' => $update_sids,
					),
					'type' => array(
						'$ne' => 'credit',
					),
				);
				$lines = $lines_coll->query($query);
				$stamps = array();
				$queue_lines = array();
				foreach ($lines as $line) {
					$stamps[] = $line['stamp'];
					$queue_lines[] = array(
						'calc_name' => false,
						'calc_time' => false,
						'stamp' => $line['stamp'],
						'type' => $line['type'],
						'urt' => $line['urt'],
						'skip_fraud' => true,
					);
				}
				$remove = array(
					'stamp' => array(
						'$in' => $stamps,
					),
				);
				$update = array(
					'$unset' => array(
						'aid' => 1,
						'aprice' => 1,
						'arate' => 1,
						'billrun' => 1,
						'out_plan' => 1,
						'over_plan' => 1,
						'plan' => 1,
						'sid' => 1,
						'usagesb' => 1,
						'usagev' => 1,
					),
				);

				if ($stamps) {
					$queue_coll->remove($remove);
					$lines_coll->update($query, $update, array('multiple' => 1));
					$queue_coll->batchInsert($queue_lines);
				}
				$offset += 10;
			}
		}
	}

}
