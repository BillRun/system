<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Compute action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 */
class ComputeAction extends Action_Base {

    /**
     * method to execute the compute suggestion process
     * it's called automatically by the cli main controller
     */
    public function execute() {

        $possibleOptions = array('type' => false);

        if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
            return;
        }
        $extraParams = $this->getController()->getParameters();
        if (!empty($extraParams)) {
            $options = array_merge($extraParams, $options);
        }
        $this->getController()->addOutput("Loading Compute ");
        $computed = Billrun_Compute::getInstance($options);
        if (!$computed) {
            $this->getController()->addOutput("Compute cannot be loaded");
        } else {
            $this->getController()->addOutput("Compute " . $computed->getComputedType() . " loaded");
            $this->getController()->addOutput("Starting to compute " . $computed->getComputedType() . ". This action can take a while...");
            $computed->compute();
            Billrun_Factory::log()->log("Writing compute " . $computed->getComputedType() ." data.", Zend_Log::INFO);
            $computed->write();
            $this->getController()->addOutput("Compute " . $computed->getComputedType(). " finished.");
        }
    }

}
