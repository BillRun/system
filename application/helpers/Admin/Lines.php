<?php

class Lines {

	public static function getFilterRow($key = null, $type = null, $operator = null, $value = null) {
		$operators = array(
			'lt' => 'Less than',
			'lte' => 'Less than or equals',
			'equals' => 'Equals',
			'gt' => 'Greater than',
			'gte' => 'Greater than or equals',
		);
		$types = array(
			'text' => 'Text',
			'number' => 'Number',
			'date' => 'Date',
		);
		$output = "<div class=\"controls controls-row\">
						<input name=\"manual_key[]\" class=\"span2\" type=\"text\" placeholder=\"field key\" value=\"" . (isset($key) ? $key : "") . "\">
						<select name=\"manual_type[]\" class=\"span2\">";
		foreach ($types as $type_key => $type_display) {
			$output.= "<option value=\"" . $type_key . "\"" . ($type == $type_key ? " selected" : "") . ">" . $type_display . "</option>";
		}
		$output.= "</select>
		<select name=\"manual_operator[]\" class=\"span2\">";
		foreach ($operators as $operator_key => $operator_display) {
			$output.="<option value=\"" . $operator_key . "\"" . ($operator == $operator_key ? " selected" : "") . ">" . $operator_display . "</option>";
		}
		$output.="</select>
			<input name=\"manual_value[]\" class=\"span2\" type=\"text\" placeholder=\"value\" value=\"" . (!is_null($value) && $type!='date' ? $value : "") . "\"" . ($type=='date'? " style=\"display:none;\" disabled" : "") . ">
			<div class=\"input-append date\" id=\"datetimepicker\" data-date=\"" . (!is_null($value) && $type=='date' ? $value : "") . "\" data-date-format=\"yyyy-MM-dd hh:mm:ss\"" . ($type=='date'? "" : " style=\"display:none;\"") . ">
							<input name=\"manual_value[]\" class=\"controls-row span2\" size=\"16\" type=\"text\" value=\"" . (!is_null($value) && $type=='date' ? $value : "") . "\"" . ($type=='date'? "" : " disabled") . ">
							<span class=\"add-on\"><i class=\"icon-th\"></i></span>
						</div>";

		$output.="<a class=\"remove-filter\" href=\"#\">
							<i class=\"icon-minus-sign\"></i>
						</a>
						<a class=\"add-filter\" href=\"#\">
							<i class=\"icon-plus-sign\"></i>
						</a>
					</div>";
		return $output;
	}
	
	/**
	 * Is the manual filter activated
	 * @param type $param
	 */
	static public function isManualFilter($session) {
		return isset($session->manual_value) && count($session->manual_value)>0 && $session->manual_value[0]!='' && $session->manual_key[0]!='';
	}

}
