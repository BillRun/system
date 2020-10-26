<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents Exchange Rate entity
 */
class Billrun_ExchangeRate {

	/**
	 * DB collection to store exchange rates
	 *
	 * @var Mongodloid_Connection
	 */
	protected $collection;
	
	/**
	 * base currency for the exchange rate
	 *
	 * @var string
	 */
	protected $baseCurrency;

	/**
	 * target currency of the exchange rate
	 *
	 * @var string
	 */
	protected $targetCurrency;

	/**
	 * the exchange rate from $baseCurrency to $targetCurrency
	 *
	 * @var float
	 */
	protected $rate;

	/**
	 * rate update time
	 *
	 * @var int unixtimestamp
	 */
	protected $time;

	public function __construct($baseCurrency, $targetCurrency, $rate = null, $time = null) {
		$this->time = is_null($time) ? time() : $time;
		$this->collection = Billrun_Factory::db()->exchangeratesCollection();
		$this->baseCurrency = $baseCurrency;
		$this->targetCurrency = $targetCurrency;
		$this->rate = is_null($rate) ? $this->getRate() : floatval($rate);
	}

	/**
	 * Add the current exchange rate to the DB
	 *
	 * @return boolean true on success, false otherwise
	 */
	public function add() {
		$entry = [
			'base_currency' => $this->baseCurrency,
			'target_currency' => $this->targetCurrency,
			'rate' => $this->rate,
			'from' => new MongoDate($this->time),
			'to' => new MongoDate(strtotime('+100 years', $this->time)),
		];

		$ret = $this->collection->insert($entry);
		return !empty($ret['ok']);
	}

	/**
	 * Saves the current exchange rate in the DB.
	 * Will add the exchange rate as a new revision
	 *
	 * @return void
	 */
	public function save() {
		$this->add();
		$this->closePrevious();
	}

	/**
	 * Close previous revisions of the exchange rate
	 *
	 * @return boolean true on success, false otherwise
	 */
	public function closePrevious() {
		$query = [
			'base_currency' => $this->baseCurrency,
			'target_currency' => $this->targetCurrency,
			'to' => [
				'$gt' => new MongoDate($this->time),
			],
			'from' => [
				'$lt' => new MongoDate($this->time),
			],
		];
		
		$update = [
			'$set' => [
				'to' => new MongoDate($this->time),
			],
		];
		
		$options = [
			'multiple' => true,
		];
		
		$ret = $this->collection->update($query, $update, $options);
		return !empty($ret['ok']);
	}

	/**
	 * get base currency
	 * 
	 * @return string currency
	 */
	public function getBaseCurrency() {
		return $this->baseCurrency;
	}

	/**
	 * get target currency
	 * 
	 * @return string currency
	 */
	public function getTargetCurrency() {
		return $this->targetCurrency;
	}
		
	/**
	 * get exchange rate from $baseCurrency to $targetCurrency
	 *
	 * @return string currency
	 */
	public function getRate() {
		if (is_null($this->rate)) {
			$query = [
				'base_currency' => $this->baseCurrency,
				'target_currency' => $this->targetCurrency,
				'to' => [
					'$gt' => new MongoDate($this->time),
				],
			];
			
			$rate = $this->collection->query($query)->cursor()->current();
			if (!$rate->isEmpty()) {
				$this->rate = floatval($rate['rate']);
			}
		}

		return $this->rate;
	}
}
