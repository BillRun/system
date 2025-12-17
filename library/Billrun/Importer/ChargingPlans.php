<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer For Charging Plans.
 * Imports from Csv to mongo collection plans
 *
 * @package  Billrun
 * @since    4.0
 */
class Billrun_Importer_ChargingPlans extends Billrun_Importer_Csv {

	protected $fieldsColumns = null;

	public function __construct($options) {
		parent::__construct($options);
		$this->fieldsColumns = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.columns', array());
	}

	protected function getCollectionName() {
		return 'plans';
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
		$ret = NULL;
		if ($rowData[$this->fieldsColumns['main_account']] > 0) {
			$ret[] = array(
				'value' => (-1) * doubleval($rowData[$this->fieldsColumns['main_account']]),
				'period' => $this->getDuration($rowData, true),
				'pp_includes_name' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.main_account', NULL),
				'pp_includes_external_id' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.main_account', NULL)
			);
		}

		if ($rowData[$this->fieldsColumns['bonus_account']] > 0) {
			$ret[] = array(
				'value' => (-1) * doubleval($rowData[$this->fieldsColumns['bonus_account']]),
				'period' => $this->getDuration($rowData),
				'pp_includes_name' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.bonus_account', NULL),
				'pp_includes_external_id' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.bonus_account', NULL)
			);
		}

		if ($rowData[$this->fieldsColumns['special_monthly_reward']] > 0) {
			$ret[] = array(
				'value' => (-1) * doubleval($rowData[$this->fieldsColumns['special_monthly_reward']]),
				'period' => $this->getDuration($rowData),
				'pp_includes_name' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.special_monthly_reward', NULL),
				'pp_includes_external_id' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.special_monthly_reward', NULL)
			);
		}

//		// remove this because we will use only array of items
//		if (is_array($ret) && count($ret) === 1) {
//			return $ret[0]['value'];
//		}
		return $ret;
	}

	protected function getPeriod($rowData) {
		if ($rowData[$this->fieldsColumns['bonus_account']] > 0 ||
			$rowData[$this->fieldsColumns['special_monthly_reward']] > 0) {
			return $this->getDuration($rowData);
		}

		return NULL;
	}

	protected function getCall($rowData) {
		$ret = array();
		if ($rowData[$this->fieldsColumns['specific']['pele_network']] != 0) {
			$ret[] = array(
				'usagev' => (-1) * doubleval($rowData[$this->fieldsColumns['specific']['pele_network']]),
				'period' => $this->getDuration($rowData),
				'pp_includes_name' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.pele_network', NULL),
				'pp_includes_external_id' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.pele_network', NULL)
				)
			;
		}
		if ($rowData[$this->fieldsColumns['specific']['call_usagev']] != 0) {
			$ret[] = array(
				'usagev' => (-1) * doubleval($rowData[$this->fieldsColumns['specific']['call_usagev']]),
				'period' => $this->getDuration($rowData),
				'pp_includes_name' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.call_usagev', NULL),
				'pp_includes_external_id' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.call_usagev', NULL)
				)
			;
		}
		if ($rowData[$this->fieldsColumns['specific']['call_cost']] != 0) {
			$ret[] = array(
				'cost' => (-1) * doubleval($rowData[$this->fieldsColumns['specific']['call_cost']]),
				'period' => $this->getDuration($rowData),
				'pp_includes_name' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.call_cost', NULL),
				'pp_includes_external_id' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.call_cost', NULL)
				)
			;
		}
		switch (count($ret)) {
			case 1:
				return $ret[0];
			case 0:
				return;
			default:
				return $ret;
		}
	}

	protected function getData($rowData) {
		if ($rowData[$this->fieldsColumns['specific']['data_usagev']] != 0) {
			$key = 'usagev';
			$value = (-1) * doubleval($rowData[$this->fieldsColumns['specific']['data_usagev']]);
			$pp_includes_name = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.data_usagev', NULL);
			$pp_includes_external_id = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.data_usagev', NULL);
		} else if ($rowData[$this->fieldsColumns['specific']['data_cost']] != 0) {
			$key = 'cost';
			$value = (-1) * doubleval($rowData[$this->fieldsColumns['specific']['data_cost']]);
			$pp_includes_name = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.data_cost', NULL);
			$pp_includes_external_id = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.data_cost', NULL);
		} else {
			return NULL;
		}
		$ret = array(
			$key => $value,
			'period' => $this->getDuration($rowData),
			'pp_includes_name' => $pp_includes_name,
			'pp_includes_external_id' => $pp_includes_external_id
		);

		return $ret;
	}

	protected function getSms($rowData) {
		if ($rowData[$this->fieldsColumns['specific']['sms_usagev']] == 0) {
			return NULL;
		}
		return
			array(
				'usagev' => (-1) * intval($rowData[$this->fieldsColumns['specific']['sms_usagev']]),
				'period' => $this->getDuration($rowData),
				'pp_includes_name' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.sms_cost', NULL),
				'pp_includes_external_id' => Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.sms_cost', NULL)
		);
	}

	protected function getDuration($rowData, $unlimited = false) {
		if (($rowData[$this->fieldsColumns['expirations_date']] == 0 || $unlimited)) {
			return array('duration' => Billrun_Service::UNLIMITED_VALUE);
		}

		return array(
			'unit' => 'days',
			'duration' => doubleval($rowData[$this->fieldsColumns['expirations_date']]),
		);
	}

	protected function getPriority($rowData) {
		$priorities = Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.priority', array());

		if ($rowData[$this->fieldsColumns['bonus_account']] != 0) {
			return $priorities['bonus_account'];
		}
		if ($rowData[$this->fieldsColumns['special_monthly_reward']] != 0) {
			return $priorities['special_monthly_reward'];
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

	protected function getPpIncludesName($rowData) {
		if ($rowData[$this->fieldsColumns['main_account']] > 0) {
			return Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.main_account', NULL);
		}

		if ($rowData[$this->fieldsColumns['bonus_account']] > 0) {
			return Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.bonus_account', NULL);
		}

		if ($rowData[$this->fieldsColumns['special_monthly_reward']] > 0) {
			return Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_name.special_monthly_reward', NULL);
		}

		return NULL;
	}

	protected function getPpIncludesExternalId($rowData) {
		if ($rowData[$this->fieldsColumns['main_account']] > 0) {
			return Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.main_account', NULL);
		}

		if ($rowData[$this->fieldsColumns['bonus_account']] > 0) {
			return Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.bonus_account', NULL);
		}

		if ($rowData[$this->fieldsColumns['special_monthly_reward']] > 0) {
			return Billrun_Factory::config()->getConfigValue('importer.ChargingPlans.pp_includes_external_id.special_monthly_reward', NULL);
		}

		return NULL;
	}

	protected function getFrom() {
		return new Mongodloid_Date(strtotime('2015-12-01'));
	}

	protected function getTo() {
		return new Mongodloid_Date(strtotime('2099-12-31'));
	}

}
