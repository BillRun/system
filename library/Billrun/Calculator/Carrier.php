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
	const DEF_CALC_DB_FIELD = 'carir';
	
	/**
	 * The rating field to update in the CDR line.
	 * @var string
	 */
	protected $ratingField = self::DEF_CALC_DB_FIELD;

	/**
	 * @see Billrun_Calculator_Base_Rate
	 * @var type 
	 */
	protected $linesQuery = array('type' => 'nsn');

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['lines_query'])) {
			$this->linesQuery = $options['lines_query'];
		}
	}

	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query($this->linesQuery)
				->notExists($this->ratingField)->cursor()->limit($this->limit);
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
	}

	/**
	 * Get the out going carrier for the line
	 * @param type $row the  row to get  the  out going carrier to.
	 * @return Mongodloid_Entity the  carrier object in the DB.
	 */
	protected function detectCarrierOut($row) {	
		$query = array('identifiction.group_name' => array(
						'$in'=> array(substr($row['out_circuit_group_name'], 0, 4))
					));
		if(in_array($row['record_type'],array('08','09'))) {
				$query = array('identifiction.sms_centre' => array(
						'$in'=> array(substr($row['sms_centre'],0,5))
					));
		}
		return Billrun_Factory::db()->carriersCollection()->query($query)->cursor()->current();
	}

	/**
	 * Get the incoming carrier for the line
	 * @param type $row the  row to get  the incoming carrier to.
	 * @return Mongodloid_Entity the carrier object in the DB.	
	 */
	protected function detectCarrierIn($row) {
		$query = array('identifiction.group_name' => array(
						'$in'=> array(substr($row['in_circuit_group_name'], 0, 4))
					));
		if(in_array($row['record_type'],array('08','09'))) {
				$query = array('identifiction.sms_centre' => array(
						'$in'=> array(substr($row['sms_centre'],0,5))
					));
		}
		return Billrun_Factory::db()->carriersCollection()->query($query)->cursor()->current();
	}

}

