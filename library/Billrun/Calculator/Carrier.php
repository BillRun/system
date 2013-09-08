<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Carrier
 *
 * @author eran
 */
class Billrun_Calculator_Carrier extends Billrun_Calculator {

	const MAIN_DB_FIELD = 'carir';

	/**
	 * The rating field to update in the CDR line.
	 * @var string
	 */
	protected $ratingField = self::MAIN_DB_FIELD;

	/**
	 * @see Billrun_Calculator_Base_Rate
	 * @var type 
	 */
	protected $linesQuery = array('type' => array('$in' => array('nsn')));
	protected $carriers = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['lines_query'])) {
			$this->linesQuery = $options['lines_query'];
		}

		$this->setCarriers();
	}

	protected function getLines() {

		return $this->getQueuedLines(array());
	}

	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));
		$carrierOut = $this->detectCarrierOut($row);
		$carrierIn = $this->detectCarrierIn($row);
		$current = $row->getRawData();

		$added_values = array(
			$this->ratingField => $carrierOut ? $carrierOut->createRef(Billrun_Factory::db()->carriersCollection()) : $carrierOut,
			$this->ratingField . '_in' => $carrierIn ? $carrierIn->createRef(Billrun_Factory::db()->carriersCollection()) : $carrierIn,
		);

		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
		return true;
	}

	/**
	 * Get the outgoing carrier for the line
	 * @param type $row the  row to get the out going carrier to.
	 * @return Mongodloid_Entity the  carrier object in the DB.
	 */
	protected function detectCarrierOut($row) {
		foreach ($this->carriers as $carrier) {
			if ( $row['record_type'] == '09' ) {
				if ($carrier['key'] != 'GOLAN') {
					continue;
				} else {
					return $carrier;
				}
			}
			if ( $row['record_type'] == '08' && isset( $carrier['identifiction']['sms_centre'] ) ) {
				if (!in_array(substr($row['sms_centre'], 0, 5), $carrier['identifiction']['sms_centre'])) {
					continue;
				} else {
					return $carrier;
				}
			}
			if (is_array($carrier['identifiction']['group_name']) && in_array($this->getCarrierName($row['out_circuit_group_name']), $carrier['identifiction']['group_name'])) {
				return $carrier;
			}
		}
	}

	/**
	 * Get the incoming carrier for the line
	 * @param type $row the row to get  the incoming carrier to.
	 * @return Mongodloid_Entity the carrier object in the DB.	
	 */
	protected function detectCarrierIn($row) {
		foreach ($this->carriers as $carrier) {
			if ( $row['record_type'] == '08') {
				if ($carrier['key'] != 'GOLAN') {
					continue;
				} else {
					return $carrier;
				}
			}
			if ( $row['record_type'] == '09' && isset( $carrier['identifiction']['sms_centre'] )) {
				if (!in_array(substr($row['sms_centre'], 0, 5), $carrier['identifiction']['sms_centre'])) {
					continue;
				} else {
					return $carrier;
				}
			}
			if (is_array($carrier['identifiction']['group_name']) && in_array($this->getCarrierName($row['in_circuit_group_name']), $carrier['identifiction']['group_name'])) {
				return $carrier;
			}
		}
	}

	/**
	 * get the  carrier identifier  from the group name  fields
	 * @param type $groupName the  group name to get the carrier identifer to.
	 * @return string containing the carrier identifer.
	 */
	protected function getCarrierName($groupName) {

		return $groupName === "" ? "" : substr($groupName, 0, min(4, strlen($groupName)));
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	protected static function getCalculatorQueueType() {
		return self::MAIN_DB_FIELD;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	protected function isLineLegitimate($line) {
		return $line['type'] == 'nsn';
	}

	protected function setCarriers() {
		$coll = Billrun_Factory::db()->carriersCollection();
		$this->carriers = array();
		foreach($coll->query() as $carrier) {
			$this->carriers[] =$carrier;
		}
	}

}

