<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer For Charging Plans.
 * Imports from Csv to mongo collection plans
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Importer_ChargingPlans extends Billrun_Importer_Csv {

	const COLUMN_SERVICE_PROVIDER = 1;
	const COLUMN_MAIN_ACCOUNT = 3;
	const COLUMN_BONUS_ACCOUNT = 4;
	const COLUMN_CHARGING_TYPE = 14;
	const COLUMN_EXPIRATION = 13;
	const COLUMN_CALL_USAGEV = 6;
	const COLUMN_SMS_USAGEV = 9;
	const COLUMN_DATA_USAGEV = 10;
	const COLUMN_DATA_COST = 7;

	public function getCollectionName() {
		return 'plans';
	}

	protected function getServiceProvider($rowData) {
		return strtoupper($rowData[self::COLUMN_SERVICE_PROVIDER]);
	}

	protected function getChargingType($rowData) {
		switch ($rowData[self::COLUMN_CHARGING_TYPE]) {
			case ('דיגיטלי'):
			case ('דיגיטלי '):
			case ('דיגטלי'):
				return array('digital');
			case ('כרטיס'):
			case ('כרטיס '):
				return array('card');
			case ('דיגיטלי+כרטיס'):
			case ('כרטיס+דיגיטלי'):
				return array('digital', 'card');
		}
		return array();
	}

	protected function getIncludes($rowData) {
		$ret = array(
			'call' => $this->getCall($rowData),
			'data' => $this->getData($rowData),
			'sms' => $this->getSms($rowData),
			'cost' => $this->getCost($rowData)
		);
		foreach ($ret as $key => $value) {
			if ($value === NULL) {
				unset($ret[$key]);
			}
		}
		if (empty($ret)) {
			return NULL;
		}
		return $ret;
	}
	
	protected function getCost($rowData) {
		if ($rowData[self::COLUMN_MAIN_ACCOUNT] > 0) {
			return intval($rowData[self::COLUMN_MAIN_ACCOUNT]);
		}

		if ($rowData[self::COLUMN_BONUS_ACCOUNT] > 0) {
			return intval($rowData[self::COLUMN_BONUS_ACCOUNT]);
		}

		return NULL;
	}

	protected function getCall($rowData) {
		if ($rowData[self::COLUMN_CALL_USAGEV] == 0) {
			return NULL;
		}
		return
			array(
				'usagev' => intval($rowData[self::COLUMN_CALL_USAGEV]),
				'period' => $this->getDuration($rowData)
			)
		;
	}

	protected function getData($rowData) {
		if ($rowData[self::COLUMN_DATA_USAGEV] != 0) {
			$key = 'usagev';
			$value = intval($rowData[self::COLUMN_DATA_USAGEV]);
		} else if ($rowData[self::COLUMN_DATA_COST] != 0) {
			$key = 'cost';
			$value = intval($rowData[self::COLUMN_DATA_COST]);
		} else {
			return NULL;
		}
		$ret = array(
			$key => $value,
			'period' => $this->getDuration($rowData)
		);
		
		return $ret;
	}

	protected function getSms($rowData) {
		if ($rowData[self::COLUMN_SMS_USAGEV] == 0) {
			return NULL;
		}
		return
			array(
				'usagev' => intval($rowData[self::COLUMN_SMS_USAGEV]),
				'period' => $this->getDuration($rowData)
			);
	}
	
	protected function getDuration($rowData) {
		$duration = ($rowData[self::COLUMN_EXPIRATION] == 0 ? 'UNLIMITED' : intval($rowData[self::COLUMN_EXPIRATION]));
		return array(
			'unit' => 'days',
			'duration' => $duration
		);
	}
	
	protected function getPriority() {
		return 1;
	}

}
