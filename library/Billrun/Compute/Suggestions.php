<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract suggestions class
 *
 * @package  Billing
 */
abstract class Billrun_Compute_Suggestions extends Billrun_Compute {

    protected $suggestions;

    public function compute() {
        if (!$this->isRecalculateEnabled()) {
            Billrun_Factory::log()->log('suggest recalculations ' . $this->getRecalculateType() . ' mode is off', Zend_Log::INFO);
            return;
        }
        Billrun_Factory::log()->log('Starting to search suggestions for ' . $this->getRecalculateType(), Zend_Log::INFO);
        $retroactiveChanges = $this->findAllTheRetroactiveChanges();
        $this->suggestions = $this->getSuggestions($retroactiveChanges);
        Billrun_Factory::log()->log('finished to search suggestions for ' . $this->getRecalculateType(), Zend_Log::INFO);
    }
    
    public function getComputedType() {
        return 'suggestions';
    }

    protected function getSuggestions($retroactiveChanges) {
        $suggestions = [];
        $lines = $this->findAllMatchingLines($retroactiveChanges);
        foreach ($lines as $line) {
            if ($this->checkIfValidLine($line)) {
                $suggestions[] = $this->buildSuggestion($line);
            }
        }
        return $suggestions;
    }

    protected function findAllTheRetroactiveChanges() {
        Billrun_Factory::log()->log("Searching all the retroactive " . $this->getRecalculateType() . " changes", Zend_Log::INFO);
        $query = array(
            'collection' => $this->getCollectionName(),
            'suggest_recalculations' => array('$ne' => true),
            //check all the relevant types (update/permanentchange through GUI / rates importer / API) 
            'type' => array('$in' => ['update', 'closeandnew', 'permanentchange']),
            //retroactive change
            'new.from' => array(
                '$lt' => new MongoDate()
            )
        );
        $update = array(
            '$set' => array(
                'suggest_recalculations' => true
            )
        );
        //check if can be done in one command. 
        $retroactiveChanges = iterator_to_array(Billrun_Factory::db()->auditCollection()->find($query)->sort(array('_id' => 1)));
        Billrun_Factory::db()->auditCollection()->update($query, $update, array('multiple' => true));

        $validRetroactiveChanges = $this->getValidRetroactiveChanges($retroactiveChanges);

        Billrun_Factory::log()->log("found " . count($retroactiveChanges) . " retroactive " . $this->getRecalculateType() . " changes", Zend_Log::INFO);
        return $validRetroactiveChanges;
    }

    protected function findAllMatchingLines($retroactiveChanges) {
        $matchingLines = array();
        $now = new MongoDate();
        foreach ($retroactiveChanges as $retroactiveChange) {
            $filters = array_merge(
                    array(
                        'urt' => array(
                            '$gte' => $retroactiveChange['new']['from'],
                            '$lt' => ($retroactiveChange['new']['to'] < $now ? $retroactiveChange['new']['to'] : $now)
                        ),
                        $this->getFieldNameOfLine() => $retroactiveChange['key'],
                        'in_queue' => array('$ne' => true)
                    ), $this->addFiltersToFindMatchingLines());
            $query = array(
                array(
                    '$match' => $filters
                ),
                array(
                    '$group' => array_merge(
                            array(
                                '_id' => array_merge(
                                        array(
                                            'aid' => '$aid',
                                            'sid' => '$sid',
                                            'billrun' => '$billrun',
                                            'key' => '$' . $this->getFieldNameOfLine()
                                        ), $this->addGroupsIdsForMatchingLines()
                                ),
                                'firstname' =>array(
                                    '$first' => '$firstname'
                                ),
                                'lastname' =>array(
                                    '$first' => '$lastname'
                                ),
                                'from' => array(
                                    '$min' => '$urt'
                                ),
                                'to' => array(
                                    '$max' => '$urt'
                                ),
                                'aprice' => array(
                                    '$sum' => '$aprice'
                                ),
                                'usagev' => array(
                                    '$sum' => '$usagev'
                                ),
                                'total_lines' => array(
                                    '$sum' => 1
                                )
                            ), $this->addGroupsForMatchingLines()
                    )
                ),
                array(
                    '$addFields' => array_merge(
                            array(
                      
                                'retroactive_change_info' =>array(
                                    array(
                                        'urt' => $retroactiveChange['urt']
                                    ),
                                    array(
                                        'from' => $retroactiveChange['new']['from']
                                    ),
                                    array(
                                        'old_price' => $retroactiveChange['urt']
                                    ),
                                    array(
                                        'new_price' => $retroactiveChange['urt']
                                    ),
                                ),
                            ), $this->addFieldsForMatchingLines()
                    )
                ),
                array(
                    '$project' => array_merge(
                            array(
                                '_id' => 0,
                                'aid' => '$_id.aid',
                                'sid' => '$_id.sid',
                                'firstname' => '$_id.firstname',
                                'lastname' => '$_id.lastname',
                                'billrun' => '$_id.billrun',
                                'key' => '$_id.key',
                                'from' => 1,
                                'to' => 1,
                                'aprice' => 1,
                                'usagev' => 1,
                                'total_lines' => 1,
                            ), $this->addProjectsForMatchingLines()
                    )
                ),
            );
            $lines = iterator_to_array(Billrun_Factory::db()->linesCollection()->aggregate($query));
            $matchingLines = array_merge($matchingLines, $lines);
        }
        return $matchingLines;
    }

