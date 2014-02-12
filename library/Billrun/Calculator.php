<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator base class
 *
 * @package  calculator
 * @since    0.5
 */
abstract class Billrun_Calculator extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'calculator';

	/**
	 * the container data of the calculator
	 * @var Mongodloid_Cursor the data container
	 */
	protected $data = array();

	/**
	 * The lines to rate
	 * @var array
	 */
	protected $lines = array();

	/**
	 * Limit iterator
	 * used to limit the count of row to calc on.
	 * 0 or less means no limit
	 *
	 * @var int
	 */
	protected $limit = 1000000;

	/**
	 *
	 * @var int calculation period in months
	 */
	protected $months_limit = null;

	/**
	 * The  time that  the queue lines were signed in for this calculator run.
	 * @var type 
	 */
	protected $signedMicrotime = 0;

	/**
	 * The work hash that this calculator used.
	 * @var type 
	 */
	protected $workHash = 0;

	/**
	 * array of rates for pre-processing
	 * @var array
	 */
	protected $rates = array();

	/**
	 * auto sort lines and queue on pull from db
	 * useful for time consumption
	 * 
	 * @var boolean
	 */
	protected $autosort = true;
	protected $queue_coll = null;

	/**
	 * constructor of the class
	 * 
	 * @param array $options the options of object load
	 */
	public function __construct($options = array()) {
		parent::__construct($options);

		if (isset($options['calculator']['limit'])) {
			$this->limit = $options['calculator']['limit'];
		}


		if (isset($options['months_limit'])) {
			$this->months_limit = $options['months_limit'];
		}

		if (!isset($options['autoload']) || $options['autoload']) {
			$this->load();
		}

		if (isset($options['autosort'])) {
			$this->autosort = $options['autosort'];
		}
		$this->queue_coll = Billrun_Factory::db()->queueCollection();
	}

	/**
	 * method to get calculator lines
	 */
	abstract protected function getLines();

	/**
	 * load the data to run the calculator for
	 * 
	 * @param boolean $initData reset the data in the calculator before loading
	 * 
	 */
	public function load($initData = true) {

		if ($initData) {
			$this->lines = array();
		}

		$this->lines = $this->getLines();

		/* foreach ($resource as $entity) {
		  $this->data[] = $entity;
		  } */

		Billrun_Factory::log()->log("entities loaded: " . count($this->lines), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorLoadData', array('calculator' => $this));
	}

	/**
	 * write the calculation into DB
	 */
	abstract public function updateRow($row);

	/**
	 * execute the calculation process
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculateData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$lines = $this->pullLines($this->lines);
		foreach ($lines as $line) {
			if ($line) {
				Billrun_Factory::log()->log("Calcuating row : " . $line['stamp'], Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('beforeCalculateDataRow', array('data' => &$line));
				$line->collection($lines_coll);
				if ($this->isLineLegitimate($line)) {
					if (!$this->updateRow($line)) {
						unset($this->lines[$line['stamp']]);
						continue;
					}
					$this->data[$line['stamp']] = $line;
				}
				Billrun_Factory::dispatcher()->trigger('afterCalculateDataRow', array('data' => &$line));
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculateData', array('data' => $this->data));
	}

	/**
	 * Execute write the calculation output into DB
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		//no need  the  line is now  written right after update @TODO now that we do use queue shuold the lines wirte be here?
		Billrun_Factory::log()->log('Writing ' . count($this->data) . ' lines to lines collection...', Zend_Log::DEBUG);
		foreach ($this->data as $key => $line) {
			$this->writeLine($line, $key);
		}
		Billrun_Factory::log()->log('Updating ' . count($this->lines) . ' queue lines calculator flags...', Zend_Log::DEBUG);
		$this->setCalculatorTag();
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}

	/**
	 * Save a modified line to the lines collection.
	 * @param Mongodloid_Entity $line the line to write
	 * @param mixed $dataKey the line key in the calculator's data container
	 */
	public function writeLine($line, $dataKey) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line));
		$line->save(Billrun_Factory::db()->linesCollection());
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line));
		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->removeLineFromQueue($line);
			unset($this->data[$dataKey]);
		}
	}

	/**
	 * Pull all the lines from the lines collection from their associated queue lines.
	 * @param type $queueLines 
	 * @return boolean|mixed a DB cursor of all the lines the 
	 */
	protected function pullLines($queueLines) {
		$stamps = array();
		foreach ($queueLines as $item) {
			$stamps[] = $item['stamp'];
		}
		//Billrun_Factory::log()->log("stamps : ".print_r($stamps,1),Zend_Log::DEBUG);
		$lines = Billrun_Factory::db()->linesCollection()
				->query()->in('stamp', $stamps)->cursor();

		if ($this->autosort) {
			$lines->sort(array('urt' => 1));
		}

		//Billrun_Factory::log()->log("Lines : ".print_r($lines->count(),1),Zend_Log::DEBUG);			
		return $lines;
	}

	/**
	 * Retrive the actual CDR line for a given queue line.
	 * @param type $queue_line the queue line to retrive  it`s CDR line.
	 * @return boolean
	 */
	protected function pullLine($queue_line) {
		$line = Billrun_Factory::db()->linesCollection()->query('stamp', $queue_line['stamp'])
				->cursor()->current();
		if ($line->isEmpty()) {
			return false;
		}
		$line->collection(Billrun_Factory::db()->linesCollection());
		return $line;
	}

	/**
	 * Mark the calculation as finished in the queue.
	 * @param array $query additional query parameters to the queue
	 * @param array $update additional fields to update in the queue
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		$calculator_tag = static::getCalculatorQueueType();
		$stamps = array();
		foreach ($this->lines as $item) {
			$stamps[] = $item['stamp'];
		}
		$query = array_merge($query, array('stamp' => array('$in' => $stamps), 'hash' => $this->workHash, 'calc_time' => $this->signedMicrotime)); //array('stamp' => $item['stamp']);
		$update = array_merge($update, array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false)));
		$this->queue_coll->update($query, $update, array('multiple' => true));
	}

	/**
	 * Get the base query to get lines from the queue for the current calculator
	 * @return array
	 */
	static protected function getBaseQuery() {
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = static::getCalculatorQueueType();
		$queryData = array();
		$queue_id = array_search($calculator_type, $queue_calculators);
		$previous_calculator = false;
		if ($queue_id > 0) {
			$previous_calculator = $queue_calculators[$queue_id - 1];
			//$queryData['hint'] = $previous_calculator_tag;
		}
		$orphand_time = strtotime(Billrun_Factory::config()->getConfigValue('queue.calculator.orphan_wait_time', "6 hours") . " ago");
		// TODO: initialize this array with PHP strict standards
		$query['$and'][0]['calc_name'] = $previous_calculator;
		$query['$and'][0]['$or'] = array(
			array('calc_time' => false),
			array('calc_time' => array(
					'$ne' => true, '$lt' => $orphand_time
				)),
		);
//		$queryData['hint'] = $current_calculator_queue_tag;
		$queryData['query'] = $query;
		return $queryData;
	}

	/**
	 * Get the base query to mark a working process in the queue
	 * @return array
	 */
	protected function getBaseUpdate() {
		$this->signedMicrotime = microtime(true);
		$update = array(
			'$set' => array(
				'calc_time' => $this->signedMicrotime,
			)
		);
		return $update;
	}

