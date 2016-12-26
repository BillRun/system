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

	public function execute() {
		if (empty($this->request['query'])) {
			$this->request['query'] = array();
		}
		return $this->runQuery($this->request['query']);
	}

	/**
	 * Run a DB query against the current collection
	 * @param array $query
	 * @return array the result set
	 */
	protected function runQuery($query, $sort = null) {
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
		$res = $this->collectionHandler->aggregate($match, $group);

		$res->setRawReturn(true);
		$aggregatedResults = array_values(iterator_to_array($res));
		$ids = array_column($aggregatedResults, 'id');
		$ret = $this->collectionHandler->find(array('_id' => array('$in' => $ids)));

		if (isset($this->request['page']) && $this->request['page'] != -1) {
			$res->skip($this->page * $this->size);
		}

		if (isset($this->request['size']) && $this->request['size'] != -1) {
			$res->limit($this->size);
		}

		if ($sort) {
			$res = $res->sort($sort);
		}
		
		return array_values(iterator_to_array($ret));;
	}

}
