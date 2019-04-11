<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for lines
 *
 * @package  Util
 * @since    5.9
 */
class Billrun_Lines_Util {

	/**
	 * Removes future lines by given stamps and additional query
	 * 
	 * @param array $stamps
	 * @param array $query
	 * @return boolean
	 */
	public static function removeLinesByStamps($stamps, $query = []) {
		if (!is_array($stamps)) {
			$stamps = [$stamps];
		}
		
		$query['stamp'] = [
			'$in' => array_values($stamps),
		];

		return self::removeLines($query);
	}
	
	/**
	 * Removes lines by given query
	 * 
	 * @param array $query
	 * @param boolean $futureOnly
	 * @return boolean
	 */
	protected static function removeLines($query, $futureOnly = true) {
		if (empty($query)) {
			return false;
		}
		
		$futureOnlyQuery = [
			'$or' => [
				[
					'billrun' => [
						'$exists' => false,
					],
				],
				[
					'billrun' => [
						'$gte' => Billrun_Billingcycle::getBillrunKeyByTimestamp(),
						'$regex' => new MongoRegex('/^\d{6}$/i'), // 6 length billrun keys only
					],
				],
			],
		];

		if (isset($query['$or'])) {
			$query['$and'] = [
				['$or' => $query['$or']],
				$futureOnly,
			];
		} else {
			$query = array_merge($query, $futureOnlyQuery);
		}
		
		$linesColl = Billrun_Factory::db()->linesCollection();
		return $linesColl->remove($query);
	}
}
