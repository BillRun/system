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

	const RESULT_PRICED_OK = 'ok';
	const RESULT_PRICED_FAILED = 'notok';


	protected $pricingField = self::DEF_CALC_DB_FIELD;

	protected $relevantRateKeysRegex = [];

	public function __construct(array $options = array()) {
	    parent::__construct($options);
		$this->active_billrun = Billrun_Billrun::getActiveBillrun();
		$this->active_billrun_end_time = Billrun_Util::getEndTime($this->active_billrun);
		$this->next_active_billrun = Billrun_Util::getFollowingBillrunKey($this->active_billrun);

		$this->relevantRateKeysRegex =  Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.relevant_rates',$this->relevantRateKeysRegex);
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
				$rawRow['billrun']	 = $row['urt']->sec <= $this->active_billrun_end_time ? $this->active_billrun : $this->next_active_billrun;
				//	move it to the next stage of the queue
				$associatedQueueLine = Billrun_Factory::db()->queueCollection()->findAndModify([type=>'nsn','stamp'=>$row['stamp']],['$set'=>['external_pricing_state'=>static::STATE_PRICED]]);
			} else if($row['external_pricing_state'] == static::STATE_FAILED) {
					$associatedQueueLine = Billrun_Factory::db()->queueCollection()->findAndModify([type=>'nsn','stamp'=>$row['stamp']],['$set'=>['external_pricing_state'=>static::STATE_FAILED]]);
					return false;
			} else {//	otherwise
				if( empty($row['stamp']['external_pricing_state']) ) {
					//unset from the cycle and mark the  line as waiting.
					unset($this->lines[$row['stamp']]['billrun']);
					unset($rawRow['billrun']);
					$rawRow['external_pricing_state']  = $this->lines[$row['stamp']]['external_pricing_state'] = static::STATE_WAITING;
				}
					//	line willbe forced to stay in the queue by the overriden setCalculatorTag function
					// return the updated line to save it to the db (at the  end of the function)
			}
			$row->setRawData($rawRow);
		} else if($row['type'] == 'ild_external_pricing') { // if line is external pricing :
			$updateValues = [];
			//if the pricingwas succesful
			if($row['status'] == static::RESULT_PRICED_OK) {
				// update line  and  price it
				$updateValues = ['external_pricing_state'=>static::STATE_PRICED,'aprice'=> $row['price']];
			} else {
				// otherwise mark the original line as failed
				$updateValues = ['external_pricing_state'=>static::STATE_FAILED];
			}
			$output = Billrun_Factory::db()->linesCollection()->findAndModify([type=>'nsn','stamp'=>$row['source_stamp']],['$set'=>$updateVlues],['new'=>true]);

			if (!($output['ok'] && isset($output['value']) && $output['value'])) {;
				//	if update unseccesfful keep external pricing line in the queue.
				return  false;
			} else {
				//if update sucessfull update the queue line o it  will be processed as soon as possible (don't updateit  if it in the middle of calculation)
				Billrun_Factory::db()->queueCollection()->findAndModify([type=>'nsn','stamp'=>$row['source_stamp'],'calc_time'=>['$lt'=>strtotime('-2 minutes')]],['$set'=>['calc_time'=>false]]);
			}

		}
		return  $row;
	}

	public function isLineLegitimate($line) {
		//is the line need external pricing or is the line is external pricing and it has out/over plan usage

		return $line['ild_external_pricing'] ||
				$line['type'] =='nsn' && !empty($line['arate_key']) && !empty(array_filter($this->relevantRateKeysRegex, function ($rte) use ($line) {
					return preg_match('/^'.$rte.'$/',$line['arate_key']);}))   ;//TODO DEBUG && (!empty($line['over_plan']) ||!empty($line['out_plan']));
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
				$stampsToAdvance [] = $item['stamp'];
			}
			foreach($stampsState as $state => &$currentStamps) {
				if($state == @$item['external_pricing_state'] ) {
					$currentStamps[] = $item['stamp'];
				}
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


	}


	protected function getLineAdditionalValues($row){
		$ret = [];
		foreach($this->getAdditionalProperties() as $field) {
			$ret[$field] = $row[$field];
		}

		return $ret;
	}

	protected function getAdditionalProperties() {
		return array('external_pricing_state','generated');
	}

}
