<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Flatten billrun generator class
 * require to generate csvs for comparison with older billing systems / charge using credit guard
 *
 * @todo this class should inherit from abstract class Generator_Golan
 * @package  Billing
 * @since    0.5
 */
class Generator_BillrunstatsCsv extends Billrun_Generator_Csv {

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrunstats_coll = null;

	public function __construct($options) {
		self::$type = 'billrunstatscsv';
//		$options['auto_create_dir'] = FALSE;
		parent::__construct($options);
		$this->billrunstats_coll = Billrun_Factory::db()->billrunstatsCollection();
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {

		$this->data = $this->billrunstats_coll
						->query('billrun_key', $this->stamp)
						->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);

		Billrun_Factory::log()->log("generator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	protected function buildHeader() {
		$this->headers = array(
			'aid',
			'billrun_key',
			'sid',
			'subscriber_status',
			'current_plan',
			' next_plan',
			'sub_before_vat',
			'day',
			'plan',
			'category',
			'zone',
			'vat',
			'usagev',
			'usaget',
			'count',
			'cost'
		);
	}

	protected function setFilename() {
		$this->filename = "billrunstats.csv";
	}

}