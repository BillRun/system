<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Jov model class to pull data from database for lines collection
 *
 * @package  Models
 * @subpackage JobsQueue
 * @since    5.16
 */
class JobsqueueModel extends TableModel {
	
	public function __construct(array $params = []) {
		$params['collection'] = Billrun_Factory::db()->jobs_messages;
		parent::__construct($params);
	}
	
	public function getParentStats($job_md5) {
		$pipeline = [];
		$pipeline[] = array(
			'$match' => array(
				'body.parent' => $job_md5,
			)
		);
		$pipeline[] = array(
			'$group' => array(
				'_id' => null,
				'total' => array(
					'$sum' => 1
				),
				'done' => array(
					'$sum' => array(
						'$cond' => array(
							array('$eq' => array('$done',  1)),
							1, 
							0
						)
					),
				),
			),
		);
		$pipeline[] = array(
			'$project' => array(
				'_id' => 0,
			)
		);
		$cursor = $this->collection->aggregate($pipeline);
		$result = $cursor->current();
		return $result->getRawData();
	}
	
	public function getLatestJob($job_type, $limit) {
		$query = array(
			'body.type' => ucfirst($job_type),
		);

		$entry = $this->collection->query($query)->cursor()->sort(['created' => -1])->limit($limit)->current();
		if ($entry->isEmpty()) {
			return FALSE;
		}
		return $entry->getRawData();
	}
	
	public function getCycleAccountsLeft($billrun_key) {
		$query = array(
			'body.type' => 'Cycle_Account',
			'body.config.billrun_key' => $billrun_key,
			'done' => ['$exists' => 0],
		);

		$cursor = $this->collection->query($query)->project(['body.config.aid' => 1])
			->cursor()->limit(10000);
		
		$ret = [];
		foreach($cursor as $rec) {
			$ret[] = $rec->get('body.config.aid');
		}
		return $ret;
	}
	
	public function cancelJob($md5) {
		$query = [
			'md5' => $md5,
			'done' => ['$ne' => 1],
		];
		$update = [
			'$set' => [
				'done' => 1,
				'cancelled' => 1,		
				'cancel_time' => new Mongodloid_Date(),		
			]
		];
		$options = ['upsert' => false];
		$ret = $this->collection->update($query, $update, $options);
		return $ret;
	}

}
