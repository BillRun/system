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
	
	public function getLatestJobs($job_type, $limit, $future_only = false, $include_cancelled = false) {
		$query = array(
			'body.type' => ucfirst($job_type),
		);
		
		if ($future_only) {
			$query['schedule'] = [
				'$gt' => new Mongodloid_Date()
			];
		}
		
		if (!$include_cancelled) {
			$query['cancelled'] = [
				'$ne' => 1
			];
		}

		$entries = $this->collection->query($query)->cursor()->sort(['created' => -1])->limit($limit);
		$ret = [];
		foreach ($entries as $entry) {
			$ret[] = $entry->getRawData();
		}
		return $ret;
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
	
	/**
	 * method to cancel job from the jobs queue
	 * @param string $md5 the jobs md5 (id)
	 * @param bool $includeChildJobs decide if to delete child jobs
	 * 
	 * @return array query results of the delete op
	 * 
	 * @todo put the cancel job into jobs queue internals or Jobsmanager class
	 */
	public function cancelJob($md5, $includeChildJobs = false) {
		$query = $beforeQuery = [
			'start_time' => ['$exists' => 0],
			'done' => ['$ne' => 1],
		];
		if ($includeChildJobs) {
			$multiple = 1;
			$query['$or'] = [
				[ 'md5' => $md5 ],
				[ 'body.parent' => $md5 ],
			];
		} else {
			$multiple = 0;
			$query['md5'] = $md5;
		}
		$update = [
			'$set' => [
				'done' => 1,
				'cancelled' => 1,		
				'cancel_time' => new Mongodloid_Date(),		
			]
		];
		
		$beforeQuery['md5'] = $md5;
		$beforeRecord = $this->collection->query($beforeQuery)->cursor()->current();
		
		$options = ['upsert' => false, 'multiple' => $multiple];
		$ret = $this->collection->update($query, $update, $options);
		
		if (!empty($ret['nModified']) && $ret['nModified'] >= 1) {
			$beforeAr = $beforeRecord->getRawData();
			$afterAr = array_merge($beforeAr, $update['$set']);
			$afterAr['includeChildJobs'] = $includeChildJobs ?? false;
			Billrun_AuditTrail_Util::trackChanges('cancelled', $md5, 'jobs_messages', $beforeAr, $afterAr);
		}
		
		return $ret;
	}

}
