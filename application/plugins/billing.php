<?php

abstract class billingPlugin extends Billrun_Plugin_BillrunPluginFraud {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name;

	const time_format = 'YmdHis';

	public function __construct($options = array()) {
		parent::__construct($options);
		$options = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.israel', 1800, 'int');
	}

	protected function validateOptions($options) {
		if (!isset($options['interval'], $options['lag'], $options['usaget'], $options['threshold'], $options['type'])) {
			return FALSE;
		} else if (!is_array($options['usaget']) || !is_array($options['type']) || empty($options['type']) || empty($options['usaget'])) {
			return FALSE;
		} else if (!is_numeric($options['lag']) || !is_numeric($options['interval']) || !is_numeric($options['threshold'])) {
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

		$options = array_merge($options, Billrun_Factory::config()->getConfigValue($this->getName()));
		if (!$this->validateOptions($options)) {
			return FALSE;
		}
		$interval = $options['interval'];
		$lag = $options['lag'];
		$types = $options['type'];
		$usage_types = $options['usaget'];
		$threshold = $options['threshold'] * 3600;

		$lines = Billrun_Factory::db()->linesCollection();

		$end_timestamp = strtotime($lag . ' hours ago');
		$start_timestamp = strtotime($interval . ' hours ago', $end_timestamp);


		$base_match = array(
			'$match' => array(
				'billrun' => array(
					'$exists' => TRUE,
				),
				'type' => array(
					'$in' => $types,
				),
				'urt' => array(
					'$lte' => new MongoDate($end_timestamp),
					'$gte' => new MongoDate($start_timestamp),
				),
				'usaget' => array(
					'$in' => $usage_types,
				),
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$sid',
				"usagev" => array(
					'$sum' => '$usagev'
				),
				'lines_stamps' => array(
					'$addToSet' => '$stamp'
				),
			),
		);

		$project = array(
			'$project' => array(
				'_id' => 0,
				'sid' => '$_id',
				'usagev' => 1,
				'event_type' => array(
					'$concat' => array(
						'BILLING'
					)
				),
			),
		);

		$having = array(
			'$match' => array(
				'usagev' => array(
					'$gte' => $threshold,
				),
			),
		);


		$ret = $lines->aggregate($base_match, $group, $project, $having);

		Billrun_Factory::log()->log($this->getName() . ' plugin located ' . count($ret) . ' items as fraud events', Zend_Log::INFO);

		return $ret;
	}

	protected function addAlertData(&$event) {
		return $event;
	}

	public function handlerMarkDown(&$items, $pluginName) {
		return false;
	}

}
