<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator base class
 *
 * @package  calculator
 * @since    0.5
 */
abstract class Billrun_Calculator extends Billrun_Base {

	use Billrun_Traits_ForeignFields;
	
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
	protected $rates_query = array();

	/**
     * is the calculator part of queue calculators
     *
     * @var bool
	 */
	 protected $isQueueCalc = true;

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

		if (isset($options['autosort'])) {
			$this->autosort = $options['autosort'];
		}

		if (!isset($options['autoload']) || $options['autoload']) {
			$this->load();
		}

		if (Billrun_Util::getFieldVal($options['calculator']['rates_query'], false)) {
			$this->rates_query = Billrun_Util::getFieldVal($options['calculator']['rates_query'], array());
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

		if ($this->isEnabled()) {
			$this->lines = $this->getLines();
			
			/* foreach ($resource as $entity) {
			$this->data[] = $entity;
			} */
			
			Billrun_Factory::log("Entities loaded: " . count($this->lines), Zend_Log::INFO);
			Billrun_Factory::dispatcher()->trigger('afterCalculatorLoadData', array('calculator' => $this));
		}
	}

	/**
	 * make the calculation
	 */
	abstract public function updateRow($row);
	
	
	abstract public function prepareData($lines);

	
	
	/**
	 * execute the calculation process
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculateData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$lines = $this->pullLines($this->lines);
		$this->prepareData($lines);
		foreach ($lines as $line) {
			if ($line) {
				Billrun_Factory::log("Calculating row: " . $line['stamp'], Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('beforeCalculateDataRow', array('data' => &$line));
				$line->collection($lines_coll);
				if ($this->isLineLegitimate($line)) {
					if ($this->updateRow($line) === FALSE) {
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
		Billrun_Factory::log('Writing ' . count($this->data) . ' lines to lines collection...', Zend_Log::DEBUG);
		foreach ($this->data as $key => $line) {
			$this->writeLine($line, $key);
		}
		$this->clearAddedForeignFields();
		Billrun_Factory::log('Updating ' . count($this->lines) . ' queue lines calculator flags...', Zend_Log::DEBUG);
		$this->setCalculatorTag();
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}

	/**
	 * Save a modified line to the lines collection.
	 * @param Mongodloid_Entity $line the line to write
	 * @param mixed $dataKey the line key in the calculator's data container
	 */
	public function writeLine($line, $dataKey) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		$linesCollection = Billrun_Factory::db()->linesCollection();
		$linesCollection->save($line, 1);
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
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
		//Billrun_Factory::log("stamps : ".print_r($stamps,1),Zend_Log::DEBUG);
		$lines = Billrun_Factory::db()->linesCollection()
				->query()->in('stamp', $stamps)->cursor();

		if ($this->autosort) {
			$lines->sort(array('urt' => 1));
		}

		//Billrun_Factory::log("Lines : ".print_r($lines->count(),1),Zend_Log::DEBUG);			
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
		$calculator_tag = $this->getCalculatorQueueType();
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
	protected function getBaseQuery() {
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = $this->getCalculatorQueueType();
		$queryData = array();
		$queue_id = array_search($calculator_type, $queue_calculators);
		$previous_calculator = false;
		if ($queue_id > 0) {
			$previous_calculator = $queue_calculators[$queue_id - 1];
			//$queryData['hint'] = $previous_calculator_tag;
		}

		$orphanConfigTime = Billrun_Factory::config()->getConfigValue('queue.calculator.orphan_wait_time', "6 hours");
		$orphand_time = strtotime($orphanConfigTime . " ago");
		// verify minimum orphan time to avoid parallel calculation
		if (Billrun_Factory::config()->isProd() && (time() - $orphand_time) < 3600) {
			Billrun_Factory::log("Calculator orphan time less than one hour: " . $orphanConfigTime . ". Please set value greater than or equal to one hour. We will take one hour for now", Zend_Log::NOTICE);
			$orphand_time = time() - 3600;
		}

		$query = array();
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
	public function removeFromQueue() {
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = $this->getCalculatorQueueType();
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
			Billrun_Factory::log('Removing ' . count($stamps) . ' lines from queue', Zend_Log::INFO);
			$query = array('stamp' => array('$in' => $stamps));
			$queue = Billrun_Factory::db()->queueCollection();
			$queue->remove($query);
			$lines = Billrun_Factory::db()->linesCollection();
 			$lines->update($query, array('$unset' => array('in_queue' => "")), array("multiple" => true));
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
		$querydata = $this->getBaseQuery();
		$query = array_merge($querydata['query'], $localquery);
		$update = $this->getBaseUpdate();
//		$fields = array();
//		$options = static::getBaseOptions();
		$retLines = array();
		$horizonlineCount = 0;
		do {
			//if There limit to the calculator set an updating limit.
			if ($this->limit != 0) {
				Billrun_Factory::log('Looking for the last available line in the queue', Zend_Log::DEBUG);
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
				Billrun_Factory::log("current limit : " . $horizonlineCount, Zend_Log::DEBUG);
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

			$this->workHash = md5(time() . rand(0, PHP_INT_MAX));
			$update['$set']['hash'] = $this->workHash;
			//Billrun_Factory::log(print_r($query,1),Zend_Log::DEBUG);
			$queue->update(array_merge($query, array('$isolated' => 1)), $update, array('multiple' => true));

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
	 * (Stab) Check if a given rate is a valid rate for rating
	 * @param type $rate the rate to check
	 * @return boolean true  if the rate is ok for use  false otherwise.
	 */
	protected function isRateValid($rate) {
		return true;
	}

	/**
	 * Caches the rates in the memory for fast computations
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = Billrun_Factory::db()->ratesCollection()->query($this->rates_query)->cursor();
		$this->rates = array();
		foreach ($rates as $rate) {
			if ($this->isRateValid($rate)) {
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
	}

	/**
	 * Get the  current  calculator type, to be used in the queue.
	 * @return string the  type  of the calculator
	 */
	abstract public function getCalculatorQueueType();

	/**
	 * Check if a given line  can be handeld by  the calcualtor.
	 * @param @line the line to check.
	 * @return boolean true if the line  can be handled  by the  calculator  false otherwise.
	 */
	abstract protected function isLineLegitimate($line);

	/**
	 * Get queue line by its stamp is it was loaded by the calculator
	 * @param type $stamp
	 * @todo create queue trait to be used in processors / calculators
	 */
	public function getQueueLine($stamp) {
		if (isset($this->lines[$stamp])) {
			return $this->lines[$stamp];
		} else {
			return null;
		}
	}

	/**
	 * 
	 * @return array
	 * @todo change this one to be abstract
	 */
	public function getPossiblyUpdatedFields() {
		return $this->getAddedFoerignFields();
	}
	
	/**
	 * is the calculator type in queue.calculators
	 *
	 * @return boolean
	 */
	public function isInQueueCalculators() {
		$queueCalculators = Billrun_Factory::config()->getConfigValue('queue.calculators', []);
		$calculatorType = $this->getCalculatorQueueType();
		return in_array($calculatorType, $queueCalculators);
	}
	
	/**
	 * should the calculator be configured as a queue calculator
	 *
	 * @return boolean
	 */
	public function isQueueType() {
		return $this->isQueueCalc;
	}
		
	/**
	 * is the calculator enabled (allowed to run)
	 *
	 * @return boolean
	 */
	public function isEnabled() {
		return !$this->isQueueType() || $this->isInQueueCalculators();
	}

}
