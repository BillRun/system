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
class Models_Action_Uniqueget extends Models_Action_Get {

	protected function runQuery() {
		$ids = $this->getUniqueIds();
		$this->query = array(
			'_id' => array(
				'$in' => $ids
			),
		);
		return parent::runQuery();
	}

	/**
	 * method to aggregate and get uniqueness 
	 * @return array of mongo ids
	 */
	protected function getUniqueIds() {
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

		if (!empty($this->query)) {
			$match['$match'] = array_merge($match['$match'], $this->query);
		}
		$res = $this->collectionHandler->aggregate($match, $group, $project);

		$res->setRawReturn(true);
		$aggregatedResults = array_values(iterator_to_array($res));
		return array_column($aggregatedResults, 'id');
	}

}
