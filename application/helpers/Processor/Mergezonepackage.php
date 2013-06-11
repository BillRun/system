<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Processor
 * @copyright  Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for merge data rates into rates collection
 *
 * @package    Processor
 * @subpackage MergeRates
 * @since      1.0
 */
class Processor_Mergezonepackage extends Billrun_Processor_Base_Separator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'mergerates';
	static protected $nsoftPLanToGolanPlan = array(
		'SMALL' => array('ZG_HAVILA_SMS', 'ZG_HAVILA_VOICE', 'ZG_HAVILA_MMS'),
		'LARGE' => array('ZG_HAVILA_SMS', 'ZG_HAVILA_VOICE', 'ZG_HAVILA_MMS', 'ZG_HAVILA_HOOL', 'ZG_NATIONAL'),
	);
	static protected $typesTranslation = array(
		'M' => 'sms',
		'C' => 'call',
		'A' => 'call',
		'I' => 'data',
		'N' => 'mms'
	);

	public function __construct($options) {
		parent::__construct($options);
		$header = $this->getLine();
		$this->parser->setStructure($header);
	}

	/**
	 * we do not need to log
	 * 
	 * @return boolean true
	 */
	public function logDB() {
		return TRUE;
	}

	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}


		while ($line = $this->getLine()) {
			$this->parseData($line);
		}

		return true;
	}

	/**
	 * method to parse data
	 * 
	 * @param array $line data line
	 * 
	 * @return array the data array
	 */
	protected function parseData($line) {
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$row = $this->parser->parse();
		$row['source'] = static::$type;
		$row['type'] = self::$type;
//		$row['header_stamp'] = $this->data['header']['stamp'];
		$row['file'] = basename($this->filePath);
		$row['process_time'] = date(self::base_dateformat);
		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
		$this->data['data'][] = $row;
		return $row;
	}

	protected function store() {
		if (!isset($this->data['data'])) {
			// raise error
			return false;
		}

		$rates = Billrun_Factory::db()->ratesCollection();

		$this->data['stored_data'] = array();

		foreach ($this->data['data'] as $key => $row) {
			$type = self::$typesTranslation[$row['zoneGroupEltId_tariffKind']];
			if (isset($row['zoneGroupEltId_tariffItem'])) {
				//$key = $row['zoneGroupEltId_tariffItem'];
				$entity = $rates->query('key', $row['zoneGroupEltId_tariffItem'])->cursor()->current();
					
				if ($entity->getId()) {
					$entity->collection($rates);

					if (isset($entity['rates'][$type])) {
						Billrun_Factory::log()->log("Setting plan for : " . $entity->getId(), Zend_Log::DEBUG);
						$rowRates = $entity['rates'];
						$plans = isset($rowRates[$type]['plans']) ? $rowRates[$type]['plans'] : array();
						foreach (self::$nsoftPLanToGolanPlan as $planName => $nsoftVal) {
							if (in_array($row['zoneGroupEltId_zoneGroupId_zoneGroupName'], $nsoftVal)) {
								$plan_ref = Billrun_Model_Plan::getPlanRef($planName);
								if ($plan_ref) {
									$plan_ref_id = $plan_ref->getMongoID();
									if (!in_array($plan_ref_id, $plans)) {
										$plans[] = $plan_ref_id;
									}
								}
							}
						}
						$rowRates[$type]['plans'] = $plans;
						$entity['rates'] = $rowRates;
					}

					$entity->save($rates);
					$this->data['stored_data'][] = $row;
				} else {
					echo $row['zoneGroupEltId_tariffItem'] . "  when  trying to merge plans zone not found" . PHP_EOL . "<br />";
				}
			}
		}

		return true;
	}

}
