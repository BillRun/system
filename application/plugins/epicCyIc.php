<?php

class epicCyIcPlugin extends Billrun_Plugin_BillrunPluginBase {

	public function afterProcessorParsing($processor) {
		if ($processor->getType() === 'ICT') {
			$dataRows = $processor->getData()['data'];
			foreach ($dataRows as $row){
				if($row["usaget"] == "transit_incoming_call") {
					$newRow = $row;
					$newRow['usaget'] = "transit_outgoing_call";
					$stampParams = Billrun_Util::generateFilteredArrayStamp($newRow, array('urt', 'eurt', 'uf', 'usagev', 'usaget', 'usagev_unit', 'connection_type'));
					$newRow['stamp'] = md5(serialize($stampParams));
					$processor->addDataRow($newRow);
				}
			}
		}
	}
	
	
	public function beforeCalculateData($dataRows, &$lines, Billrun_Calculator $calculator) {
		if($calculator->getType() == 'rate'){
			foreach ($lines as $stamp => $row){
				unset($lines[$stamp]);
				$this->calcCfFields($row, $calculator);
			}
		}
	}

	protected function calcCfFields($row, Billrun_Calculator $calculator) {
		$current = $row->getRawData();
		$type = $current['type'];
		$current["cf"]["call_direction"] = $this->determineCallDirection($current["usaget"]);
		$row->setRawData($current);
		$this->setOperator($row, $current, $type, $calculator);
		$row->setRawData($current);

		//$row->setRawData(setParameter($current, ["operator", "poin"], $operator_entity));

		$product_entity = $this->getParameterProduct($type, "parameter_product", $row, $calculator);
		if(!$product_entity) {
			$this->addSplitRow($current, $calculator);
			return;
		}
		$current["cf"]["product"] = $product_entity["params"]["product"];
		$row->setRawData($current);

		$anaa_entity = $this->getParameterProduct($type, "parameter_anaa", $row, $calculator);
		if(!$anaa_entity) {
			$this->addSplitRow($current, $calculator);
			return;
		}
		$current["cf"]["anaa"] = $anaa_entity["params"]["anaa"];
		$row->setRawData($current);

		$sms_activity_types = ["incoming_sms","outgoing_sms"];
		if(!in_array($current["usaget"], $sms_activity_types)) {
			$bnaa_entity = $this->getParameterProduct($type, "parameter_bnaa", $row, $calculator);
			if(!$bnaa_entity) {
				$this->addSplitRow($current, $calculator);
				return;
			}
			$current["cf"]["bnaa"] = $bnaa_entity["params"]["bnaa"];
			$row->setRawData($current);
		}

		$scenario_entity = $this->getParameterProduct($type, "parameter_scenario", $row, $calculator);
		if(!$scenario_entity) {
			$this->addSplitRow($current, $calculator);
			return;
		}
		$current["cf"]["scenario"] = $scenario_entity["params"]["scenario"];
		$row->setRawData($current);
		if ($scenario_entity["params"]["anaa"] != "*") {
			$is_anaa_relevant = true;
		}

		//TODO: check if there are multiple results and split
		$component_entities = $this->getParameterProduct($type, "parameter_component", $row, $calculator, true);
		if(!$component_entities) {
			$this->addSplitRow($current, $calculator);
			return;
		}
		foreach ($component_entities as $key => $component_entity){
			if(count($component_entities) > 1){
				$isSplitLine = true;
			}
			$newRow = new Mongodloid_Entity($row->getRawData());
			$newCurrent = $current;
			$component_entity = $component_entity->getRawData();
			$newCurrent["cf"]["component"] = $component_entity["params"]["component"];
			$newCurrent["cf"]["cash_flow"] = $component_entity["params"]["cash_flow"];
			$newCurrent["cf"]["tier_derivation"] = $component_entity["params"]["tier_derivation"];
			$newRow->setRawData($newCurrent);
			if ($component_entity["params"]["anaa"] != "*") {
				$is_anaa_relevant = true;
			}

			switch ($newCurrent["cf"]["tier_derivation"]) {
				case "N":
					$newCurrent["cf"]["tier"] = $component_entity["params"]["tier"];
					break;
				case "CB":
					$newCurrent["cf"]["tier"] = "";
					$tier_entity = $this->getParameterProduct($type, "parameter_tier_cb", $newRow, $calculator);
					if(!$tier_entity) {
						$this->addSplitRow($newCurrent, $calculator, $isSplitLine);
						continue;
					}
					$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					$newRow->setRawData($newCurrent);
					$tier_entity_star_operator = $this->getParameterProduct($type, "parameter_tier_cb", $newRow, $calculator);
					if ($tier_entity_star_operator) {
						$operatorPrefix = $this->findLongestPrefix($newCurrent["uf"]["BNUM"], $tier_entity["params"]["prefix"]);
						$starPrefix = $this->findLongestPrefix($newCurrent["uf"]["BNUM"], $tier_entity_star_operator["params"]["prefix"]);
						if (strlen($starPrefix) > strlen($operatorPrefix)) {
							$newCurrent["cf"]["tier"] = $tier_entity_star_operator["params"]["tier"];
						}
					}
					break;
				case "ABA":
					$tier_entity = $this->getParameterProduct($type, "parameter_tier_aba", $newRow, $calculator);
					if(!$tier_entity) {
						$this->addSplitRow($newCurrent, $calculator, $isSplitLine);;
						continue;
					}
					$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					break;
				case "PB":
					if ($is_anaa_relevant) {
						$tier_entity = $this->getParameterProduct($type, "parameter_tier_pb_anaa", $newRow, $calculator);
						if(!$tier_entity) {
							$this->addSplitRow($newCurrent, $calculator, $isSplitLine);;
							continue;
						}
						$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					} else {
						$tier_entity = $this->getParameterProduct($type, "parameter_tier_pb", $newRow, $calculator);
						if(!$tier_entity) {
							$this->addSplitRow($newCurrent, $calculator, $isSplitLine);;
							continue;
						}
						$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					}
					break;
			}
			$newRow->setRawData($newCurrent);
			$this->addSplitRow($newCurrent, $calculator, $isSplitLine);;
		}
		$calculator->removeLineFromQueue($row);
		$calculator->removeLineFromLines($row);
	}

	
	protected function addSplitRow($row, $calculator, $isSplitLine) {
		$originalStamp = $row['stamp'];
		$line = Billrun_Factory::db()->linesCollection()->query(array('stamp' => $originalStamp))->cursor()->limit(1)->current()->getRawData();
		
		$stampParams = Billrun_Util::generateFilteredArrayStamp($row, array('urt', 'eurt', 'uf', 'cf', 'usagev', 'usaget', 'usagev_unit', 'connection_type'));
		unset($row['_id'], $line['_id']);
		$row['is_split_row'] = $line['is_split_row'] = $isSplitLine;
		$row['stamp'] = $line['stamp'] = md5(serialize($stampParams));
		$line['cf'] = $row['cf'];
		
		//add line to queue
		$calculator->addLineToQueue(new Mongodloid_Entity($row));
		
		//add line to lines
		$calculator->addLineToLines(new Mongodloid_Entity($line));
				
	}

