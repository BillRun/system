<?php



/**
 * This class defines a query logic that is comparable to mongo query but  can apply to php arrays
 *
 * @author eran
 */
class Billrun_Utils_Arrayquery_Aggregate {

	protected $mapping = array(
		'$match' => '_match',
		'$group' => '_group',
		'$project' => '_project',
		'$unwind' => '_unwind',
		'$sort' => '_sort'
	);

	public function aggregate($pipeline, $data, $previouslyAggregatedResults = FALSE ) {
		foreach($pipeline as $stage) {
			$data = $this->evaluate($stage, $data, $previouslyAggregatedResults);
		}
		return !empty($previouslyAggregatedResults) ? array_merge($previouslyAggregatedResults,$data) : $data;
	}

	/**
	*
	*/
	public static function clearLeadingDollar($fieldsWithDollar) {
		if(!is_array($fieldsWithDollar)) {
			return preg_replace('/^\$/', '', $fieldsWithDollar);
		}
		foreach($fieldsWithDollar as &$value) {
			$value = preg_replace('/^\$/', '', $value);
		}
		return $fieldsWithDollar;
	}
	
	public static function hasLeadingDollar($fieldExpr) {
		return preg_match('/^\$/', $fieldExpr);
	}
	
	public static function getFieldValue ($data, $fieldPath) {
		if(static::hasLeadingDollar($fieldPath)) {
			return Billrun_Util::getIn( $data, static::clearLeadingDollar($fieldPath) );
		}
		return $fieldPath;
	}

	//================================= Protected ==============================

	protected function evaluate($stage, $data, $previouslyAggregatedResults = FALSE) {
		foreach($stage as $stageKey => $expression ) {
			if(empty($this->mapping[$stageKey]) || !method_exists($this,$this->mapping[$stageKey])) {
				Billrun_Factory::log("Aggregation opretion {$stageKey} is not supported by ArrayAggerate.",Zend_Log::ERR);
			}

			if($stageKey == '$group' && !empty($previouslyAggregatedResults)) {
				$data = $this->{$this->mapping[$stageKey]}($expression, $data, $previouslyAggregatedResults );
			} else {
				$data = $this->{$this->mapping[$stageKey]}($expression, $data);
			}
		}

		return $data;
	}

	//================

	protected function _match($expression, $data) {
		return Billrun_Utils_Arrayquery_Query::query($data, $expression, TRUE);
	}

	protected function _group($expression, $data , $prevGroupedData = array()) {
		$groupedData = array();
		$aggregateExpretion = new Billrun_Utils_Arrayquery_Aggregate_Expression();
		foreach($data as $line) {
			$stamp = Billrun_Util::generateArrayStamp(array('_id' => $aggregateExpretion->evaluate($line, $expression['_id']) ) );
			$groupedData[$stamp] = $aggregateExpretion->evaluate($line, $expression, Billrun_Util::getFieldVal($prevGroupedData[$stamp],@$groupedData[$stamp]));
		}

		return $groupedData;
	}

	protected function _project($expression, $data) {
		$retData = [];
		$aggregateExpretion = new Billrun_Utils_Arrayquery_Aggregate_Expression();
		foreach($data as $key => $dataValue) {
			$retData[$key] = $aggregateExpretion->evaluate($dataValue, $expression ,$dataValue);
		}

		return $retData;
	}

	protected function _unwind($field, $data) {
		$retData = array();
		foreach ($data as $line) {
			//TODO add incorrect field handling an none array handling.
			$arrayField = Billrun_Util::getIn($line, $this->clearLeadingDollar($field));
			if(!empty($arrayField)) {
				foreach ($arrayField as $unwindedValue) {
						$tempData = $line;
						Billrun_Util::setIn($tempData, $this->clearLeadingDollar($field) ,$unwindedValue);
						$retData[] = $tempData;
				}
			} else {
				$retData[] = $line;
			}
		}

		return $retData;
	}

	protected function _sort($expression, $data) {
		//TODO add support for mulltiple fields (currently only suports one sort field)
		$key = key($expression);
		$direction = current($expression);
		usort($data, function($a,$b) use ($key,$direction) {
			return Billrun_Util::getIn( $a, $key ) < Billrun_Util::getIn( $b, $key ) ?
				-$direction :
				 $direction * (Billrun_Util::getIn( $a, $key ) != Billrun_Util::getIn( $b, $key ));
		});
		return $data;
	}
}
