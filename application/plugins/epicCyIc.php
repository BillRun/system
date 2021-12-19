<?php

class epicCyIcPlugin extends Billrun_Plugin_BillrunPluginBase {

	protected $extraLines;
	protected $ict_configuration = [];

	public function __construct($options = array()) {
		$this->ict_configuration = !empty($options['ict']) ? $options['ict'] : [];
	}

	public function beforeImportRowFormat(&$row, $operation, $requestCollection, $update) {
		if ($operation == "permanentchange") {
			switch ($update['mapper_name']) {
				case "One file loader - Tier create":
				case "One file loader - Tier update":
					$row[2] = "TIER_CB_" . $row[8] . "_" . $row[4] . "_" . $row[5];
					$row[2] = $this->modifyStrigToKeyStructure($row[2]);
					break;
				case "One file loader - Rates create I calls":
				case "One file loader - Rates create O calls":
				case "One file loader - Rates create TI calls":
				case "One file loader - Rates create TO calls":
				case "One file loader - Rates create I SMS":
				case "One file loader - Rates create O SMS":
				case "One file loader - Rates update":
					$row[2] = "RATE_" . $row[4] . "_" . $row[5] . "_" . $row[6] . "_" . $row[7] . "_" . $row[8];
					$row[2] = $this->modifyStrigToKeyStructure($row[2]);
					break;
			}
		}
	}

	public function afterImportRowFormat(&$entity, $operation, $requestCollection, $update) {
		switch ($update['mapper_name']) {
			case "One file loader - Tier create":
			case "One file loader - Tier update":
				$entity["key"] = $this->generateProductKey($entity, "tier");
				break;
			case "One file loader - Rates create I calls":
			case "One file loader - Rates create O calls":
			case "One file loader - Rates create TI calls":
			case "One file loader - Rates create TO calls":
			case "One file loader - Rates create I SMS":
			case "One file loader - Rates create O SMS":
			case "One file loader - Rates update":
				$entity["key"] = $this->generateProductKey($entity, "rate");
				if (!empty($entity["params"]["additional_charge"])) {
					$entity["price_value"] = $entity["params"]["additional_charge"];
				}
				break;
			case "Missing ERP Mappings":
				$entity["key"] = $entity["params"]["scenario"] . "_" .
						$entity["params"]["product"] . "_" .
						$entity["params"]["component"] . "_" .
						$entity["params"]["cash_flow"] . "_" .
						$entity["params"]["user_summarisation"] . "_" .
						$entity["params"]["operator"];
				break;
		}
	}

	public function beforeImportEntity(&$entity, $operation, $requestCollection, $update) {
		switch ($update['mapper_name']) {
			case "One file loader - Rates create I calls":
			case "One file loader - Rates create O calls":
			case "One file loader - Rates create TI calls":
			case "One file loader - Rates create TO calls":
			case "One file loader - Rates update":
				if (!empty($entity["params"]["additional_charge"])) {
					$usagetype = reset(array_keys($entity['rates']));
					$one_time_charge_call_usage_type = ["incoming_call", "outgoing_call", "transit_incoming_call", "transit_outgoing_call"];
					if (in_array($usagetype, $one_time_charge_call_usage_type)) {
						$entity["rates"][$usagetype]["BASE"]["rate"] = $this->addZeroPriceTier($entity);
					}
				}
				break;
		}
	}

	public function afterRunManualMappingQuery(&$output, $requestCollection, $update) {
		if ($requestCollection == 'rates' && $update['mapper_name'] == 'Missing ERP Mappings') {
			$match = array(
				'$match' => array(
					"rates.erp_mapping" => array('$exists' => 1)
				)
			);
			$out = array(
				'$out' => "epic_cy_erp_mappings"
			);
			try {
				Billrun_Factory::db()->ratesCollection()->aggregate($match, $out);
			} catch (Exception $ex) {
				Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
			}
		}
	}

	public function generateProductKey($entity, $type) {
		switch ($type) {
			case "tier":
				$entity["key"] = "TIER_CB_" . $entity["params"]["tier"] . "_" . $entity["params"]["operator"] . "_" . $entity["params"]["cash_flow"];
				$entity["key"] = $this->modifyStrigToKeyStructure($entity["key"]);
				return $entity["key"];
			case "rate":
				$entity["key"] = "RATE_" . $entity["params"]["operator"] . "_" . $entity["params"]["component"] . "_" . $entity["params"]["product"] . "_" . $entity["params"]["direction"] . "_" . $entity["params"]["tier"];
				$entity["key"] = $this->modifyStrigToKeyStructure($entity["key"]);
				return $entity["key"];
		}
	}

