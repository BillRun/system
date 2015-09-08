<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Fetch data from lines when find.
 * @package  Lines
 * @since    2.8
 * @todo Make this more generic, for now just handling the lines table
 */
class Lines_Fetcher_Find implements Lines_Fetcher_I {
	
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
		$cursor = $collection->query($filter_query)->cursor()
			->sort($sort)->skip($offset)->limit($size);

		if (isset($filter_query['$and']) && Billrun_Util::filterExists($filter_query['$and'], array('aid', 'sid', 'stamp'))) {
			$this->_count = $cursor->count(false);
		} else {
			// TODO: Why is _count not set to count($ret)?
			$this->_count = Billrun_Factory::config()->getConfigValue('admin_panel.lines.global_limit', 10000);
		}

		$ret = array();
		foreach ($cursor as $item) {
			$item->collection(Billrun_Factory::db()->linesCollection());
			$arate = Billrun_DBRef::getDBRefField($item, 'arate');
			if ($arate) {
				$item['arate'] = $arate['key'];
				$item['arate_id'] = strval($arate['_id']);
			} else {
				$item['arate'] = $arate;
			}
			if(isset($item['rat_type'])) {
				$item['rat_type'] = Admin_Table::translateField($item, 'rat_type');
			}
			$ret[] = $item;
		}
		return $ret;
	}

}
