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
		} else if ($this->request['collection'] == 'exchangerates') {
			// base_currency is always the system default, so target_currency uniquely
			// identifies a rate and is its revision grouping key (BRCD-2852).
			$this->group = 'target_currency';
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
	 * Builds the core aggregation pipeline for finding a unique entity.
	 * This is the reusable "building block" for all unique-get operations.
	 * @return array The array of pipeline stages.
	 */
	protected function buildUniqueGetPipeline()
	{
		$pipelines = array();

		$project1 = array(
			'_id' => 1,
			'from' => 1,
			'to' => 1,
			$this->group => 1,
		);

		if (!empty($this->sort)) {
			foreach ($this->sort as $field => $dir) {
				$project1[$field] = 1;
			}
		}

		$project1['state'] = array(
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
		);

		$pipelines[] = array('$project' => $project1);
		$pipelines[] = array(
			'$sort' => array(
				'state' => 1,
				'to' => -1
			),
		);

		$group = array(
			'_id' => '$' . $this->group,
			'state' => array('$first' => '$state'),
			'id' => array('$first' => '$_id'),
		);

		if (!empty($this->sort)) {
			foreach ($this->sort as $field => $dir) {
				if (!in_array($field, ['id', 'state', '_id', $this->group])) {
					$group[$field] = array('$first' => '$' . $field);
				}
			}
		}

		$pipelines[] = array('$group' => $group);

		$project2 = array(
			'_id' => 0,
			'id' => 1,
			'state' => 1,
			$this->group => '$_id', 
		);

		if (!empty($this->sort)) {
			foreach ($this->sort as $field => $dir) {
				if ($field !== $this->group) {
					$project2[$field] = 1;
				}
			}
		}
		$pipelines[] = array('$project' => $project2);

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

		return $pipelines;
	}

	/**
     * method to aggregate and get uniqueness 
     * @return array of mongo ids
     */
    protected function getUniqueIds() {
        $pipelines = array();
        if (!empty($this->query)) {
            $pipelines[] = array('$match' => $this->query);
        }
        
        $core_pipeline = $this->buildUniqueGetPipeline();
        $pipelines = array_merge($pipelines, $core_pipeline);

        error_log(json_encode($pipelines));
        $res = call_user_func_array(array($this->collectionHandler, 'aggregateWithOptions'), array($pipelines, array('allowDiskUse' => TRUE)));

        $res->setRawReturn(true);
        $aggregatedResults = array_values(iterator_to_array($res));
        return array_column($aggregatedResults, 'id');
    }

	/**
	 * Gets the unique IDs using a high-performance, paginated pipeline
	 * This is a reusable helper for child classes.
	 *
	 * @param array $base_query The initial filter (e.g., ['type' => 'subscriber']).
	 * @return array The final array of unique mongo ids for the requested page.
	 */
	protected function getPaginatedUniqueIds(array $base_query)
	{
		if (!empty($this->query)) {
			$pipelines[] = array('$match' => array_merge($base_query, $this->query));
		} else {
			$pipelines[] = array('$match' => $base_query);
		}

		$core_pipeline = $this->buildUniqueGetPipeline();
		$pipelines = array_merge($pipelines, $core_pipeline);

		if (!empty($this->sort)) {
			$pipelines[] = array('$sort' => $this->sort);
		} else {
			$pipelines[] = array('$sort' => array('id' => 1));
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