	public function addZeroPriceTier($entity) {
		$usagetype = reset(array_keys($entity['rates']));
		$rates_array = $entity["rates"][$usagetype]["BASE"]["rate"];
		$rates_array[1] = $rates_array[0];
		$rates_array[0]["to"] = 1;
		$rates_array[1]["from"] = 1;
		$rates_array[0]["price"] = $entity["params"]["additional_charge"];
		$rates_array[1]["price"] = 0;
		return $rates_array;
	}
        
        public function beforeLineMediation($calculator, $type, &$row) {
            if ($type === 'ICT') {
                if ($row["EVENT_START_DATE"] == "" || $row["EVENT_START_TIME"] == "") {
                    $row["originalDate"] = $row["EVENT_START_DATE"];
                    $row["EVENT_START_DATE"] = "19700101";
                    $row["originalTime"] = $row["EVENT_START_TIME"];
                    $row["EVENT_START_TIME"] = "020000";
                    $row["dateOrTimeWasEmpty"] = true;
                }
            }
	}
        
        public function afterLineMediation($calculator, $type, &$row) {
            if ($type === 'ICT') {
                if ($row["uf"]["dateOrTimeWasEmpty"]) {
                    $row["uf"]["EVENT_START_DATE"] = $row["uf"]["originalDate"];
                    $row["uf"]["EVENT_START_TIME"] = $row["uf"]["originalTime"];
                    unset($row["uf"]["originalDate"]);
                    unset($row["uf"]["originalTime"]);
                    unset($row["uf"]["dateOrTimeWasEmpty"]);
                }
            }
	}

	public function afterProcessorParsing($processor) {
		if ($processor->getType() === 'ICT') {
			$dataRows = $processor->getData()['data'];
			foreach ($dataRows as $row) {
				if ($row["usaget"] == "transit_incoming_call") {
					$newRow = $row;
					$newRow['usaget'] = "transit_outgoing_call";
					$stampParams = Billrun_Util::generateFilteredArrayStamp($newRow, array('urt', 'eurt', 'uf', 'usagev', 'usaget', 'usagev_unit', 'connection_type'));
					$newRow['stamp'] = md5(serialize($stampParams));
					$processor->addDataRow($newRow);
				}
			}
		}
	}

	function modifyStrigToKeyStructure($str) {
		$unwanted_array = array('Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
			'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
			'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
			'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
			'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y', '(' => "", ')' => "");
		$str = strtr($str, $unwanted_array);
		$str = str_replace('&', '_AND_', $str);
		$str = str_replace('(', '_', str_replace("'", "", str_replace('-', '_', (str_replace('__', '_', str_replace(' ', '_', str_replace('.', '_', str_replace('$', '_', str_replace(',', '_', str_replace('‘', '_', $str))))))))));
		$str = str_replace('+', '_', $str);
		$str = str_replace('___', '_', $str);
		$str = str_replace('/', '_', $str);
		$str = str_replace('*', 'ANY', $str);
		$str = str_replace('|', '_OR_', $str);
		$str = str_replace('__', '_', $str);
		return strtoupper($str);
	}

