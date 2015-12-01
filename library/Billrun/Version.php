<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Version information class
 *
 * @package  Billrun
 * @since    4.0
 */
final class Billrun_Version
{
	/** @var  string  Product name. */
	public $PRODUCT = 'BillRun';

	/** @var  string  Release version. */
	public $RELEASE = '4.0';

	/** @var  string  Maintenance version. */
	public $DEV_LEVEL = '0';

	/** @var  string  Development STATUS. */
	public $DEV_STATUS = 'Alpha';

	/** @var  string  Build number. */
	public $BUILD = '';

	/** @var  string  Release date. */
	public $RELDATE = '01-December-2015';

	/** @var  string  Release time. */
	public $RELTIME = '20:30';

	/** @var  string  Release timezone. */
	public $RELTZ = 'GMT';

	/** @var  string  Copyright Notice. */
	public $COPYRIGHT = 'Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.';

	/** @var  string  Link text. */
	public $URL = '<a href="https://bill.run">BillRun</a>';

	/**
	 * Compares two a "PHP standardized" version number against the current Joomla version.
	 *
	 * @param   string  $minimum  The minimum version of the Joomla which is compatible.
	 *
	 * @return  bool    True if the version is compatible.
	 *
	 * @see     http://www.php.net/version_compare
	 * @since   4.0
	 */
	public static function isCompatible($minimum)
	{
		return version_compare(JVERSION, $minimum, 'ge');
	}

	/**
	 * Gets a "PHP standardized" version string for the current Joomla.
	 *
	 * @return  string  Version string.
	 *
	 * @since   4.0
	 */
	public static function getShortVersion()
	{
		return $this->RELEASE . '.' . $this->DEV_LEVEL;
	}

	/**
	 * Gets a version string for the current Joomla with all release information.
	 *
	 * @return  string  Complete version string.
	 *
	 * @since   4.0
	 */
	public static function getLongVersion()
	{
		return $this->PRODUCT . ' ' . $this->RELEASE . '.' . $this->DEV_LEVEL . ' '
			. $this->DEV_STATUS . ' [ ' . $this->CODENAME . ' ] ' . $this->RELDATE . ' '
			. $this->RELTIME . ' ' . $this->RELTZ;
	}

}
