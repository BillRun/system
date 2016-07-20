<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for auto-renew
 *
 * @package         Tests
 * @subpackage      Auto-renew
 * @since           4.4
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');
require_once(APPLICATION_PATH . '/application/helpers/Subscriber/Golan.php');

Mock::generate('Subscriber_Golan');

class Tests_SubscriberGolan extends UnitTestCase {
	function testCalcFractionOfMonth() {
		$subscriber = new MockSubscriber_Golan();
	}
}
