<?php

abstract class billingPlugin extends Billrun_Plugin_BillrunPluginFraud {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * lag caused by the receiving + processing, in seconds
	 * @var int
	 */
	protected $lag;

	/**
	 * time interval to query on, in seconds
	 * @var int
	 */
	protected $interval;

	/**
	 * lines types to query on
	 * @var array
	 */
	protected $types;

	/**
	 * lines usage types to query on
	 * @var array
	 */
	protected $usage_types;

	/**
	 * amount of abuse usage, in seconds
	 * @var int
	 */
	protected $threshold;

	const time_format = 'YmdHis';

	public function __construct($options = array()) {
		parent::__construct($options);

		$pluginOptions = Billrun_Factory::config()->getConfigValue($this->getName());

		if (isset($pluginOptions['lag'])) {
			$this->lag = $pluginOptions['lag'];
		}
		if (isset($pluginOptions['interval'])) {
			$this->interval = $pluginOptions['interval'];
		}
		if (isset($pluginOptions['type'])) {
			$this->types = $pluginOptions['type'];
		}
		if (isset($pluginOptions['usaget'])) {
			$this->usage_types = $pluginOptions['usaget'];
		}
		if (isset($pluginOptions['threshold'])) {
			$this->threshold = intval($pluginOptions['threshold']);
		}
	}

	protected function validateOptions() {
		if (!isset($this->interval, $this->lag, $this->usage_types, $this->threshold, $this->types)) {
			return FALSE;
		} else if (!is_array($this->usage_types) || !is_array($this->types) || empty($this->types) || empty($this->usage_types)) {
			return FALSE;
		} else if (!is_numeric($this->lag) || !is_numeric($this->interval) || !is_numeric($this->threshold)) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if ($options['type'] != $this->getName()) {
			return FALSE;
		}
		if (!$this->validateOptions()) {
			return FALSE;
		}
		$billing_db_config = Billrun_Factory::config()->getConfigValue('billing.db');
		if (!$billing_db_config) {
			return FALSE;
		}
		$lines = Billrun_Factory::db($billing_db_config)->linesCollection();

		$end_timestamp = strtotime($this->lag . ' seconds ago');
		$start_timestamp = strtotime($this->interval . ' seconds ago', $end_timestamp);


		$base_match = array(
			'$match' => array(
				'billrun' => array(
					'$exists' => TRUE,
				),
				'type' => array(
					'$in' => $this->types,
				),
				'urt' => array(
					'$lte' => new MongoDate($end_timestamp),
					'$gte' => new MongoDate($start_timestamp),
				),
				'usaget' => array(
					'$in' => $this->usage_types,
				),
			),
		);

		$group = array(
			'$group' => array(
				'_id' => '$sid',
				'value' => array(
					'$sum' => '$usagev'
				),
				'plan' => array(
					'$first' => '$plan'
				),
				'aid' => array(
					'$first' => '$aid'
				),
			),
		);

		$having = array(
			'$match' => array(
				'value' => array(
					'$gte' => $this->threshold,
				),
			),
		);

		$project = array(
			'$project' => array(
				'_id' => 0,
				'sid' => '$_id',
				'value' => 1,
				'plan' => 1,
				'aid' => 1,
			),
		);

		$ret = $lines->aggregate($base_match, $group, $having, $project);

		Billrun_Factory::log()->log($this->getName() . ' plugin located ' . count($ret) . ' items as fraud events', Zend_Log::INFO);

		return $ret;
	}

	protected function addAlertData(&$event) {
		$event['threshold'] = $this->threshold;
		$event['event_type'] = 'BILLING';
		if (array_intersect($this->usage_types, array('call', 'incoming_call'))) {
			$event['units'] = 'SEC';
		}
		return $event;
	}

	public function handlerMarkDown(&$items, $pluginName) {
		return false;
	}

}
