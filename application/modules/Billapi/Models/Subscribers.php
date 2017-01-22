<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Subscribers extends Models_Entity {

	public function create() {
		$this->update['type'] = 'subscriber';
		return parent::create();
	}

}
