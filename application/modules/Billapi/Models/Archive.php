<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for archive entity
 *
 * @package  Billapi
 * @since    5.6
 */
class Models_Archive extends Models_Entity {

	/**
	 * 
	 * @see Models_Entity::canEntityBeDeleted
	 */
	protected function canEntityBeDeleted() {
		return false;
	}

}
