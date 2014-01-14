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
	protected $billrun_stats_coll = null;

	public function __construct($options) {
		self::$type = 'billrunstats';
		$options['auto_create_dir'] = FALSE;
		parent::__construct($options);
		$this->billrun_stats_coll = Billrun_Factory::db()->billrun_statsCollection();
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
			$flat_record = array();
			foreach ($this->data as $billrun_doc) {
				$flat_record['aid'] = $billrun_doc['aid'];
				$flat_record['billrun_key'] = $billrun_doc['billrun_key'];
				Billrun_Factory::log()->log('Flattening billrun ' . $flat_record['billrun_key'] . ' of account ' . $flat_record['aid'], Zend_Log::DEBUG);
				foreach ($billrun_doc['subs'] as $sub_entry) {
					$flat_record['sid'] = $sub_entry['sid'];
					$flat_record['subscriber_status'] = $sub_entry['subscriber_status'];
					$flat_record['current_plan'] = is_null($sub_entry['current_plan']) ? null : Billrun_Factory::plan(array('id' => $sub_entry['current_plan']['$id']))->getName();
					$flat_record['next_plan'] = is_null($sub_entry['next_plan']) ? null : Billrun_Factory::plan(array('id' => $sub_entry['next_plan']['$id']))->getName();
					if (isset($sub_entry['breakdown'])) {
						foreach ($sub_entry['breakdown'] as $flat_record['plan_key'] => $categories) {
							foreach ($categories as $flat_record['category_key'] => $zones) {
								foreach ($zones as $flat_record['zone_key'] => $zone_totals) {
									if ($flat_record['plan_key'] != 'credit') {
										if (isset($zone_totals['totals'])) {
											$flat_record['vat'] = $this->getFieldVal($zone_totals['vat'],$default_vat);
											foreach ($zone_totals['totals'] as $flat_record['usaget'] => $usage_totals) {
												$flat_record['usagev'] = $usage_totals['usagev'];
												$flat_record['cost'] = $this->getFieldVal($usage_totals['cost'],0);
												$flat_record['count'] = $this->getFieldVal($usage_totals['count'],1);
											}
										} else {
											$flat_record['vat'] = $zone_totals['vat'];
											$flat_record['cost'] = $zone_totals['cost'];
											$flat_record['usaget'] = 'flat';
											$flat_record['usagev'] = 1;
											$flat_record['count'] = 1;
										}
									} else {
										$flat_record['vat'] = $default_vat;
										$flat_record['cost'] = $zone_totals;
										$flat_record['usaget'] = strpos($flat_record['category_key'], 'charge') === 0 ? 'charge' : 'refund';
										$flat_record['usagev'] = 1;
										$flat_record['count'] = 1;
									}
									$this->addFlatRecord($flat_record);
									unset($flat_record['_id']);
								}
							}
						}
					}
				}
			}
		}
	}

	protected function addFlatRecord($record) {
		$this->billrun_stats_coll->insert($record);
	}
	
	protected function getFieldVal(&$field, $defVal) {
		if (isset($field)) {
			return $field;
		}
		return $defVal;
	}

}
