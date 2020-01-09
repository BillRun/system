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

	const STATE_ACTIVE = 0;
	const STATE_FUTURE = 1;
	const STATE_EXPIRE = 2;

	/**
	 * aggregate field to map the uniqueness
	 * @var string
	 */
	protected $group = 'name';

	public function __construct(array $params = array()) {
		parent::__construct($params);
		$this->initGroup();
	}

	/**
	 * initialize the collection
	 * 
	 * @todo override by child class
	 */
	protected function initGroup() {
		if ($this->request['collection'] == 'rates') {
			$this->group = 'key';
		} else {
			$this->group = 'name';
		}
	}

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
		if (!empty($this->query)) {
			$pipelines[] = array('$match' => $this->query);
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
								array('$lte' => array('$from', new MongoDate())),
								array('$gt' => array('$to', new MongoDate())),
							),
						),
						'then' => self::STATE_ACTIVE,
						'else' => array(
							'$cond' => array(
								'if' => array(
									'$gte' => array('$from', new MongoDate()),
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
		$res = call_user_func_array(array($this->collectionHandler, 'aggregateWithOptions'), array($pipelines, array('allowDiskUse' => TRUE)));

		$res->setRawReturn(true);
		$aggregatedResults = array_values(iterator_to_array($res));
		return array_column($aggregatedResults, 'id');
	}

}