	public function getParameterProduct($type, $parameter_name, $row, Billrun_Calculator $calculator, $multiple_entities = false) {
		$params = [
			'type' => $type,
			'usaget' => $parameter_name,
			'multiple_entities' => $multiple_entities
		];
		Billrun_Factory::log('Finding ' . $parameter_name);
		$entities = $multiple_entities ? $this->getMatchingEntitiesByCategories($row, $params, $calculator) :
			$calculator->getMatchingEntitiesByCategories($row, $params);
		if ($entities) {
			return $multiple_entities ? $entities["retail"] : $entities["retail"]->getRawData();
		}
		Billrun_Factory::log('Failed finding' . $parameter_name);
		return false;
	}

	protected function getMatchingEntitiesByCategories($row, $params, $calculator) {
		$ret = [];
		$type = $params['type'] ?: '';
		$matchFilters = Billrun_Factory::config()->getFileTypeSettings($type, true)['rate_calculators'];
		if (empty($matchFilters)) {
			Billrun_Factory::log('No filters found for row ' . $row['stamp'] . ', params: ' . print_R($params, 1), Billrun_Log::WARN);
			return false;
		}

		foreach ($matchFilters as $category => $categoryFilters) {
			$usaget = $params['usaget'] ?: '';
			$params['category'] = $category;
			$params['filters'] = Billrun_Util::getIn($categoryFilters, [$usaget, 'priorities'], Billrun_Util::getIn($categoryFilters, $usaget, []));
			$filters = Billrun_Util::getIn($params, 'filters', $matchFilters);
			foreach ($filters as $priority) {
				$currentPriorityFilters = Billrun_Util::getIn($priority, 'filters', $priority);
				$params['cache_db_queries'] = Billrun_Util::getIn($priority, 'cache_db_queries', false);
				$query = $calculator->getEntityQuery($row, $currentPriorityFilters, $category, $params);

				if (!$query) {
					Billrun_Factory::log('Cannot get query for row ' . $row['stamp'] . '. filters: ' . print_R($currentPriorityFilters, 1) . ', params: ' . print_R($params, 1), Billrun_Log::DEBUG);
					continue;
				}

				Billrun_Factory::dispatcher()->trigger('extendEntityParamsQuery', [&$query, &$row, &$calculator, $params]);
				$entities = $calculator->getEntities($row, $query, $params);
				$firstEntity = is_array($entities) ? current($entities) : false;
				if ($firstEntity && !$firstEntity->isEmpty()) {
					break;
				}
			}

			$entities = $this->getFullEntitiesData($entities, $calculator, $row, $params);
			if ($entities) {
				$ret[$category] = $entities;
			}
		}

		return $ret;
	}

