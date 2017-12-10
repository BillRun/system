<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Csv generator class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Generator_AggregatedCsv extends Billrun_Generator_Csv {

	protected $aggregation_array = array();

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $collection = array();

	public function __construct($options) {
		self::$type = 'aggregatedcsv';
		parent::__construct($options);
		$this->setCollection();
		$this->buildAggregationQuery();
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {
		$this->data = $this->collection->aggregateWithOptions($this->aggregation_array, array('allowDiskUse'=> true)); //TODO how to perform it on the secondaries?
		//Billrun_Factory::log("generator entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		if (count($this->data)) {
			$this->writeHeaders();
			$this->writeRows();
		}
	}

	abstract protected function buildAggregationQuery();

	abstract protected function setCollection();
}
