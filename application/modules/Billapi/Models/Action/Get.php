<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi get operation
 * Retrieve list of entities
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Get extends Models_Action {

	/**
	 * the size of page
	 * @var int
	 */
	protected $size = 0;

	/**
	 * the page index
	 * @var int
	 */
	protected $page = 0;

	/**
	 * the query
	 * @var array
	 */
	protected $query = array();

	/**
	 * the sort
	 * @var array
	 */
	protected $sort = array();

	public function execute() {
//		if (!empty($this->request['query'])) {
//			$this->query = (array) json_decode($this->request['query'], true);
//		}

		if (isset($this->request['page']) && is_numeric($this->request['page'])) {
			$this->page = (int) $this->request['page'];
		}

		if (isset($this->request['size']) && is_numeric($this->request['size'])) {
			$this->size = (int) $this->request['size'];
		}

		if (isset($this->request['sort'])) {
			$this->sort = (array) json_decode($this->request['sort'], true);
		}

		return $this->runQuery();
	}

	/**
	 * Run a DB query against the current collection
	 * @return array the result set
	 */
	protected function runQuery() {

		if (isset($this->request['project'])) {
			$project = (array) json_decode($this->request['project'], true);
		} else {
			$project = array();
		}

		$ret = $this->collectionHandler->find($this->query, $project);

		if ($this->size != 0) {
			$ret->limit($this->size + 1); // the +1 is to indicate if there is next page
		}

		if ($this->page != 0) {
			$ret->skip($this->page * $this->size);
		}

		if (!empty($this->sort)) {
			$ret->sort((array) $this->sort);
		}

		$records =  array_values(iterator_to_array($ret));
		foreach($records as  &$record) {
			$record = Billrun_Utils_Mongo::recursiveConvertRecordMongoDatetimeFields($record);
			if(empty($project) || (array_key_exists('revision_info', $project) && $project['revision_info'])) {
				$record = Models_Entity::setRevisionInfo($record, $this->getCollectionName());
			}
		}
		return $records;
	}

	/**
	 * method to get page size
	 * @return type
	 */
	public function getSize() {
		return $this->size;
	}

}
