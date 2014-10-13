<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Refund action class
 *
 * @package  Action
 * @since    1.0
 */
class IrddaysAction extends Action_Base {

        /**
         * method to execute the refund
         * it's called automatically by the api main controller
         */
public function execute() {
                Billrun_Factory::log()->log("Execute ird days API call", Zend_Log::INFO);

                $this->getController()->setOutput(array(
                        'status' => 1,
                        'desc' => 'success',
                        'input' => $post,
                        'details' => array(
                                'days' => 0,
                                'min_day' => 40,
                                'max_day' => 45,
                        )
                ));
        }

}
