<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Fetch data from lines when aggregate.
 * @package  Lines
 * @since    2.8
 * @todo Make this more generic, for now just handling the lines table
 */
class Lines_Fetcher_Aggregate implements Lines_Fetcher_I {
	
	/**
	 * Get the data to show.
	 * @param array $filter_query - Query to get the data for.
	 * @param Mongodloid_Collection $collection - Colletion to query,
	 * @param type $sort - Sort for the cursor.
	 * @param type $offset - Offset for the cursor.
	 * @param type $size - Size for the cursor.
	 * @return aray - Mongo entities to return.
	 * @todo: Create a class to hold sort offset and size.
	 */
	public function fetch($filter_query, $collection, $sort, $offset,$size) {
		$cursor = $collection->aggregatecursor($filter_query)
			->sort($sort)->skip($offset)->limit($size);
		$ret = array();
		
		$groupKeys = array_keys($filter_query[1]['$group']['_id']);
					
		// Go through the items and construct aggregated entities.
		foreach ($cursor as $item) {
			$aggregatedItem = new Mongodloid_AggregatedEntity($item, $groupKeys);
			$ret[] = $aggregatedItem;
		}
		
		$this->_count = count($ret);
		return $ret;
	}

}
