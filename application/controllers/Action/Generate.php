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
        if($this->checkConfig($generator->getConfigByType(), $generator)){
            $this->_controller->addOutput("Config validation passed successfully.");
        }
        $generator->initLogFile();
        $this->_controller->addOutput("Log file was generated.");
        
        if (method_exists($generator, 'lock')) {
            if (!$generator->lock()) {
                $this->_controller->addOutput("Generator is already running");
                return;
            }
        }

        $this->_controller->addOutput("Generator loaded");
        $this->_controller->addOutput("Loading data to Generate...");
            $generator->load();
            $this->_controller->addOutput("Starting to Generate. This action can take a while...");
            if ($generator->generate()) {
                $this->_controller->addOutput("Finished generating.");
                if (method_exists($generator, 'release')) {
                    if (!$generator->release()) {
                        $this->_controller->addOutput("Problem in releasing operation");
                        return;
                    }
                }
                if ($generator->shouldFileBeMoved()) {
                    $this->_controller->addOutput("Exporting the file");
                    $generator->move();
                    $this->_controller->addOutput("Finished exporting");
                }
            } else {
                $this->_controller->addOutput("Something went wrong, no file was genertaed.");
            }
    }
    
    protected function checkConfig($config, $generator){
        $fileGenerator = $generator->getGeneratorClassName();
        $response = $fileGenerator::validateOptions($config);
        if($response !== true){
            throw new Exception($response);
        }
        return true;
    }
}
