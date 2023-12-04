<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the plan data to be aggregated.
 */
class Billrun_Cycle_Data_Plan extends Billrun_Cycle_Data_Line {

	use Billrun_Traits_ForeignFields;
	
	protected static $copyFromChargeData = ['prorated_start', 'prorated_end', 'prorated_start_date', 'is_upfront'];
	protected $plan = null;
	protected $name = null;
	protected $start = 0;
	protected $end = PHP_INT_MAX;
	protected $cycle;
	protected $tax = [];
	protected $foreignFields = array();

	public function __construct(array $options) {
		parent::__construct($options);
		if (!$this->verifyConstrctionOptions($options)) {
			Billrun_Factory::log("Invalid aggregate data for : ".get_class($this), Zend_Log::ERR);
		}
		$this->name = $options['plan'];
		$this->plan = $options['plan'];
		$this->cycle = $options['cycle'];
		$this->tax = Billrun_Util::getIn($options, 'tax', []);
		$this->start = Billrun_Util::getFieldVal($options['start'], $this->start);
		$this->end = Billrun_Util::getFieldVal($options['end'], $this->end);
		$this->foreignFields = $this->getForeignFields(array('plan' => $options), $this->stumpLine);
	}

	protected  function verifyConstrctionOptions($options) {
	 return isset($options['plan'], $options['cycle']);
	}

	protected function getCharges($options) {
		$charger = new Billrun_Plans_Charge();
		//Only charge  if the configuration suggest it should be in the cycle
		return empty($options['cycle']) || Billrun_Utils_Cycle::shouldBeInCycle($options,$options['cycle'])  ?
					$charger->charge($options, $options['cycle']) :
					[];
	}

	protected function getLine($chargeingKey, $chargeData) {

		$entry = $this->getFlatLine();
		$entry['aprice'] = $chargeData['value'];
		$entry['full_price'] = $chargeData['full_price'];
		$entry['charge_op'] = $chargeingKey;
		$entry['tax'] = $this->tax;
		if (isset($chargeData['cycle'])) {
			$entry['cycle'] = $chargeData['cycle'];
		}
		$chargeFieldsToCopy = array_merge(	Billrun_Factory::config()->getConfigValue('plans.plan_charge_fields_to_copy.fields',["start_date","end_date"]),
											self::$copyFromChargeData );
		foreach($chargeFieldsToCopy as $field) {
			if( isset($chargeData[$field]) ) {
				$entry[$field] = $chargeData[$field];
			}
		}
		if (!empty($chargeData['start']) && $this->cycle->start() < $chargeData['start']) {
			$entry['start'] = new Mongodloid_Date($chargeData['start']);
		}
		if (!empty($chargeData['end']) && $this->cycle->end() - 1 > $chargeData['end']) {
			$entry['end'] = new Mongodloid_Date($chargeData['end']);
		}

		$entry['stamp'] = $this->generateLineStamp($entry);

		$entry = $this->addExternalFoerignFields($entry,$chargeData);
		$entry = $this->addTaxationToLine($entry);
		unset($entry['tax']);
		foreach ($this->subscriberFields as $fieldName => $value) {
			$entry['subscriber'][$fieldName] = $value;
		}
		foreach ($this->subscriberFields as $fieldName => $value) {
			$entry['subscriber'][$fieldName] = $value;
		}
		
		if (!empty($this->plan)) {
			$entry['plan'] = $this->plan;
		}
		return $entry;
	}

	protected function getFlatLine() {
		$flatEntry = array(
			'plan' => $this->plan,
			'name' => $this->name,
			'process_time' => new Mongodloid_Date(),
			'usagev' => 1
		);

		if (FALSE !== $this->vatable) {
			$flatEntry['vatable'] = TRUE;
		}

		
		return array_merge($flatEntry, $this->stumpLine);
	}
	
	protected function addExternalFoerignFields($entry, $addedQueryData =[]) {
		return array_merge($this->getForeignFields(array(), array_merge($this->foreignFields, $entry), true, $addedQueryData), $entry);
	}

	protected function generateLineStamp($line) {
		return md5(	$line['charge_op'] . '_' . $line['aid'] . '_' . $line['sid'] . $this->plan . '_' . $this->cycle->start() .
					 $this->cycle->key() . '_' . $line['aprice'] . $this->start . @$line['prorated_start_date']);
	}
	
	//TODO move this to the account/subscriber lines addition logic and work in batch mode.
	protected function addTaxationToLine($entry) {
		$entryWithTax = FALSE;
		for ($i = 0; $i < 3 && !$entryWithTax; $i++) {//Try 3 times to tax the line.
			$taxCalc = Billrun_Calculator::getInstance(array('autoload' => false, 'type' => 'tax'));
			$entryWithTax = $taxCalc->updateRow($entry);
			if (!$entryWithTax) {
				Billrun_Factory::log("Taxation of {$entry['name']} failed. Retrying...", Zend_Log::WARN);
				sleep(1);
			}
		}
		if (!empty($entryWithTax)) {
			$entry = $entryWithTax;
		} else {
			throw new Exception("Couldn`t tax flat line {$entry['name']} for aid: {$entry['aid']} , sid : {$entry['sid']}");
		}

		return $entry;
	}

}
