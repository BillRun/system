<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class holds start and end times of dates range.
 * 
 */
class Billrun_DataTypes_DateRange {
	
	/**
	 * start time
	 * @var MongoDate
	 */
	private $start = 0;
	
	/**
	 * end time
	 * @var MongoDate 
	 */
	private $end = 31556995200;
	
	/**
	 * Create a new instance of Billrun_DataTypes_DateRange class, based on cycle time object.
	 * @param unix time stamp $startDate : the start date of the dates range.
         * @param unix time stamp $endDate : the end date of the dates range.
	 */
	public function __construct($startDate, $endDate) {
		$this->start = new MongoDate($startDate);
		$this->end = new MongoDate($endDate);
	}

	/**
	 * Get the range start date
	 * @return MongoDate
	 */
	public function start() {
		return $this->start;
	}
	
	/**
	 * Get the range end date
	 * @return MongoDate
	 */
	public function end() {
		return $this->end;
	}
}