	public function afterCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) {
		if ($calculator->getType() == 'rate') {
			$current = $row->getRawData();
			$rate_tier_array = $current["foreign"]["rate"]["rates"][$current["usaget"]]["BASE"]["rate"];
			if ($rate_tier_array[0]["uom_display"]["range"] == "counter" || (count($rate_tier_array) == 2 && $rate_tier_array[0]["to"] == 1 && $rate_tier_array[1]["price"] == 0)) {
				$current["cf"]["rate_type"] = "flat_rate";
			} else {
				$current["cf"]["rate_type"] = "unit_cost";
			}
			$current["cf"]["rate_price"] = $current["foreign"]["rate"]["rates"][$current["usaget"]]["BASE"]["rate"][0]["price"];
			if ($current["cf"]["rate_type"] == "unit_cost") {
				$current["cf"]["rate_price"] *= 60;
			}
			unset($current["foreign"]["rate"]["rates"]);
			$row->setRawData($current);
		}

		if ($calculator->getType() == 'pricing') {
			$current = $row->getRawData();
			if ($current["cf"]["cash_flow"] == "E") {
				unset($current["billrun"]);
				for ($i = 0; $i < count($current["rates"]); $i++) {
					unset($current["rates"][$i]["pricing"]["billrun"]);
				}
				$row->setRawData($current);
			}
		}
	}

	public function beforeCalculatorAddExtraLines(&$row, &$extraData, Billrun_Calculator $calculator) {
		if ($calculator->getType() == 'rate') {
			$this->extraLines = [];
			$newRows = $this->calcCfFields($row, $calculator);
			if (count($newRows) === 1) {
				$this->updateCfFields($newRows[0], $row); //only update
			} else {
				$current = is_array($row) ? $row : $row->getRawData();
				$alreadySplit = $current["cf"]["is_split_row"] ?? false;
				$first = true;
				foreach ($newRows as $newRow) {
					if ($alreadySplit) {
						if ($this->isTheSameSplitRow($newRow, $current)) {
							$this->updateCfFields($newRow, $row); //only update the matching line.
						}
					} else {
						$newRow["cf"]["is_split_row"] = true;
						if ($first) {
							$this->updateCfFields($newRow, $row);
							$first = false;
						} else {
							$this->addExtraRow($newRow);
						}
					}
				}
			}
			$extraData = $this->extraLines;
		}
	}
        
        public function beforeUpdateRebalanceLines(&$updateQuery) {
            $updateQuery['$unset'] = array_merge($updateQuery['$unset'], array('cf.is_split_row' => 1));
        }

	protected function updateCfFields($newRow, &$row) {
		if (is_array($row)) {
			$row = $newRow;
		} else {
			$row->setRawData($newRow);
		}
	}

	protected function isTheSameSplitRow($newRow, $row) {
		return $row["cf"]["component"] === $newRow["cf"]["component"] && $row["cf"]["cash_flow"] === $newRow["cf"]["cash_flow"] && $row["cf"]["tier_derivation"] === $newRow["cf"]["tier_derivation"];
	}

	protected function addExtraRow($row) {

		$oldStamp = $row['stamp'];
		$newStamp = md5(serialize(Billrun_Util::generateFilteredArrayStamp($row, array('urt', 'eurt', 'uf', 'cf', 'usagev', 'usaget', 'usagev_unit', 'connection_type'))));
		unset($row['_id']);

		$row['stamp'] = $newStamp;
		$this->extraLines[$oldStamp][$newStamp] = $row;
	}

	protected function calcCfFields($row, Billrun_Calculator $calculator) {
		$is_anaa_relevant = false;
		if (is_array($row)) {
			$row = new Mongodloid_Entity($row);
		}
		$current = $row->getRawData();
		Billrun_Factory::log('Start rate mapping for stamp - ' . $current["stamp"] . ", RECORD_SEQUENCE_NUMBER - " . $current["uf"]["RECORD_SEQUENCE_NUMBER"]);
		$type = $current['type'];
		$current["cf"]["call_direction"] = $this->determineCallDirection($current["usaget"]);
		$current["cf"]["event_direction"] = substr($current["cf"]["call_direction"], 0, 1);
		Billrun_Factory::log('The call direction is - ' . $current["cf"]["call_direction"]);
		$row->setRawData($current);
		$setOperator = $this->setOperator($row, $current, $type, $calculator);
		if(!$setOperator) {
			return [$current];
		}
		$row->setRawData($current);

		//$row->setRawData(setParameter($current, ["operator", "poin"], $operator_entity));

		$product_entity = $this->getParameterProduct($type, "parameter_product", $row, $calculator);
		if (!$product_entity) {
			return [$current];
		}
		Billrun_Factory::log('Product found - ' . $product_entity["key"]);
		Billrun_Factory::log('The product is - ' . $product_entity["params"]["product"]);
		Billrun_Factory::log('The product group is - ' . $product_entity["params"]["product_group"]);
		$current["cf"]["product"] = $product_entity["params"]["product"];
		$current["cf"]["product_group"] = $product_entity["params"]["product_group"];
		$current["cf"]["product_title"] = $product_entity["description"];
		$current["cf"]["anaa"] = "";
		$row->setRawData($current);

		$anaa_entity = $this->getParameterProduct($type, "parameter_naa", $row, $calculator);
		if (!$anaa_entity) {
			return [$current];
		}
		Billrun_Factory::log('ANUM naa found - ' . $anaa_entity["key"]);
		Billrun_Factory::log('The ANUM naa parent is - ' . $anaa_entity["params"]["naa_parent"]);
		Billrun_Factory::log('The ANUM naa group is - ' . $anaa_entity["params"]["naa"]);
		$current["cf"]["anaa"] = $anaa_entity["params"]["naa_parent"];
		$current["cf"]["anaa_group"] = $anaa_entity["params"]["naa"];
		$current["cf"]["anaa_title"] = $anaa_entity["description"];
		$row->setRawData($current);

		$sms_activity_types = ["incoming_sms", "outgoing_sms"];
		if (!in_array($current["usaget"], $sms_activity_types)) {
			$bnaa_entity = $this->getParameterProduct($type, "parameter_naa", $row, $calculator);
			if (!$bnaa_entity) {
				return [$current];
			}
				Billrun_Factory::log('BNUM naa found - ' . $bnaa_entity["key"]);
				Billrun_Factory::log('The BNUM naa group is - ' . $bnaa_entity["params"]["naa"]);
			$current["cf"]["bnaa"] = $bnaa_entity["params"]["naa"];
			$row->setRawData($current);
		}

		$scenario_entity = $this->getParameterProduct($type, "parameter_scenario", $row, $calculator);
		if (!$scenario_entity) {
			return [$current];
		}
		Billrun_Factory::log('Scenario found - ' . $scenario_entity["key"]);
		Billrun_Factory::log('The scenario is - ' . $scenario_entity["params"]["scenario"]);
		$current["cf"]["scenario"] = $scenario_entity["params"]["scenario"];
		$row->setRawData($current);
		if ($scenario_entity["params"]["anaa"] != "*") {
			$is_anaa_relevant = true;
		}

		$component_entities = $this->getParameterProduct($type, "parameter_component", $row, $calculator, true);
		if (!$component_entities) {
			return [$current];
		}
		$newRows = [];
		foreach ($component_entities as $key => $component_entity) {
			Billrun_Factory::log('Component found - ' . $component_entity["key"]);
			Billrun_Factory::log('The Component is - ' . $component_entity["params"]["component"]);
			Billrun_Factory::log('The tier derivation is - ' . $component_entity["params"]["tier_derivation"]);
			Billrun_Factory::log('The cash flow is - ' . $component_entity["params"]["cash_flow"]);
			$newRow = new Mongodloid_Entity($row->getRawData());
			$newCurrent = $current;
			$component_entity = $component_entity->getRawData();
			$newCurrent["cf"]["component"] = $component_entity["params"]["component"];
			$newCurrent["cf"]["cash_flow"] = $component_entity["params"]["cash_flow"];
			$newCurrent["cf"]["tier_derivation"] = $component_entity["params"]["tier_derivation"];
			$newCurrent["cf"]["settlement_operator"] = $component_entity["params"]["settlement_operator"];
			$newCurrent["cf"]["virtual_operator"] = $component_entity["params"]["virtual_operator"];
			$newRow->setRawData($newCurrent);
			if ($component_entity["params"]["anaa"] != "*") {
				$is_anaa_relevant = true;
			}

			switch ($newCurrent["cf"]["tier_derivation"]) {
				case "N":
					Billrun_Factory::log('The tier is specified directly - ' . $component_entity["params"]["tier"]);
					$newCurrent["cf"]["tier"] = $component_entity["params"]["tier"];
					break;
				case "CB":
					$newCurrent["cf"]["tier"] = "";
					$newRow->setRawData($newCurrent);
					$tier_entity = $this->getParameterProduct($type, "parameter_tier_cb", $newRow, $calculator);
					if (!$tier_entity) {
						break;
					}
					Billrun_Factory::log('Tier found - ' . $tier_entity["key"]);
					Billrun_Factory::log('The tier is - ' . $tier_entity["params"]["tier"]);
					$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					$newRow->setRawData($newCurrent);
					$tier_entity_star_operator = $this->getParameterProduct($type, "parameter_tier_cb", $newRow, $calculator);
					if ($tier_entity_star_operator) {
						$operatorPrefix = $this->findLongestPrefix($newCurrent["uf"]["BNUM"], $tier_entity["params"]["prefix"]);
						$starPrefix = $this->findLongestPrefix($newCurrent["uf"]["BNUM"], $tier_entity_star_operator["params"]["prefix"]);
						if (strlen($starPrefix) > strlen($operatorPrefix)) {
							Billrun_Factory::log('There is a longer prefix for a general tier and is - ' . $tier_entity_star_operator["params"]["tier"]);
							$newCurrent["cf"]["tier"] = $tier_entity_star_operator["params"]["tier"];
						}
					}
					break;
				case "ABA":
					$tier_entity = $this->getParameterProduct($type, "parameter_tier_aba", $newRow, $calculator);
					if (!$tier_entity) {
						break;
					}
					Billrun_Factory::log('Tier found - ' . $tier_entity["key"]);
					Billrun_Factory::log('The tier is - ' . $tier_entity["params"]["tier"]);
					$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					break;
				case "PB":
					if ($is_anaa_relevant) {
						$tier_entity = $this->getParameterProduct($type, "parameter_tier_pb_anaa", $newRow, $calculator);
						if (!$tier_entity) {
							break;
						}
						Billrun_Factory::log('Tier found - ' . $tier_entity["key"]);
						Billrun_Factory::log('The tier is - ' . $tier_entity["params"]["tier"]);
						$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					} else {
						$tier_entity = $this->getParameterProduct($type, "parameter_tier_pb", $newRow, $calculator);
						if (!$tier_entity) {
							break;
						}
						Billrun_Factory::log('Tier found - ' . $tier_entity["key"]);
						Billrun_Factory::log('The tier is - ' . $tier_entity["params"]["tier"]);
						$newCurrent["cf"]["tier"] = $tier_entity["params"]["tier"];
					}
					break;
			}
			$newRows[] = $newCurrent;
		}
		return $newRows;
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

				$coll = Billrun_Factory::db()->ratesCollection();
				;
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
		$row->setRawData($current);
		if ($current["cf"]["call_direction"] != "O") {
			$operator_entity = $this->getParameterProduct($type, "parameter_operator", $row, $calculator);
			if(!$operator_entity) {
				return false;
			}
			Billrun_Factory::log('Incoming operator found - ' . $operator_entity["key"]);
			Billrun_Factory::log('The operator is - ' . $operator_entity["params"]["operator"]);
			$current["cf"]["incoming_operator"] = $operator_entity["params"]["operator"];
			$current["cf"]["incoming_poin"] = $operator_entity["params"]["poin"];
			if ($current["cf"]["call_direction"] != "TO") {
				$current["cf"]["operator"] = $operator_entity["params"]["operator"];
				$current["cf"]["operator_title"] = $operator_entity["description"];
				$current["cf"]["poin"] = $operator_entity["params"]["poin"];
			}
			$row->setRawData($current);
		}
		if ($current["cf"]["call_direction"] != "I") {
			$operator_entity = $this->getParameterProduct($type, "parameter_operator", $row, $calculator);
			if(!$operator_entity) {
				return false;
			}
			Billrun_Factory::log('outgoing operator found - ' . $operator_entity["key"]);
			Billrun_Factory::log('The operator is - ' . $operator_entity["params"]["operator"]);
			$current["cf"]["outgoing_operator"] = $operator_entity["params"]["operator"];
			$current["cf"]["outgoing_poin"] = $operator_entity["params"]["poin"];
			if ($current["cf"]["call_direction"] != "TI") {
				$current["cf"]["operator"] = $operator_entity["params"]["operator"];
				$current["cf"]["operator_title"] = $operator_entity["description"];
				$current["cf"]["poin"] = $operator_entity["params"]["poin"];
			}
		}
		return true;
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

	public function cronHour() {
		$ict_reports_manager = ICT_Reports_Manager::getInstance($this->ict_configuration);
		$ict_reports_manager->runReports();
	}

	public function getConfigurationDefinitions() {
		return [
			[
				"type" => "json",
				"field_name" => "ict.reports",
				"title" => "ICT's reports configuration",
				"editable" => true,
				"display" => true,
				"nullable" => false,
			], [
				"type" => "text",
				"field_name" => "ict.export.connection_type",
				"title" => "ICT remote connection type",
				"select_list" => true,
				"select_options" => "ssh",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "ict.export.host",
				"title" => "ICT export server's host",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "ict.export.user",
				"title" => "ICT export server's user name",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "password",
				"field_name" => "ict.export.password",
				"title" => "ICT export server's password",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "ict.export.remote_directory",
				"title" => "ICT report files' remote directory",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "ict.export.export_directory",
				"title" => "ICT report files' export directory",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
			], [
				"type" => "string",
				"field_name" => "ict.metabase_details.url",
				"title" => "Metabase's url",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true,
			]
		];
	}

	public function ExportBeforeGetRecordData(&$row, $exporter) {
		if (!empty($row['rebalance'])) {
			end($row['rebalance']);
			$row['rebalance'] = reset($row['rebalance']);
		}
	}
	
	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator) {
		unset($update['$set']['tx.' . $row['stamp']]);
		if (count($update['$set']) === 0) {
			unset($update['$set']);
		}
	}

}

