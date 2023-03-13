<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Reset model class to reset subscribers balance & lines for a billrun month
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class ResetLinesModel {

    use Billrun_Traits_ConditionsCheck;

    /**
     *
     * @var array
     */
    protected $aids;

    /**
     *
     * @var string
     */
    protected $billrun_key;

    /**
     * Don't get newly stuck lines because they might have not been inserted yet to the queue
     * @var string
     */
    protected $process_time_offset;

    /**
     * Usage to substract from extended balance in rebalance.
     * @var array
     */
    protected $extendedBalanceSubstract;

    /**
     * Usage to substract from default balance in rebalance.
     * @var array
     */
    protected $balanceSubstract = [];

    protected $balanceAlreadyUpdated = [];

    /**
     * used for rebalance multiple balances affected by the same line
     * @var type 
     */
    protected $alreadyUpdated = [];
    protected $balances;

    /**
     * Conditions to add to the query when rebalancing.
     * @var array
     */
    protected $conditions;
    protected $linesStampsByRebalanceStamp = [];

	public function __construct($aids, $billrun_key, $conditions, $rebalanceStamps, $stampsToRecoverByAidAndSid = array()) {
        $this->initBalances($aids, $billrun_key);
        $this->aids = $aids;
        $this->billrun_key = strval($billrun_key);
        $this->process_time_offset = Billrun_Config::getInstance()->getConfigValue('resetlines.process_time_offset', '15 minutes');
        $this->conditions = $conditions;
        $this->stampsToRecoverByAidAndSid = $stampsToRecoverByAidAndSid;
        $this->rebalnceQueueRecoverStampsPath = Billrun_Util::getBillRunSharedFolderPath('workspace' . DIRECTORY_SEPARATOR . 'rebalance' . DIRECTORY_SEPARATOR . 'rebalance_queue' . DIRECTORY_SEPARATOR . 'recover_stamps' . DIRECTORY_SEPARATOR . $this->billrun_key);
        if (Billrun_Config::getInstance()->getConfigValue('resetlines.avoid_repeating_reset', false)) {
            $this->rebalanceStamps = $rebalanceStamps;
        }
    }

    public function reset() {
        Billrun_Factory::log('Reset subscriber activated', Zend_Log::INFO);
        $ret = $this->resetLines();
        return $ret;
    }

    /**
     * Removes the balance doc for each of the subscribers
     */
    public function resetBalances($aids) {
        $ret = true;
        $balances_coll = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
        if (!empty($this->aids) && !empty($this->billrun_key)) {
            $ret = $this->resetDefaultBalances($aids, $balances_coll);
            $this->resetExtendedBalances($aids, $balances_coll);
        }
        return $ret;
    }

    /**
     * Get the reset lines query.
     * @param array $update_aids - Array of aid's to reset.
     * @return array Query to run in the collection for reset lines.
     */
    protected function getResetLinesQuery($update_aids) {
        $query = array(
            'type' => array(
                '$nin' => array('credit', 'flat', 'service'),
            ),
            'process_time' => array(
                '$lt' => new MongoDate(strtotime($this->process_time_offset . ' ago')),
            ),
        );
        //add support to multi day cycle
        $aidsByInvoiceDay = [];
        foreach ($update_aids as $aid) {
            $invoicing_day = Billrun_Factory::config()->getConfigChargingDay();
            if (Billrun_Factory::config()->isMultiDayCycle()) {
                $account = Billrun_Factory::account()->loadAccountForQuery(array('aid' => $aid));
                $invoicing_day = !empty($account['invoicing_day']) ? $account['invoicing_day'] : Billrun_Factory::config()->getConfigChargingDay();
            }
            $aidsByInvoiceDay[$invoicing_day][] = $aid;
        }
        foreach ($aidsByInvoiceDay as $invoiceDay => $aids) {
            if ($this->billrun_key <= Billrun_Billingcycle::getLastClosedBillingCycle($invoicing_day)) {// billrun already closed
                $cond = array(
                    'billrun' => array(
                        '$exists' => FALSE,
                    ),
                    'urt' => array(
                        '$gte' => new MongoDate(Billrun_Billingcycle::getStartTime($this->billrun_key, $invoiceDay)),
                        '$lt' => new MongoDate(Billrun_Billingcycle::getEndTime($this->billrun_key, $invoiceDay)),
                    )
                );
            } else {
                $cond = array(
                    '$or' => array(
                        array(
                            'billrun' => $this->billrun_key
                        ),
                        array(
                            'billrun' => array(
                                '$exists' => FALSE,
                            ),
                            'urt' => array(
                                '$gte' => new MongoDate(Billrun_Billingcycle::getStartTime($this->billrun_key, $invoiceDay)),
                                '$lt' => new MongoDate(Billrun_Billingcycle::getEndTime($this->billrun_key, $invoiceDay)),
                            )
                        ),
                    )
                );
            }
            $query['$or'][] = array_merge(
                    array(
                        'aid' => array('$in' => $aids),
                    ), $cond);
        }
        return $query;
    }

    /**
     * Reset lines for subscribers based on input array of AID's
     * @param array $update_aids - Array of account ID's to reset.
     * @param array $advancedProperties - Array of advanced properties.
     * @param Mongodloid_Collection $lines_coll - The lines collection.
     * @param Mongodloid_Collection $queue_coll - The queue colection.
     * @return boolean true if successful false otherwise.
     */
    protected function resetLinesForAccounts($update_aids, $advancedProperties, $lines_coll, $queue_coll) {
        $conditionsQuery = $this->buildConditionsQuery($update_aids);
        $basicQuery = $this->getResetLinesQuery($update_aids);
        if (!empty($conditionsQuery)) {
            $query = array_merge($basicQuery, array('$and' => array($conditionsQuery)));
        } else {
            $query = $basicQuery;
        }
        $stamps = $this->getAllLinesStamps($lines_coll, $query);
        $linesSizeToHandle = Billrun_Config::getInstance()->getConfigValue('resetlines.lines.size', 100000);
        $iteration = 1;
        $totalIterations = ceil(count($stamps)/$linesSizeToHandle);
        while ($update_stamps_count = count($update_stamps = array_slice($stamps, 0, $linesSizeToHandle))) {
            Billrun_Factory::log('resetLinesForAccounts iteration ' . $iteration++ . ' from ' . $totalIterations . ' iterations', Zend_Log::DEBUG);
            $stampQuery = array('stamp' => array('$in' => $update_stamps));
            Billrun_Factory::log("Rebalance lines query start. Query is: " . json_encode($stampQuery), Zend_Log::DEBUG);
            $lines = $lines_coll->query(array_merge($query, $stampQuery))->cursor();
            Billrun_Factory::log("Rebalance lines query end", Zend_Log::DEBUG);
            $lines = iterator_to_array($lines);
            Billrun_Factory::log("Rebalance after iterator_to_array", Zend_Log::DEBUG);
            $this->resetLinesByQuery($lines, $update_aids, $advancedProperties, $lines_coll, $queue_coll);
            //rempve the stamps that already used from the array (for the memory)
            $stamps = array_slice($stamps, $linesSizeToHandle);
        }
    }

    protected function getAllLinesStamps($lines_coll, $query) {
        Billrun_Factory::log("Rebalance get all stamps query start. Query is: " . json_encode($query), Zend_Log::DEBUG);
        $lines = $lines_coll->query($query)->cursor()->setReadPreference('RP_PRIMARY')->fields(array('stamp' => 1))->setRawReturn(true);
        Billrun_Factory::log("Rebalance get all stamps query end", Zend_Log::DEBUG);
        return array_column(iterator_to_array($lines), 'stamp');
    }

    /**
     * Reset lines based on input array of stamps
     * @param array $stamps - Array of stamps of lines to reset.
     * @param array $update_aids - Array of account ID's that in the rebalance queue and have stamps inside the rebalance queue.
     * @param array $advancedProperties - Array of advanced properties.
     * @param Mongodloid_Collection $lines_coll - The lines collection.
     * @param Mongodloid_Collection $queue_coll - The queue colection.
     * @return boolean true if successful false otherwise.
     */
    protected function resetLinesByStamps($stamps, $update_aids, $advancedProperties, $lines_coll, $queue_coll) {
        $query = array('stamp' => array('$in' => $stamps));
        Billrun_Factory::log("resetLinesByStamps before query " . json_encode($query), Zend_Log::DEBUG);
        $lines = $lines_coll->query($query);
        Billrun_Factory::log("resetLinesByStamps after query", Zend_Log::DEBUG);
        return $this->resetLinesByQuery(iterator_to_array($lines), $update_aids, $advancedProperties, $lines_coll, $queue_coll);
    }

    protected function resetLinesByQuery($lines, $update_aids, $advancedProperties, $lines_coll, $queue_coll) {
        Billrun_Factory::dispatcher()->trigger('beforeResetLinesByQuery', array(&$lines, &$update_aids, &$advancedProperties));
        $rebalanceTime = new MongoDate();
        $stamps = array();
        $queue_lines = array();
        $former_exporter = array();
        $this->stampsByAidAndSid = [];
        $this->splitLinesStamp = [];
        // Go through the collection's lines and fill the queue lines.
        Billrun_Factory::log("Rebalance resetLinesByQuery starts iteration", Zend_Log::DEBUG);
        $i = 0;
        $totalLines = count($lines);
        foreach ($lines as $line) {
            $i++;
            Billrun_Factory::log("reached $i line from $totalLines. line stamp: " . $line['stamp'] , Zend_Log::DEBUG);
            Billrun_Factory::dispatcher()->trigger('beforeRebalancingLines', array(&$line));
            Billrun_Factory::log("after beforeRebalancingLines", Zend_Log::DEBUG);
            $rebalanceStamp = $this->isLineRelevantForRebalanceStampsHash($line);
            if ($line['source'] === 'unify') {
                $batchSize = Billrun_Config::getInstance()->getConfigValue('resetlines.archived_lines.batch_size', 100000);
                Billrun_Factory::log("before get unify lines", Zend_Log::DEBUG);
                $archiveLinesSize = $line['lcount'];
                Billrun_Factory::log("after get unify lines", Zend_Log::DEBUG);
		Billrun_Factory::log("line have " . $archiveLinesSize . " archive lines. line stamp: " . $line['stamp'], Zend_Log::DEBUG);
                $j = 1;
                for ($skip = 0; $skip < $archiveLinesSize; $skip += $batchSize) {
                    Billrun_Factory::log("before get unify lines 2", Zend_Log::DEBUG);

                    $archivedLines = Billrun_Calculator_Unify::getUnifyLines($line['stamp'], $batchSize, $skip);
                    Billrun_Factory::log("after get unify lines 2", Zend_Log::DEBUG);

                    $archivedLinesToInsert = [];
                    Billrun_Factory::log("Before archived lines loop", Zend_Log::DEBUG);
                    foreach ($archivedLines as $archivedLine) {
                        Billrun_Factory::log("reached $j archive line from $archiveLinesSize lines. archive line stamp: " . $archivedLine['stamp'], Zend_Log::DEBUG);
                        $j++;
                        unset($archivedLine["u_s"]);
                        unset($archivedLine["_id"]);//in case already exist line with this _id but different (rare case) - can cause lose this archive line 
                        $archivedLinesToInsert[$archivedLine['stamp']] = $archivedLine;
                        $this->resetLine($archivedLine, $stamps, $queue_lines, $rebalanceTime, $advancedProperties, $former_exporter);
                        $this->addLineStampToRebalanceStampsHash($archivedLine, $rebalanceStamp);
                    }
                    Billrun_Factory::log("end archived lines loop", Zend_Log::DEBUG);
                    $this->restoringArchivedLinesToLines($archivedLinesToInsert);
                    Billrun_Factory::log("after restoringArchivedLinesToLines", Zend_Log::DEBUG);
                }
                Billrun_Factory::log("before lines remove", Zend_Log::DEBUG);
                $ret1 = Billrun_Factory::db()->linesCollection()->remove(array('stamp' => $line['stamp']));
                Billrun_Factory::log("Removed " . $ret1['n'] . " lines", Zend_Log::DEBUG);
                $ret2 = Billrun_Factory::db()->archiveCollection()->remove(array('u_s' => $line['stamp']));
                Billrun_Factory::log("Removed " . $ret2['n'] . " archive lines", Zend_Log::DEBUG);
                continue;
            }
            Billrun_Factory::log("before resetLine", Zend_Log::DEBUG);
            $this->resetLine($line, $stamps, $queue_lines, $rebalanceTime, $advancedProperties, $former_exporter);
            Billrun_Factory::log("after resetLine", Zend_Log::DEBUG);
            $this->addLineStampToRebalanceStampsHash($line, $rebalanceStamp);
            Billrun_Factory::log("end $i line", Zend_Log::DEBUG);
        }
        // If there are stamps to handle.
        if ($stamps) {
            // Handle the stamps.
            Billrun_Factory::log("before handleStamps", Zend_Log::DEBUG);
            if (!$this->handleStamps($stamps, $queue_coll, $queue_lines, $lines_coll, $update_aids, $rebalanceTime, $former_exporter)) {
                Billrun_Factory::log("after handleStamps", Zend_Log::DEBUG);
                return false;
            }
            Billrun_Factory::log("after handleStamps", Zend_Log::DEBUG);
        }
    }

    protected function restoringArchivedLinesToLines($archivedLinesToInsert) {
        try {
            Billrun_Factory::log("before lines batch insert", Zend_Log::DEBUG);
            $ret = Billrun_Factory::db()->linesCollection()->batchInsert(array_values($archivedLinesToInsert));
            Billrun_Factory::log("after lines batch insert ", Zend_Log::DEBUG);
            if (isset($ret['err']) && !is_null($ret['err'])) {
                Billrun_Factory::log('Rebalance: batch insertion of restoring archive lines to lines failed, Insert Error: ' . $ret['err'], Zend_Log::ALERT);
                throw new Exception();
            }
        } catch (Exception $e) {
            try {
                Billrun_Factory::log("Rebalance: Batch insert failed during of restoring archive lines to lines, removing duplicate archive lines and retry the bulkInsert, Error: " . $e->getMessage(), Zend_Log::ERR);
                $archivedLinesWithoutDuplicates = $this->removeDuplicateFromArchivedLines($archivedLinesToInsert);
                $ret = Billrun_Factory::db()->linesCollection()->batchInsert($archivedLinesWithoutDuplicates);
                if (isset($ret['err']) && !is_null($ret['err'])) {
                    Billrun_Factory::log('Rebalance: batch insertion of restoring archive lines to lines failed, Insert Error: ' . $ret['err'], Zend_Log::ALERT);
                    throw new Exception();
                }
            } catch (Exception $ex) {
                Billrun_Factory::log("Rebalance: Batch insert failed during of restoring archive lines to lines, inserting line by line, Error: " . $ex->getMessage(), Zend_Log::ERR);
                $this->restoringArchivedLinesLineByLine($archivedLinesToInsert);
            }
        }
    }

    /**
     * Remove all the lines that are in $archivedLinesToInsert and also already in lines collection
     * @param array $archivedLinesToInsert
     * @param array $archivedLinesStamps
     */
    protected function removeDuplicateFromArchivedLines($archivedLinesToInsert) {
        $query = array('stamp' => array('$in' => array_keys($archivedLinesToInsert)));
        $duplicateArchiveLines = Billrun_Factory::db()->linesCollection()->query($query)->cursor()->fields(array('stamp' => 1))->setRawReturn(true);
        $duplicateArchiveLinesStamps = array_column(iterator_to_array($duplicateArchiveLines), 'stamp');
        foreach ($duplicateArchiveLinesStamps as $duplicateArchiveLineStamp) {
            unset($archivedLinesToInsert[$duplicateArchiveLineStamp]);
        }
        return array_values($archivedLinesToInsert);
    }

    protected function restoringArchivedLinesLineByLine($archivedLinesToInsert) {
        foreach ($archivedLinesToInsert as $stamp => $archiveLine) {
            try {
                $ret = Billrun_Factory::db()->linesCollection()->insert($archiveLine); // ok==1, err null
                if (isset($ret['err']) && !is_null($ret['err'])) {
                    Billrun_Factory::log('Rebalance: line insertion of restoring archive line to lines failed, Insert Error: ' . $ret['err'] . ', failed_line ' . $stamp, Zend_Log::ALERT);
                    throw new Exception($ret['err']);
                }
            } catch (Exception $e) {
                if (in_array($e->getCode(), Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR)) {
                    Billrun_Factory::log('Rebalance: line insertion of restoring archive line to lines failed, Insert Error: ' . $e->getMessage() . ', failed_line ' . $stamp, Zend_Log::NOTICE);
                    continue;
                } else {
                    Billrun_Factory::log('Rebalance: line insertion of restoring archive line to lines failed, Insert Error: ' . $e->getMessage() . ', failed_line ' . $stamp, Zend_Log::ALERT);
                    throw $e;
                }
            }
        }
    }

    protected function resetLine($line, &$stamps, &$queue_lines, $rebalanceTime, $advancedProperties, &$former_exporter) {
        Billrun_Factory::log("start resetLine", Zend_Log::DEBUG);
        $queue_line = array(
            'calc_name' => false,
            'calc_time' => false,
            'skip_fraud' => true,
            'reset_query_hash' => key($this->conditions[$line['aid']])
        );
        $this->aggregateLineUsage($line);
        Billrun_Factory::log("after aggregateLineUsage", Zend_Log::DEBUG);

        $queue_line['rebalance'] = array();
        $stamps[] = $line['stamp'];
        if (isset($line['aid']) && isset($line['sid'])) {
            $this->stampsByAidAndSid[$line['aid']][$line['sid']][] = $line['stamp'];
        }
        $former_exporter = $this->buildFormerExporterForLine($line);
        Billrun_Factory::log("after buildFormerExporterForLine", Zend_Log::DEBUG);
        $split_line = $line['split_line'] ?? false;
        if ($split_line) {//CDR which is duplicated/split shouldn't enter the queue on a rebalance
            $addToQueue = false;
            Billrun_Factory::dispatcher()->trigger('beforSplitLineNotAddedToQueue', array($line, &$addToQueue));
            if (!$addToQueue) {
				Billrun_Factory::log("Adding line ". $line['stamp'] . " to splitLinesStamp", Zend_Log::DEBUG);
                $this->splitLinesStamp[] = $line['stamp'];
                return;
            }
        }
        if (!empty($line['rebalance'])) {
            $queue_line['rebalance'] = $line['rebalance'];
        }
        $queue_line['rebalance'][] = $rebalanceTime;
        $queue_line['in_queue_since'] = new MongoDate();
        $this->buildQueueLine($queue_line, $line, $advancedProperties);
        Billrun_Factory::log("after buildQueueLine", Zend_Log::DEBUG);
        $queue_lines[] = $queue_line;
        Billrun_Factory::log("end resetLine", Zend_Log::DEBUG);
    }
    
    /**
     * Check if the line matches the rebalance queue record conditions
     * @param array $line
     * @return boolean|int - return the rebalance_queue stamp that line matches, false if line is not match/.
     */
    protected function isLineRelevantForRebalanceStampsHash($line){
        if (!Billrun_Config::getInstance()->getConfigValue('resetlines.avoid_repeating_reset', false)){
            return false;
        }
        if(!isset($line['aid'])){// in case line already rested and still not finish the rebalance(recover stamp). 
            return false;
        }           
        //Optimization: If the rebalance works on one rebalance queue record at a time, it must match.
        if (count($this->rebalanceStamps[$line['aid']]) === 1) {
            return current($this->rebalanceStamps[$line['aid']]);
        }
        $conditionsByHash = $this->conditions[$line['aid']];
        foreach ($conditionsByHash as $conditionHash => $conditions) {
            //Check if the line matches the rebalance queue record conditions (using ArrayQuery)
            if (empty($conditions) || $this->isConditionMeet($line->getRawData(), $this->translateConditionArrayToQuery($conditions))) {
                return $this->rebalanceStamps[$line['aid']][$conditionHash];
            }
        }
        Billrun_Factory::log("No rebalance queue record was found for this line. line stamp: " . $line['satmp'] , Zend_Log::WARN);
        return false;
    }

    /**
     * Store to a new hash table (key = rebalance queue stamp, value = array of matching lines)
     * @param array $line - The matching line
     * @param int $rebalanceStamp - the rebalance_queue stamp that line matches
     */
    protected function addLineStampToRebalanceStampsHash($line, $rebalanceStamp) {
        if (empty($rebalanceStamp)) {
            return;
        }     
        
        $this->linesStampsByRebalanceStamp[$rebalanceStamp][] = $line['stamp'];
    } 

    protected function buildFormerExporterForLine($line) {
        $former_exporter = [];
        if (isset($line['export_stamp']) && isset($line['export_start'])) {
            $former_exporter = array(
                'export_stamp' => $line['export_stamp'],
                'export_start' => $line['export_start']
            );
        }
        return $former_exporter;
    }

    /**
     * Removes lines from queue, reset added fields off lines and re-insert to queue first stage
     * @todo support update/removal of credit lines
     */
    protected function resetLines() {
        $lines_coll = Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY');
        $queue_coll = Billrun_Factory::db()->queueCollection()->setReadPreference('RP_PRIMARY');
        if (empty($this->aids) || empty($this->billrun_key)) {
            // TODO: Why return true?
            return true;
        }

        $offset = 0;
        $configFields = array('imsi', 'msisdn', 'called_number', 'calling_number');
        $advancedProperties = Billrun_Factory::config()->getConfigValue("queue.advancedProperties", $configFields);

        //handle lines that already reset but not finish the rebalance (crash in the midle)
        $stamps = [];
        foreach ($this->stampsToRecoverByAidAndSid as $aid => $sids) {
            foreach ($sids as $sid => $sidStamps) {
                $stamps = array_merge($stamps, $sidStamps);
            }
        }
        if (!empty($stamps)) {
            $reset_stamps_size = Billrun_Config::getInstance()->getConfigValue('resetlines.stamps.size', 10000);
            $totalStamps = count($stamps);
            $totalIteration = ceil($totalStamps/$reset_stamps_size);
            $i=1;
            while ($update_stamps_count = count($update_stamps = array_slice($stamps, $offset, $reset_stamps_size))) {
                Billrun_Factory::log('Fix rebalance for ' . $update_stamps_count . ' lines from ' . $totalStamps . ' lines', Zend_Log::INFO);
                Billrun_Factory::log('Fix rebalance. iteration ' . $i . ' from ' . $totalIteration . ' iterations', Zend_Log::INFO);
                $this->resetLinesByStamps($update_stamps, $this->aids, $advancedProperties, $lines_coll, $queue_coll);
                $offset += $reset_stamps_size;
                $i++;
            }
        }
        $offset = 0;
        $reset_accounts_size = Billrun_Config::getInstance()->getConfigValue('resetlines.updated_aids.size', 10);
        $totalAids = count($this->aids);
        $totalIteration = ceil($totalAids/$reset_accounts_size);
        $i=1;
        while ($update_count = count($update_aids = array_slice($this->aids, $offset, $reset_accounts_size))) {
            Billrun_Factory::log('Resetting lines of ' . $update_count .  ' accounts from ' . $totalAids . " accounts", Zend_Log::INFO);
            Billrun_Factory::log('Resetting lines of accounts ' . implode(',', $update_aids), Zend_Log::INFO);
            Billrun_Factory::log('Resetting lines of accounts.  iteration ' . $i . ' from ' . $totalIteration . ' iterations', Zend_Log::INFO);
            $this->resetLinesForAccounts($update_aids, $advancedProperties, $lines_coll, $queue_coll);
            $offset += $reset_accounts_size;
            $i++;
        }

        return TRUE;
    }

    /**
     * Construct the queue line based on the input line from the collection.
     * @param array $queue_line - Line to construct.
     * @param array $line - Input line from the collection.
     * @param array $advancedProperties - Advanced config properties.
     */
    protected function buildQueueLine(&$queue_line, $line, $advancedProperties) {
        $queue_line['stamp'] = $line['stamp'];
        $queue_line['type'] = $line['type'];
        $queue_line['urt'] = $line['urt'];

        foreach ($advancedProperties as $property) {
            if (isset($line[$property]) && !isset($queue_line[$property])) {
                $queue_line[$property] = $line[$property];
            }
        }
    }

    /**
     * Get the query to update the lines collection with.
     * @return array - Query to use to update lines collection.
     */
    protected function getUpdateQuery($rebalanceTime, $former_exporter) {
        $updateQuery = array(
            '$unset' => array(
                'aid' => 1,
                'sid' => 1,
                'subscriber' => 1,
                'apr' => 1,
                'aprice' => 1,
                'arate' => 1,
                'arate_key' => 1,
                'arategroups' => 1,
                'firstname' => 1,
                'lastname' => 1,
                'billrun' => 1,
                'in_arate' => 1,
                'in_group' => 1,
                'in_plan' => 1,
                'out_plan' => 1,
                'over_arate' => 1,
                'over_group' => 1,
                'over_plan' => 1,
                'out_group' => 1,
                'plan' => 1,
                'plan_ref' => 1,
                'services' => 1,
                'usagesb' => 1,
//				'usagev' => 1,
                'balance_ref' => 1,
                'tax_data' => 1,
                'final_charge' => 1,
                'rates' => 1,
                'services_data' => 1,
                'foreign' => 1, // this should be replaced by querying lines.fields and resetting all custom fields found there
                'export_stamp' => 1,
                'export_start' => 1,
                'exported' => 1,
                'full_calculation' => 1
            ),
            '$set' => array(
                'in_queue' => true,
            ),
            '$push' => array(
                'rebalance' => $rebalanceTime,
            ),
        );
        if (!empty($former_exporter)) {
            $updateQuery['$push']['former_exporters'] = $former_exporter;
        }
        Billrun_Factory::log("before beforeUpdateRebalanceLines", Zend_Log::DEBUG);
        Billrun_Factory::dispatcher()->trigger('beforeUpdateRebalanceLines', array(&$updateQuery));
        Billrun_Factory::log("after beforeUpdateRebalanceLines", Zend_Log::DEBUG);

        return $updateQuery;
    }

    /**
     * Get the query to return all lines including the collected stamps.
     * @param $stamps - Array of stamps to query for.
     * @return array Query to run for the lines collection.
     */
    protected function getStampsQuery($stamps) {
        return array(
            'stamp' => array(
                '$in' => $stamps,
            ),
        );
    }

    /**
     * Handle stamps for reset lines.
     * @param array $stamps
     * @param type $queue_coll
     * @param type $queue_lines
     * @param type $lines_coll
     * @param type $update_aids
     * @return boolean
     */
    protected function handleStamps($stamps, $queue_coll, $queue_lines, $lines_coll, $update_aids, $rebalanceTime, $former_exporter) {
        $update = $this->getUpdateQuery($rebalanceTime, $former_exporter);

        Billrun_Factory::log('Removing records from queue', Zend_Log::DEBUG);
        $offset = 0;
        $batch_size = Billrun_Config::getInstance()->getConfigValue('resetlines.queue.removal_size', '10000');
        while ($stamps_batch = array_slice($stamps, $offset, $batch_size)) {
            $stamps_query = $this->getStampsQuery($stamps_batch);
            $ret = $queue_coll->remove($stamps_query); // ok == 1, err null
            if (isset($ret['err']) && !is_null($ret['err'])) {
                return FALSE;
            }
            Billrun_Factory::log('Removed ' . $ret['n'] . ' records from queue', Zend_Log::DEBUG);
            $offset += $batch_size;
        }

        Billrun_Factory::log('Starting to reset balances', Zend_Log::DEBUG);
        $ret = $this->resetBalances($update_aids); // err null
        if (isset($ret['err']) && !is_null($ret['err'])) {
            return FALSE;
        }
        $this->addStampsToRebalnceQueue($stamps);
        Billrun_Factory::log('Resetting ' . count($stamps) . ' lines', Zend_Log::DEBUG);
        $this->updateLines($stamps, $update, $lines_coll);
        if (!empty($this->splitLinesStamp)) {
            $split_lines_stamps_query = $this->getStampsQuery($this->splitLinesStamp);
            Billrun_Factory::log("Removing split lines", Zend_Log::DEBUG);
            $ret = $lines_coll->remove($split_lines_stamps_query); // err null
            if (isset($ret['err']) && !is_null($ret['err'])) {
                return FALSE;
            }
        }
        if (Billrun_Factory::db()->compareServerVersion('2.6', '>=') === true) {
            $offset = 0;
            $batch_size = Billrun_Config::getInstance()->getConfigValue('resetlines.queue.insert_size', '10000');
            Billrun_Factory::log("Queue batch insert size is " . $batch_size, Zend_Log::DEBUG);
            while ($queue_batch = array_slice($queue_lines, $offset, $batch_size)) {
                try {
                    Billrun_Factory::log("batchinsert " . count($queue_batch) . " queue lines start", Zend_Log::DEBUG);
                    $ret = $queue_coll->batchInsert($queue_batch); // ok==true, nInserted==0 if w was 0
                    Billrun_Factory::log("batchinsert queue lines end", Zend_Log::DEBUG);
                    if (isset($ret['err']) && !is_null($ret['err'])) {
                        Billrun_Factory::log('Rebalance: batch insertion to queue failed, Insert Error: ' . $ret['err'], Zend_Log::ALERT);
                        throw new Exception();
                    }
                } catch (Exception $e) {
                    Billrun_Factory::log("Rebalance: Batch insert failed during insertion to queue, inserting line by line, Error: " . $e->getMessage(), Zend_Log::ERR);
                    $this->insertQueueLinesLineByLine($queue_coll, $queue_batch);
                }
                $offset += $batch_size;
            }
        } else {
            Billrun_Factory::log("foreach queue lines", Zend_Log::DEBUG);
            $this->insertQueueLinesLineByLine($queue_coll, $queue_lines);
        }
        $this->removeStampsfromRebalnceQueue($stamps);
        $this->unsetTx2FromRelevantBalances();
        foreach ($this->linesStampsByRebalanceStamp as $rebalanceStamp => $linesStamps) {
            Billrun_Factory::log('Updating rebalance stamps field ' . $rebalanceStamp . ' for ' . count($linesStamps) . ' lines', Zend_Log::DEBUG);
            $update = array('$push' => array(
                    'rebalance_stamps' => $rebalanceStamp,
                )
            );
            $this->updateLines($linesStamps, $update, $lines_coll);
        }
        Billrun_Factory::log('Rebalance stamps field updated for ' . count($stamps) . ' lines', Zend_Log::DEBUG);
        $this->linesStampsByRebalanceStamp = [];
        return true;
    }

    protected function updateLines($stamps, $update, $lines_coll) {
        $offset = 0;
        $batch_size = Billrun_Config::getInstance()->getConfigValue('resetlines.lines.update_size', '10000');
        $i=1;
        $totalStamps = count($stamps);
        $totalIteration = ceil($totalStamps/$batch_size);
        while ($stamps_batch = array_slice($stamps, $offset, $batch_size)) {
            $stamps_query = $this->getStampsQuery($stamps_batch);
            $ret = $lines_coll->update($stamps_query, $update, array('multiple' => true)); // err null
            if (isset($ret['err']) && !is_null($ret['err'])) {
                return FALSE;
            }
            Billrun_Factory::log('update lines. ' . $i . ' iteration from ' . $totalIteration . ' iterations', Zend_Log::DEBUG);
            $offset += $batch_size;
            $i++;
        }
    }

    protected function insertQueueLinesLineByLine($queue_coll, $queue_lines) {
        foreach ($queue_lines as $qline) {
            try {
                $ret = $queue_coll->insert($qline); // ok==1, err null
                if (isset($ret['err']) && !is_null($ret['err'])) {
                    Billrun_Factory::log('Rebalance: line insertion to queue failed, Insert Error: ' . $ret['err'] . ', failed_line ' . $qline['stamp'], Zend_Log::ALERT);
                    continue;
                }
            } catch (Exception $e) {
                if (in_array($e->getCode(), Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR)) {
                    Billrun_Factory::log('Rebalance: line insertion to queue failed, Insert Error: ' . $e->getMessage() . ', failed_line ' . $qline['stamp'], Zend_Log::NOTICE);
                    continue;
                } else {
                    Billrun_Factory::log('Rebalance: line insertion to queue failed, Insert Error: ' . $e->getMessage() . ', failed_line ' . $qline['stamp'], Zend_Log::ALERT);
                    throw $e;
                }
            }
        }
    }

    protected function addStampsToRebalnceQueue($stamps) {
        foreach ($this->stampsByAidAndSid as $aid => $stampsBySid) {
            $query = $this->getRebalanceQueueQuery($aid);
            try {
                if ($this->checkIfStampsCanStoreInDB($stamps)) {
                    try {
                        $updateData = array('$set' => array('stamps_by_sid' => $stampsBySid));
                        Billrun_Factory::log("before update rebalance queue recover stamps", Zend_Log::DEBUG);
                        Billrun_Factory::db()->rebalance_queueCollection()->update($query, $updateData);
                        Billrun_Factory::log("after update rebalance queue recover stamps", Zend_Log::DEBUG);
                    } catch (Exception $ex) {
                        Billrun_Factory::log("Rebalance: failed to add stamps to rebalance queue, Error: " . $ex->getMessage(), Zend_Log::ERR);
                        $this->addStampsToRebalnceQueueFile($aid, $this->rebalnceQueueRecoverStampsPath, $stampsBySid, $query);          
                    }
                } else {
                    $this->addStampsToRebalnceQueueFile($aid, $this->rebalnceQueueRecoverStampsPath, $stampsBySid, $query);          
                }
            } catch (Exception $ex) {
                Billrun_Factory::log("Error: " . $ex->getMessage(), Zend_Log::ERR);
                throw $ex;
            }
        }
    }

    protected function addStampsToRebalnceQueueFile($aid, $path, $stampsBySid, $query) {
        $filename = $aid;
	Billrun_Factory::log("before write recover stamps to rebalance queue file", Zend_Log::DEBUG);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $fp = fopen($path . DIRECTORY_SEPARATOR . $filename, 'w');
        if (!$fp) {
            throw new Exception("Failed to write stamps to recover stamps file");
        }
        $ret = fwrite($fp, json_encode($stampsBySid));
        fclose($fp);
        if (!$ret) {
            throw new Exception("Failed to write stamps to recover stamps file");
        }
	Billrun_Factory::log("after write recover stamps to rebalance queue file", Zend_Log::DEBUG);
	Billrun_Factory::log("before update rebalance queue stamps recover path", Zend_Log::DEBUG);        
	$updateData = array('$set' => array('stamps_recover_path' => $this->rebalnceQueueRecoverStampsPath . DIRECTORY_SEPARATOR . $filename));
        Billrun_Factory::db()->rebalance_queueCollection()->update($query, $updateData);
	Billrun_Factory::log("after update rebalance queue stamps recover path", Zend_Log::DEBUG);
    }

    /**
     * @param mixed $stampsToInsert - the stamps we want to store in mongodb 
     * @param int $limit - the size of stamps that can be store in db entity (rebalance_queue/balance) . default 250k
     * @return boolean true if stamps can store in db. false otherwise
     */
    protected function checkIfStampsCanStoreInDB($stampsToInsert) {
        $limit = Billrun_Config::getInstance()->getConfigValue('resetlines.stamps_store_in_db.limit', 100000);
        if (count($stampsToInsert) >= $limit) {
            return false;
        }
        return true;
    }

    protected function removeStampsfromRebalnceQueue($stamps) {
        foreach ($this->stampsByAidAndSid as $aid => $stampsBySid) {
            $query = $this->getRebalanceQueueQuery($aid);
            if ($this->checkIfStampsCanStoreInDB($stamps)) {
                $updateData = [];
                $updateData['$unset']['stamps_by_sid'] = 1;
                Billrun_Factory::log("before remove rebalance queue recover stamps", Zend_Log::DEBUG);
                Billrun_Factory::db()->rebalance_queueCollection()->update($query, $updateData);
                Billrun_Factory::log("after remove rebalance queue recover stamps", Zend_Log::DEBUG);
            } else {
                Billrun_Factory::log("before remove rebalance queue recover stamps from file", Zend_Log::DEBUG);
                $this->removeStampsfromRebalnceQueueFile($aid);
                Billrun_Factory::log("after remove rebalance queue recover stamps from file", Zend_Log::DEBUG);
                Billrun_Factory::log("before remove stamps recover path from rebalance queue ", Zend_Log::DEBUG);
                $updateData = array('$unset' => array('stamps_recover_path' => 1));
                Billrun_Factory::db()->rebalance_queueCollection()->update($query, $updateData);
                Billrun_Factory::log("after remove stamps recover path from rebalance queue ", Zend_Log::DEBUG);
            }
        }
    }

    protected function removeStampsfromRebalnceQueueFile($aid) {
        $path = $this->rebalnceQueueRecoverStampsPath . DIRECTORY_SEPARATOR . $aid;
        $ret = unlink($path);
        if (!$ret) {
            Billrun_Factory::log("failed to delete recover stamps file. path: " . $path, Zend_Log::ALERT);
        }
    }

    protected function getRebalanceQueueQuery($aid) {
        return array('aid' => $aid, 'billrun_key' => $this->billrun_key);
    }

    protected function unsetTx2FromRelevantBalances() {
        $balances_coll = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
        if (!empty($this->aids) && !empty($this->billrun_key)) {
            foreach ($this->stampsByAidAndSid as $aid => $sids) {
                foreach ($sids as $sid => $stamps) {
                    $query = array(
                        'aid' => $aid,
                        'sid' => $sid
                    );
                    $updateData = [];
                    foreach ($stamps as $stamp) {
                        $query['tx2.' . $stamp] = array('$exists' => true);
                        $updateData['$unset']['tx2.' . $stamp] = 1;
                    }
                    if (empty($updateData)) {
                        continue;
                    }
                    $balances_coll->update($query, $updateData, array('multiple' => true));
                }
            }
        }
    }

    /**
     * method to calculate the usage need to be subtracted from the balance.
     * 
     * @param type $line
     * 
     */
    protected function aggregateLineUsage($line) {
        if (!isset($line['usagev']) || !isset($line['aprice'])) {
            return;
        }
        if(isset($this->balanceAlreadyUpdated[$line['stamp']])){
            return;
        }
        $this->balanceAlreadyUpdated[$line['stamp']] =  true;
        $lineInvoicingDay = $this->getLineInvoicingDay($line);
        $billrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($line['urt']->sec, $lineInvoicingDay);
        $arategroups = isset($line['arategroups']) ? $line['arategroups'] : array();
        foreach ($arategroups as $arategroup) {
            $balanceId = $arategroup['balance_ref']['$id']->{'$id'};
            $group = $arategroup['name'];
            $arategroupValue = isset($arategroup['usagev']) ? $arategroup['usagev'] : $arategroup['cost'];
            $aggregatedUsage = isset($this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['usage']) ? $this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['usage'] : 0;
            $this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['usage'] = $aggregatedUsage + $arategroupValue;
            $this->extendedBalanceByLine[$line['stamp']][$line['aid']][$balanceId][$group][$line['usaget']]['usage'] = $aggregatedUsage + $arategroupValue;
            @$this->extendedBalanceUsageSubtract[$line['aid']][$balanceId][$group][$line['usaget']]['count'] += 1;
            @$this->extendedBalanceByLine[$line['stamp']][$line['aid']][$balanceId][$group][$line['usaget']]['count'] += 1;
            $groupUsage = isset($this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['usage']) ? $this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['usage'] : 0;
            $this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['usage'] = $groupUsage + $arategroupValue;
            @$this->balanceSubstract[$line['aid']][$line['sid']][$billrunKey]['groups'][$group][$line['usaget']]['count'] += 1;
        }

        if ($this->affectsMainBalance($line)) {
            if (!empty(($line['over_group']))) {
                Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'over_group', 'usage'], $line['over_group']);
            }
            if (!empty(($line['out_group']))) {
                Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'out_group', 'usage'], $line['out_group']);
            }
            Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'usage'], $this->getMainBalanceUsage($line));
            Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'cost'], $line['aprice']);
            Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'totals', $line['usaget'], 'count'], 1);
            Billrun_Util::increaseIn($this->balanceSubstract, [$line['aid'], $line['sid'], $billrunKey, 'cost'], $line['aprice']);
        }
    }

    protected function affectsMainBalance($line) {
        $arategroups = $line['arategroups'] ?? [];
        return empty($arategroups) ||
                $this->isInMainBalance($arategroups) ||
                (isset($line['over_group']) && $line['over_group'] > 0 && isset($line['in_group']) && $line['in_group'] > 0);
    }

    protected function getMainBalanceUsage($line) {
        $arategroups = $line['arategroups'] ?? [];
        if (empty($arategroups)) {
            return $line['usagev'];
        }
        $ret = 0;
        if (!empty($line['over_group'])) {
            $ret += $line['over_group'];
        } else if (!empty($line['out_group'])) {
            $ret += $line['out_group'];
        }

        foreach ($arategroups as $arategroup) {
            $balanceId = $arategroup['balance_ref']['$id']->{'$id'};
            if ($this->isMainBalance($balanceId)) {
                $ret += $arategroup['usagev'] ?? 0;
            }
        }

        return $ret;
    }

    protected function getRelevantBalances($balances, $balanceId, $params = array(), $invoicing_day = null) {
        $this->alreadyUpdated = [];
        $ret = [];
        foreach ($balances as $balance) {
            $rawData = $balance->getRawData();
            if (isset($rawData['_id']) && !empty($balanceId) && $rawData['_id']->{'$id'} == $balanceId) {
                return [$rawData];
            }

            if (empty($balanceId) && !empty($params)) {
                $startTime = Billrun_Billingcycle::getStartTime($params['billrun_key'], $invoicing_day);
                $endTime = Billrun_Billingcycle::getEndTime($params['billrun_key'], $invoicing_day);
                if ($params['aid'] == $rawData['aid'] && in_array($rawData['sid'], [$params['sid'], 0]) && $startTime == $rawData['from']->sec && $endTime == $rawData['to']->sec) {
                    $ret[] = $rawData;
                }
            }
        }
        return !empty($ret) ? $ret : false;
    }

    protected function buildUpdateBalance($balance, $volumeToSubstract, $totalsUsage = array(), $balanceCost = 0) {
        $isMainBlalance = isset($balance['balance']['totals']);
        $update = array();
        foreach ($volumeToSubstract as $group => $usaget) {
            foreach ($usaget as $usageType => $usagev) {
                if (isset($balance['balance']['groups'][$group])) {
                    $usedUsage = isset($balance['balance']['groups'][$group]['usagev']) ? $balance['balance']['groups'][$group]['usagev'] : $balance['balance']['groups'][$group]['cost'];
                    $usage = min($usagev['usage'] + ($this->alreadyUpdated[$group]['usage'] ?? 0), $usedUsage);
                    $count = min($usagev['count'] + ($this->alreadyUpdated[$group]['count'] ?? 0), $balance['balance']['groups'][$group]['count']);
                    $update['$set']['balance.groups.' . $group . '.left'] = $balance['balance']['groups'][$group]['left'] + $usage;
                    $usageToSet = $usedUsage - $usage;
                    if (isset($balance['balance']['groups'][$group]['usagev'])) {
                        $update['$set']['balance.groups.' . $group . '.usagev'] = $usageToSet;
                    } else if (isset($balance['balance']['groups'][$group]['cost'])) {
                        $update['$set']['balance.groups.' . $group . '.cost'] = $usageToSet;
                    }
                    $update['$set']['balance.groups.' . $group . '.count'] = $balance['balance']['groups'][$group]['count'] - $count;
                    $this->alreadyUpdated[$group]['usage'] = $usage;
                    $this->alreadyUpdated[$group]['count'] = $count;
                }
            }
        }

        if ($isMainBlalance) {
            foreach ($totalsUsage as $usageType => $usage) {
                if (isset($usage['usage'])) {
                    $update['$set']['balance.totals.' . $usageType . '.usagev'] = $balance['balance']['totals'][$usageType]['usagev'] - $usage['usage'];
                }
                if (isset($usage['cost'])) {
                    $update['$set']['balance.totals.' . $usageType . '.cost'] = $balance['balance']['totals'][$usageType]['cost'] - $usage['cost'];
                }
                if (isset($usage['count'])) {
                    $update['$set']['balance.totals.' . $usageType . '.count'] = $balance['balance']['totals'][$usageType]['count'] - $usage['count'];
                }
                $update['$set']['balance.cost'] = $balance['balance']['cost'] - $balanceCost;
                if (isset($usage['out_group'])) {
                    $update['$set']['balance.totals.' . $usageType . '.out_group.usagev'] = $balance['balance']['totals'][$usageType]['out_group']['usagev'] - $usage['out_group']['usage'];
                }
                if (isset($usage['over_group'])) {
                    $update['$set']['balance.totals.' . $usageType . '.over_group.usagev'] = $balance['balance']['totals'][$usageType]['over_group']['usagev'] - $usage['over_group']['usage'];
                }
            }
        }
        return $update;
    }

    protected function resetExtendedBalances($aids, $balancesColl) {
        if (empty($this->extendedBalanceUsageSubtract)) {
            return;
        }
        $verifiedArray = Billrun_Util::verify_array($aids, 'int');
        $aidsAsKeys = array_flip($verifiedArray);
        $balancesToUpdate = array_intersect_key($this->extendedBalanceUsageSubtract, $aidsAsKeys);
        $queryBalances = array(
            'aid' => array('$in' => $aids),
            'period' => array('$ne' => 'default'),
        );
        $balances = $balancesColl->query($queryBalances)->cursor();
        foreach ($balancesToUpdate as $aid => $packageUsage) {
            $account = Billrun_Factory::account()->loadAccountForQuery(['aid' => $aid]);
            $invoicing_day = isset($account['invoicing_day']) ? $account['invoicing_day'] : Billrun_Factory::config()->getConfigChargingDay();
            foreach ($packageUsage as $balanceId => $usageByUsaget) {
                $relevantBalances = $this->getRelevantBalances($balances, $balanceId, [], $invoicing_day);
                if (empty($relevantBalances)) {
                    continue;
                }
                foreach ($relevantBalances as $balanceToUpdate) {
                    if (empty($balanceToUpdate)) {
                        continue;
                    }
                    $updateData = $this->buildUpdateBalance($balanceToUpdate, $usageByUsaget);
                    $query = array(
                        '_id' => new MongoId($balanceId),
                    );
                    $stamps = array_merge($this->stampsToRecoverByAidAndSid[$balanceToUpdate['aid']][$balanceToUpdate['sid']] ?? [], $this->stampsByAidAndSid[$balanceToUpdate['aid']][$balanceToUpdate['sid']] ?? []);
                    foreach ($stamps as $stamp) {
                        $query['tx2.' . $stamp] = array('$exists' => false);
                        $updateData['$set']['tx2.' . $stamp] = true;
                    }
                    Billrun_Factory::log('Resetting extended balance for aid: ' . $aid . ', balance_id: ' . $balanceId, Zend_Log::DEBUG);
                    $ret = $balancesColl->update($query, $updateData);
                    if (isset($ret['err']) && !is_null($ret['err'])) {
                        Billrun_Factory::log('Rebalance: extended balance update failed, Error: ' . $ret['err'] . ', failed_balance ' . print_r($balanceToUpdate, 1), Zend_Log::ALERT);
                    }
                }
            }
        }
        $this->extendedBalanceUsageSubtract = array();
    }

    protected function resetDefaultBalances($aids, $balancesColl) {
        if (empty($this->balanceSubstract)) {
            return;
        }
        $queryBalances = array(
            'aid' => array('$in' => $aids),
            'period' => 'default',
        );
        $balances = $balancesColl->query($queryBalances)->cursor();
        $accounts = Billrun_Factory::account()->loadAccountsForQuery(['aid' => array('$in' => array_keys($this->balanceSubstract))]);
        foreach ($this->balanceSubstract as $aid => $usageBySid) {
            $current_account = array_filter($accounts, function ($account) use ($aid) {
                return $account['aid'] == $aid;
            });
            $invoicing_day = isset($current_account['invoicing_day']) ? $current_account['invoicing_day'] : Billrun_Factory::config()->getConfigChargingDay();
            foreach ($usageBySid as $sid => $usageByMonth) {
                foreach ($usageByMonth as $billrunKey => $usage) {
                    $relevantBalances = $this->getRelevantBalances($balances, '', array('aid' => $aid, 'sid' => $sid, 'billrun_key' => $billrunKey), $invoicing_day);
                    if (empty($relevantBalances)) {
                        continue;
                    }
                    foreach ($relevantBalances as $balanceToUpdate) {
                        if (empty($balanceToUpdate)) {
                            continue;
                        }
                        $groups = !empty($usage['groups']) ? $usage['groups'] : array();
                        $totals = !empty($usage['totals']) ? $usage['totals'] : array();
                        $cost = !empty($usage['cost']) ? $usage['cost'] : 0;
                        $updateData = $this->buildUpdateBalance($balanceToUpdate, $groups, $totals, $cost);
                        if (empty($updateData)) {
                            continue;
                        }
                        $query = array(
                            '_id' => $balanceToUpdate['_id'],
                        );
                        $stamps = array_merge($this->stampsToRecoverByAidAndSid[$balanceToUpdate['aid']][$balanceToUpdate['sid']] ?? [], $this->stampsByAidAndSid[$balanceToUpdate['aid']][$balanceToUpdate['sid']] ?? []);
                        foreach ($stamps as $stamp) {
                            $query['tx2.' . $stamp] = array('$exists' => false);
                            $updateData['$set']['tx2.' . $stamp] = true;
                        }
                        Billrun_Factory::log('Resetting default balance for sid: ' . $sid . ', billrun: ' . $billrunKey, Zend_Log::DEBUG);
                        $ret = $balancesColl->update($query, $updateData);
                        if (isset($ret['err']) && !is_null($ret['err'])) {
                            Billrun_Factory::log('Rebalance: default balance update failed, Error: ' . $ret['err'] . ', failed_balance ' . print_r($balanceToUpdate, 1), Zend_Log::ALERT);
                        }
                    }
                }
            }
        }
        $this->balanceSubstract = array();
        return $ret;
    }

    protected function initBalances($aids) {
        $queryBalances = array(
            'aid' => array('$in' => $aids),
        );

        $balances = Billrun_Factory::db()->balancesCollection()->query($queryBalances)->cursor();
        foreach ($balances as $balance) {
            $balanceId = $balance->getRawData()['_id']->{'$id'};
            $this->balances[$balanceId] = $balance;
        }
    }

    protected function isInExtendedBalance($arategroups) {
        $arategroupBalances = array_column($arategroups, 'balance_ref');
        foreach ($arategroupBalances as $balanceRef) {
            $balanceId = $balanceRef['$id']->{'$id'};
            if (isset($this->balances[$balanceId]) && $this->balances[$balanceId]['period'] != 'default') {
                return true;
            }
        }

        return false;
    }

    protected function isInMainBalance($arategroups) {
        $arategroupBalances = array_column($arategroups, 'balance_ref');
        foreach ($arategroupBalances as $balanceRef) {
            $balanceId = $balanceRef['$id']->{'$id'};
            if ($this->isMainBalance($balanceId)) {
                return true;
            }
        }

        return false;
    }

    protected function isMainBalance($balanceId) {
        return isset($this->balances[$balanceId]) &&
                $this->balances[$balanceId]['period'] == 'default' &&
                !empty($this->balances[$balanceId]['balance']['totals']);
    }

    protected function buildConditionsQuery($updateAids) {
        $conditionsQuery = array();
        $groupedAids = array();
        $conditionsHashArray = array();
        $rebalanceStamps = array();
        foreach ($this->conditions as $aid => $conditionsByHash) {
            if (!in_array($aid, $updateAids)) {
                continue;
            }
            foreach ($conditionsByHash as $conditionHash => $conditions) {
                $conditionsHashArray[$conditionHash] = $conditions;
                $groupedAids[$conditionHash][] = $aid;
                if (!empty($this->rebalanceStamps)) {//if avoid_repeating_reset = false must be empty
                    $rebalanceStamps[] = $this->rebalanceStamps[$aid][$conditionHash];
                }
            }
        }
        foreach ($groupedAids as $conditionHash => $aids) {
            if (empty($conditionsHashArray[$conditionHash])) {
                $translatedCondition = array();
            } else {
                $translatedCondition = $this->translateConditionArrayToQuery($conditionsHashArray[$conditionHash]);
            }
            $rebalanceStampsQuery = array();
            if (!empty($rebalanceStamps)) { //if avoid_repeating_reset = false must be empty
                $rebalanceStampsQuery = array('rebalance_stamps' => array('$nin' => $rebalanceStamps));
            }
            $conditionsQuery['$or'][] = array_merge(array_merge(
                            array('aid' => array('$in' => $aids)),
                            $translatedCondition), $rebalanceStampsQuery
            );
        }

        return $conditionsQuery;
    }

    protected function translateConditionArrayToQuery($conditionArray) {
        foreach ($conditionArray as $orCondValue) {
            $andStructure = array();
            foreach ($orCondValue as $andCondValue) {
                $andStructure[] = $this->translateCondition($andCondValue);
            }
            $orStructure[] = array('$and' => $andStructure);
        }

        return array('$or' => $orStructure);
    }

    protected function translateCondition($condition) {
        $op = $condition['op'];
        return array($condition['field_name'] => array("$op" => $condition['value']));
    }

    protected function getLineInvoicingDay($line) {
        return isset($line['foregin']['account']['invoicing_day']) ? $line['foregin']['account']['invoicing_day'] : Billrun_Factory::config()->getConfigChargingDay();
    }	

}
