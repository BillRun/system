<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Static functions for plays
 * @package  Util
 * @since 5.7
 */
class Billrun_Utils_Plays {
	
	/**
	 * get plays used in the system
	 * 
	 * @return array
	 */
	public static function getAvailablePlays() {
		$plays = Billrun_Factory::config()->getConfigValue('plays', array());
		return array_filter($plays, function($play) {
			return Billrun_Util::getIn($play, 'enabled', true);
		});
	}
	
	/**
	 * checks if Plays are in use
	 * 
	 * @return boolean
	 */
	public static function isPlaysInUse() {
		$plays = self::getAvailablePlays();
		return count($plays) > 1;
	}
	
	/**
	 * filter out custom fields that are not related to the play
	 * 
	 * @param array $fields
	 * @param string $play
	 * @return array
	 */
	public static function filterCustomFields($fields, $play) {
		if (!self::isPlaysInUse()) {
			return $fields;
		}
		
		if (!is_array($play)) {
			$play = [$play];
		}
		
		return array_filter($fields, function($field) use ($play) {
			return !isset($field['plays']) || count(array_intersect($play, $field['plays'])) > 0;
		});
	}
	
	/**
	 * Returns the default play
	 * 
	 * @return String - default play
	 */
	public static function getDefaultPlay() {
		$plays = self::getAvailablePlays();
		$defaultPlay = array();
		foreach ($plays as $play) {
			if (!empty($play['default'])) {
				$defaultPlay = $play;
				break;
			}
		}
		return $defaultPlay;
	}
	
	/**
	 * Adding play details from subscriber to line created during billing cycle
	 * 
	 * @param mixed $line
	 * @return mixed line with play data
	 */
	public static function addPlayToLineDuringCycle($line) {
		if (!self::isPlaysInUse()) {
			return $line;
		}
		$newEntry = $line;
		$line['time'] = date(Billrun_Base::base_datetimeformat);
		$subscriber = Billrun_Factory::subscriber();
		$subscriber->load($line);
		$subscriberData = $subscriber->getData();
		$newEntry['subscriber'] = isset($subscriberData['play']) ? array('play' => $subscriberData['play']) : array('play' => self::getDefaultPlay()['name']);
		
		return $newEntry;
	}
	
}
