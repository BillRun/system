<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Wholesale line archiving plugin
 * (TODO unify  this with  archive customer)
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class archiveWholesalePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'archiveWholesale';

	/**
	 * the container dataWholesale of the archive
	 * @var array
	 */
	protected $dataWholesale = array();

	/**
	 * Limit  the  amount of line to handle in a single run.
	 * @var int (default to 3,000,000)
	 */
	protected $limit = 5000000;

	/**
	 * this variable hold the time to start archiving  from.
	 * @var type 
	 */
	protected $archvingHorizion = '-2 months';

	public function __construct() {
		$this->limit = Billrun_Factory::config()->getConfigValue('archive.wholesale.limit', $this->limit);
		$this->archvingHorizion = Billrun_Factory::config()->getConfigValue('archive.wholesale.archiveFrom', $this->archvingHorizion);
	}

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if ($this->getName() != $options['type']) {
			return FALSE;
		}

		Billrun_Factory::log()->log("Collect archive - wholesale line that older then {$this->archvingHorizion}", Zend_Log::INFO);
		$lines = Billrun_Factory::db()->linesCollection();

		$results = $lines->query(array(
				'urt' => array('$lte' => new MongoDate(strtotime($this->archvingHorizion))), //TODO  move this to configuration
				'arate' => false,
			))->cursor()->limit($this->limit);

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
		$this->dataWholesale = array();
		foreach ($items as $item) {
			$current = $item->getRawData();
			$options['w'] = 1;
			try {
				$insertResult = $archive->insert($current, $options);

				if ($insertResult['ok'] == 1) {
					Billrun_Factory::log()->log("line with the stamp: " . $current['stamp'] . " inserted to the archive", Zend_Log::INFO);
					$this->dataWholesale[] = $current;
				} else {
					Billrun_Factory::log()->log("Failed insert line with the stamp: " . $current['stamp'] . " to the archive", Zend_Log::ERR);
				}
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Failed insert line with the stamp: " . $current['stamp'] . " to the archive got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
				if ($e->getCode() == "11000") {	$this->dataWholesale[] = $current;	}
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
		if ($options['type'] != 'archiveWholesale') {
			return FALSE;
		}

		if (!empty($this->dataWholesale)) {

			$lines = Billrun_Factory::db()->linesCollection();

			foreach ($this->dataWholesale as $item) {
				try {
					$result = $lines->remove($item);
				} catch (Exception $e) {
					Billrun_Factory::log()->log("Failed to remove line with the stamp: " . $item['stamp'] . " to the archive got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
				}
			}
		}
		return TRUE;
	}

}