	protected function getFullEntitiesData($entities, $calculator, $row = [], $params = []) {
		$entitiesData = [];
		foreach ($entities as $entity) {
			$cacheKey = strval($entity->getRawData()['_id']['_id']);
			if (empty($calculator::$entitiesData[$cacheKey])) {
				$rawEntity = $entity->getRawData();
				$query = $calculator->getFullEntityDataQuery($rawEntity);
				if (!$query) {
					return false;
				}

				$coll = Billrun_Factory::db()->ratesCollection();;
				$calculator::$entitiesData[$cacheKey] = $coll->query($query)->cursor()->current();
			}
			$entitiesData[] = $calculator::$entitiesData[$cacheKey];
		}
		return $entitiesData;
	}

	public function setParameter($current, $params, $entity) {
		foreach ($params as $param) {
			$current["cf"][$param] = $entity["params"][$param];
		}
		return $current;
	}

	public function determineCallDirection($usaget) {
		$call_direction = "";
		switch ($usaget) {
			case "incoming_call":
			case "incoming_sms":
				$call_direction = "I";
				break;
			case "outgoing_call":
			case "outgoing_sms":
				$call_direction = "O";
				break;
			case "transit_incoming_call":
				$call_direction = "TI";
				break;
			case "transit_outgoing_call":
				$call_direction = "TO";
				break;
		}

		return $call_direction;
	}

	public function setOperator($row, &$current, $type, $calculator) {
		$current["cf"]["incoming_operator"] = "";
		$current["cf"]["outgoing_operator"] = "";
		if ($current["cf"]["call_direction"] != "O") {
			//TODO - change to parameter_incoming operator
			$operator_entity = $this->getParameterProduct($type, "parameter_operator", $row, $calculator);
			$current["cf"]["incoming_operator"] = $operator_entity["params"]["operator"];
			$current["cf"]["incoming_poin"] = $operator_entity["params"]["poin"];
			if ($current["cf"]["call_direction"] != "TO") {
				$current["cf"]["operator"] = $operator_entity["params"]["operator"];
				$current["cf"]["poin"] = $operator_entity["params"]["poin"];
			}
			$row->setRawData($current);
		}
		if ($current["cf"]["call_direction"] != "I") {
			//TODO - change to parameter_outgoing_operator
			$operator_entity = $this->getParameterProduct($type, "parameter_operator", $row, $calculator);
			$current["cf"]["outgoing_operator"] = $operator_entity["params"]["operator"];
			$current["cf"]["outgoing_poin"] = $operator_entity["params"]["poin"];
			if ($current["cf"]["call_direction"] != "TI") {
				$current["cf"]["operator"] = $operator_entity["params"]["operator"];
				$current["cf"]["poin"] = $operator_entity["params"]["poin"];
			}
		}
	}

	public function findLongestPrefix($num, $productPrefixes) {
		usort($productPrefixes, function ($a, $b) {
			return strlen($b) - strlen($a);
		});
		$numPrefixes = Billrun_Util::getPrefixes($num);
		$curr = 0;
		for ($i = 0; $i < strlen($num); $i++) {
			for ($k = $curr; $k < count($productPrefixes); $k++) {
				if (strlen($numPrefixes[$i]) > strlen($productPrefixes[$k])) {
					$curr = $k;
					break;
				}
				if ($numPrefixes[$i] == $productPrefixes[$k]) {
					return $productPrefixes[$k];
				}
			}
		}
		return false;
	}

}
