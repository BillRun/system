<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing handler class that can take of process 
 * The handler can be extend the system to plugins
 *
 * @package  handler
 * @since    1.0
 */
class Billrun_Handler extends Billrun_Base {

	/**
	 * method to execute simple handler that collect data to handle
	 */
	public function execute() {

		$collect_data = $this->collect();

		$this->alert($collect_data);

		$this->markdown($collect_data);
	}

	/**
	 * method to collect the data to handle
	 * 
	 * @return array list of handling items
	 */
	protected function collect() {
		$items = $this->dispatcher->trigger('handlerCollect');

		return $items;
	}

	/**
	 * method to alert the data collected
	 * 
	 * @param array $items list of items to be alerted
	 * 
	 * @return boolean true if success
	 */
	protected function alert($items) {
		foreach ($items as $item) {
			$this->dispatcher->trigger('handlerAlert', array(&$item));
		}
		// TODO: check return values
		return true;
	}

	/**
	 * method to mark all the lines that take care in the handler to avoid double runnning
	 * 
	 * @param array $data list of items to be mark down
	 * 
	 * @return boolean true if success
	 */
	protected function markdown($items) {
		foreach ($items as $item) {
			$this->dispatcher->trigger('handlerMarkDown', array(&$item));
		}
		// TODO: check return values
		return true;
	}

}
