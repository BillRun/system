<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing compute base class
 *
 * @package  compute
 */
abstract class Billrun_Compute extends Billrun_Base {

    public static function getInstance() {
        $args = func_get_args();
        $args = $args[0];
        $class = 'Billrun_Compute_' . ucfirst($args['type']);
        if(isset($args['recalculation_type'])){
            $class .= '_' . ucfirst($args['recalculation_type']) . 'Recalculation';
        }
        if (!@class_exists($class, true)) {
            Billrun_Factory::log("Can't find class: " . $class, Zend_Log::EMERG);
            return false;
        }
        return new $class();
    }
    
    abstract public function compute();
    abstract public function write();
    abstract public function getComputedType();


}
