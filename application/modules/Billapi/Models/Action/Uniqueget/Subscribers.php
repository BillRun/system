<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is subscribers unique get
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Uniqueget_Subscribers extends Models_Action_Uniqueget {

	protected function runQuery()
	{
		$ids = $this->getUniqueIds();
		if (empty($ids)) {
			return [];
		}
		$this->query = array(
			'_id' => array(
				'$in' => $ids
			),
		);

		if (isset($this->request['project'])) {
			$project = (array) json_decode($this->request['project'], true);
			$revision_info = !empty($project['revision_info']);
			unset($project['revision_info']);
			// if revision_info requested, all entity unique fields are required for query
			if ($revision_info) {
				$uniqueFields = Billrun_Factory::config()->getConfigValue("billapi.{$this->request['collection']}.duplicate_check", array());
				foreach ($uniqueFields as $fieldName) {
					$project[$fieldName] = 1;
				}
			}
		} else {
			$revision_info = true;
			$project = array();
		}
		Billrun_Factory::log("Billapi get runs query: " . json_encode($this->query), Zend_Log::DEBUG);
		$ret = $this->collectionHandler->find($this->query, $project);
		$records = array_values(iterator_to_array($ret));
		Billrun_Factory::log('Billapi get received ' . count($records) . " results", Zend_Log::DEBUG);
		foreach ($records as  &$record) {
			if (isset($record['invoice_id'])) {
				$record['invoice_id'] = (int)$record['invoice_id'];
			}
			if ($revision_info && isset($record['from'], $record['to'])) {
				$record = Models_Entity::setRevisionInfo($record, $this->getCollectionName(), $this->request['collection']);
			}
			$record = Billrun_Utils_Mongo::recursiveConvertRecordMongodloidDatetimeFields($record, $this->getDateFields());
		}
		return $records;
	}

	protected function initGroup() {
		$this->group = 'sid';
	}
	
	protected function getCustomFieldsKey() {
		return $this->getCollectionName() . ".subscriber";
	}

	/**
	 * Overrides the parent getUniqueIds to add pagination to the original selection logic.
	 * * @return array of mongo ids
	 */
	protected function getUniqueIds()
	{
		$base_query = ['type' => 'subscriber'];
		if (!empty($this->query)) {
			$pipelines[] = array('$match' => array_merge($base_query, $this->query));
		} else {
			$pipelines[] = array('$match' => $base_query);
		}
		$pipelines[] = array(
			'$project' => array(
				'_id' => 1,
				'from' => 1,
				'to' => 1,
				$this->group => 1,
				'state' => array(
					'$cond' => array(
						'if' => array(
							'$and' => array(
								array('$lte' => array('$from', new Mongodloid_Date())),
								array('$gt' => array('$to', new Mongodloid_Date())),
							),
						),
						'then' => self::STATE_ACTIVE,
						'else' => array(
							'$cond' => array(
								'if' => array(
									'$gte' => array('$from', new Mongodloid_Date()),
								),
								'then' => self::STATE_FUTURE,
								'else' => self::STATE_EXPIRE,
							),
						),
					),
				),
			),
		);

		$pipelines[] = array(
			'$sort' => array(
				'state' => 1,
				'to' => -1
			),
		);

		$pipelines[] = array(
			'$group' => array(
				'_id' => '$' . $this->group,
				'state' => array(
					'$first' => '$state'
				),
				'id' => array(
					'$first' => '$_id'
				),
			),
		);

		$pipelines[] = array(
			'$project' => array(
				'_id' => 0,
				'id' => 1,
				'state' => 1,
			),
		);

		if (isset($this->request['states']) && $states = @json_decode($this->request['states'])) {
			$filter_states = array_intersect($states, array(self::STATE_ACTIVE, self::STATE_EXPIRE, self::STATE_FUTURE));
			$match = array(
				'$match' => array(
					'state' => array(
						'$in' => $filter_states,
					)
				),
			);
		} else {
			$match = array(
				'$match' => array(
					'state' => array(
						'$in' => array(self::STATE_ACTIVE, self::STATE_FUTURE),
					)
				),
			);
		}
		$pipelines[] = $match;
		if (!empty($this->sort)) {
			$pipelines[] = array('$sort' => $this->sort);
		}

		if ($this->page != 0) {
			$pipelines[] = array('$skip' => $this->page * $this->size);
		}

		if ($this->size != 0) {
			$pipelines[] = array('$limit' => $this->size + 1);
		}
		error_log(json_encode($pipelines));
		$res = call_user_func_array(array($this->collectionHandler, 'aggregateWithOptions'), array($pipelines, array('allowDiskUse' => TRUE)));

		$res->setRawReturn(true);
		$aggregatedResults = array_values(iterator_to_array($res));
		return array_column($aggregatedResults, 'id');
	}
}
