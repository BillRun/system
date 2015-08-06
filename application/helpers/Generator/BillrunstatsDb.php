<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Flatten billrun generator class and insert rows to billrunstats collection
 *
 * @package  Billing
 * @since    0.5
 */
class Generator_BillrunstatsDb extends Generator_Billrunstats {

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrunstats_coll = null;

	public function __construct($options) {
		parent::__construct($options);
		$this->billrunstats_coll = Billrun_Factory::db(array('name' => 'billrunstats'))->billrunstatsCollection();
	}

	protected function flushBuffer() {
		$this->billrunstats_coll->batchinsert($this->buffer);
		$this->resetBuffer();
	}

	protected function timeToFlush() {
		return (count($this->buffer) >= 1000);
	}

}