class ICT_Reports_Manager {

	/**
	 * Singleton handler
	 * 
	 * @var ICT_Reports_Manager
	 */
	protected static $instance = null;

	/**
	 * Holds the report file's export details
	 * @var array 
	 */
	protected $export_details;

	/**
	 * Holds MB details
	 * @var array 
	 */
	protected $metabase_details;

	/**
	 * Holds the reports details
	 * @var array 
	 */
	protected $reports_details;

	/**
	 * Holds MB domain
	 * @var string
	 */
	protected $domain;
	protected static $type = 'ssh';
	protected $port = '22';

	public function __construct($options) {
		$this->reports_details = $options['reports'];
		if (!empty($options['metabase_details'])) {
			$this->metabase_details = $options['metabase_details'];
		} else {
			throw new Exception("Missing metabase configuration - no report was downloaded.");
		}
		if (!empty($options['export'])) {
			$this->export_details = $options['export'];
		} else {
			throw new Exception("Missing export configuration - no report was downloaded.");
		}
	}

	/**
	 * Function to fetch the reports that should run in the current day and hour.
	 */
	public function runReports() {
		$reports = $this->getReportsToRun();
		Billrun_Factory::log("Found " . count($reports) . " interconnect reports to run.", Zend_Log::INFO);
		foreach ($reports as $index => $report_settings) {
			if (@class_exists($report_class = 'ICT_report_' . $report_settings['name'])) {
				$report = new $report_class($report_settings);
			} else {
				$report = new ICT_report($report_settings);
			}
			$metabase_url = rtrim($this->metabase_details['url'], "/");
			try {
				$report_params = $report->getReportParams();
				$params_query = $this->createParamsQuery($report_params);
				Billrun_Factory::log($report->name . " report's params json query: " . $params_query, Zend_Log::DEBUG);
				$this->fetchReport($report, $metabase_url, $params_query);
				Billrun_Factory::log($report->name . " report was downloaded successfully", Zend_Log::INFO);
				if ($report->need_post_process) {
					Billrun_Factory::log("Starting " . $report->name . " report's post process", Zend_Log::DEBUG);
					$report->reportPostProcess();
				}
			} catch (Throwable $e) {
				Billrun_Factory::log("Report: " . $report_settings['name'] . " download ERR: " . $e->getMessage(), Zend_Log::ALERT);
				continue;
			}
			try {
				Billrun_Factory::log("Saving " . $report->name . " report.", Zend_Log::DEBUG);
				$this->save($report);
				Billrun_Factory::log("Uploading " . $report->name . " report.", Zend_Log::INFO);
				$this->upload($report);
			} catch (Exception $e) {
				Billrun_Factory::log("Report: " . $report_settings['name'] . " saving ERR: " . $e->getMessage(), Zend_Log::ALERT);
				continue;
			}
			try {
				$this->sendEmails($report);
			} catch (Exception $e) {
				Billrun_Factory::log("Report: " . $report_settings['name'] . " sending emails ERR: " . $e->getMessage(), Zend_Log::ALERT);
				continue;
			}
		}
	}

