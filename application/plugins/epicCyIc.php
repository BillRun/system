<?php

class epicCyIcPlugin extends Billrun_Plugin_BillrunPluginBase {
    public function beforeCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) 
	{
		$is_anaa_relevant = false;
		
        if ($calculator->getType() == 'rate') {
			$type = $row['type'];
            $current = $row->getRawData();
            $current["cf"]["call_direction"] = $this->determineCallDirection($current["usaget"]);
			$current = $this->setOperator($row, $current, $type, $calculator);
			$row->setRawData($current);
			
			//$row->setRawData(setParameter($current, ["operator", "poin"], $operator_entity));
			
			$product_entity = $this->getParameterProduct($type, "parameter_product", $row, $calculator);
            $current["cf"]["product"] = $product_entity["params"]["product"];
            $row->setRawData($current);
			
			$anaa_entity = $this->getParameterProduct($type, "parameter_anaa", $row, $calculator);
            $current["cf"]["anaa"] = $anaa_entity["params"]["anaa"];
            $row->setRawData($current);
			
			$bnaa_entity = $this->getParameterProduct($type, "parameter_bnaa", $row, $calculator);
            $current["cf"]["bnaa"] = $bnaa_entity["params"]["bnaa"];
            $row->setRawData($current);
			
			$scenario_entity = $this->getParameterProduct($type, "parameter_scenario", $row, $calculator);
            $current["cf"]["scenario"] = $scenario_entity["params"]["scenario"];
            $row->setRawData($current);
			if($scenario_entity["params"]["anaa"] != "*") {
				$is_anaa_relevant = true;
			}
			
			//TODO: check if there are multiple results and split
			$component_entity = $this->getParameterProduct($type, "parameter_component", $row, $calculator);
            $current["cf"]["component"] = $component_entity["params"]["component"];
            $current["cf"]["cash_flow"] = $component_entity["params"]["cash_flow"];
            $current["cf"]["tier_derivation"] = $component_entity["params"]["tier_derivation"];
            $row->setRawData($current);
			if($component_entity["params"]["anaa"] != "*") {
				$is_anaa_relevant = true;
			}
			
			switch ($current["cf"]["tier_derivation"]) {
				case "N":
					$current["cf"]["tier"] = $component_entity["params"]["tier"];
					break;
				case "CB":
					$current["cf"]["tier"] = "";
					$tier_entity = $this->getParameterProduct($type, "parameter_tier_cb", $row, $calculator);
					$current["cf"]["tier"] = $tier_entity["params"]["tier"];
					$row->setRawData($current);
					$tier_entity_star_operator = $this->getParameterProduct($type, "parameter_tier_cb", $row, $calculator);
					if($tier_entity_star_operator) {
						$operatorPrefix = $this->findLongestPrefix($current["uf"]["BNUM"], $tier_entity["params"]["prefix"]);
						$starPrefix = $this->findLongestPrefix($current["uf"]["BNUM"], $tier_entity_star_operator["params"]["prefix"]);
						if(strlen($starPrefix) > strlen($operatorPrefix)) {
							$current["cf"]["tier"] = $tier_entity_star_operator["params"]["tier"];
						}
					}
					break;
				case "ABA":
					$tier_entity = $this->getParameterProduct($type, "parameter_tier_aba", $row, $calculator);
					$current["cf"]["tier"] = $tier_entity["params"]["tier"];
					break;
				case "PB":
					if($is_anaa_relevant) {
						$tier_entity = $this->getParameterProduct($type, "parameter_tier_pb_anaa", $row, $calculator);
						$current["cf"]["tier"] = $tier_entity["params"]["tier"];
					}
					else {
						$tier_entity = $this->getParameterProduct($type, "parameter_tier_pb", $row, $calculator);
						$current["cf"]["tier"] = $tier_entity["params"]["tier"];
					}
					break;
			}
			$row->setRawData($current);
        }
    }
    
    public function getParameterProduct($type, $parameter_name, $row, Billrun_Calculator $calculator)
    {
        $params = [
                'type' => $type,
                'usaget' => $parameter_name,
        ];
        $entity = $calculator->getMatchingEntitiesByCategories($row, $params);
		if($entity){
			return $entity["retail"]->getRawData();;
		}
        return false;
    }
	
	public function setParameter($current, $params, $entity) {
		foreach ($params as $param){
			$current["cf"][$param] = $entity["params"][$param];
		}
		return $current;
	}


	public function determineCallDirection ($usaget) 
	{
		$call_direction = "";
		switch ($usaget) {
			case "incoming_call":
				$call_direction = "I";
				break;
			case "outgoing_call":
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
	
	public function setOperator($row, $current, $type, $calculator) {
		if($current["cf"]["call_direction"] != "O") {
			//TODO - change to parameter_incoming operator
			$operator_entity = $this->getParameterProduct($type, "parameter_operator", $row, $calculator);
			$current["cf"]["incoming_operator"] = $operator_entity["params"]["operator"];
			$current["cf"]["incoming_poin"] = $operator_entity["params"]["poin"];
			if($current["cf"]["call_direction"] != "TO"){
				$current["cf"]["operator"] = $operator_entity["params"]["operator"];
				$current["cf"]["poin"] = $operator_entity["params"]["poin"];
			}
			$row->setRawData($current);
		}
		if($current["cf"]["call_direction"] != "I") {
			//TODO - change to parameter_outgoing_operator
			$operator_entity = $this->getParameterProduct($type, "parameter_operator", $row, $calculator);
			$current["cf"]["outgoing_operator"] = $operator_entity["params"]["operator"];
			$current["cf"]["outgoing_poin"] = $operator_entity["params"]["poin"];
			if($current["cf"]["call_direction"] != "TI"){
				$current["cf"]["operator"] = $operator_entity["params"]["operator"];
				$current["cf"]["poin"] = $operator_entity["params"]["poin"];
			}
		}
		
		return $current;
	}
	
	public function sortByLength($a, $b) {
		return strlen($b)-strlen($a);
	}
	
	public function findLongestPrefix($num, $productPrefixes) {
		usort($productPrefixes, function ($a, $b) {
		return strlen($b)-strlen($a);
	});
		$numPrefixes = Billrun_Util::getPrefixes($num);
		$curr = 0;
		for($i = 0; $i < strlen($num); $i++){
			for($k = $curr; $k < count($productPrefixes); $k++) {
				if(strlen($numPrefixes[$i]) > strlen($productPrefixes[$k])){
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