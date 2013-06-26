<?php

require_once 'application/helpers/Subscriber/Golan.php';

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
	protected $page = 0;

	/**
	 * 
	 * @var int
	 */
	protected $size = 10000;

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
	 * @var int invoice id to start from
	 */
	protected $min_invoice_id = 101;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['page']) && $options['page']) {
			$this->page = $options['page'];
		}
		if (isset($options['size']) && $options['size']) {
			$this->size = $options['size'];
		}
		if (isset($options['aggregator']['min_invoice_id'])) {
			$this->min_invoice_id = $options['aggregator']['min_invoice_id'];
		}

		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();

		$closeBillrunFunc = <<<EOT
function (account_id, billrun_key) {
    while (1) {
	var targetCollection = db.billrun;
        var cursor = targetCollection.find( {}, { invoice_id: 1 } ).sort( { invoice_id: -1 } ).limit(1);
        var invoice_id = cursor.hasNext() ? cursor.next().invoice_id + 1 : $this->min_invoice_id;
        targetCollection.update({'account_id': account_id, 'billrun_key': billrun_key, 'invoice_id': {\$exists:false}},{\$set: { 'invoice_id': invoice_id }});
        var err = db.getLastErrorObj();
        if( err && err.code ) {
            if( err.code == 11000 /* dup key */ )
                continue;
            else
                print( "unexpected error updating invoice_id: " + tojson( err ) );
        }
		return invoice_id;
    }
}
EOT;
		$save_function_command = "db.system.js.save({_id : \"closeBillrun\" , value : $closeBillrunFunc})";

		Billrun_Factory::db()->execute($save_function_command);
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {
		$date = Billrun_Util::getLastChargeTime(true);
		$this->data = Subscriber_Golan::getList($this->page, $this->size, $date);

		Billrun_Factory::log()->log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

	/**
	 * execute aggregate
	 */
	public function aggregate() {
		// @TODO trigger before aggregate
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($this->data, &$this));

		$billrun_key = $this->getStamp();

		foreach ($this->data as $account_id => $account) {
			//TODO refactor this to use the Billrun_Factory ??
			$billrun = Billrun_Factory::billrun(array( 'account_id' => $account_id, 'billrun_key' => $billrun_key ));
			if (!$billrun->isValid()) {
				$billrun->create($account_id, $billrun_key);
			}
			if ($billrun->isOpen()) { // open billrun
				foreach ($account as $subscriber) {
					Billrun_Factory::dispatcher()->trigger('beforeAggregateLine', array(&$subscriber, &$this));
					$account_id = $subscriber->account_id;
					$subscriber_id = $subscriber->subscriber_id;

					try {
						$flat_price = $subscriber->getFlatPrice();
						Billrun_Factory::log('Adding flat to subscriber ' . $subscriber_id, Zend_Log::INFO);
						$flat_entry = new Mongodloid_Entity($subscriber->getFlatEntry($billrun_key));
						$flat_entry->collection($this->lines);
						$flat_entry->save();
					} catch (Exception $e) {
						Billrun_Factory::log()->log("Flat line already exists for subscriber " . $subscriber_id . " for billrun " . $billrun_key, Zend_Log::NOTICE);
					}

					if (!$billrun->exists($subscriber_id)) {
						$billrun->addSubscriber($subscriber_id);
					}
					try {
						$plan = $flat_entry['current_plan'];
						$billrun->update($subscriber_id, array(), array('price_customer' => $flat_price), $flat_entry, $plan['vatable']);
					} catch (Exception $e) {
						Billrun_Factory::log()->log("Flat costs already exist in billrun collection for subscriber " . $subscriber_id . " for billrun " . $billrun_key, Zend_Log::NOTICE);
					}
				}
				$billrun->close();
			} else {
				Billrun_Factory::log('Billrun ' . $billrun_key . " already closed for account " . $account_id, Zend_Log::NOTICE);
			}
		}
//		Billrun_Factory::dispatcher()->trigger('beforeAggregateSaveLine', array(&$save_data, &$this));
		// @TODO trigger after aggregate
		Billrun_Factory::dispatcher()->trigger('afterAggregate', array($this->data, &$this));
	}

	protected function save($data) {
	}

	/**
	 *
	 * @param type $subscriber_id
	 * @param type $item
	 * @deprecated update of billing line is done in customer pricing stage
	 */
	protected function updateBillingLine($subscriber_id, $item) {
		
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

}
