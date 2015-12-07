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

	protected $fieldsColumns = null;
	
	public function __construct($options) {
		parent::__construct($options);
		$this->fieldsColumns = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.columns', array());
	}
	public function getCollectionName() {
		return 'plans';
	}

	protected function getServiceProvider($rowData) {
		return strtoupper($rowData[$this->fieldsColumns['service_provider']]);
	}

	protected function getChargingType($rowData) {
		switch ($rowData[$this->fieldsColumns['charging_type']]) {
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
		if ($rowData[$this->fieldsColumns['main_account']] > 0) {
			return intval($rowData[$this->fieldsColumns['main_account']]);
		}

		if ($rowData[$this->fieldsColumns['bonus_account']] > 0) {
			return intval($rowData[$this->fieldsColumns['bonus_account']]);
		}

		return NULL;
	}

	protected function getCall($rowData) {
		if ($rowData[$this->fieldsColumns['specific']['call_usagev']] == 0) {
			return NULL;
		}
		return
			array(
				'usagev' => intval($rowData[$this->fieldsColumns['specific']['call_usagev']]) * 60,
				'period' => $this->getDuration($rowData)
			)
		;
	}

	protected function getData($rowData) {
		if ($rowData[$this->fieldsColumns['specific']['data_usagev']] != 0) {
			$key = 'usagev';
			$value = intval($rowData[$this->fieldsColumns['specific']['data_usagev']]);
		} else if ($rowData[$this->fieldsColumns['specific']['data_cost']] != 0) {
			$key = 'cost';
			$value = intval($rowData[$this->fieldsColumns['specific']['data_cost']]);
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
		if ($rowData[$this->fieldsColumns['specific']['sms_usagev']] == 0) {
			return NULL;
		}
		return
			array(
				'usagev' => intval($rowData[$this->fieldsColumns['specific']['sms_usagev']]),
				'period' => $this->getDuration($rowData)
			);
	}
	
	protected function getDuration($rowData) {
		$duration = ($rowData[$this->fieldsColumns['expirations_date']] == 0 ? 'UNLIMITED' : intval($rowData[$this->fieldsColumns['expirations_date']]));
		return array(
			'unit' => 'days',
			'duration' => $duration
		);
	}
	
	protected function getPriority($rowData) {
		$priorities = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.priority', array());
		
		if ($rowData[$this->fieldsColumns['bonus_account']] != 0) {
			return $priorities['bonus_account'];
		}
		$specificBalancesCounter = 0;
		foreach ($this->fieldsColumns['specific'] as $key => $columnIndex) {
			if ($rowData[$columnIndex] != 0) {
				$specificBalancesCounter++;
			}
		}
		
		if ($specificBalancesCounter === 0) {
			return $priorities['main_account'];
		}
		return $priorities['basic'] + $specificBalancesCounter;
	}

}
