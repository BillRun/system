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
            $isFake = $retroactiveChange['is_fake'] ?? false;
            $filters = array_merge(
                    array(
                        'urt' => array(
                            '$gte' => $retroactiveChange['new']['from'],
                            '$lt' => $retroactiveChange['new']['to'] < $now ? $retroactiveChange['new']['to'] : $now
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
                                'retroactive_change_info.urt' => $retroactiveChange['urt'],
                                'retroactive_change_info.from' => !$isFake ? $retroactiveChange['new']['from'] : $retroactiveChange['real']['new']['from'],
                                'retroactive_change_info.old_price' => !$isFake ? $this->getRetroactiveChangeOldPrice($retroactiveChange['old']) : $retroactiveChange['real']['old_price'],
                                'retroactive_change_info.new_price' => !$isFake ? $this->getRetroactiveChangeNewPrice($retroactiveChange['new']) : $retroactiveChange['real']['new_price']
                            ), $this->addFieldsForMatchingLines($retroactiveChange)
                    )
                ),
                array(
                    '$project' => array_merge(
                            array(
                                '_id' => 0,
                                'aid' => '$_id.aid',
                                'sid' => '$_id.sid',
                                'billrun' => '$_id.billrun',
                                'key' => '$_id.key',
                                'firstname' => 1,
                                'lastname' => 1,
                                'from' => 1,
                                'to' => 1,
                                'aprice' => 1,
                                'usagev' => 1,
                                'total_lines' => 1,
                                'retroactive_change_info.urt' => 1,
                                'retroactive_change_info.from' => 1,
                                'retroactive_change_info.old_price' => 1,
                                'retroactive_change_info.new_price' => 1
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
        $suggestion = array_merge(array(
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
            'total_lines' => $line['total_lines'],
            'retroactive_change_info' => array(
                'urt' => $line['retroactive_change_info.urt'], 
                'from' => $line['retroactive_change_info.from'],
                'old_price' => intval($line['retroactive_change_info.old_price']), 
                'new_price' => intval($line['retroactive_change_info.new_price'])
            )
        ), $this->addForeignFieldsForSuggestion($line));
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
        if (!empty($this->suggestions)) {
            foreach ($this->suggestions as $suggestion) {

                $overlapSuggestion = $this->getOverlap($suggestion);
                if (!$overlapSuggestion->isEmpty()) {
                    $overlapSuggestion = $overlapSuggestion->getRawData();
                    if ($this->checkIfTheSameSuggestion($overlapSuggestion, $suggestion)) {
                        continue;
                    } else {
                        $this->handleOverlapSuggestion($overlapSuggestion, $suggestion);
                    }
                } else {
                    if ($suggestion['amount'] != 0) {
                         Billrun_Factory::db()->suggestionsCollection()->insert($suggestion);
                    }
                }
            }
        }
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
            $this->addFieldsForFakeRetroactiveChange($fakeRetroactiveChange, $overlapSuggestion, $suggestion, 'from');
            $fakeRetroactiveChanges[] = $fakeRetroactiveChange;
        }
        if ($oldTo !== $newTo) {
            $fakeRetroactiveChange['new']['from'] = $oldTo;
            $fakeRetroactiveChange['new']['to'] = $newTo;
            $this->addFieldsForFakeRetroactiveChange($fakeRetroactiveChange, $overlapSuggestion, $suggestion, 'to');
            $fakeRetroactiveChanges[] = $fakeRetroactiveChange;
        }
        if ($newFrom !== $oldTo) {
            $fakeRetroactiveChange['new']['from'] = $newFrom;
            $fakeRetroactiveChange['new']['to'] = $oldTo;
            $this->addFieldsForFakeRetroactiveChange($fakeRetroactiveChange, $overlapSuggestion, $suggestion, 'from');
            $fakeRetroactiveChanges[] = $fakeRetroactiveChange;
        }
        return $fakeRetroactiveChanges;
    }
    
    private function addFieldsForFakeRetroactiveChange(&$fakeRetroactiveChange, $overlapSuggestion, $suggestion, $field) {
        $relvantSuggestion = $fakeRetroactiveChange['new']['from'] === $suggestion[$field] ? $suggestion : $overlapSuggestion;
        $fakeRetroactiveChange['is_fake'] = true;
        $fakeRetroactiveChange['real']['old_price'] = Billrun_Util::getIn($relvantSuggestion, 'retroactive_change_info.old_price');
        $fakeRetroactiveChange['real']['new_price'] = Billrun_Util::getIn($relvantSuggestion, 'retroactive_change_info.new_price');
        $fakeRetroactiveChange['real']['new']['from'] = Billrun_Util::getIn($relvantSuggestion, 'retroactive_change_info.from');
        $fakeRetroactiveChange['urt'] = Billrun_Util::getIn($relvantSuggestion, 'retroactive_change_info.urt');     
    }

    protected function unifyOverlapSuggestions($suggestions) {
        $newSuggestion = $suggestions[0];
        $newSuggestion['usagev'] = 0;
        $newSuggestion['total_lines'] = 0;
        $aprice = 0;
        foreach ($suggestions as $suggestion) {
            if($suggestion['amount'] != 0) {
                $aprice += $suggestion['type'] === 'credit' ? (0 - $suggestion['amount']) : $suggestion['amount'];
                $this->unifyOverlapSuggestion($newSuggestion, $suggestion);
            }
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
    
    protected function addForeignFieldsForSuggestion($line) {
        return array();
    }
    
    protected function addFieldsForMatchingLines($retroactiveChange) {
        return array();
    }

    protected function checkIfValidLine($line) {
        return true;
    }
    
    abstract protected function getRetroactiveChangeOldPrice($retroactiveChangeOld);
    
    abstract protected function getRetroactiveChangeNewPrice($retroactiveChangeNew);

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
