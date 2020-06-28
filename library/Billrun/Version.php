<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Version information class
 *
 * @package  Billrun
 * @since    4.0
 */
class Billrun_Version {

	/** @var  string  Product name. */
	public static $PRODUCT = 'BillRun';

	/** @var  string  Release version. */
	public static $RELEASE = '5.12';

	/** @var  string  Maintenance version. */
	public static $DEV_LEVEL = '';

	/** @var  string  Development STATUS. */
	public static $DEV_STATUS = 'Beta';

	/** @var  string  Build number. */
	public static $BUILD = '4';

	/** @var  string  Release date. */
	public static $RELDATE = '28-June-2020';

	/** @var  string  Link text. */
	public static $URL = '<a href="https://bill.run">BillRun</a>';

	/**
	 * Gets a "PHP standardized" version string for the current Joomla.
	 *
	 * @return  string  Version string.
	 *
	 * @since   4.0
	 */
	public static function getShortVersion() {
		return self::$RELEASE . '.' . self::$DEV_LEVEL;
	}

	/**
	 * Gets a version string for the current Joomla with all release information.
	 *
	 * @return  string  Complete version string.
	 *
	 * @since   4.0
	 */
	public static function getLongVersion() {
		return self::$PRODUCT . '-' . self::$RELEASE . '.' . self::$DEV_LEVEL . '-'
			. self::$DEV_STATUS . '-' . self::$BUILD;
	}

}
