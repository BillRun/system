<?php //

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Refund action class
 *
 * @package  Action
 * @since    0.5
 */
class RefundAction extends Action_Base {

        /**
         * backward compatible method to execute the credit
         */
        public function execute() {
                $this->forward("credit");
                return false;
        }
}

