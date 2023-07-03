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

	static protected $type = "ilds_external_pricing";

	const STATE_WAITING = 'waiting';
	const STATE_PRICED = 'priced';
	const STATE_FAILED = 'failed';

	const RESULT_PRICED_OK = 'ok';
	const RESULT_PRICED_FAILED = 'notok';


	protected $pricingField = self::DEF_CALC_DB_FIELD;

	protected $relevantRateKeys = [];

	public function __construct(array $options = array()) {
	    parent::__construct($options);
		$this->active_billrun = Billrun_Billrun::getActiveBillrun();
		$this->active_billrun_end_time = Billrun_Util::getEndTime($this->active_billrun);
		$this->next_active_billrun = Billrun_Util::getFollowingBillrunKey($this->active_billrun);

		$this->relevantRateKeys =  Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.relevant_rates',$this->relevantRateKeys);
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
		// if line needs external pricing:
		if($row['type'] == 'nsn') {
			//		if the line has been priced
			if ($row['external_pricing_state'] == static::STATE_PRICED) {
				//Add the  current cycle stamp
				$row['billrun']	 = $row['urt']->sec <= $this->active_billrun_end_time ? $this->active_billrun : $this->next_active_billrun;
				//	move it to the next stage of the queue
				$associatedQueueLine = Billrun_Factory::db()->queueCollection()->findAndModify([type=>'nsn','stamp'=>$row['stamp']],['$set'=>['external_pricing_state'=>static::STATE_PRICED]]);
			} else if($row['external_pricing_state'] == static::STATE_FAILED) {
					$associatedQueueLine = Billrun_Factory::db()->queueCollection()->findAndModify([type=>'nsn','stamp'=>$row['stamp']],['$set'=>['external_pricing_state'=>static::STATE_FAILED]]);
					return false;
			} else {//	otherwise
				if( empty($row['stamp']['external_pricing_state']) ) {
					//unset from the  cycle  and mark the  line as waiting.
					unset($this->lines[$row['stamp']]['billrun']);
					$this->lines[$row['stamp']]['external_pricing_state'] =static::STATE_WAITING;
				}
					//	force it to stay on the queue
					return false;
			}
		} else { // if line is external pricing :
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
			}

		}
		return  $row;
	}

	public function isLineLegitimate($line) {
		//is the line need external pricing or is the line is external pricing and it has out/over plan usage
		return $line['ild_external_pricing'] ||
				$line['nsn'] && in_array($line['arate_key'], $this->relevantRateKeys)  ;//&& (!empty($line['over_plan']) ||!empty($line['out_plan']));
	}

	 public function getCalculatorQueueType() {
		 return 'external_pricing';

	 }

}