	/**
	 * 
	 * @param array $metabase - MB details
	 * @return string - user id to use for MB's Apis.
	 * @throws Exception - if one of the MB's details is missing.
	 */
	protected function connectToMetabase($metabase) {
		$header = ['Content-Type' => 'application/json'];
		$user_name = $metabase['user'] ?: null;
		$password = $metabase['password'] ?: null;
		$mb_url = $metabase['url'] ?: null;
		if (is_null($user_name) || is_null($password) || is_null($host)) {
			throw new Exception("Missing 'password' / 'user'/ 'url' field in metabase configuration");
		}
		$data = ["username" => $user_name, "password" => $password];
		$url = $mb_url . '/api/session';
		$res = $this->sendRequest($url, json_encode(['header' => $header, 'data' => $data]));
		print_r(json_decode($res));
		return $res;
	}

	/**
	 * Function that returns the reports that should run in the current day and hour.
	 * @return array of the relevant reports settings.
	 */
	protected function getReportsToRun() {
		$reportsToRun = [];
		foreach ($this->reports_details as $reportSettings) {
			if ((isset($reportSettings['enable']) ? $reportSettings['enable'] : true) && $this->shouldReportRun($reportSettings)) {
				Billrun_Factory::log("Report: " . $reportSettings['name'] . " should run.", Zend_Log::INFO);
				$reportsToRun[] = $reportSettings;
			}
		}
		return $reportsToRun;
	}

