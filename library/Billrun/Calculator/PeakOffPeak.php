<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator for marking  billing line  if they're  on peak  or off peak  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_PeakOffPeak extends Billrun_Calculator {
	const DEF_CALC_DB_FIELD = 'peak';
	
	/**
	 * The rating field to update in the CDR line.
	 * @var string
	 */
	protected $ratingField = self::DEF_CALC_DB_FIELD;
	
	/**
	 * @see Billrun_Calculator_Base_Rate
	 * @var type 
	 */
	protected $linesQuery = array('type' => 'nsn', '$or' => array(	
										array('in_circuit_group_name' => array('$regex' => '^\wBZ[QI]')),
										array('out_circuit_group_name' => array('$regex' => '^\wBZ[QI]'))
								));
	
	/**
	 * Array holding all the peak off peak times for a given day type, in hours of the day.
	 * @param array $peakTimes
	 */
	protected $peakTimes = array(
									'weekday' => array('start' => 9 , 'end' => 19),
									'weekend' => array('start' => 0 , 'end' => -1),
									'shortday' => array('start' => 9 , 'end' => 13),
									'holyday' => array('start' => 0 , 'end' => -1)
								);
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['lines_query'])) {
			$this->linesQuery = $options['lines_query'];
		}
		if (isset($options['peak_times'])) {
			$this->peakTimes = $options['peak_times'];
		}
	}
	
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query($this->linesQuery)
				->exists(Billrun_Calculator_Carrier::DEF_CALC_DB_FIELD)
				->notExists($this->ratingField)->cursor()->limit($this->limit);
	}

	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$isPeak = $this->isPeak($row);

		$current = $row->getRawData();

		$added_values = array(
			$this->ratingField => $isPeak,		
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}
	
	/**
	 * Check if a given row is in peak time.
	 * @param type $row the line to check if  it is in peak time.
	 * @return true if the line time is in peak time for the given carrier
	 */
	protected function isPeak($row) {
		$dayType = Billrun_HebrewCal::getDayType($row['unified_record_time']->sec);
		$localoffset = date('Z',$row['unified_record_time']->sec);
		$hour = (( ($row['unified_record_time']->sec + $localoffset) / 3600 ) % (24)) ;
		//Billrun_Factory::log()->log($hour,Zend_Log::DEBUG);
		return  ($hour - $this->peakTimes[$dayType]['start']) > 0 && $hour < $this->peakTimes[$dayType]['end'] ;
	}
}
