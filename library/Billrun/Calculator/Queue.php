<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BRCD-3562 chain-mode calculator: fetches queue lines ONCE (with no start type
 * the single marked pull takes lines waiting at ANY stage) and runs them
 * through the standard in-memory calculators chain
 * (Billrun_Helpers_QueueCalculators::run - the same engine the processor uses;
 * each line joins the chain at its own stage). A line that fails a stage is
 * left in the queue, claimed, at the last stage it completed - full
 * circulation is preserved.
 *
 * @package  calculator
 * @since    6.0
 */
class Billrun_Calculator_Queue extends Billrun_Calculator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'queue';

	/**
	 * not a queue-chain stage itself (also keeps isEnabled() true)
	 *
	 * @var bool
	 */
	protected $isQueueCalc = false;

	/**
	 * calculator (or stage) name to start the chain from; null = whole chain
	 * @var string|null
	 */
	protected $chainStart = null;

	/**
	 * the queue stage chainStart resolves to (false = unknown type, pull nothing)
	 * @var string|false|null
	 */
	protected $startStage = null;

	/**
	 * the run options, passed on to the per-row calculators chain
	 * @var array
	 */
	protected $runOptions = array();

	/**
	 * the configured queue.calculators stages, in order
	 * @var array
	 */
	protected $queueCalculators = array();

	/**
	 * stamps of lines that completed their whole chain (removed from the queue at the end)
	 * @var array
	 */
	protected $completedStamps = array();

	/**
	 * stamp => the last stage a line completed before failing a later one
	 * @var array
	 */
	protected $advancedStages = array();

	/**
	 * queue rows the chain created (extra lines), inserted at the end
	 * @var array
	 */
	protected $extraQueueRows = array();

	/**
	 * the per-row calculators chain runner, released after the write
	 * @var Billrun_Helpers_QueueCalculators
	 */
	protected $chainRunner = null;

	public function __construct($options = array()) {
		$this->runOptions = $options;
		$this->queueCalculators = array_values(Billrun_Factory::config()->getConfigValue('queue.calculators', array()));
		if (isset($options['chain_start'])) {
			$this->chainStart = $options['chain_start'];
			$this->startStage = self::getChainStage($this->chainStart, $options);
		}
		if (!isset($options['calculator']['limit'])) {
			//batch by the pulled stage's configured limit (e.g. customer.calculator.limit,
			//Rate_Usage.calculator.limit); a whole-chain pull takes the MINIMUM among all stages' limits
			$stages = $this->startStage ? array($this->startStage) : $this->queueCalculators;
			$limits = array();
			foreach ($stages as $stage) {
				$stageType = Billrun_Helpers_QueueCalculators::getCalculatorOptions($stage, $options)['type'];
				$stageLimit = (int) Billrun_Factory::config()->getConfigValue($stageType . '.calculator.limit', 5000);
				if ($stageLimit > 0) {//0 or less means no limit - it never lowers the batch
					$limits[] = $stageLimit;
				}
			}
			if (!empty($limits)) {
				$options['calculator']['limit'] = min($limits);
			}
		}
		parent::__construct($options);
	}

	/**
	 * The queue stage a requested calculator name belongs to: a stage name is
	 * taken as-is, a concrete calculator name (e.g. rate_Usage) is instantiated
	 * (without loading) to ask for its stage. A calculator outside the chain -
	 * e.g. rebalance, which works off its own rebalance_queue - has no stage.
	 *
	 * @param string $requestedType calculator or stage name
	 * @param array $options run options, passed on to the calculator instantiation
	 * @return string|false the stage, or false when the calculator is not part of the queue chain
	 */
	public static function getChainStage($requestedType, $options = array()) {
		$queueCalculators = array_values(Billrun_Factory::config()->getConfigValue('queue.calculators', array()));
		if (in_array($requestedType, $queueCalculators, true)) {
			return $requestedType;
		}
		$calculator = Billrun_Calculator::getInstance(array_merge($options, array('type' => $requestedType, 'autoload' => false)));
		$stage = $calculator ? $calculator->getCalculatorQueueType() : null;
		return in_array($stage, $queueCalculators, true) ? $stage : false;
	}

	public function getCalculatorQueueType() {
		return self::$type;
	}

	protected function getLines() {
		if ($this->startStage === false) {//chain_start is not a queue chain stage (the action runs such calculators on their own) - nothing to chain
			Billrun_Factory::log('Calculator type ' . $this->chainStart . ' is not a queue chain stage - nothing to pull', Zend_Log::INFO);
			return array();
		}
		return $this->getQueuedLines(array());
	}

	/**
	 * Take lines waiting at any stage of this run's chain: the entry values are
	 * false + every stage except the last (lines never wait at the last stage's
	 * tag), and a chain start narrows them to its stage onward.
	 *
	 * @param mixed $previous_calculator unused - the pull is chain-driven
	 * @return array
	 */
	protected function getPullCalcNameCondition($previous_calculator) {
		$entryStages = array_merge(array(false), array_slice($this->queueCalculators, 0, -1));
		if ($this->startStage) {
			//the entry of stage i is $entryStages[i], so the chain's entries are the slice from the start stage on
			$entryStages = array_slice($entryStages, array_search($this->startStage, $this->queueCalculators));
		}
		return array('$in' => $entryStages);
	}

	/**
	 * The chain pull filters calc_name with a low-selectivity $in, so the
	 * branch-free {calc_time, urt} index serves its marking update best.
	 *
	 * @return array index specification
	 */
	protected function getQueueUpdateHint() {
		return array('calc_time' => 1, 'urt' => 1);
	}

	/**
	 * Run the fetched lines through the standard in-memory calculators chain
	 * (Billrun_Helpers_QueueCalculators::run - the same engine the processor
	 * uses; each line joins at its own stage), accumulating the outcomes;
	 * nothing is persisted here - write() saves the lines and queue state,
	 * removeFromQueue() drops the finished ones.
	 */
	public function calc() {
		if (empty($this->lines)) {
			return;
		}

		// A dummy processor holds the queue-row bookkeeping for the chain (the
		// Billrun_Utils_Credit::calculateCreditCDRs pattern).
		$processor = Billrun_Processor::getInstance(array('type' => 'Credit', 'parser' => 'none'));
		$processor->init();
		$data = array('data' => array());
		foreach ($this->pullLines($this->lines) as $line) {
			$data['data'][$line['stamp']] = $line->getRawData();
			$processor->setQueueRow($this->lines[$line['stamp']]->getRawData());
		}
		$missingLines = count($this->lines) - count($data['data']);
		if ($missingLines > 0) {
			//as in a regular calculator run, a queue row whose line is gone is never
			//calculated but still flushed through the chain - complete it out of the queue
			Billrun_Factory::log('Chain run: ' . $missingLines . ' queue lines have no matching line, removing them from the queue', Zend_Log::WARN);
			foreach ($this->lines as $stamp => $queueRow) {
				if (!isset($data['data'][$stamp])) {
					$this->completedStamps[] = $stamp;
				}
			}
		}

		// autoload=0: the calculators the chain instantiates must not pull the queue
		// themselves. Strip this instance's own instantiation leftovers - most
		// importantly 'calculator' (the merged queue.calculator section + our batch
		// limit), which would otherwise override every stage's own
		// <type>.calculator config section (e.g. customer_identification_translation).
		$chainOptions = $this->runOptions;
		unset($chainOptions['calculator'], $chainOptions['type'], $chainOptions['chain_start']);
		$this->chainRunner = new Billrun_Helpers_QueueCalculators(array_merge($chainOptions, array('autoload' => 0)));
		$this->chainRunner->run($processor, $data);

		// Record every line's outcome.
		$unchanged = 0;
		foreach ($data['data'] as $stamp => $lineData) {
			if (!isset($this->lines[$stamp])) {//an extra line the chain created - collected below
				continue;
			}
			$finalRow = $processor->getQueueRow($stamp);
			if ($finalRow === false) {//the line completed its remaining chain
				unset($lineData['in_queue']);
				$processor->setFullCalculationTime($lineData);//completed = fully calculated, whichever stage emptied its queue row (same config gate as the processor)
				$this->data[$stamp] = new Mongodloid_Entity($lineData);
				$this->completedStamps[] = $stamp;
			} elseif ($finalRow['calc_name'] !== $this->lines[$stamp]['calc_name']) {//advanced, then failed a later stage: park at the last completed stage, still claimed
				$this->data[$stamp] = new Mongodloid_Entity($lineData);
				$this->advancedStages[$stamp] = $finalRow['calc_name'];
			} else {//nothing completed - stays claimed at its stage for a later retry
				$unchanged++;
			}
		}
		// queue rows the chain created (extra lines) do not exist in the queue yet
		foreach ($processor->getQueueData() as $extraStamp => $extraRow) {
			if (!isset($this->lines[$extraStamp])) {
				$this->extraQueueRows[$extraStamp] = $extraRow;
				if (isset($data['data'][$extraStamp])) {
					$this->data[$extraStamp] = new Mongodloid_Entity($data['data'][$extraStamp]);
				}
			}
		}
		Billrun_Factory::log('Chain results: ' . count($this->completedStamps) . ' lines completed, '
			. count($this->advancedStages) . ' advanced and left for retry, ' . $unchanged . ' left unchanged', Zend_Log::INFO);
	}

	/**
	 * The queue-state part of the parent write(): park the advanced lines at the
	 * last stage they completed (still claimed, for the orphan retry) and insert
	 * the queue rows the chain created - instead of the single-tag update a
	 * regular calculator performs.
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		$stampsByStage = array();
		foreach ($this->advancedStages as $stamp => $stage) {
			$stampsByStage[$stage][] = $stamp;
		}
		foreach ($stampsByStage as $stage => $stamps) {
			$this->queue_coll->update(array('stamp' => array('$in' => $stamps), 'hash' => $this->workHash),
				array('$set' => array('calc_name' => $stage)), array('multiple' => true));
		}
		foreach ($this->extraQueueRows as $extraRow) {
			$this->queue_coll->insert($extraRow);
		}
		if ($this->chainRunner) {
			$this->chainRunner->release();
		}
	}

	/**
	 * Remove the lines that completed their whole chain from the queue
	 * (their saved line documents no longer carry in_queue).
	 */
	public function removeFromQueue() {
		if (empty($this->completedStamps)) {
			return;
		}
		Billrun_Factory::log('Removing ' . count($this->completedStamps) . ' lines from queue', Zend_Log::INFO);
		Billrun_Factory::db()->queueCollection()->remove(array('stamp' => array('$in' => $this->completedStamps)));
	}

	public function updateRow($row) {
		return FALSE;
	}

	public function prepareData($lines) {
	}

	protected function isLineLegitimate($line) {
		return false;
	}

}
