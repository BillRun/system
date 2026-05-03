<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Generate
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Generate extends Billrun_Job_Abstract
{

    protected $method = 'Generate';

    protected function init($params)
    {
        if (isset($this->config['limit_runs']) && is_numeric($this->config['limit_runs']) && $this->config['limit_runs'] > 0) {
            $this->limitRuns = (int) $this->config['limit_runs'];
        } else {
            $this->limitRuns = 1;
        }
    }

    /**
     * job main execution method
     */
    protected function run()
    {
        Billrun_Factory::log("Loading generator", Zend_Log::INFO);

        try {
            $generator = Billrun_Generator::getInstance($this->config);
        } catch (Exception $ex) {
            Billrun_Factory::log()->log($ex->getTraceAsString(), Zend_Log::ERR);
            Billrun_Factory::log()->log('Something went wrong while building the generator. No generate was made.', Zend_Log::ALERT);
            return;
        }

        if (!$generator) {
            Billrun_Factory::log("Generator cannot be loaded", Zend_Log::INFO);
            return;
        }

        Billrun_Factory::log("Generator loaded", Zend_Log::INFO);
        Billrun_Factory::log("Loading data to Generate...", Zend_Log::INFO);

        try {
            $generator->load();
        } catch (Exception $ex) {
            Billrun_Factory::log()->log($ex->getTraceAsString(), Zend_Log::ERR);
            Billrun_Factory::log()->log('Something went wrong while loading. No generate was made.', Zend_Log::ALERT);
            return;
        }

        Billrun_Factory::log("Starting to Generate. This action can take a while...", Zend_Log::INFO);

        try {
            $generator->generate();
        } catch (Exception $ex) {
            Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
            Billrun_Factory::log()->log('Something went wrong while generating. Please pay attention.', Zend_Log::ERR);
        }

        Billrun_Factory::log("Finished generating.", Zend_Log::INFO);

        if ($generator->shouldFileBeMoved()) {
            Billrun_Factory::log("Exporting the file", Zend_Log::INFO);
            $generator->move();
            Billrun_Factory::log("Finished exporting", Zend_Log::INFO);
        }
    }
}
