<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Flatten billrun generator class
 * require to generate csvs for comparison with older billing systems / charge using credit guard
 *
 * @todo this class should inherit from abstract class Generator_Golan
 * @package  Billing
 * @since    0.5
 */
abstract class Generator_Billrunstats extends Billrun_Generator {

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $ggsn_zone = 'INTERNET_BILL_BY_VOLUME';
	protected $buffer = array();

	public function __construct($options) {
		$options['auto_create_dir'] = FALSE;
		parent::__construct($options);
	}

	public function __destruct() {
		$this->flushBuffer();
	}

	/**
	 * load the container the need to be generate
	 */
	public function load() {
		$billrun = Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection();

		$this->data = $billrun
				->query('billrun_key', $this->stamp)
				->exists('invoice_id')
				->cursor();

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
					if (isset($sub_entry['breakdown'])) {
						$plansNames = array_keys($sub_entry['breakdown']);
					} else {
						$plansNames = is_null($sub_entry['plans']) ? ($sub_entry['sid'] == 0 ? array('ACCOUNT') : array()) : $this->getPlanNames($sub_entry['plans']);
					}
					$flat_data_record['kosher'] = $flat_breakdown_record['kosher'] = ((isset($sub_entry['kosher']) && ($sub_entry['kosher'] == "true" || (is_bool($sub_entry['kosher']) && $sub_entry['kosher']))) ? 1 : 0);
					foreach ($plansNames as $key => $planName) {
						$flat_data_record['current_plan'] = $flat_breakdown_record['current_plan'] = $planName;
	//					$flat_data_record['sub_before_vat'] = $flat_breakdown_record['sub_before_vat'] = isset($sub_entry['totals']['before_vat']) ? $sub_entry['totals']['before_vat'] : 0;
						if (isset($sub_entry['breakdown'][$planName])) {
							foreach ($sub_entry['breakdown'][$planName] as $planid => $offer) {
								if(!preg_match('/^\d{12,16}$/',$planid) && !in_array($planid,['in_plan','out_plan','over_plan','credit'])) {
										continue;
								}
								if(in_array($planid,['in_plan','out_plan','over_plan','credit'])) {
									$offer=[$planid => $offer];
								}
								foreach ($offer as $flat_breakdown_record['plan'] => $categories) {
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
													$flat_breakdown_record['cost'] = $zone_totals['cost'] + (isset($zone_totals['cost_without_vat']) ? $zone_totals['cost_without_vat'] : 0);
													$flat_breakdown_record['usaget'] = 'flat';
													$flat_breakdown_record['usagev'] = 1;
													$flat_breakdown_record['count'] = 1;
													$this->addFlatRecord($flat_breakdown_record);
													unset($flat_breakdown_record['_id']);
												}
											} else {
												$flat_breakdown_record['vat'] = in_array($flat_breakdown_record['category'], array('refund_vat_free', 'charge_vat_free')) ? 0 : $default_vat; // remove this hack
												$flat_breakdown_record['cost'] = $zone_totals;
												$flat_breakdown_record['usaget'] = strpos($flat_breakdown_record['category'], 'charge') === 0 ? 'charge' : 'refund';
												$flat_breakdown_record['usagev'] = 1;
												$flat_breakdown_record['count'] = isset($flat_breakdown_record['count']) ? $flat_breakdown_record['count'] : 1;
												$this->addFlatRecord($flat_breakdown_record);
												unset($flat_breakdown_record['_id']);
											}
										}
									}
								}
							}
						}
					}
					if (isset($sub_entry['lines']['data']['counters'])) {
						foreach ($sub_entry['lines']['data']['counters'] as $flat_data_record['day'] => $counters) {
							foreach(array('usage_3g','usage_4g') as $data_generation) {
								if(isset($counters[$data_generation])) {
									$flat_data_record['plan'] = $counters[$data_generation]['plan_flag'] . '_plan';
									$flat_data_record['category'] = str_replace('usage_', '', $data_generation);
									$flat_data_record['zone'] = $this->ggsn_zone;
									$flat_data_record['vat'] = $default_vat;
									$flat_data_record['usagev'] = $counters[$data_generation]['usagev'];
									$flat_data_record['usaget'] = 'data';
									$flat_data_record['count'] = 1;
									$flat_data_record['cost'] = $counters[$data_generation]['aprice'];
									$this->addFlatRecord($flat_data_record);
									unset($flat_data_record['_id']);
								}
							}
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
		$this->buffer[] = $record;
		if ($this->timeToFlush()) {
			$this->flushBuffer();
		}
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

	abstract protected function flushBuffer();

	abstract protected function timeToFlush();

	protected function resetBuffer() {
		$this->buffer = array();
	}
	
	protected function getPlanNames($plans) {
		foreach ($plans as $plan) {
			$planNames[] = $plan['plan'];
		}
		return $planNames;
	}

}
