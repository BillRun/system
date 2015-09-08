<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * 
 * Interface for the Lines Data fetcher.
 * @package  Lines
 * @since    2.8
 * @todo Make this more generic, for now just handling the lines table
 */
interface Lines_Fetcher_I {
	
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
	public function fetch($filter_query, $collection, $sort, $offset,$size);
}
