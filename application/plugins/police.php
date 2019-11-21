<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculator cpu plugin make the calculative operations in the cpu (before line inserted to the DB)
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.9
 */
class policePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'police';

	public function beforeProcessorStore($processor) {
		Billrun_Factory::log('Plugin police triggered before processor store', Zend_Log::INFO);
		$options = array(
			'autoload' => 0,
		);

		$remove_duplicates = Billrun_Factory::config()->getConfigValue('police.remove_duplicates', ['ggsn','nsn']);
		if (!empty($remove_duplicates) && in_array($processor->getType(),$remove_duplicates)) {
			$this->removeDuplicates($processor);
		}

		$queue_calculators = Billrun_Factory::config()->getConfigValue("queue.calculators");

		$data = &$processor->getData();
		Billrun_Factory::log('Plugin police usage_classifier stage', Zend_Log::INFO);
		$classifierOptions = Billrun_Factory::config()->getConfigValue('usageclassifier',[]);
		foreach ($data['data'] as &$line) {
			$entity = new Mongodloid_Entity($line);
			$classifierCalc = Billrun_Calculator::getInstance(array_merge(['type'=>'usageclassifier'],$classifierOptions,$options));
			if ($classifierCalc !== FALSE  && $classifierCalc->isLineLegitimate($entity)) {
				if ($classifierCalc->updateRow($entity) !== FALSE) {
						if ($queue_calculators[count($queue_calculators) - 1] == 'usage_classifier') {
							$processor->unsetQueueRow($entity['stamp']);
						} else {
							$processor->setQueueRowStep($entity['stamp'], 'usage_classifier');
						}

				}
			} else {
				$processor->setQueueRowStep($entity['stamp'], 'usage_classifier');
			}
			$line = $entity->getRawData();
			$processor->addAdvancedPropertiesToQueueRow($line);
		}

		Billrun_Factory::log('Plugin police before processor store end', Zend_Log::INFO);
	}

	protected function removeDuplicates($processor) {
		Billrun_Factory::log('Plugin police remove duplicates', Zend_Log::INFO);
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$data = &$processor->getData();
		$stamps = array();
		foreach ($data['data'] as $key => $line) {
			$stamps[$line['stamp']] = $key;
		}
		if ($stamps) {
			$query = array(
				'stamp' => array(
					'$in' => array_keys($stamps),
				),
			);
			$existing_lines = $lines_coll->query($query)->cursor();
			$count_duplicate_lines = 0;
			foreach ($existing_lines as $line) {
				$count_duplicate_lines = $count_duplicate_lines + 1;
				$stamp = $line['stamp'];
				if ($count_duplicate_lines <= 3) {
					$print_stamps[] = $stamp;
				} 
				unset($data['data'][$stamps[$stamp]]);
				$processor->unsetQueueRow($stamp);
			}
			if ($count_duplicate_lines > 0){
				$print_stamps = implode(", ", $print_stamps);
				Billrun_Factory::log('Plugin calc cpu skips ' . $count_duplicate_lines . ' duplicate lines in file ' . $line['file'] . ', example lines: ' . '(' . $print_stamps . ')', Zend_Log::ALERT);
			}	
		}		
	}

}
