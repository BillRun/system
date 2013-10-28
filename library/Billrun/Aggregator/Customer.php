<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing aggregator class for Golan customers records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Aggregator_Customer extends Billrun_Aggregator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	/**
	 * 
	 * @var int
	 */
	protected $page = 1;

	/**
	 * 
	 * @var int
	 */
	protected $size = 200;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $plans = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $lines = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrun = null;

	/**
	 *
	 * @var int invoice id to start from
	 */
	protected $min_invoice_id = 101;

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $vatable = true;
	protected $rates;

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $testAcc = false;
	
	/**
	 * @var boolean allow billrun to recompute  marked line (as long as they have the  same billrun_key)
	 */
	protected $allowOverride = false;	
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['aggregator']['page']) && $options['aggregator']['page']) {
			$this->page = $options['aggregator']['page'];
		}
		if (isset($options['page']) && $options['page']) {
			$this->page = $options['page'];
			
		}
		if (isset($options['aggregator']['size']) && $options['aggregator']['size']) {
			$this->size = $options['aggregator']['size'];
		}
		if (isset($options['size']) && $options['size']) {
			$this->size = $options['size'];
		}
		if (isset($options['aggregator']['vatable'])) {
			$this->vatable = $options['aggregator']['vatable'];
		}

		if (isset($options['aggregator']['test_accounts'])) {
			$this->testAcc = $options['aggregator']['test_accounts'];
		}
		
		if (isset($options['aggregator']['allow_override'])) {
			$this->allowOverride = $options['aggregator']['allow_override'];
		}
		
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun = Billrun_Factory::db()->billrunCollection();

		$this->loadRates();
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {
		$date = date(Billrun_Base::base_dateformat, strtotime(Billrun_Util::getLastChargeTime()));
		$subscriber = Billrun_Factory::subscriber();
		Billrun_Factory::log()->log("Loading page " . $this->page . " of size " . $this->size, Zend_Log::INFO);
		$this->data = $subscriber->getList($this->page, $this->size, $date);

		Billrun_Factory::log()->log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

	/**
	 * execute aggregate
	 */
	public function aggregate() {
		// @TODO trigger before aggregate
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($this->data, &$this));
		$account_billrun = false;
		$billrun_key = $this->getStamp();

		foreach ($this->data as $accid => $account) {
			if (!Billrun_Factory::config()->isProd()) {
				if ($this->testAcc && is_array($this->testAcc) && !in_array($accid, $this->testAcc)) {
						//Billrun_Factory::log("Moving on nothing to see here... , account Id : $accid");
						continue ;
				}
			}
			//Billrun_Factory::log()->log("Updating Accoount : " . print_r($account),Zend_Log::DEBUG);			
			//Billrun_Factory::log(microtime(true));
			if (empty($this->options['live_billrun_update'])) {
					Billrun_Billrun::createBillrunIfNotExists($accid, $billrun_key);
					$params = array(
						'aid' => $accid,
						'billrun_key' => $billrun_key,
					);
					$account_billrun = Billrun_Factory::billrun($params);
					foreach ($account as &$subscriber) {
						$aid = $subscriber->aid;
						$sid = $subscriber->sid;
						$plan_name = $subscriber->plan;
						if ( $account_billrun ) {
							if ($account_billrun->exists($sid)) {
								Billrun_Factory::log()->log("Billrun already exists for " . $sid . " for billrun " . $billrun_key, Zend_Log::ALERT);
								continue;
							} else {
								$updatedLines = array();
								$account_billrun->addSubscriber($sid);
								Billrun_Factory::log()->log("Querying subscriber " . $sid . " for lines...", Zend_Log::DEBUG);
								$subscriber_lines = $this->getSubscriberLines($sid, $billrun_key);
		//						Billrun_Factory::log()->log("Found " . count($subscriber_lines) . " lines.", Zend_Log::DEBUG);
								Billrun_Factory::log("Processing subscriber Lines $sid");
								foreach ($subscriber_lines as &$line) {
									//Billrun_Factory::log("Processing subscriber Line for $sid : ".  microtime(true));
									$line->collection($this->lines);
									$pricingData = array('aprice' => $line['aprice']);
									if (isset($line['over_plan'])) {
										$pricingData['over_plan'] = $line['over_plan'];
									} else if (isset($line['out_plan'])) {
										$pricingData['out_plan'] = $line['out_plan'];
									}

									$rate = $this->getRowRate($line);
									$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
									Billrun_Billrun::updateBillrun($billrun_key, array($line['usaget'] => $line['usagev']), $pricingData, $line, $vatable, $account_billrun);
									//Billrun_Factory::log("Done Processing subscriber Line for $sid : ".  microtime(true));
									$updatedLines[] = $line['stamp'];
								}
								Billrun_Factory::log()->log("Querying subscriber " . $sid . " for ggsn lines...", Zend_Log::DEBUG);
								$subscriber_aggregated_data = $this->getSubscriberDataLines($sid);
								$account_billrun->updateAggregatedData($sid, $billrun_key, $subscriber_aggregated_data);
								$this->lines->update(	array('stamp' => array('$in' => $updatedLines)),
														array('$set'=>array('billrun'=>$billrun_key)),
														array('multi'=>true));
							}
						} else {
							Billrun_Factory::log()->log("Couldn't load account  for $accid and Billrun $billrun_key",Zend_Log::NOTICE);
						}
				}
				
				if ($account_billrun) {
					$account_billrun->updateTotals();
					Billrun_Factory::log("Saving account $accid");
					//save  the billrun
					$account_billrun->save();
					$account_billrun = false;
				}
			}

			Billrun_Factory::log()->log("Updating  flat  line  for  Accoount :  $accid , which  has ".count($account) ." subscribers ",Zend_Log::INFO);
			foreach ($account as &$subscriber) {
				Billrun_Factory::dispatcher()->trigger('beforeAggregateLine', array(&$subscriber, &$this));
				$aid = $subscriber->aid;
				$sid = $subscriber->sid;
				$plan_name = $subscriber->plan;
				 //else {
				//add the subscriber plan for next month
				if (is_null($plan_name)) {
					$subscriber_status = "closed";
					Billrun_Billrun::setSubscriberStatus($aid, $sid, $billrun_key, $subscriber_status);
					Billrun_Factory::log()->log("Closed subscriber $sid.",Zend_Log::INFO);
				} else {
					$subscriber_status = "open";
					$flat_price = $subscriber->getFlatPrice();
					if (is_null($flat_price)) {
						Billrun_Factory::log()->log("Couldn't find flat price for subscriber " . $sid . " for billrun " . $billrun_key, Zend_Log::ALERT);
						continue;
					}
					Billrun_Factory::log('Adding flat to subscriber ' . $sid, Zend_Log::INFO);
					$flat_line = $this->saveFlatLine($subscriber, $billrun_key);
					$plan = $flat_line['plan_ref'];
					if (!$billrun = Billrun_Billrun::updateBillrun($billrun_key, array(), array('aprice' => $flat_price), $flat_line, $plan['vatable'])) {
						Billrun_Factory::log()->log("Flat costs already exist in billrun collection for subscriber " . $sid . " for billrun " . $billrun_key, Zend_Log::NOTICE);
					} else {
						Billrun_Billrun::setSubscriberStatus($aid, $sid, $billrun_key, $subscriber_status);
					}
				}
				//}
			}

			Billrun_Billrun::close($accid, $billrun_key, $this->min_invoice_id);


		}
//		Billrun_Factory::dispatcher()->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));
		// @TODO trigger after aggregate
		Billrun_Factory::dispatcher()->trigger('afterAggregate', array($this->data, &$this));
	}

	protected function saveFlatLine($subscriber, $billrun_key) {
		$aid = $subscriber->aid;
		$sid = $subscriber->sid;
		$flat_entry = new Mongodloid_Entity($subscriber->getFlatEntry($billrun_key));
		$flat_entry->collection($this->lines);
		$query = array(
			'stamp' => $flat_entry['stamp'],
			'aid' => $aid,
			'sid' => $sid,
			'billrun_key' => $billrun_key,
			'type' => 'flat',

		);
		$update = array(
			'$setOnInsert' => $flat_entry->getRawData(),
		);
		$options = array(
			'upsert' => true,
			'new' => true,
		);
		return $this->lines->findAndModify($query, $update, array(), $options);
	}

	protected function save($data) {
		
	}

	/**
	 *
	 * @param type $sid
	 * @param type $item
	 * @deprecated update of billing line is done in customer pricing stage
	 */
	protected function updateBillingLine($sid, $item) {
		
	}

	/**
	 * method to update the billrun by the billing line (row)
	 * @param Mongodloid_Entity $billrun the billrun line
	 * @param Mongodloid_Entity $line the billing line
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillrun($billrun, $line) {
		
	}

	protected function getSubscriberLines($sid, $billrun_key) {
		$end_time = new MongoDate(Billrun_Util::getEndTime($this->getStamp()));
		$query = array(
			'sid' => $sid,
			'urt' => array(
				'$lt' => $end_time,
			),
			'aprice' => array(
				'$exists' => true,
			),			
			'type' => array(
				'$ne' => 'ggsn',
			),
		);
		if($this->allowOverride) {
			Billrun_Factory::log("Overriding!!!!!");
			$query['$or'] = array(				
				array('billrun' => array('$exists' => false) ),
				array('billrun' => $billrun_key),
			);
		} else {
			$query['billrun'] = array('$exists' => false);
		}
		$cursor = $this->lines->query($query)->cursor()->hint(array('sid' => 1));
		$results = array();
		foreach ($cursor as $entity) {
			$results[] = $entity;
		}
		return $results;
	}

	protected function getSubscriberDataLines($sid) {
		$end_time = new MongoDate(Billrun_Util::getEndTime($this->getStamp()));

		$match_sid = array(
			'$match' => array(
				'sid' => $sid,
		));

		$match_type = array(
			'$match' => array(
				"type" => "ggsn",
		));
		
		$match = array(
			'$match' => array(
			//	"type" => "ggsn",
				'urt' => array(
					'$lt' => $end_time,
				),
				'aprice' => array(
					'$exists' => true,
				),
				'billrun' => array(
					'$exists' => false,
				),
			)
		);

		$group = array(
			'$group' => array(
				'_id' => array(
					'urt' => array(
						'$substr' => array(
							'$record_opening_time',
							0,
							8,
						),
					),
					'arate' => '$arate',
				),
				'counters' => array(
					'$sum' => '$usagev',
				),
				'over_plan' => array(
					'$sum' => '$over_plan',
				),
				'out_plan' => array(
					'$sum' => '$out_plan',
				),
				'in_plan_aprice' => array(
					'$sum' => array(
						'$cond' => array(
							array(
								'$and' => array(
									array(
										'$lt' => array('$over_plan', 0),
									),
									array(
										'$lt' => array('$out_plan', 0),
									),
								),
							),
							'$aprice',
							0,
						),
					),
				),
				'over_plan_aprice' => array(
					'$sum' => array(
						'$cond' => array(
							array(
								'$gt' => array('$over_plan', 0),
							),
							'$aprice',
							0,
						),
					),
				),
				'out_plan_aprice' => array(
					'$sum' => array(
						'$cond' => array(
							array(
								'$gt' => array('$out_plan', 0),
							),
							'$aprice',
							0,
						),
					),
				),
				'lines' => array(
					'$push' => '$_id',
				),
			),
		);
		$agg = $this->lines->aggregate($match_sid, $match_type, $match, $group);
		return $agg;
	}

	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query()->cursor();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			$this->rates[strval($rate->getId())] = $rate;
		}
	}

	/**
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param type $db_ref
	 */
	protected function getRowRate($row) {
		$raw_rate = $row->get('arate', true);
		$id_str = strval($raw_rate['$id']);
		if (isset($this->rates[$id_str])) {
			return $this->rates[$id_str];
		} else {
			return $row->get('arate', false);
		}
	}

}
