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

	use Billrun_Traits_ForeignFields {
		getForeignFieldsFromConfig as baseGetForeignFieldsFromConfig;
	}
	
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
	 * BRCD-3562 chain mode: the run pulls the queue ONCE - lines waiting at this
	 * calculator's stage and at every later stage - and runs every pulled line
	 * through all its remaining calculators in memory (the processor's engine,
	 * Billrun_Helpers_QueueCalculators). Switched on by the 'chain' option, for
	 * calculators that are a configured queue stage.
	 *
	 * @var bool
	 */
	protected $chain = false;

	/**
	 * the run options handed to the in-memory chain (chain mode)
	 * @var array
	 */
	protected $chainOptions = array();

	/**
	 * stamps of lines that completed their whole chain (chain mode)
	 * @var array
	 */
	protected $completedStamps = array();

	/**
	 * stamp => the last stage a line completed before failing a later one (chain mode)
	 * @var array
	 */
	protected $advancedStages = array();

	/**
	 * queue rows the chain created (extra lines), inserted at write (chain mode)
	 * @var array
	 */
	protected $extraQueueRows = array();

	/**
	 * the chain runner, released after the write (chain mode)
	 * @var Billrun_Helpers_QueueCalculators
	 */
	protected $chainRunner = null;

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

		if (isset($options['file_types'])) {
			if(!is_array($options['file_types'])){
				$options['file_types'] = [$options['file_types']];
			}
			$this->file_types = $options['file_types'];
		}

		if (!empty($options['chain']) && $this->isInQueueCalculators()) {
			$this->chain = true;
			// The chain re-instantiates every stage: hand it the run options without
			// this instance's own leftovers - above all 'calculator' (the merged
			// <type>.calculator section), which would override every stage's own section.
			$this->chainOptions = $options;
			unset($this->chainOptions['calculator'], $this->chainOptions['type'], $this->chainOptions['chain']);
			// A chain run is bounded by the smallest limit among the stages it may run;
			// a stage without a limit of its own counts with this calculator's limit
			// (already set above from the merged <type>.calculator section, or the default).
			$this->limit = self::getChainLimit($this->getCalculatorQueueType(), $options, $this->limit);
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
		$lines = $this->pullLines($this->lines);
		$this->prepareData($lines);
		foreach ($lines as $line) {
                    $extraLines = $this->addExtraLines($line);
                    $this->calculateDataRow($line);
                    foreach ($extraLines as $extraLine){
                        $extraLine = ($extraLine instanceof Mongodloid_Entity) ? $extraLine : new Mongodloid_Entity($extraLine);
                        $this->calculateDataRow($extraLine);
                    }
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculateData', array('data' => $this->data));
	}
	
        protected function calculateDataRow($line) {
            $lines_coll = Billrun_Factory::db()->linesCollection();
			if ($line) {
				Billrun_Factory::log("Calculating row: " . $line['stamp'], Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('beforeCalculateDataRow', array('data' => &$line));
				$line->collection($lines_coll);
				if ($this->isLineLegitimate($line)) {
					if ($this->updateRow($line) === FALSE) {
						unset($this->lines[$line['stamp']]);
					}
					$this->data[$line['stamp']] = $line;
				}
				Billrun_Factory::dispatcher()->trigger('afterCalculateDataRow', array('data' => &$line));
			}
		}
        /**
         * Adding extra lines to calculated lines. 
         * @param Mongodloid_Entity $line- that we want to check if generate extra lines (inside the trigger)
         * @param array $extraData - the extra lines we need to add to the calculator data. 
         * @return array $extraData - the extra lines we need to add to the calculator data. 
         */
	protected function addExtraLines($line, $extraData = []){
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorAddExtraLines', array('data' => &$line, 'extraData' => &$extraData, 'calculator' => $this));
		if(!empty($extraData)){
			$queueLinesToInsert = [];
			$linesToInsert = [];
			foreach ($extraData as $originalStamp => $extraDataByStamp){
				foreach ($extraDataByStamp as $newStamp => $extraRow){
					$newQueueLine = $this->pullQueueLineByStamp($originalStamp);
					$saveProperties = $this->getPossiblyUpdatedFields();
					foreach ($saveProperties as $p) {
						if (!is_null($val = Billrun_Util::getIn($extraRow, $p, null))) {
							$newQueueLine[$p] = $val;
						}
					}
					unset($newQueueLine['_id']);
					$newQueueLine['stamp'] = $newStamp;
					$queueLinesToInsert[$newStamp] = $newQueueLine;
					$linesToInsert[$newStamp] = $extraRow;
					$this->lines[$newStamp] = $newQueueLine;
				}
			}
			Billrun_Factory::log("Calculator " . $this->getType() . ": before lines batch insert to lines collection", Zend_Log::DEBUG);
			$this->batchInsertLines($linesToInsert, Billrun_Factory::db()->linesCollection());
			Billrun_Factory::log("Calculator " . $this->getType() . ": before lines batch insert to queue collection", Zend_Log::DEBUG);
			$this->batchInsertLines($queueLinesToInsert, Billrun_Factory::db()->queueCollection());
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorAddExtraLines', array('data' => &$line, 'extraData' => &$extraData, 'calculator' => $this));
		return $extraData[$line['stamp']] ?? [];
	}

	/**
	 * Batch inserting lines to collection
	 * @param array $linesToInsert
	 * @param type $collection 
	 */
	protected function batchInsertLines($linesToInsert, $collection) {
		try {
			$ret = $collection->batchInsert($linesToInsert);
			Billrun_Factory::log("Calculator " . $this->getType() . ": after lines batch insert", Zend_Log::DEBUG);
			if (isset($ret['err']) && !is_null($ret['err'])) {
				Billrun_Factory::log("Calculator " . $this->getType() . ": batch insertion of adding extra lines to lines failed, Insert Error: " . $ret['err'], Zend_Log::ALERT);
				throw new Exception();
			}
		} catch (Exception $e) {
			try {
				Billrun_Factory::log("Calculator " . $this->getType() . ": Batch insert failed during of adding extra lines to lines, removing duplicate lines and retry the bulkInsert, Error: " . $e->getMessage(), Zend_Log::ERR);
				$linesWithoutDuplicates = $this->removeDuplicateLines($linesToInsert, $collection);
				$ret = $collection->batchInsert($linesWithoutDuplicates);
				if (isset($ret['err']) && !is_null($ret['err'])) {
					Billrun_Factory::log("Calculator " . $this->getType() . ": batch insertion of adding extra lines to lines failed, Insert Error: " . $ret['err'], Zend_Log::ALERT);
					throw new Exception();
				}
			} catch (Exception $ex) {
				Billrun_Factory::log("Calculator " . $this->getType() . ": Batch insert failed during of adding extra lines to lines, inserting line by line, Error: " . $ex->getMessage(), Zend_Log::ERR);
				$this->restoringLinesLineByLine($linesToInsert, $collection);
			}
		}
	}
	
	/**
	 * Remove all the lines that are in $archivedLinesToInsert and also already in lines collection
	 * @param array $archivedLinesToInsert
	 * @param array $archivedLinesStamps
	 */
	protected function removeDuplicateLines($linesToInsert, $collection) {
		$query = array('stamp' => array('$in' => array_keys($linesToInsert)));
		$duplicateLines = $collection->query($query)->cursor()->fields(array('stamp' => 1))->setRawReturn(true);
		$duplicateLinesStamps = array_column(iterator_to_array($duplicateLines), 'stamp');
		foreach ($duplicateLinesStamps as $duplicateLineStamp) {
			unset($linesToInsert[$duplicateLineStamp]);
		}
		return array_values($linesToInsert);
	}
	
	protected function restoringLinesLineByLine($linesToInsert, $collection) {
		foreach ($linesToInsert as $stamp => $line) {
			try {
				$ret = $collection->insert($line); // ok==1, err null
				if (isset($ret['err']) && !is_null($ret['err'])) {
					Billrun_Factory::log("Calculator " . $this->getType() . ": line insertion of adding extra line to lines failed, Insert Error: " . $ret['err'] . ", failed_line " . $stamp, Zend_Log::ALERT);
					throw new Exception($ret['err']);
				}
			} catch (Exception $e) {
				if (in_array($e->getCode(), Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR)) {
					Billrun_Factory::log("Calculator " . $this->getType() . ": line insertion of adding extra line to lines failed, Insert Error: " . $e->getMessage() . ", failed_line " . $stamp, Zend_Log::NOTICE);
					continue;
				} else {
					Billrun_Factory::log("Calculator " . $this->getType() . ": line insertion of adding extra line to lines failed, Insert Error: " . $e->getMessage() . ", failed_line " . $stamp, Zend_Log::ALERT);
					throw $e;
				}
			}
		}
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
			$lines->sort(array('calc_time'=> 1, 'urt' => 1));
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

	protected function pullQueueLineByStamp($stamp) {
		$queue_line = Billrun_Factory::db()->queueCollection()->query('stamp', $stamp)
				->cursor()->current();
		if ($queue_line->isEmpty()) {
			return false;
		}
		$queue_line->collection(Billrun_Factory::db()->queueCollection());
		return $queue_line;
	}
	/**
	 * Mark the calculation as finished in the queue.
	 * @param array $query additional query parameters to the queue
	 * @param array $update additional fields to update in the queue
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		$calculator_tag = $this->getQueueTagType();
		$stamps = array();
		foreach ($this->lines as $item) {
			$stamps[] = $item['stamp'];
		}
		$query = array_merge($query, array('stamp' => array('$in' => $stamps), 'hash' => $this->workHash, 'calc_time' => $this->signedMicrotime)); //array('stamp' => $item['stamp']);
		$update = array_merge($update, array('$set' => array('calc_name' => $calculator_tag, 'calc_time' => false)));
		$this->queue_coll->update($query, $update, array('multiple' => true));
	}

	/**
	 * The calc_name condition queue pulls filter by: the previous calculator - or,
	 * in chain mode (BRCD-3562), the entry of this stage and of every later one,
	 * so the single pull takes lines waiting at any stage the chain may run.
	 *
	 * @param mixed $previous_calculator this calculator's previous stage (false for the first)
	 * @return mixed
	 */
	protected function getPullCalcNameCondition($previous_calculator) {
		if ($this->chain) {
			$queueCalculators = Billrun_Factory::config()->getConfigValue('queue.calculators', array());
			return array('$in' => self::getChainEntryStages($this->getCalculatorQueueType(), $queueCalculators));
		}
		return $previous_calculator;
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

		$calcNameCondition = $this->getPullCalcNameCondition($previous_calculator);
		$query = array();
		$query['$and'][0]['calc_name'] = $calcNameCondition;
		$query['$and'][0]['$or'] = array(
			array('calc_name'=> $calcNameCondition, 'calc_time' => false),//improving query using index with calc_name
			array('calc_name' => $calcNameCondition, 'calc_time' => array(
					'$ne' => true, '$lt' => new Mongodloid_Date($orphand_time)
				)),
		);
		if(isset($this->file_types) ){
			
			$query['$and'][0]['type'] = array('$in' => $this->file_types);
		}
//		$queryData['hint'] = $current_calculator_queue_tag;
		$queryData['query'] = $query;
		return $queryData;
	}

	/**
	 * Get the base query to mark a working process in the queue
	 * @return array
	 */
	protected function getBaseUpdate() {
		$this->signedMicrotime = new Mongodloid_Date();
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
	 * Get the queue stage to tag processed lines with.
	 * By default this is the calculator's own queue type; a calculator that
	 * completes additional queue stages within its own run (e.g. pricing
	 * applies tax) may return a later stage in order to skip the stages it
	 * has already covered.
	 *
	 * @return string
	 */
	public function getQueueTagType() {
		return $this->getCalculatorQueueType();
	}

	/**
	 * Remove lines from the queue if the current calculator is the last one or if final_calc is set for a queue line and equals the current calculator
	 */
	public function removeFromQueue() {
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = $this->getCalculatorQueueType();
		$completed_type = $this->getQueueTagType();
		$queue_id = array_search($completed_type, $queue_calculators);
		end($queue_calculators);
		// remove  recalculated lines.
		$stamps = array();
		foreach ($this->lines as $queueLine) {
			if (($queue_id == key($queue_calculators)) || (isset($queueLine['final_calc']) && in_array($queueLine['final_calc'], array($calculator_type, $completed_type)))) {
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
		$queue = Billrun_Factory::db()->queueCollection()->setReadPreference('RP_PRIMARY');
		$querydata = $this->getBaseQuery();
		$query = array_merge($querydata['query'], $localquery);
		$update = $this->getBaseUpdate();
		$retLines = array();
		$horizonlineCount = 0;
		do {
			//if There limit to the calculator set an updating limit.
			if ($this->limit != 0) {
				Billrun_Factory::log('Looking for the last available line in the queue', Zend_Log::DEBUG);
				if (isset($querydata['hint'])) {
					$hq = $queue->query($query)->cursor()->hint(array($querydata['hint'] => 1));
					if ($this->autosort) {
						$hq->limit($this->limit + 1)->sort(array('calc_time'=>1, 'urt' => 1));
					}else{
						$hq->limit($this->limit);
					}
				} else {
					$hq = $queue->query($query)->cursor()->limit($this->limit);
					if ($this->autosort) {
						$hq->limit($this->limit + 1)->sort(array('calc_time'=>1, 'urt' => 1));
					}else{
						$hq->limit($this->limit);
					}
				}
				$updateQuery = $this->getBaseUpdateQuery($query, $hq);
				if(!$updateQuery){
					return $retLines;
				}		
			}

			$this->workHash = md5(time() . rand(0, PHP_INT_MAX));
			Billrun_Factory::log('Work hash: ' . $this->workHash, Zend_Log::DEBUG);
			$update['$set']['hash'] = $this->workHash;
			if (($response = $this->applyQueueHash($updateQuery, $update, $queue)) === FALSE) {
				continue;
			}
			Billrun_Factory::log('Queue records updated number: ' . $response['nModified'], Zend_Log::DEBUG);

			$foundLines = $queue->query(array_merge($localquery, array('hash' => $this->workHash, 'calc_time' => $this->signedMicrotime)))->cursor();
			if ($foundLines->count() != $response['nModified']) {
				// TODO remove when https://billrun.atlassian.net/browse/BRCD-3852 is fixed
				Billrun_Factory::log('Found wrong number of lines: ' . $foundLines->count(), Zend_Log::DEBUG);
			}

			if ($this->autosort) {
				$foundLines->sort(array('urt' => 1));
			}
		} while ($horizonlineCount != 0 && $foundLines->count() == 0);//this is probably because another process already 'stole' and mark this so this process should try again.

		foreach ($foundLines as $line) {
			$retLines[$line['stamp']] = $line;
		}
		return $retLines;
	}

	protected function getBaseUpdateQuery($query, $hq){
		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");
		$calculator_type = $this->getCalculatorQueueType();
		$queryData = array();
		$queue_id = array_search($calculator_type, $queue_calculators);
		$previous_calculator = false;
		if ($queue_id > 0) {
			$previous_calculator = $queue_calculators[$queue_id - 1];
		}
		$horizonlineCount = $hq->count(true);
		$fields = array(
			'urt' => 1,
			'calc_time' => 1
		);
		// First document
		$first = (clone $hq)->limit(1)->fields($fields)->current();

		// Last document
		$last = (clone $hq)->skip(abs($horizonlineCount - 1))->limit(1)->fields($fields)->current();
		Billrun_Factory::log("current limit : " . $horizonlineCount, Zend_Log::DEBUG);
		if (!$last->isEmpty()) {
			if ($this->autosort) {
				if (Mongodloid_Date::equals($last['calc_time'], $first['calc_time'])) {
					$query['$and'][] = ['calc_time' => $last['calc_time']];
					if ($horizonlineCount == $this->limit + 1 && !Mongodloid_Date::equals($first['urt'], $last['urt'])) {
						$query['$and'][] = [
							'urt' => ['$gte' => $first['urt'], '$lt' => $last['urt']]//the (limit+1)th doc is only the boundary - exclude its urt so no more than `limit` docs are marked
						];
					} else {//partial last page, or all docs share one urt - $lt would mark nothing and loop forever
						$query['$and'][] = [
							'urt' => ['$gte' => $first['urt'], '$lte' => $last['urt']]
						];
					}
				} else {
					if($first['calc_time'] ==  false){
						$calcNameCondition = $this->getPullCalcNameCondition($previous_calculator);
						$query['$and'][] = [
							'$or' => [
								['calc_name' => $calcNameCondition, 'calc_time' => ['$lt' => $last['calc_time'], '$gte' => $first['calc_time']]],//$lt will not update the exect limit but this prevent to update (2limit) and than can grow to more.
								['calc_name' => $calcNameCondition, 'calc_time' => false],

							]
						];
					}else{
						$query['$and'][] = ['calc_time' => ['$gte' => $first['calc_time'], '$lt' => $last['calc_time']]];//$lt will not update the exect limit but this prevent to update (2limit) and than can grow to more. 

					}
				}	
			} else {
				$query['_id'] = array('$lte' => $last->getId()->getMongoID());
			}
			return $query;
		} else {
			return false;
		}
	}

	/**
	 * method to apply query update hash
	 * 
	 * @param array $query the query to filter
	 * @param array $update the update to be applied
	 * @param Collection $queue the mongodb collection
	 * 
	 * @return boolean true on success else false
	 */
	protected function applyQueueHash($query, $update, $queue) {
		/*if (Billrun_Factory::db()->compareServerVersion('4.2.0', '>=')) {
			$session = Billrun_Factory::db()->startSession();
			if ($session !== false) {
				$session->startTransaction();
				try {
					$queue->update($query, $update, array('multiple' => true, 'session' => $session));
					$session->commitTransaction();
					return true;
				} catch (Exception $ex) {
					$session->abortTransaction();
					return false;
				}
			}
			Billrun_Factory::log("No support for transactions as you're running on mongodb standalone", Zend_Log::NOTICE);
		} else {
			Billrun_Factory::log("No support for transactions or \$isolated; Please upgrade MongoDB server or client", Zend_Log::WARN);
		}*/
		
		$options = array('multiple' => true);
		Billrun_Factory::log('Queue hash update, query: ' . json_encode($query) . ', options: ' . json_encode($options), Zend_Log::DEBUG);
		$res = $queue->update($query, $update, $options);
		return $res;
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
	 * Get the ordered list of queue calculator types the --calculate command
	 * should run.
	 *
	 * With an explicit calculator name only that calculator runs. Without one
	 * (BRCD-3562) every configured queue calculator runs, in chain order, so a
	 * line flows through the whole chain in a single --calculate invocation and
	 * a line that fails at some calculator is left in the queue with the
	 * relevant calc_name to be retried from that stage.
	 *
	 * @param string|null $requestedType a specific calculator to run, or null/empty for all
	 * @return array ordered calculator type names
	 */
	public static function getQueueCalculatorTypes($requestedType = null) {
		if (!empty($requestedType)) {
			return array($requestedType);
		}
		return array_values(Billrun_Factory::config()->getConfigValue('queue.calculators', array()));
	}

	/**
	 * The queue entry values (calc_name) a chain run starting at $stage pulls:
	 * false plus every stage but the last (lines never wait at the last stage's
	 * tag), from $stage's own entry onward. An unknown stage pulls nothing.
	 *
	 * @param string $stage the stage the chain starts at
	 * @param array $queueCalculators the configured queue.calculators, in order
	 * @return array
	 */
	public static function getChainEntryStages($stage, array $queueCalculators) {
		$queueCalculators = array_values($queueCalculators);
		$index = array_search($stage, $queueCalculators, true);
		if ($index === false) {
			return array();
		}
		return array_slice(array_merge(array(false), array_slice($queueCalculators, 0, -1)), $index);
	}

	/**
	 * The batch limit of a chain run starting at $stage: the MINIMUM among the
	 * configured <type>.calculator.limit of that stage and every later one. A
	 * limit of 0 or less means "no limit" and never lowers the batch; an unknown
	 * stage takes the whole chain into account.
	 *
	 * @param string $stage the stage the chain starts at
	 * @param array $options run options (e.g. 'credit' selects the rate calculator variant)
	 * @param int $ownLimit the caller's own limit: what a stage without a limit of its
	 *                      own counts with, and the result when no stage has a positive one
	 * @return int
	 */
	public static function getChainLimit($stage, array $options, $ownLimit) {
		$queueCalculators = array_values(Billrun_Factory::config()->getConfigValue('queue.calculators', array()));
		$index = array_search($stage, $queueCalculators, true);
		$limits = array();
		foreach (array_slice($queueCalculators, $index === false ? 0 : $index) as $chainStage) {
			$stageType = Billrun_Helpers_QueueCalculators::getCalculatorOptions($chainStage, $options)['type'];
			$stageLimit = (int) Billrun_Factory::config()->getConfigValue($stageType . '.calculator.limit', $ownLimit);
			if ($stageLimit > 0) {
				$limits[] = $stageLimit;
			}
		}
		return empty($limits) ? $ownLimit : min($limits);
	}

	/**
	 * is this run a chain-mode run (see $chain)
	 *
	 * @return bool
	 */
	public function isChainMode() {
		return $this->chain;
	}

	/**
	 * Chain mode calc(): run the pulled lines through the standard in-memory
	 * calculators chain (Billrun_Helpers_QueueCalculators::run - the same engine
	 * the processor uses; each line joins at its own stage) and record every
	 * pulled queue row's outcome - completed / advanced (claim kept) / unchanged.
	 * Nothing is persisted here: writeChain() saves the lines and queue state,
	 * removeChainFromQueue() drops the finished rows. Fires its own events,
	 * beforeCalculateChainData / afterCalculateChainData, so listeners of the
	 * single-calculator events are not fed a multi-stage run. Final so that no
	 * stage calculator's calc() override can intercept a chain run.
	 */
	final public function calcChain() {
		if (empty($this->lines)) {
			return;
		}

		// A dummy processor holds the queue-row bookkeeping for the chain (the
		// Billrun_Utils_Credit::calculateCreditCDRs pattern). The chain works on the
		// processor's own data array, as the inline path does (calcCpu): a stage that
		// consumes a line - unify archiving a source line - removes it from there, and
		// the outcomes below must see that, or the line would be saved back.
		$processor = Billrun_Processor::getInstance(array('type' => 'Credit', 'parser' => 'none'));
		$processor->init();
		$data = &$processor->getData();
		Billrun_Factory::dispatcher()->trigger('beforeCalculateChainData', array('lines' => $this->lines, 'calculator' => $this));
		foreach (self::pullLines($this->lines) as $line) {//self:: = the base pull, not a stage calculator's override (Customer::pullLines bulk-preloads subscribers)
			$data['data'][$line['stamp']] = $line->getRawData();
			$processor->setQueueRow($this->lines[$line['stamp']]->getRawData());
		}
		// autoload=0: the calculators the chain instantiates must not pull the queue themselves
		$this->chainRunner = new Billrun_Helpers_QueueCalculators(array_merge($this->chainOptions, array('autoload' => 0)));
		$this->chainRunner->run($processor, $data);

		// Record every line's outcome - the pulled lines and the extra lines the chain
		// created (e.g. a split CDR), which exist in neither collection yet: $data is
		// what writeChain() saves, $completedStamps the rows removeChainFromQueue()
		// drops, $advancedStages the rows parked at the last completed stage and
		// $extraQueueRows the rows to insert, released.
		$unchanged = 0;
		$extraCompleted = 0;
		foreach ($data['data'] as $stamp => $lineData) {
			$pulled = isset($this->lines[$stamp]);
			$finalRow = $processor->getQueueRow($stamp);
			if ($finalRow === false) {//completed its remaining chain
				unset($lineData['in_queue']);
				$processor->setFullCalculationTime($lineData);//completed = fully calculated, whichever stage emptied its queue row (same config gate as the processor)
				$this->data[$stamp] = new Mongodloid_Entity($lineData);
				if ($pulled) {
					$this->completedStamps[] = $stamp;
				} else {
					$extraCompleted++;
				}
			} elseif (!$pulled) {//an extra line with stages left: queue it released at the stage it reached, like a freshly processed line (the engine cloned its row from the claimed original)
				$lineData['in_queue'] = true;
				$this->data[$stamp] = new Mongodloid_Entity($lineData);
				unset($finalRow['hash']);
				$finalRow['calc_time'] = false;
				$finalRow['in_queue_since'] = new Mongodloid_Date();
				$this->extraQueueRows[$stamp] = $finalRow;
			} elseif ($finalRow['calc_name'] !== $this->lines[$stamp]['calc_name']) {//advanced, then failed a later stage: park at the last completed stage, still claimed
				$this->data[$stamp] = new Mongodloid_Entity($lineData);
				$this->advancedStages[$stamp] = $finalRow['calc_name'];
			} else {//nothing completed - stays claimed at its stage for a later retry
				$unchanged++;
			}
		}
		// A pulled row whose line is no longer in the data - consumed by the chain
		// (unify archived it) or missing from the lines collection to begin with - has
		// no queue row left either: completed, with nothing to save.
		foreach ($this->lines as $stamp => $queueRow) {
			if (!isset($data['data'][$stamp])) {
				if ($processor->getQueueRow($stamp) === false) {
					$this->completedStamps[] = $stamp;
				} else {
					$unchanged++;
				}
			}
		}
		if ($extraCompleted + count($this->extraQueueRows) > 0) {
			Billrun_Factory::log('Chain run: ' . ($extraCompleted + count($this->extraQueueRows)) . ' extra lines created, '
				. count($this->extraQueueRows) . ' queued for later stages', Zend_Log::INFO);
		}
		Billrun_Factory::log('Chain results: ' . count($this->completedStamps) . ' lines completed, '
			. count($this->advancedStages) . ' advanced and left for retry, ' . $unchanged . ' left unchanged', Zend_Log::INFO);
		Billrun_Factory::dispatcher()->trigger('afterCalculateChainData', array('data' => $this->data, 'calculator' => $this));
	}

	/**
	 * Chain mode write(): save the lines, park the advanced rows at the last stage
	 * they completed (still claimed, for the orphan retry) and insert the queue
	 * rows the chain created - instead of the single-tag update setCalculatorTag()
	 * performs (and which the customer/pricing calculators override). Fires its
	 * own events - beforeCalculatorChainWriteData / afterCalculatorChainWriteData
	 * and, per line, beforeCalculatorChainWriteLine / afterCalculatorChainWriteLine.
	 * Final so that no stage calculator's write() override can intercept a chain run.
	 */
	final public function writeChain() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorChainWriteData', array('data' => $this->data, 'calculator' => $this));
		Billrun_Factory::log('Writing ' . count($this->data) . ' lines to lines collection...', Zend_Log::DEBUG);
		$linesCollection = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $line) {
			// whole-document save: a line went through several stages here, so a stage
			// calculator's $set-only writeLine() (customer, rate, pricing) must not run
			Billrun_Factory::dispatcher()->trigger('beforeCalculatorChainWriteLine', array('data' => $line, 'calculator' => $this));
			$linesCollection->save($line, 1);
			Billrun_Factory::dispatcher()->trigger('afterCalculatorChainWriteLine', array('data' => $line, 'calculator' => $this));
		}
		Billrun_Factory::log('Updating ' . count($this->advancedStages) . ' queue lines calculator flags...', Zend_Log::DEBUG);
		$stampsByStage = array();
		foreach ($this->advancedStages as $stamp => $stage) {
			$stampsByStage[$stage][] = $stamp;
		}
		foreach ($stampsByStage as $stage => $stamps) {
			$this->queue_coll->update(array('stamp' => array('$in' => $stamps), 'hash' => $this->workHash),
				array('$set' => array('calc_name' => $stage)), array('multiple' => true));
		}
		if (!empty($this->extraQueueRows)) {
			Billrun_Factory::log('Inserting ' . count($this->extraQueueRows) . ' extra lines to queue collection...', Zend_Log::DEBUG);
		}
		foreach ($this->extraQueueRows as $extraRow) {
			$this->queue_coll->insert($extraRow);
		}
		if ($this->chainRunner) {
			$this->chainRunner->release();
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorChainWriteData', array('data' => $this->data, 'calculator' => $this));
	}

	/**
	 * Chain mode removeFromQueue(): drop the rows whose line completed its whole
	 * chain (their saved line documents no longer carry in_queue).
	 */
	final public function removeChainFromQueue() {
		if (empty($this->completedStamps)) {
			return;
		}
		Billrun_Factory::log('Removing ' . count($this->completedStamps) . ' lines from queue', Zend_Log::INFO);
		Billrun_Factory::db()->queueCollection()->remove(array('stamp' => array('$in' => $this->completedStamps)));
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
