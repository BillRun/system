<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class is to hold the logic for the Statistics module.
 *
 * @package  Models
 * @subpackage Table
 * @since    4.0
 */
class ArchiveModel extends LinesModel {

	/**
	 * constructor
	 * 
	 * @param array $params of parameters to preset the object
	 */
	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->archive;
		$this->collection = call_user_func(array(Billrun_Factory::db(), $params['collection'] . 'Collection'));
		$this->collection_name = $params['collection'];

		if (isset($params['page'])) {
			$this->setPage($params['page']);
		}

		if (isset($params['size'])) {
			$this->setSize($params['size']);
		}

		if (isset($params['sort'])) {
			$this->sort = $params['sort'];
		}

		if (isset($params['extra_columns'])) {
			$this->extra_columns = $params['extra_columns'];
		}
	}

}