	/**
	 * 
	 * @param array $reportSettings
	 * @param array $params
	 * @return true if the report should run now, else - returns false.
	 */
	protected function shouldReportRun($reportSettings, $params = []) {
		$currentDay = intval(date('d'));
		$currentHour = intval(date('H'));
		$isRightHour = $reportSettings['hour'] == $currentHour;
		$isRightDay = true;
		if (!empty($reportSettings['day']) && (intval($reportSettings['day']) != $currentDay)) {
			$isRightDay = false;
		}

		return $isRightDay && $isRightHour;
	}

	/**
	 * Function to download the wanted report from MB.
	 * @param ICT_report object $report
	 * @param string $metabase_url
	 * @param string $report_params
	 * @throws Exception - if the report couldn't be downloaded
	 */
	protected function fetchReport($report, $metabase_url, $report_params) {
		$url = $metabase_url . '/api/public/card/' . $report->getId() . '/query/' . $report->format;
		Billrun_Factory::log('ICT report request: ' . $url, Zend_Log::DEBUG);
		$params = !empty($report_params) ? ['parameters' => $report_params] : [];
		$response = Billrun_Util::sendRequest($url, $params, Zend_Http_Client::GET, array('Accept-encoding' => 'deflate'), null, null, true);
		$response_body = $response->getBody();
		if (empty($response_body)) {
			throw new Exception("Couldn't download " . $report->name . " report. Metabase response is empty.");
		}
		if (!$response->isSuccessful()) {
			Billrun_Factory::log('Report response: ' . $response_body, Zend_Log::DEBUG);
			throw new Exception("Couldn't download " . $report->name . " report. Error code: " . $response->getStatus());
		}
		$report->setData($response_body);
	}

