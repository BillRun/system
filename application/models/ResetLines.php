<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
		Billrun_Factory::log()->log('Reset subscriber activated', Zend_Log::INFO);
		$ret = $this->resetLines();
		return $ret;
	}

	/**
	 * Removes the balance doc for each of the subscribers
	 */
	public function resetBalances($sids, $lineStamps) {
		Billrun_Factory::dispatcher()->trigger('beforeResetBalances', array($sids));
		$ret = true;
		$balances_coll = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
		if (!empty($this->sids) && !empty($this->billrun_key)) {
			$query = array(
				'billrun_month' => $this->billrun_key,
				'sid' => array(
					'$in' => $sids,
				),
			);
			$ret = $balances_coll->remove($query, array('w' => 1)); // ok ==1 && n>0
			Billrun_Factory::dispatcher()->trigger('afterResetBalances', array($sids, $lineStamps, $this->billrun_key));
		}		
		return $ret;
	}

	/**
	 * Removes lines from queue, reset added fields off lines and re-insert to queue first stage
	 * @todo support update/removal of credit lines
	 */
	protected function resetLines() {
		$lines_coll = Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY');
		$queue_coll = Billrun_Factory::db()->queueCollection()->setReadPreference('RP_PRIMARY');
		if (!empty($this->sids) && !empty($this->billrun_key)) {
			$offset = 0;
			while ($update_count = count($update_sids = array_slice($this->sids, $offset, 10))) {
				Billrun_Factory::log()->log('Resetting lines of subscribers ' . implode(',', $update_sids), Zend_Log::INFO);
				$query = array(
					'$or' => array(
						array(
							'billrun' => $this->billrun_key
						),
						array(
							'billrun' => array(
								'$exists' => FALSE,
							),
							'urt' => array(// resets non-billable lines such as ggsn with rate INTERNET_VF
								'$gte' => new MongoDate(Billrun_Util::getStartTime($this->billrun_key)),
								'$lte' => new MongoDate(Billrun_Util::getEndTime($this->billrun_key)),
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
				$lines = $lines_coll->query($query);
				$stamps = array();
				$queue_lines = array();
				foreach ($lines as $line) {
					Billrun_Factory::dispatcher()->trigger('beforeResetLines', array($line));
					$stamps[] = $line['stamp'];  
					$queue_line = array(
						'calc_name' => false,
						'calc_time' => false,
						'skip_fraud' => true,
						'rebalance' => new MongoDate(),
					);
					
					// todo: refactoring
				
					$line = $line->getRawData();
					$updated_queue_line = array_merge($queue_line, $line);
					$queue_lines[] = $updated_queue_line;
				}
				$stamps_query = array(
					'stamp' => array(
						'$in' => $stamps,
					),
				);
				$update = array(
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
					   'external_pricing_state' => 1,
//						'usagev' => 1,
					),
					'$set' => array(
						'rebalance' => new MongoDate(),
						'in_queue' => true,
					),
				);

				if ($stamps) {
					$ret = $queue_coll->remove($stamps_query, array('w' => 1)); // ok == 1, err null
					if (isset($ret['err']) && !is_null($ret['err'])) {
						return FALSE;
					} else {
						$ret = $this->resetBalances($update_sids, $stamps); // err null
						if (isset($ret['err']) && !is_null($ret['err'])) {
							return FALSE;
						} else {
							if (Billrun_Factory::db()->compareServerVersion('2.6', '>=') === true) {
								$ret = $queue_coll->batchInsert($queue_lines, array('w' => 1)); // ok==true, nInserted==0 if w was 0
								if (isset($ret['err']) && !is_null($ret['err'])) {
									return FALSE;
								}
							} else {
								foreach ($queue_lines as $qline) {
									$ret = $queue_coll->insert($qline, array('w' => 1)); // ok==1, err null
									if (isset($ret['err']) && !is_null($ret['err'])) {
										return FALSE;
									}
								}
							}
							$ret = $lines_coll->update($stamps_query, $update, array('multiple' => 1, 'w' => 1)); // err null
							if (isset($ret['err']) && !is_null($ret['err'])) {
								return FALSE;
							}
						}
					}
				}
				$offset += 10;
			}
		}
		return TRUE;
	}

}
