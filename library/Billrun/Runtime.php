<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds facts about the current run that are set once by the entry point
 * and read anywhere (e.g. whether we are serving a realtime request).
 *
 * @package  Billrun
 */
class Billrun_Runtime {

	protected static $values = [];

	public static function setRealtimeRequest($isRealtime = true) {
		self::$values['realtime_request'] = (bool) $isRealtime;
	}

	public static function isRealtimeRequest() {
		return !empty(self::$values['realtime_request']);
	}

}
