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
class Generator_Billrunstats extends Billrun_Generator {

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrunstats_coll = null;
	protected $ggsn_zone = 'INTERNET_BILL_BY_VOLUME';

	public function __construct($options) {
		self::$type = 'billrunstats';
		$options['auto_create_dir'] = FALSE;
		parent::__construct($options);
		$this->billrunstats_coll = Billrun_Factory::db(array('name' => 'billrunstats'))->billrunstatsCollection();
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {
		$billrun = Billrun_Factory::db()->billrunCollection();

		$this->data = $billrun
						->query('billrun_key', $this->stamp)
						->exists('invoice_id')
						->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);

		Billrun_Factory::log()->log("generator entities loaded: " . $this->data->count(), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
	}

	/**
	 * execute the generate action
	 */
	public function generate() {
		$default_vat = floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18));
		if ($this->data->count()) {
			$flat_breakdown_record = array();
			$flat_data_record = array();
			foreach ($this->data as $billrun_doc) {
				$flat_data_record['aid'] = $flat_breakdown_record['aid'] = $billrun_doc['aid'];
				$flat_data_record['billrun_key'] = $flat_breakdown_record['billrun_key'] = $billrun_doc['billrun_key'];
				Billrun_Factory::log()->log('Flattening billrun ' . $flat_breakdown_record['billrun_key'] . ' of account ' . $flat_breakdown_record['aid'], Zend_Log::DEBUG);
				foreach ($billrun_doc['subs'] as $sub_entry) {
					$flat_data_record['sid'] = $flat_breakdown_record['sid'] = $sub_entry['sid'];
					$flat_data_record['subscriber_status'] = $flat_breakdown_record['subscriber_status'] = $sub_entry['subscriber_status'];
					$flat_data_record['current_plan'] = $flat_breakdown_record['current_plan'] = is_null($sub_entry['current_plan']) ? null : Billrun_Factory::plan(array('id' => $sub_entry['current_plan']['$id']))->getName();
					$flat_data_record['next_plan'] = $flat_breakdown_record['next_plan'] = is_null($sub_entry['next_plan']) ? null : Billrun_Factory::plan(array('id' => $sub_entry['next_plan']['$id']))->getName();
					$flat_data_record['sub_before_vat'] = $flat_breakdown_record['sub_before_vat'] = isset($sub_entry['totals']['before_vat']) ? $sub_entry['totals']['before_vat'] : 0;
					if (isset($sub_entry['breakdown'])) {
						foreach ($sub_entry['breakdown'] as $flat_breakdown_record['plan'] => $categories) {
							foreach ($categories as $flat_breakdown_record['category'] => $zones) {
								foreach ($zones as $flat_breakdown_record['zone'] => $zone_totals) {
									if ($flat_breakdown_record['zone'] == $this->ggsn_zone) {
										continue; // it's taken from lines->data->counters
									}
									if ($flat_breakdown_record['plan'] != 'credit') {
										if (isset($zone_totals['totals'])) {
											$flat_breakdown_record['vat'] = $this->getFieldVal($zone_totals['vat'], $default_vat);
											foreach ($zone_totals['totals'] as $flat_breakdown_record['usaget'] => $usage_totals) {
												$flat_breakdown_record['usagev'] = $usage_totals['usagev'];
												$flat_breakdown_record['cost'] = $this->getFieldVal($usage_totals['cost'], 0);
												$flat_breakdown_record['count'] = $this->getFieldVal($usage_totals['count'], 1);
												$this->addFlatRecord($flat_breakdown_record);
												unset($flat_breakdown_record['_id']);
											}
										} else {
											$flat_breakdown_record['vat'] = $zone_totals['vat'];
											$flat_breakdown_record['cost'] = $zone_totals['cost'];
											$flat_breakdown_record['usaget'] = 'flat';
											$flat_breakdown_record['usagev'] = 1;
											$flat_breakdown_record['count'] = 1;
											$this->addFlatRecord($flat_breakdown_record);
											unset($flat_breakdown_record['_id']);
										}
									} else {
										$flat_breakdown_record['vat'] = $default_vat;
										$flat_breakdown_record['cost'] = $zone_totals;
										$flat_breakdown_record['usaget'] = strpos($flat_breakdown_record['category'], 'charge') === 0 ? 'charge' : 'refund';
										$flat_breakdown_record['usagev'] = 1;
										$flat_breakdown_record['count'] = 1;
										$this->addFlatRecord($flat_breakdown_record);
										unset($flat_breakdown_record['_id']);
									}
								}
							}
						}
					}
					if (isset($sub_entry['lines']['data']['counters'])) {
						foreach ($sub_entry['lines']['data']['counters'] as $flat_data_record['day'] => $counters) {
							$flat_data_record['plan'] = $counters['plan_flag'] . '_plan';
							$flat_data_record['category'] = 'base';
							$flat_data_record['zone'] = $this->ggsn_zone;
							$flat_data_record['vat'] = $default_vat;
							$flat_data_record['usagev'] = $counters['usagev'];
							$flat_data_record['usaget'] = 'data';
							$flat_data_record['count'] = 1;
							$flat_data_record['cost'] = $counters['aprice'];
							$this->addFlatRecord($flat_data_record);
							unset($flat_data_record['_id']);
						}
					}
				}
			}
		}
	}

	/**
	 * Adds a flattened record to the dbs
	 * @param array $record
	 */
	protected function addFlatRecord($record) {
		$this->billrunstats_coll->insert($record);
	}

	/**
	 * Returns an array value if it is set
	 * @param mixed $field the array value
	 * @param mixed $defVal the default value to return if $field is not set
	 * @return mixed the array value if it is set, otherwise returns $defVal
	 */
	protected function getFieldVal(&$field, $defVal) {
		if (isset($field)) {
			return $field;
		}
		return $defVal;
	}

}

//create table billrunstats(
//aid INT NOT NULL, 
//billrun_key varchar(10) NOT NULL, 
//sid INT NOT NULL, 
//subscriber_status varchar(100), 
//current_plan varchar(100), 
//next_plan varchar(100), 
//sub_before_vat DECIMAL(64,25), 
//day INT, 
//plan varchar(100), 
//category varchar(100), 
//zone varchar(150), 
//vat DECIMAL(5,5), 
//usagev BIGINT,
//usaget varchar(100), 
//count INT, 
//cost DECIMAL(64,25)
//);
//
//mysqlimport --ignore-lines=1 --fields-optionally-enclosed-by='"' --fields-terminated-by=',' --lines-terminated-by='\n' --local test /home/shani/Desktop/subscribers.csv