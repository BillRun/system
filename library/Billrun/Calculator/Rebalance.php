<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Rebalance calculator class for records
 *
 * @package  calculator
 * @since    2.5
 */
class Billrun_Calculator_Rebalance extends Billrun_Calculator {

	static protected $type = 'rebalance';
	
	/**
	 * see parent: isQueueCalc
	 */
	protected $isQueueCalc = false;

	public function __construct($options = array()) {
		parent::__construct($options);
	}

	public function calc() {
		Billrun_Factory::log("Execute reset", Zend_Log::INFO);

		$rebalance_queue = Billrun_Factory::db()->rebalance_queueCollection();
		$limit = Billrun_Config::getInstance()->getConfigValue('resetlines.limit', 10);
		$offset = Billrun_Config::getInstance()->getConfigValue('resetlines.offset', '1 hour');
		$query = array(
			'creation_date' => array(
				'$lt' => new MongoDate(strtotime($offset . ' ago')),
			),
			'end_time' => array(
				'$exists' => false,
			)
		);
		$sort = array(
			'creation_date' => 1,
		);
		$results = $rebalance_queue->find($query)->sort($sort)->limit($limit);

		$billruns = array();
		$all_aids = array();
		$conditions = array();
		$stampsByBillrunAndAid = array();
		$rebalanceStamps = array();
		foreach ($results as $result) {
			$billruns[$result['billrun_key']][] = ['aid' => $result['aid'], 'stamp' => $result['stamp']];
			$rebalanceStamps[$result['billrun_key']][$result['aid']][$result['conditions_hash']] = $result['stamp'];
			$conditions[$result['billrun_key']][$result['aid']][$result['conditions_hash']] = $result['conditions'];
                        if(!empty($result['stamps_by_sid'])){
				$stampsByBillrunAndAid[$result['billrun_key']][$result['aid']] = $result['stamps_by_sid'];
			}
                        if(!empty($result['stamps_recover_path'])){
				$stampsBySid = $this->getStampsByStampsRecoverFile($result['stamps_recover_path']);
                            if(!empty($stampsBySid)){
					$stampsByBillrunAndAid[$result['billrun_key']][$result['aid']] = $stampsBySid;
				}
			}
			$all_aids[] = $result['aid'];
		}

		foreach ($billruns as $billrun_key => $data) {
			$model = new ResetLinesModel(array_column($data, 'aid'), $billrun_key, $conditions[$billrun_key], $rebalanceStamps[$billrun_key], $stampsByBillrunAndAid[$billrun_key] ?? []);
			try {
				Billrun_Factory::log("Updating cycle $billrun_key reset start time", Zend_Log::DEBUG);
				$this->updateResetTime($data);
				$ret = $model->reset();
				Billrun_Factory::log("Updating cycle $billrun_key reset end time", Zend_Log::DEBUG);
				$this->updateResetTime($data, false);
				if (isset($ret['err']) && !is_null($ret['err'])) {
					return FALSE;
				}
			} catch (Exception $exc) {
				Billrun_Factory::log('Error resetting aids ' . implode(',', array_column($data, 'aid')) . ' of billrun ' . $billrun_key . '. Error was ' . $exc->getMessage() . ' : ' . $exc->getTraceAsString(), Zend_Log::ALERT);
				return FALSE;
			}
		}
		Billrun_Factory::log("Success resetting aids " . implode(',', $all_aids), Zend_Log::INFO);
		return true;
	}
        
        protected function getStampsByStampsRecoverFile($path){
            $myfile = fopen($path, 'r');
            if(!$myfile){
                Billrun_Factory::log("Failed to open file. Failed to get content from recover stamps file. path: " . $path, Zend_Log::ALERT);
                return [];
            }
            $ret = fread($myfile, filesize($path));
            fclose($myfile);
            if(!$ret){
                Billrun_Factory::log("Failed to read file. Failed to get content from recover stamps file. path: " . $path, Zend_Log::ALERT);
            }
            return json_decode($ret, true);
            
        }

        protected function getLines() {
		return array();
	}

	protected function updateResetTime($data, $start = true){
		$relevant_stamps = array_column($data, 'stamp');
		$field_name = $start ? 'start_time' : 'end_time';
		$updateQuery = array(
            '$set' => array(
                $field_name => new Mongodate()
            )
        );
		Billrun_Factory::db()->rebalance_queueCollection()->update(['stamp' => array('$in' => $relevant_stamps)], $updateQuery, array('multiple' => 1));
	}
	
	protected function isLineLegitimate($line) {
		return true;
	}

	public function getCalculatorQueueType() {
		return '';
	}

	public function updateRow($row) {
		return true;
	}

	public function write() {
		
	}

	public function removeFromQueue() {
		
	}
	
	public function prepareData($lines) {
		
	}

}
