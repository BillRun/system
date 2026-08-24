<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

class Billrun_Utils_Realtime {

	public static function getRealtimeConfigValue($config, $keys, $usaget = 'general', $defaultValue = null) {
		if (empty($config['realtime'])) {
			return $defaultValue;
		}

		$config = $config['realtime'];
		$default = Billrun_Util::getIn($config, $keys, $defaultValue);
		
		$keys = !is_array($keys) ? "{$usaget}.{$keys}" : array_merge([$usaget], $keys);
		return Billrun_Util::getIn($config, $keys, $default);
	}

}
