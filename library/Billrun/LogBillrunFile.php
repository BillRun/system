<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun LogBillrunFile class
 *
 * @package  Billrun
 * @since    1
 */
class Billrun_LogBillrunFile extends Billrun_LogFile {

	/**
	 *
	 * @var string
	 */
	protected $stamp;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $collection;

	/**
	 * source field of the log file
	 * @var string
	 */
	protected $source = NULL;

	/**
	 * 
	 * @param type $options
	 */
	public function __construct($options = array()) {
		$this->collection = Billrun_Factory::db()->logCollection();
		$source = isset($options['source'])? $options['source'] : $this->source;
		if (isset($options['stamp']) && !is_null($source)) {
			$this->stamp = $options['stamp'];
			$docs = $this->collection->query('key', $this->stamp)->equals('source', $source)->cursor();
			if ($docs->count() > 1) {
				throw new Exception('Billrun_LogBillrunFile: More than one log file was found');
			} elseif ($docs->count() == 1) {
				$this->data = $docs->current();
				if (isset($this->data['process_time'])) {
					throw new Exception('Billrun_LogBillrunFile: file already created');
				}
				$this->setStartProcessTime();
				$this->data->collection($this->collection);
			} else {
				$this->data = new Mongodloid_Entity();
				$this->data->collection($this->collection);
				$this->data['key'] = $this->stamp;
				$this->data['source'] = $source;
				$this->setStartProcessTime();
				$this->save();
			}
		} else {
			throw new Exception('Billrun_LogBillrunFile: stamp not supplied');
		}
	}

}