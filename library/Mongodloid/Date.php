<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Date implements Mongodloid_TypeInterface{

	private $_mongoDate;
	private $_stringDate;
	public $sec;
	public $usec;

	public function __toString() {
		return $this->_stringDate;
	}

	public function toDateTime() {
		return $this->_mongoDate->toDateTime();
	}

	public function __construct($sec = 0, $usec = 0) {
		if (func_num_args() == 0) {
            $time = microtime(true);
            $sec = floor($time);
            $usec = ($time - $sec) * 1000000.0;
        } elseif ($sec instanceof MongoDB\BSON\UTCDatetime) {
            $msecString = (string) $sec;

            $sec = substr($msecString, 0, -3);
            $usec = ((int) substr($msecString, -3)) * 1000;
        }
		
        $this->sec = (int) $sec;
        $this->usec = (int) $this->truncateMicroSeconds($usec);
		$milliSeconds = ($this->sec * 1000) + ($this->truncateMicroSeconds($this->usec) / 1000);
		$this->_mongoDate = new MongoDB\BSON\UTCDatetime($milliSeconds);
		$this->_stringDate = $this->_mongoDate->__toString();
	}
	
	/**
     * @param int $usec
     * @return int
     */
    private function truncateMicroSeconds($usec)
    {
        return (int) floor($usec / 1000) * 1000;
    }
	
	/**
     * Converts this Mongodloid_Date to the new BSON date type
     *
     * @return MongoDB\BSON\UTCDatetime
     */
    public function toBSONType()
    {
        return $this->_mongoDate;
    }

    public static function phpDateFormatToMongoFormat($phpFormat) {
        // Mapping PHP date format chars to MongoDB $dateToString format specifiers
        $map = [
            // Year
            'Y' => '%Y',   // 4-digit year
            'y' => '%y',   // 2-digit year
            
            // Month
            'm' => '%m',   // 2-digit month
            'n' => '%m',   // 1-12 month without leading zeros (Mongo only supports %m for 2-digit)
            'M' => '%b',   // short month name (Jan-Dec)
            'F' => '%B',   // full month name (January-December)
            
            // Day
            'd' => '%d',   // 2-digit day of month
            'j' => '%d',   // day of month without leading zeros (Mongo only supports %d for 2-digit)
            'D' => '%a',   // short day name (Mon-Sun)
            'l' => '%A',   // full day name (Monday-Sunday)
            
            // Hour
            'H' => '%H',   // 24-hour format with leading zeros
            'G' => '%H',   // 24-hour format without leading zeros (Mongo only supports %H)
            'h' => '%I',   // 12-hour format with leading zeros
            'g' => '%I',   // 12-hour format without leading zeros (Mongo only supports %I)
            
            // Minutes and seconds
            'i' => '%M',   // minutes with leading zeros
            's' => '%S',   // seconds with leading zeros
            
            // AM/PM
            'A' => '%p',   // AM or PM uppercase
            'a' => '%P',   // am or pm lowercase (Mongo supports %P for lowercase am/pm)
            
            // Literal characters
            '\\' => ''     // Escape char in PHP format (we'll just remove it)
        ];
        
        // Replace PHP date format chars with Mongo equivalents
        $mongoFormat = '';
        $len = strlen($phpFormat);
        $escaping = false;
        for ($i = 0; $i < $len; $i++) {
            $char = $phpFormat[$i];
            if ($char === '\\') {
                // Next character is literal, add it directly
                $i++;
                if ($i < $len) {
                    $mongoFormat .= $phpFormat[$i];
                }
                continue;
            }
            
            if (isset($map[$char])) {
                $mongoFormat .= $map[$char];
            } else {
                // Other characters add literally (e.g. -, :, space)
                $mongoFormat .= $char;
            }
        }
        
        return $mongoFormat;
    }    

}