	/**
	 * Function that converts report's params array to json string.
	 * @param array $report_params
	 * @return string
	 */
	protected function createParamsQuery($report_params) {
		$query = [];
		foreach ($report_params as $name => $data) {
			$parameters[] = [
				'type' => $data['type'],
				'target' => ["variable", ["template-tag", $data['template-tag']]],
				'value' => $data['value']
			];
		}
		$query = json_encode($parameters);
		return $query;
	}

	/**
	 * Function that saves the report's files locally
	 * @param ICT_report $report
	 */
	public function save($report) {
		$file_path = $this->export_details['export_directory'] . DIRECTORY_SEPARATOR . $report->getFileName();
		Billrun_Factory::log("Saving " . $report->name . " under: " . $file_path, Zend_Log::INFO);
		file_put_contents($file_path, $report->getData());
	}

	/**
	 * Function that saves the report's files remotely 
	 * @param ICT_report $report
	 */
	public function upload($report) {
		$hostAndPort = $this->export_details['host'] . ':' . $this->port;
		$auth = array(
			'password' => $this->export_details['password'],
		);
		$connection = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
		Billrun_Factory::log()->log("Connecting to SFTP server: " . $connection->getHost(), Zend_Log::INFO);
		$connected = $connection->connect($this->export_details['user']);
		if (!$connected) {
			Billrun_Factory::log()->log("SSH: Can't connect to server", Zend_Log::ALERT);
			return;
		}
		Billrun_Factory::log()->log("Success: Connected to: " . $connection->getHost(), Zend_Log::INFO);
		Billrun_Factory::log("Uploading " . $report->getFileName(), Zend_Log::INFO);
		$fileName = $report->getFileName();
		if (!empty($connection)) {
			try {
				$local = $this->export_details['export_directory'] . '/' . $fileName;
				$remote = $this->export_details['remote_directory'] . '/' . $fileName;
				$connection->put($local, $remote);
			} catch (Exception $e) {
				Billrun_Factory::log("Report: " . $report_settings['name'] . " uploading ERR: " . $e->getMessage(), Zend_Log::ALERT);
				return;
			}
			Billrun_Factory::log("Uploaded " . $report->getFileName() . " file successfully", Zend_Log::INFO);
		}
	}