    protected function buildSuggestion($line) {
        //params to search the suggestions and params to for creating onetimeinvoice/rebalance.  
        $suggestion = array(
            'recalculationType' => $this->getRecalculateType(),
            'aid' => $line['aid'],
            'sid' => $line['sid'],
            'firstname' => $line['firstname'],
            'lastname' => $line['lastname'],
            'billrun_key' => $line['billrun'],
            'from' => $line['from'],
            'to' => new MongoDate(strtotime('+1 sec', $line['to']->sec)),
            'usagev' => $line['usagev'],
            'key' => $line['key'],
            'status' => 'open',
            'total_lines' => $line['total_lines']
        );
        $oldPrice = $line['aprice'];
        $newPrice = $this->recalculationPrice($line);
        $amount = $newPrice - $oldPrice;
        $suggestion['amount'] = abs($amount);
        $suggestion['type'] = $amount > 0 ? 'debit' : 'credit';
        $suggestion['stamp'] = $this->getSuggestionStamp($suggestion);
        $suggestion['urt'] = new MongoDate();
        return $suggestion;
    }

    protected function getValidRetroactiveChanges($retroactiveChanges) {
        $validRetroactiveChanges = [];
        foreach ($retroactiveChanges as $retroactiveChange) {
            if ($this->checkIfValidRetroactiveChange($retroactiveChange)) {
                $validRetroactiveChanges[] = $retroactiveChange;
            }
        }
        return $validRetroactiveChanges;
    }

    public function write() {
        $suggestionToInsert = [];
        if (!empty($this->suggestions)) {
            foreach ($this->suggestions as $suggestion) {

                $overlapSuggestion = $this->getOverlap($suggestion);
                if (!$overlapSuggestion->isEmpty()) {
                    if ($this->checkIfTheSameSuggestion($overlapSuggestion, $suggestion)) {
                        continue;
                    } else {
                        $this->handleOverlapSuggestion($overlapSuggestion, $suggestion);
                    }
                } else {
                    if ($suggestion['amount'] != 0) {
                        $suggestionToInsert[] = $suggestion;
                    }
                }
            }

            Billrun_Factory::db()->suggestionsCollection()->batchInsert($suggestionToInsert);
        }
        Billrun_Factory::log()->log("Writing " . count($suggestionToInsert) . " suggestion to suggestions collection...", Zend_Log::INFO);
    }

    protected function getOverlap($suggestion) {
        $query = array(
            'aid' => $suggestion['aid'],
            'sid' => $suggestion['sid'],
            'billrun_key' => $suggestion['billrun_key'],
            'key' => $suggestion['key'],
            'status' => 'open',
            'recalculationType' => $suggestion['recalculationType']
        );
        return Billrun_Factory::db()->suggestionsCollection()->query($query)->cursor()->limit(1)->current();
    }

    protected function checkIfTheSameSuggestion($overlapSuggestion, $suggestion) {
        return $overlapSuggestion['stamp'] === $suggestion['stamp'];
    }

    protected function handleOverlapSuggestion($overlapSuggestion, $suggestion) {

        $fakeRetroactiveChanges = $this->buildFakeRetroactiveChanges($overlapSuggestion, $suggestion);
        $newSuggestions = $this->getSuggestions($fakeRetroactiveChanges);
        $newSuggestion = $this->unifyOverlapSuggestions($newSuggestions);
        //TODO:: consider update instead of remove and insert
        if ($newSuggestion['amount'] != 0) {
            Billrun_Factory::db()->suggestionsCollection()->insert($newSuggestion);
        }
        Billrun_Factory::db()->suggestionsCollection()->remove($overlapSuggestion);
    }

