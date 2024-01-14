<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_ExternalPricing extends Billrun_Calculator {

	const DEF_CALC_DB_FIELD = 'aprice';

	static protected $type = "ild_external_pricing";

	const STATE_WAITING = 'waiting';
	const STATE_PRICED = 'priced';
	const STATE_FAILED = 'failed';

	const RESULT_PRICED_OK = '0';
	const RESULT_PRICED_FAILED = [ '1','2','3','4','5' ];

	protected $FandMOpts = ['new'=>true,'w'=>1];


	protected $pricingField = self::DEF_CALC_DB_FIELD;
	protected $relevantRateKeysRegex = [];
	protected $keepFailedPricingCDRsInQueue =false;
	protected $extPricingActivationDate=false;

	public function __construct(array $options = array()) {
	    parent::__construct($options);
		$this->active_billrun = Billrun_Billrun::getActiveBillrun();
		$this->active_billrun_end_time = Billrun_Util::getEndTime($this->active_billrun);
		$this->next_active_billrun = Billrun_Util::getFollowingBillrunKey($this->active_billrun);

		$this->relevantRateKeysRegex = Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.relevant_rates',$this->relevantRateKeysRegex);
		$this->keepFailedPricingCDRsInQueue = Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.keep_failed_pricing_cdrs_in_queue',$this->keepFailedPricingCDRsInQueue);

		$this->extPricingActivationDate = new MongoDate( strtotime(Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.external_pricing_activation_date','2023-12-24')));
	}


	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		return $this->getQueuedLines([
				'external_pricing_state'=>['$nin'=>[static::STATE_FAILED]]
			]);
	}

	/**
	 * write the calculation into DB
	 */
	public function updateRow($row) {
		$rawRow = $row->getRawData();
		// if line needs external pricing:
		if($row['type'] == 'nsn') {
			//		if the line has been priced
			if ($row['external_pricing_state'] == static::STATE_PRICED) {
				//Add the  current cycle stamp
				$rawRow['billrun'] = $row['urt']->sec <= $this->active_billrun_end_time ? $this->active_billrun : $this->next_active_billrun;
				//	move it to the next stage of the queue
				$associatedQueueLine = Billrun_Factory::db()->queueCollection()->findAndModify(['type'=>'nsn','stamp'=>$row['stamp']],['$set'=>['external_pricing_state'=>static::STATE_PRICED]],$this->FandMOpts);
				 $this->lines[$row['stamp']]['external_pricing_state'] = static::STATE_PRICED;
				 $this->updatedBalanceCosts($row);
			} else if($row['external_pricing_state'] == static::STATE_FAILED) {
					$associatedQueueLine = Billrun_Factory::db()->queueCollection()->findAndModify(['type'=>'nsn','stamp'=>$row['stamp']],['$set'=>['external_pricing_state'=>static::STATE_FAILED]],$this->FandMOpts);
					$this->lines[$row['stamp']]['external_pricing_state'] = static::STATE_FAILED;
					return false;
			} else {//	otherwise (Thats  probably  the first time we see the line)
				if( empty($row['external_pricing_state']) ) {
					//unset from the cycle and mark the  line as waiting.
					unset($this->lines[$row['stamp']]['billrun']);
					unset($rawRow['billrun']);
					$rawRow['billrun_aprice'] = $rawRow['aprice'];
					$rawRow['external_pricing_state']  = $this->lines[$row['stamp']]['external_pricing_state'] = static::STATE_WAITING;
					//	line willbe forced to stay in the queue by the overriden setCalculatorTag function
					// return the updated line to save it to the db (at the  end of the function)

				} else {
					//  Line  was allready forced to stay in the  queue and no update needed as it still waiting for pricing.
					return false;
				}
			}
			$row->setRawData($rawRow);
		} else if($row['type'] == 'ild_external_pricing') { // if line is external pricing :
			$updateValues = [];
			//if the pricingwas succesful
			if($row['status'] == static::RESULT_PRICED_OK) {
				// update line  and  price it
				$updateValues = ['external_pricing_state'=>static::STATE_PRICED,'aprice'=> floatval($row['price'])];
			} else if( in_array($row['status'], static::RESULT_PRICED_FAILED) ) {
				// otherwise mark the original line as failed
				$updateValues = ['external_pricing_state'=>static::STATE_FAILED, 'external_pricing_status_code' => $row['status'] ];
				if($this->keepFailedPricingCDRsInQueue) {
					return false;
				}
				Billrun_Factory::db()->queueCollection()->findAndModify(['type'=>'nsn','stamp'=>$row['source_stamp']],['$set'=>$updateValues],$this->FandMOpts);
			} else {
				//keep the cdr in the queue
				Billrun_Factory::log("External pricing CDR with stamp {$row['stamp']} returned  with invalid  state : {$row['status']}.",Zend_Log::WARN);
				return false;
			}
			if(empty($row['source_stamp'])) {
				Billrun_Factory::log('No source stamp provided  for external pricing ',Zend_log::ERR);
				return false;
			}
			$output = Billrun_Factory::db()->linesCollection()->findAndModify(['type'=>'nsn','stamp'=>$row['source_stamp']],['$set'=>$updateValues],$this->FandMOpts);

			if (!(isset($output['_id']) && !empty($output['_id']))) {;
				//	if update unsecussful keep external pricing line in the queue.
				return  false;
			} else {
				//if update sucessfull update the queue line so it  will be processed as soon as possible (don't updateit  if it in the middle of calculation)
				Billrun_Factory::db()->queueCollection()->findAndModify(['type'=>'nsn','stamp'=>$row['source_stamp'],'calc_time'=>['$lt'=>strtotime('-2 minutes')]],['$set'=>['calc_time'=>false]]);
			}

		}
		return  $row;
	}

	public function isLineLegitimate($line) {
		//is the line need external pricing or is the line is external pricing and it has out/over plan usage

		return $line['urt'] > $this->extPricingActivationDate  && (
				$line['type'] === 'ild_external_pricing' ||
				$line['type'] =='nsn' && !empty($line['arate_key']) && !empty(array_filter($this->relevantRateKeysRegex, function ($rte) use ($line) {
					return preg_match('/^'.$rte.'$/',$line['arate_key']);})) && (!empty($line['over_plan']) ||!empty($line['out_plan'])));
	}

	 public function getCalculatorQueueType() {
		 return 'external_pricing';
	 }

	 	/**
	 * Mark the calculation as finished in the queue.
	 * @param array $query additional query parameters to the queue
	 * @param array $update additional fields to update in the queue
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		$calculator_tag = $this->getCalculatorQueueType();
		$stampsToAdvance  = array();
		$balancesToClear = [];

		$stampsState = [
			static::STATE_WAITING => [],
			static::STATE_PRICED => [],
			static::STATE_FAILED => [],
		];
		foreach ($this->lines as $item) {
			if(	@$item['external_pricing_state'] !== static::STATE_WAITING
				&&
				@$item['external_pricing_state'] !== static::STATE_FAILED
			) {
				$stampsToAdvance[] = $item['stamp'];
				$balancesToClear[] = [ 'stamp' => "{$item['stamp']}_external_pricing", 'sid' => $item['sid'] ];
			}
			if(!empty($item['external_pricing_state']) && isset($stampsState[$item['external_pricing_state']]) ) {
				$stampsState[$item['external_pricing_state']][] = $item['stamp'];
			}
		}
		//update queue external pricing state
		foreach($stampsState as $state => $currentStamps) {
			if(!empty($currentStamps)) {
				$query = array_merge($query, ['stamp' => ['$in' => $currentStamps ], 'hash' => $this->workHash, 'calc_time' => $this->signedMicrotime]);
				$update = array_merge($update, ['$set' => ['external_pricing_state' => $state]]);
				$this->queue_coll->update($query, $update, array('multiple' => true, 'w' => 1));
			}
		}
		//Advance queue lines  to the  next calculator
		$query = array_merge($query, array('stamp' => array('$in' => $stampsToAdvance ), 'hash' => $this->workHash, 'calc_time' => $this->signedMicrotime));
		$update = array_merge($update, array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false)));
		$this->queue_coll->update($query, $update, array('multiple' => true, 'w' => 1));

		// Remove  tx from the balance for lines  that were priced
		foreach($balancesToClear as $balanceToClear) {
			$updateQuery = [
				'sid' => $balanceToClear['sid'],
				"tx.{$balanceToClear['stamp']}" => ['$exists'=> 1 ]
			];

			Billrun_Balance::getCollection()->update($updateQuery,['$unset' => ["tx.{$balanceToClear['stamp']}" => 1]]);
		}

	}

	protected function updatedBalanceCosts($row) {
		if(!isset($row['billrun_aprice'],$row['aprice']) || !is_numeric($row['billrun_aprice']) || !is_numeric($row['aprice']) ) {
			Billrun_Factory::log('CDR missing pricing data to allow for balance cost adjustment, stamp :'.$row['stamp'], Zend_log::ERR);
			return false;
		}

		$adjutmemtAmount = $row['aprice'] - $row['billrun_aprice'];
		if(abs($adjutmemtAmount) > 0) {
			$adjutmentsUpdate = [
				'balance.totals.cost' => $adjutmemtAmount,
				'balance.cost' => $adjutmemtAmount,
			 ];
			if(!empty($row['arategroup'])) {
				$adjutmentsUpdate['balance.groups.'.$row['arategroup'].'.'.$row['usaget'].'.cost'] = $adjutmemtAmount;
			}

			$updateQuery = [
				'sid' => $row['sid'],
				'billrun_month'=> Billrun_Util::getBillrunKey($row->get('urt')->sec),
				"tx.{$row['stamp']}_external_pricing" => ['$exists'=> 0 ]
			];
			$options = array(
				'upsert' => true,
				'new' => false,
				'w' => 1,
			);
			$balance = Billrun_Balance::getCollection()->findAndModify($updateQuery, ['$inc'=> $adjutmentsUpdate, '$set' => ["tx.{$row['stamp']}_external_pricing" => 1]],[],$options);
			Billrun_Factory::dispatcher()->trigger('afterPricingDoneWithBalance',[$row, $balance, ['aprice'=> $adjutmemtAmount], $this]);
		}
	}

	public function getPricingField() {
		return 'aprice';
	}

	protected function getLineAdditionalValues($row){
		$ret = [];
		foreach($this->getAdditionalProperties() as $field) {
			$ret[$field] = $row[$field];
		}

		return $ret;
	}

	protected function getAdditionalProperties() {
		return array('external_pricing_state','generated','billrun_aprice');
	}
}
