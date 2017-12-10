<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun CollectionsSteps class
 *
 * @package  Billrun
 * @since    5.0
 */
abstract class Billrun_CollectionSteps extends Billrun_Base {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	protected static $type = 'collection_steps';

	abstract public function createCollectionSteps($aid);

	abstract public function removeCollectionSteps($aid);
}