    protected function buildFakeRetroactiveChanges($overlapSuggestion, $suggestion) {
        $fakeRetroactiveChanges = array();
        $fakeRetroactiveChange['key'] = $overlapSuggestion['key']; //equal to suggestion['key']
        $oldFrom = min($overlapSuggestion['from'], $suggestion['from']);
        $newFrom = max($overlapSuggestion['from'], $suggestion['from']);
        $oldTo = min($overlapSuggestion['to'], $suggestion['to']);
        $newTo = max($overlapSuggestion['to'], $suggestion['to']);
        if ($oldFrom !== $newFrom) {
            $fakeRetroactiveChange['new']['from'] = $oldFrom;
            $fakeRetroactiveChange['new']['to'] = new MongoDate(strtotime('-1 sec', $newFrom->sec));
            $fakeRetroactiveChanges[] = $fakeRetroactiveChange;
        }
        if ($oldTo !== $newTo) {
            $fakeRetroactiveChange['new']['from'] = $oldTo;
            $fakeRetroactiveChange['new']['to'] = $newTo;
            $fakeRetroactiveChanges[] = $fakeRetroactiveChange;
        }
        if ($newFrom !== $oldTo) {
            $fakeRetroactiveChange['new']['from'] = $newFrom;
            $fakeRetroactiveChange['new']['to'] = $oldTo;
            $fakeRetroactiveChanges[] = $fakeRetroactiveChange;
        }
        return $fakeRetroactiveChanges;
    }

    protected function unifyOverlapSuggestions($suggestions) {
        $newSuggestion = $suggestions[0];
        $newSuggestion['usagev'] = 0;
        $newSuggestion['total_lines'] = 0;
        $aprice = 0;
        foreach ($suggestions as $suggestion) {
            $aprice += $suggestion['type'] === 'credit' ? (0 - $suggestion['amount']) : $suggestion['amount'];
            $this->unifyOverlapSuggestion($newSuggestion, $suggestion);
        }
        $newSuggestion['type'] = $aprice < 0 ? 'credit' : 'debit';
        $newSuggestion['amount'] = abs($aprice);
        return $newSuggestion;
    }

    protected function unifyOverlapSuggestion(&$newSuggestion, $suggestion) {
        $newSuggestion['from'] = min($suggestion['from'], $newSuggestion['from']);
        $newSuggestion['to'] = max($suggestion['to'], $newSuggestion['to']);
        $newSuggestion['usagev'] += $suggestion['usagev'];
        $newSuggestion['total_lines'] += $suggestion['total_lines'];
    }

    protected function getSuggestionStamp($suggestion) {
        unset($suggestion['urt']);
        unset($suggestion['stamp']);
        return Billrun_Util::generateArrayStamp($suggestion);
    }

    protected function fromRevisionChange($retroactiveChange) {
        return $retroactiveChange['old']['from']->sec !== $retroactiveChange['new']['from']->sec;
    }

    protected function toRevisionChange($retroactiveChange) {
        return $retroactiveChange['old']['to']->sec !== $retroactiveChange['new']['to']->sec;
    }

    protected function addFiltersToFindMatchingLines() {
        return array();
    }

    protected function addGroupsForMatchingLines() {
        return array();
    }

    protected function addGroupsIdsForMatchingLines() {
        return array();
    }

    protected function addProjectsForMatchingLines() {
        return array();
    }
    
    protected function addFieldsForMatchingLines() {
        return array();
    }

    protected function checkIfValidLine($line) {
        return true;
    }

    abstract protected function checkIfValidRetroactiveChange($retroactiveChange);

    abstract protected function getCollectionName();

    abstract protected function getFieldNameOfLine();

    abstract protected function recalculationPrice($line);

    abstract protected function getRecalculateType();
    
    abstract protected function isRecalculateEnabled();
    
    abstract static protected function getCoreIntervals();
    
    abstract static protected function getCmd();
    
    static public function runCommand() {
        $minutesIntervals = static::getCoreIntervals();
        $currentMinute = date('i');
        if ($currentMinute == 0) {
            $currentMinute = 60;
        }
        $minutesToRun = [];
        foreach ($minutesIntervals as $minuteInterval) {
            if ($minuteInterval == 0) {
                $minuteInterval = 60;
            }
            if ($currentMinute % $minuteInterval == 0) {
                $minutesToRun[] = $minuteInterval;
            }
        }
        if (!empty($minutesToRun)) {
            $command = static::getCmd();
            try {
                Billrun_Factory::log('Running compute suggestions on background', Billrun_Log::INFO);
                Billrun_Util::forkProcessCli($command);
            } catch (Exception $ex) {
                Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ALERT);
            }
        }
    }
}
