<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Uniqueget extends Models_Action {

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
	 * next page flag
	 * @var boolean
	 */
	protected $nextPage = false;

	public function execute() {
		if (empty($this->request['query'])) {
			$this->request['query'] = array();
		}

		if (isset($this->request['page']) && is_numeric($this->request['page'])) {
			$this->page = (int) $this->request['page'];
		}

		if (isset($this->request['size']) && is_numeric($this->request['size'])) {
			$this->size = (int) $this->request['size'];
		}

		if (isset($this->request['next_page']) && is_numeric($this->request['next_page'])) {
			$this->nextPage = (bool) $this->request['next_page'];
		}

		return $this->runQuery($this->request['query']);
	}

	/**
	 * Run a DB query against the current collection
	 * @param array $query pre-defined query to match
	 * @return array the result set
	 */
	protected function runQuery($query, $sort = null) {
		$ids = $this->getUniqueIds($query);

		$filter = array(
			'_id' => array(
				'$in' => $ids
			),
		);

		if (isset($this->request['project'])) {
			$project = (array) json_decode($this->request['project'], true);
		} else {
			$project = array();
		}

		$ret = $this->collectionHandler->find($filter, $project);

		if ($this->size != 0) {
			$ret->limit($this->size + ($this->nextPage ? 1 : 0));
		}

		if ($this->page != 0) {
			$ret->skip($this->page * $this->size);
		}

		if ($sort) {
			$ret->sort((array) $sort);
		}

		return array_values(iterator_to_array($ret));
	}

	/**
	 * method to aggregate and get uniqueness 
	 * @param array $query pre-defined query to match
	 * @return array of mongo ids
	 */
	protected function getUniqueIds($query) {
		$group = array(
			'$group' => array(
				'_id' => '$' . ($this->request['collection'] == 'rates' ? 'key' : 'name'),
				'from' => array(
					'$min' => '$from'
				),
				'to' => array(
					'$max' => '$to'
				),
				'id' => array(
					'$first' => '$_id'
				)
			),
		);

		if (!isset($this->request['history']) || !$this->request['history']) {
			$match = array(
				'$match' => Billrun_Utils_Mongo::getDateBoundQuery(),
			);
		} else {
			// this is workaround, because aggregate cannot receive only 1 argument
			$match = array(
				'$match' => array(
					'from' => array(
						'$gte' => new MongoDate(strtotime('1970-01-01 00:00:00')),
					)
				),
			);
		}

		$project = array(
			'$project' => array(
				'_id' => 0,
				'id' => 1,
			),
		);

		$find = (array) json_decode($query, true);
		if (!empty($find)) {
			$match['$match'] = array_merge($match['$match'], $find);
		}
		$res = $this->collectionHandler->aggregate($match, $group, $project);

		$res->setRawReturn(true);
		$aggregatedResults = array_values(iterator_to_array($res));
		return array_column($aggregatedResults, 'id');
	}

}
