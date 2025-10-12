<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is accounts unique get
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Uniqueget_Accounts extends Models_Action_Uniqueget {

	protected function runQuery() {
		$ids = $this->getUniqueIds();
		$this->query = array(
			'_id' => array(
				'$in' => $ids
			),
		);

		$project = $this->prepareProjection();

		Billrun_Factory::log("Billapi get runs query: " . json_encode($this->query), Zend_Log::DEBUG);
		$cursor = $this->collectionHandler->find($this->query, $project);

		return $this->processResults($cursor);
	}

	protected function initGroup() {
		$this->group = 'aid';
	}

	protected function getCollectionName() {
		return 'subscribers';
	}
	
	protected function getCustomFieldsKey() {
		return $this->getCollectionName() . ".account";
	}

	/**
	 * Overrides the parent getUniqueIds to add pagination to the original selection logic.
	 * * @return array of mongo ids
	 */
	protected function getUniqueIds() {
		 $base_query = ['type' => 'account'];
        if (!empty($this->query)) {
            $pipelines[] = array('$match' => array_merge($base_query, $this->query));
        } else {
            $pipelines[] = array('$match' => $base_query);
        }

        $core_pipeline = parent::buildUniqueGetPipeline();
        $pipelines = array_merge($pipelines, $core_pipeline);

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