//	/**
//	 * 
//	 * @return array
//	 */
//	static protected function getBaseOptions() {
//		$options = array(
//			"sort" => array(
//				"_id" => 1,
//			),
//		);
//		return $options;
//	}

	/**
	 * Remove lines from the queue if the current calculator is the last one or if final_calc is set for a queue line and equals the current calculator
	 */
	public final function removeFromQueue() {
		$queue = Billrun_Factory::db()->queueCollection();
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = static::getCalculatorQueueType();
		$queue_id = array_search($calculator_type, $queue_calculators);
		end($queue_calculators);
		// remove  recalculated lines.	
		$stamps = array();
		foreach ($this->lines as $queueLine) {
			if (($queue_id == key($queue_calculators)) || (isset($queueLine['final_calc']) && ($queueLine['final_calc'] == $calculator_type ))) {
				$stamps[] = $queueLine['stamp'];
			}
		}

		// remove end of queue stack calculator
		if (!empty($stamps)) { // last calculator
			Billrun_Factory::log()->log("Removing lines from queue", Zend_Log::INFO);
			$query = array('stamp' => array('$in' => $stamps));
			$queue->remove($query);
		}
	}

	/**
	 * Remove a particular line from the queue
	 * @param type $line the line to be removed
	 */
	protected function removeLineFromQueue($line) {
		$query = array(
			'stamp' => $line['stamp'],
		);
		Billrun_Factory::db()->queueCollection()->remove($query);
	}

	/**
	 * Get the queue lines for the current calculator
	 * @param array $localquery the specific calculator filter query (usually on 'type' field)
	 * @return array the queue lines
	 */
	protected function getQueuedLines($localquery) {
		$queue = Billrun_Factory::db()->queueCollection();
		$querydata = static::getBaseQuery();
		$query = array_merge($querydata['query'], $localquery);
		$update = $this->getBaseUpdate();
//		$fields = array();
//		$options = static::getBaseOptions();
		$retLines = array();
		$horizonlineCount = 0;
		do {
			//if There limit to the calculator set an updating limit.
			if ($this->limit != 0) {
				Billrun_Factory::log()->log('Looking for the last available line in the queue', Zend_Log::DEBUG);
				if (isset($querydata['hint'])) {
					$hq = $queue->query($query)->cursor()->hint(array($querydata['hint'] => 1))->limit($this->limit);
					if ($this->autosort) {
						$hq->sort(array('urt' => 1));
					}
				} else {
					$hq = $queue->query($query)->cursor()->limit($this->limit);
					if ($this->autosort) {
						$hq->sort(array('urt' => 1));
					}
				}
				$horizonlineCount = $hq->count(true);
				$horizonline = $hq->skip(abs($horizonlineCount - 1))->limit(1)->current();
				Billrun_Factory::log()->log("current limit : " . $horizonlineCount, Zend_Log::DEBUG);
				if (!$horizonline->isEmpty()) {
					if ($this->autosort) {
						$query['urt'] = array('$lte' => $horizonline['urt']);
					} else {
						$query['_id'] = array('$lte' => $horizonline->getId()->getMongoID());
					}
				} else {
					return $retLines;
				}
			}

			$query['$isolated'] = 1; //isolate the update
			$this->workHash = md5(time() . rand(0, PHP_INT_MAX));
			$update['$set']['hash'] = $this->workHash;
			//Billrun_Factory::log()->log(print_r($query,1),Zend_Log::DEBUG);
			$queue->update($query, $update, array('multiple' => true));

			$foundLines = $queue->query(array_merge($localquery, array('hash' => $this->workHash, 'calc_time' => $this->signedMicrotime)))->cursor();

			if ($this->autosort) {
				$foundLines->sort(array('urt' => 1));
			}
		} while ($horizonlineCount != 0 && $foundLines->count() == 0);

		foreach ($foundLines as $line) {
			$retLines[$line['stamp']] = $line;
		}
		return $retLines;
	}

	/**
	 * Caches the rates in the memory for fast computations
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = Billrun_Factory::db()->ratesCollection()->query()->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
		$this->rates = array();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			if (isset($rate['params']['prefix'])) {
				foreach ($rate['params']['prefix'] as $prefix) {
					$this->rates[$prefix][] = $rate;
				}
			} else if ($rate['key'] == 'UNRATED') {
				$this->rates['UNRATED'] = $rate;
			} else {
				$this->rates['noprefix'][] = $rate;
			}
		}
	}

	/**
	 * Get the  current  calculator type, to be used in the queue.
	 * @return string the  type  of the calculator
	 */
	abstract public static function getCalculatorQueueType();

	/**
	 * Check if a given line  can be handeld by  the calcualtor.
	 * @param @line the line to check.
	 * @return ture if the line  can be handled  by the  calculator  false otherwise.
	 */
	abstract protected function isLineLegitimate($line);
}
