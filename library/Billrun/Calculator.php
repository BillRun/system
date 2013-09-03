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
abstract class	Billrun_Calculator extends Billrun_Base {

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
	abstract protected function updateRow($row);

	/**
	 * execute the calculation process
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforeRateData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();
		foreach ($this->lines as $key => $item) {
			$line = $this->pullLine($item);
			if ($line) {
				//Billrun_Factory::log()->log("Calcuating row : ".print_r($item,1),  Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('beforeRateDataRow', array('data' => &$line));
				$line->collection($lines_coll);
				if ($this->isLineLegitimate($line)) {
					if (!$this->updateRow($line)) {
						unset($this->lines[$key]);
						continue;
					}
					$this->writeLine($line);
				}
				$this->data[] = $line;
				Billrun_Factory::dispatcher()->trigger('afterRateDataRow', array('data' => &$line));
			} else {
				unset($this->lines[$key]);
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterRateData', array('data' => $this->data));
	}

	/**
	 * execute write the calculation output into DB
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		//no need  the  line is now  written right after update
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
		$this->setCalculatorTag();
	}

	/**
	 * Save a modified line to the lines collection.
	 */
	public function writeLine($line) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line));
		$line->save(Billrun_Factory::db()->linesCollection());
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line));
	}

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
	 * 
	 * @param type $calculator_type
	 * @return type
	 */
	static protected function getCalculatorQueueTag($calculator_type = null) {
		if (is_null($calculator_type)) {
			$calculator_type = static::getCalculatorQueueType();
		}
		return 'calculator_' . $calculator_type;
	}

	protected function setCalculatorTag() {
		$queue = Billrun_Factory::db()->queueCollection();
		$calculator_tag = $this->getCalculatorQueueTag();
		//foreach ($this->data as $item) {
		$query = array('hash' => $this->workHash , $calculator_tag => $this->signedMicrotime );//array('stamp' => $item['stamp']);
		$update = array('$set' => array($calculator_tag => true));
		$queue->update($query, $update);
		//}
	}

	static protected function getBaseQuery() {
		$calculators_queue_order = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = static::getCalculatorQueueType();
		$queue_id = array_search($calculator_type, $calculators_queue_order);
		if ($queue_id > 0) {
			$previous_calculator_type = $calculators_queue_order[$queue_id - 1];
			$previous_calculator_tag = self::getCalculatorQueueTag($previous_calculator_type);
			$query[$previous_calculator_tag] = true;
		}
		$current_calculator_queue_tag = self::getCalculatorQueueTag($calculator_type);
		$orphand_time =  strtotime(Billrun_Factory::config()->getConfigValue('queue.calculator.orphan_wait_time', "6 hours") . " ago") ;
		$query['$and'][0]['$or'] = array(
			array($current_calculator_queue_tag => array('$exists' => false)),
			array($current_calculator_queue_tag => array(
					'$ne' => true, '$lt' => $orphand_time
				))
		);
		return $query;
	}

	protected function getBaseUpdate() {
		$current_calculator_queue_tag = self::getCalculatorQueueTag();
		$this->signedMicrotime = microtime(true);
		$update = array(
			'$set' => array(
				$current_calculator_queue_tag => $this->signedMicrotime,
			)
		);
		return $update;
	}

	static protected function getBaseOptions() {
		$options = array(
			"sort" => array(
				"_id" => 1,
			),
		);
		return $options;
	}

	public final function removeFromQueue() {
		$calculators_queue_order = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = static::getCalculatorQueueType();
		$queue_id = array_search($calculator_type, $calculators_queue_order);
		end($calculators_queue_order);
		if ($queue_id == key($calculators_queue_order)) { // last calculator
			Billrun_Factory::log()->log("Removing lines from queue", Zend_Log::INFO);
			$queue = Billrun_Factory::db()->queueCollection();
			foreach ($this->data as $item) {
				$query = array('stamp' => $item['stamp']);
				$queue->remove($query);
			}
		}
	}

	protected function getQueuedLines($localquery) {
		$queue = Billrun_Factory::db()->queueCollection();
		$query = array_merge(static::getBaseQuery(), $localquery);
		$update = $this->getBaseUpdate();
//		$fields = array();
//		$options = static::getBaseOptions();
		$current_calculator_queue_tag = $this->getCalculatorQueueTag();
//		$docs = array();
//		$qlines =  $queue->query($query)->cursor()->limit( $this->limit );
//		foreach($qlines as $qline) {
//			$doc = $queue->findAndModify(array_merge( array('stamp' => $qline['stamp']), $query), $update, $fields, $options);
//			if(!$doc->isEmpty()) {
//				$docs[] = $doc;		
//			}
//		}
//		return $docs;				 					

		$horizonline = new Mongodloid_Entity();
		for($limit=$this->limit; $limit > 1 && $horizonline->isEmpty();$limit=intval(max(1,$limit/2)) ) {			
			Billrun_Factory::log()->log("searching for limit of : $limit",Zend_Log::DEBUG);
			$horizonline = $queue->query($query)->cursor()->sort(array('_id'=> 1))->skip($limit)->limit(1)->current();
		}		
		
		if(!$horizonline->isEmpty()) {
			$query['$isolated'] = 1;//isolate the update
			$this->workHash= md5( time() . rand(0,PHP_INT_MAX) );
			$update['$set']['hash'] = $this->workHash;
			$query['_id'] = array( '$lt' => $horizonline['_id']->getMongoID() );
			//Billrun_Factory::log()->log(print_r($query,1),Zend_Log::DEBUG);
			$queue->update($query, $update, array('multiple'=> true));			
			return $queue->query( array_merge($localquery,array('hash' => $this->workHash , $current_calculator_queue_tag => $this->signedMicrotime )))->cursor();
		}
		return array();
	}

	/**
	 * Get the  current  calculator type, to be used in the queue.
	 * @return string the  type  of the calculator
	 */
	abstract protected static function getCalculatorQueueType();


	/**
	 * Check if a given line  can be handeld by  the calcualtor.
	 * @param @line the line to check.
	 * @return ture if the line  can be handled  by the  calculator  false otherwise.
	 */
	abstract protected function isLineLegitimate($line);
}
