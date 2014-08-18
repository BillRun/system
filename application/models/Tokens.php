<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Tokens model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class TokensModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->tokens;
		parent::__construct($params);
		$this->search_key = "key";
	}

	public function storeData($GUT, $OUT, $sid) {
		$entity = new Mongodloid_Entity(array(
			'GUT' => $GUT,
			'OUT' => $OUT,
			'sid' => $sid,
		));
		$tokensCollection = Billrun_Factory::db()->tokensCollection();
		return $entity->save($tokensCollection, 1);
	}

}
