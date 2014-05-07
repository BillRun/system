<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Archive lines plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
abstract class archivePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'archive';

	/**
	 * the container data of the archive
	 * this will include the lines to move to archive
	 * @var array
	 */
	protected $data = array();

	/**
	 * Limit  the  amount of line to handle in a single run.
	 * @var int (default to 3,000,000)
	 */
	protected $limit = 1000000;

	/**
	 * this variable hold the time to start archiving  from.
	 * @var type 
	 */
	protected $archivingHorizon = '-2 months';

	public function __construct() {
		$this->limit = Billrun_Factory::config()->getConfigValue($this->name . '.limit', $this->limit);
		$this->archivingHorizon = Billrun_Factory::config()->getConfigValue($this->name . '.archiveFrom', $this->archivingHorizon);
	}

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if ($this->getName() != $options['type']) {
			return FALSE;
		}

		Billrun_Factory::log()->log("Collect " . $this->name . " - line that older then " . $this->archivingHorizon, Zend_Log::INFO);
		$lines = Billrun_Factory::db()->linesCollection();

		$query = $this->getQuery();
		$results = $lines->query($query)->cursor()->limit($this->limit);

		Billrun_Factory::log()->log("archive found " . $results->count() . " lines", Zend_Log::INFO);
		return $results;
	}

	/**
	 * 
	 * @param type $items
	 * @param type $pluginName
	 * @return array
	 */
	public function handlerMarkDown(&$items, $pluginName) {
		if ($pluginName != $this->getName() || !$items) {
			return;
		}

		$archive = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('archive.db'))->linesCollection();

		Billrun_Factory::log()->log("Marking down archive lines For archive plugin", Zend_Log::INFO);

		$options = array();
		$this->data = array();
		foreach ($items as $item) {
			$current = $item->getRawData();
			$options['w'] = 1;
			try {
				$insertResult = $archive->insert($current, $options);

				if ($insertResult['ok'] == 1) {
					Billrun_Factory::log()->log("line with the stamp: " . $current['stamp'] . " inserted to the archive", Zend_Log::DEBUG);
					$this->data[] = $current;
				} else {
					Billrun_Factory::log()->log("Failed insert line with the stamp: " . $current['stamp'] . " to the archive", Zend_Log::WARN);
				}
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Failed insert line with the stamp: " . $current['stamp'] . " to the archive got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
				if ($e->getCode() == "11000") { // duplicate => already exists
					$this->data[] = $current;
				}
			}
		}
		return TRUE;
	}

	/**
	 * Handle Notification that should be done on events that were logged in the system.
	 * @param type $handler the caller handler.
	 * @return type
	 */
	public function handlerNotify($handler, $options) {
		if ($options['type'] != $this->name) {
			return FALSE;
		}

		if (!empty($this->data)) {

			$lines = Billrun_Factory::db()->linesCollection();

			foreach ($this->data as $item) {
				try {
					// TODO: remove by stamp => shard key
					$result = $lines->remove($item);
				} catch (Exception $e) {
					Billrun_Factory::log()->log("Failed to remove line with the stamp: " . $item['stamp'] . " to the archive got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::WARN);
				}
			}
		}
		return TRUE;
	}

	/**
	 * method to declare the archive scope data
	 * 
	 * @return array query to run. the results lines will be removed
	 */
	abstract protected function getQuery();
}