	/**
	 * Function that send ICT (Metabase) reports by email
	 * @param ICT_report $report
	 */
	public function sendEmails($report) {
		$emails = $report->getEmails();
		if (empty($emails)) {
			return;
		}
		Billrun_Factory::log("Sending " . $report->name . " report to emails: " . implode(', ', $emails), Zend_Log::INFO);
		Billrun_Util::sendMail($report->name . " Report", $report->getData(), $emails, array(), true);
	}

	/**
	 * get ICT reports manager instance
	 * 
	 * @param array $params the parameters of the manager
	 * 
	 * @return ICT_Reports_Manager object
	 */
	public static function getInstance($options) {
		if (is_null(self::$instance)) {
			$class = 'ICT_Reports_Manager';
			if (@class_exists($class, true)) {
				self::$instance = new $class($options);
			}
		}
		return self::$instance;
	}

}

class ICT_report {

	/**
	 * Report name
	 * @var string 
	 */
	public $name;

	/**
	 * Report id in MB
	 * @var string 
	 */
	protected $id;

	/**
	 * Day to run the report, null if the report runs daily.
	 * @var number 
	 */
	public $day = null;

	/**
	 * Hour to run the report.
	 * @var number 
	 */
	public $hour;

	/**
	 * Csv file name.
	 * @var string
	 */
	public $file_name;

	/**
	 * Report params
	 * @var array
	 */
	public $params;

	/**
	 * Report actual data
	 * @var array
	 */
	protected $data;

	/**
	 * True if the report needs post process
	 * @var boolean
	 */
	public $need_post_process;

	/**
	 * Report format - csv/json
	 * @var string
	 */
	public $format;

	/**
	 * Is report enabled
	 * @var boolean 
	 */
	protected $enabled;

	/**
	 * Emails to send report
	 * @var array 
	 */
	protected $emails;

	public function __construct($options) {
		if (is_null($options['id'])) {
			throw new Exception("Report ID is missing");
		}
		$this->id = $options['id'];
		$this->name = $options['name'];
		$this->day = !empty($options['day']) ? $options['day'] : $this->day;
		$this->hour = $options['hour'];
		$this->file_name = $options['csv_name'];
		$this->params = $options['params'];
		$this->need_post_process = !empty($options['need_post_process']) ? $options['need_post_process'] : false;
		$this->format = $this->need_post_process ? "json" : "csv";
		$this->enabled = !empty($options['enable']) ? $options['enable'] : true;
		$this->emails = !empty($options['send_by_email']) ? $options['send_by_email'] : [];
	}

	public function reportPostProcess($values = []) {
		$data = array_map('str_getcsv', explode("\n", $report->getData()));
		return;
	}

	/**
	 * Function that process the configured report params, and return it as array.
	 * @return type
	 * @throws Exception - if one of the configured params is in wrong configuration.
	 */
	public function getReportParams() {
		$params = [];
		if (!empty($this->params)) {
			foreach ($this->params as $index => $param) {
				switch ($param['type']) :
					case "date" :
						$dateFormat = isset($param['format']) ? $param['format'] : 'Y-m-d';
						if (isset($param['value']) && is_array($param['value'])) {
							$date = Billrun_Util::calcRelativeTime($param['value'], time());
							$params[$param['template_tag']]['value'] = date($dateFormat, $date);
						} else {
							throw new Exception("Invalid params for 'date' type, in parameter" . $param['template_tag']);
						}
						break;
					case "string" || "number" :
						$params[$param['template_tag']]['value'] = $param['value'];
						break;
					default :
						throw new Exception("Invalid param type, in parameter" . $param['template_tag']);
				endswitch;
				$params[$param['template_tag']]['template-tag'] = $param['template_tag'];
				$params[$param['template_tag']]['type'] = $param['type'];
			}
		}
		return $params;
	}

	public function getData() {
		return $this->data;
	}

	public function getEmails() {
		return $this->emails;
	}

	public function setData($data) {
		$this->data = $data;
	}

	public function getId() {
		return $this->id;
	}

	public function getFileName() {
		return $this->file_name . '_' . date('Ymd', time()) . '.csv';
	}

}
