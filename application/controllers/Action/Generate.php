<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generate action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class GenerateAction extends Action_Base {

    /**
     * method to execute the generate process
     * it's called automatically by the cli main controller
     */
    public function execute() {

        $possibleOptions = array(
            'type' => false,
            'stamp' => true,
            'page' => true,
            'size' => true,
        );

        if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
            return;
        }

        $this->_controller->addOutput("Loading generator");


        $extraParams = $this->_controller->getParameters();
        if (!empty($extraParams)) {
            $options = array_merge($extraParams, $options);
        }

        $generator = Billrun_Generator::getInstance($options);

        if (!$generator) {
            $this->_controller->addOutput("Generator cannot be loaded");
            return;
        }
        
        $this->_controller->addOutput("Generator loaded");
        $this->_controller->addOutput("Loading data to Generate...");
        try{
            $generator->load();
        } catch(Exception $ex){
            Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
            Billrun_Factory::log()->log('Something went wrong while loading. No generate was made.', Zend_Log::ALERT);
            return;
        }
        $this->_controller->addOutput("Starting to Generate. This action can take a while...");
        try{
            $generator->generate();
        } catch(Exception $ex){
            Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
            Billrun_Factory::log()->log('Something went wrong while generating. Please pay attention.', Zend_Log::ERR);
        }
        $this->_controller->addOutput("Finished generating.");
        if ($generator->shouldFileBeMoved()) {
            $this->_controller->addOutput("Exporting the file");
            $generator->move();
            $this->_controller->addOutput("Finished exporting");
        }
    }
}
