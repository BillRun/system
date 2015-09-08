<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Base class for the admin filter handler.
 * @package  Admin
 * @since    2.8
 */
abstract class Admin_Filter_Base implements Admin_Filter_I {
	
	/**
	 * Get the query for the filters.
	 * @param AdminController $admin - The admin controller.
	 * @param $table - Name for the current mongo collection.
	 * @param $model - Current model in use.
	 * @return array Query for the filter.
	 */
	public abstract function query($admin, $table);
	
	/**
	 * Get the manual filters.
	 * @param Object $request - The request object instance,
	 * @param Session $session - The session object.
	 * @param Model $model - Current model in use.
	 * @return array Query for the manual filters.
	 */
	protected function getManualFilters($request, $session, $model) {
		$query = false;
		
		$keys = AdminController::setRequestToSession($request, $session, 'manual_key', 'manual_key');
		if ($model instanceof LinesModel) {
			$advanced_options = Admin_Lines::getOptions();
		} else if ($model instanceof BalancesModel) {
			// TODO: make refactoring of the advanced options for each page (lines, balances, etc)
			$advanced_options = array(
				$keys[0] => array(
					'type' => 'number',
					'display' => 'usage',
				)
			);
		} else if ($model instanceof EventsModel) {
			// TODO: Why is this here?
			$avanced_options = array(
				$keys[0] => array(
					'type' => 'text',
				)
			);
		} else {
			return $query;
		}
		$operators = AdminController::setRequestToSession($request, $session, 'manual_operator', 'manual_operator');
		$values = AdminController::setRequestToSession($request, $session, 'manual_value', 'manual_value');
		settype($operators, 'array');
		settype($values, 'array');
		for ($i = 0; $i < count($keys); $i++) {
			if ($keys[$i] == '' || $values[$i] == '') {
				continue;
			}
			switch ($advanced_options[$keys[$i]]['type']) {
				case 'number':
					$values[$i] = floatval($values[$i]);
					break;
				case 'date':
					if (Zend_Date::isDate($values[$i], 'yyyy-MM-dd hh:mm:ss')) {
						$values[$i] = new MongoDate((new Zend_Date($values[$i], null, new Zend_Locale('he_IL')))->getTimestamp());
					} else {
						continue 2;
					}
				default:
					break;
			}
			if (isset($advanced_options[$keys[$i]]['case'])) {
				$values[$i] = Admin_Table::convertValueByCaseType($values[$i], $advanced_options[$keys[$i]]['case']);
			}
			// TODO: decoupling to config of fields
			switch ($operators[$i]) {
				case 'starts_with':
					$operators[$i] = '$regex';
					$values[$i] = "^$values[$i]";
					break;
				case 'ends_with':
					$operators[$i] = '$regex';
					$values[$i] = "$values[$i]$";
					break;
				case 'like':
					$operators[$i] = '$regex';
					$values[$i] = "$values[$i]";
					break;
				case 'lt':
					$operators[$i] = '$lt';
					break;
				case 'lte':
					$operators[$i] = '$lte';
					break;
				case 'gt':
					$operators[$i] = '$gt';
					break;
				case 'gte':
					$operators[$i] = '$gte';
					break;
				case 'ne':
					$operators[$i] = '$ne';
					break;
				case 'equals':
					$operators[$i] = '$in';
					$values[$i] = array($values[$i]);
					break;
				default:
					break;
			}
			if ($advanced_options[$keys[$i]]['type'] == 'dbref') {
				$collection = Billrun_Factory::db()->{$advanced_options[$keys[$i]]['collection'] . "Collection"}();
				$pre_query[$advanced_options[$keys[$i]]['collection_key']][$operators[$i]] = $values[$i];
				$cursor = $collection->query($pre_query);
				$values [$i] = array();
				foreach ($cursor as $entity) {
					$values[$i][] = $entity->createRef($collection);
				}
				$operators[$i] = '$in';
			}
			$query[$keys[$i]][$operators[$i]] = $values[$i];
		}
		return $query;
	}
}
