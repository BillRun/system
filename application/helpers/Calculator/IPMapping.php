<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 *
 * @package  calculator
 * @since    0.5
 *
 */
class Calculator_IPMapping extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ipmapping';

	protected $subFieldsToMap = ['datetime','internal_ip','external_ip','start_port','end_port','change_type','network' ];
	protected $associationDelaySec = 1200; // 20 minutes


	public function __construct($options = array()) {
		parent::__construct($options);
		$this->ipmapColl = Billrun_Factory::db()->ipmappingCollection();
		$this->associationDelaySec =  Billrun_Factory::config()->getConfigValue(static::$type.'.calculator.association_delay',$this->associationDelaySec);
	}

	/**
	 * @see Billrun_Calculator_Rate::updateRow
	 */
	public function updateRow($row)
	{
	Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
		$current = $row->getRawData();
		$ipmapping = $this->getLineIPMapping($row);

		if(empty($ipmapping)) {
			return FALSE;
		}

		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);

		$added_values = array(
			'ipmapping' => $ipmapping,
			'usaget' => $usage_type,
			'usagev' => $volume
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
		return $row;
	}

		/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		return $row['fbc_downlink_volume'] + $row['fbc_uplink_volume'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return 'data';
	}

	/**
	 * @see Billrun_Calculator::getLines
	 */
	protected function getLines() {
		return $this->getQueuedLines(array('type' => 'ggsn','urt'=> ['$lt'=> new MongoDate(time() - $this->associationDelaySec)]));
	}


	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineIPMapping($row) {
		$query = [ 'urt' => [ '$lte' => $row['urt'] ], 'internal_ip' => $row['served_pdp_address']];

		$mapping = $this->ipmapColl->query($query)->cursor()->sort(['urt'=>-1])->limit(1)->current();
		if( empty($mapping) || $mapping->isEmpty() ) {
			Billrun_Factory::log()->log("Couldn't find ip mapping for row : " . print_r($row['stamp'], 1), Zend_Log::DEBUG);
			return FALSE;
		}

		$retMapping = [];
		foreach($this->subFieldsToMap as $field) {
			if(isset($mapping[$field])) {
				$retMapping[$field] = $mapping[$field];
			}
		}

		return $retMapping;
	}

	protected function getAdditionalProperties() {
		return array_merge(array('ipmapping'), parent::getAdditionalProperties());
	}

		/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	public function getCalculatorQueueType() {
		return 'rate';
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'ggsn';
	}

}

