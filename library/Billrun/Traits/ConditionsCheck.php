<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Validate conditions on given entitiy
 */
trait Billrun_Traits_ConditionsCheck {

	/**
	 * get matching entities for all categories by the specific conditions of every category
	 * 
	 * @param array $entity
	 * @param array $conditions
	 * @return boolean
	 */
	public function isConditionsMeet($entity, $conditions = [], $logic = '$and') {
		if (empty($conditions)) {
			return $this->getNoConditionsResult($entity);
		}

		$query = [
			$logic => [],
		];

		foreach ($conditions as $condition) {
			$cond = $this->getConditionQuery($entity, $condition);
			if (!is_null($cond)) {
				$query[$logic][] = $cond;
			}
		}

		return $this->isConditionMeet($entity, $query);
	}

	/**
	 * build condition query that will be later used by isConditionMeet
	 * 
	 * @param array $entity
	 * @param array $condition
	 * @return array
	 */
	protected function getConditionQuery($entity, $condition) {
		$fieldName = $this->getFieldName($condition, $entity);
		$operator = $this->getOperator($condition, $entity);
		$value = $this->getValueToCompare($condition, $entity);

		return [
			$fieldName => [
				$operator => $value,
			],
		];
	}

	/**
	 * get condition's field name
	 * 
	 * @param array $condition
	 * @param array $entity
	 * @return string
	 */
	protected function getFieldName($condition, $entity = []) {
		return Billrun_Util::getIn($condition, 'field');
	}

	/**
	 * get condition's operator
	 * 
	 * @param array $condition
	 * @param array $entity
	 * @return string
	 */
	protected function getOperator($condition, $entity = []) {
		return Billrun_Util::getIn($condition, 'op');
	}

	/**
	 * get condition's value to compare
	 * 
	 * @param array $condition
	 * @param array $entity
	 * @return string
	 */
	protected function getValueToCompare($condition, $entity = []) {
		return Billrun_Util::getIn($condition, 'value');
	}

	/**
	 * 
	 * 
	 * @param array $entity
	 * @param array $query
	 * @return boolean
	 */
	public function isConditionMeet($entity, $query) {
		return Billrun_Utils_Arrayquery_Query::exists($entity, $query);
	}

	protected function getNoConditionsResult($entity) {
		return true;
	}

}
