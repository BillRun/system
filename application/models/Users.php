<?php

require_once APPLICATION_PATH . '/library/password_compat/password.php';

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Users model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class UsersModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->users;
		parent::__construct($params);
		$this->search_key = "username";
	}

	public function getFilterFields() {
		$filter_fields = array(
			'username' => array(
				'key' => 'username',
				'db_key' => 'username',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Username',
				'default' => '',
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'username' => array(
					'width' => 2,
				),
			),
		);
		return $filter_field_order;
	}

	public function getTableColumns() {
		$columns = array(
			'username' => 'Username',
			'roles' => 'Roles',
		);
		return $columns;
	}

	public function update($params) {
		if (isset($params['password'])) {
			if (!password_get_info($params['password'])['algo']) {
				$params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
			}
		}
		return parent::update($params);
	}

	public function getEmptyItem() {
		return new Mongodloid_Entity(array(
			'username' => '',
			'password' => '',
			'roles' => array(
			),
		));
	}

	public function getData($filter_query = array()) {
		$resource = parent::getData();
		$ret = array();
		foreach ($resource as $item) {
			$item['roles'] = json_encode($item['roles']);
			$ret[] = $item;
		}
		return $ret;
	}

}
