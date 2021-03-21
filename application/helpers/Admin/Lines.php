<?php

class Admin_Lines {

	public static function getOptions() {
		return Billrun_Factory::config()->getConfigValue('admin.advancedOptions');
	}

	public static function getFilterRow($key = null, $type = null, $operator = null, $value = null) {
		// @TODO: move to config
		$operators = array(
			'equals' => 'Equals',
			'like' => 'Contains',
			'ne' => 'Not equals',
			'lt' => 'Less than',
			'lte' => 'Less than or equals',
			'gt' => 'Greater than',
			'gte' => 'Greater than or equals',
			'starts_with' => 'Starts with',
			'ends_with' => 'Ends with',
		);

		$types = self::getOptions();
		$output = "<div class=\"controls controls-row\">
                               <select name=\"manual_key[]\" class=\"form-control span2 multiselect\">";
		foreach ($types as $manual_key => $manual_type) {
			$manual_display = isset($manual_type['display']) ? $manual_type['display'] : ucfirst(str_replace('_', ' ', $manual_key));
			$output.= "<option value=\"" . $manual_key . "\"" . ($key == $manual_key ? " selected" : "") . ">" . $manual_display . "</option>";
		}
		$output.= "</select>
                                <select name=\"manual_operator[]\" class=\"form-control span2 multiselect\">";
		foreach ($operators as $operator_key => $operator_display) {
			$output.="<option value=\"" . $operator_key . "\"" . ($operator == $operator_key ? " selected" : "") . ">" . $operator_display . "</option>";
		}
		$output.="</select>
			<input name=\"manual_value[]\" class=\"form-control span2\" type=\"text\" placeholder=\"value\" value=\"" . (!is_null($value) && $type != 'date' ? $value : "") . "\"" . ($type == 'date' ? " style=\"display:none;\" disabled" : "") . ">
			<div class=\"input-append date\" id=\"datetimepicker_manual_operator\" data-date=\"" . (!is_null($value) && $type == 'date' ? $value : "") . "\" data-date-format=\"YYYY-MM-DD HH:MM:SS\"" . ($type == 'date' ? "" : " style=\"display:none;\"") . ">
							<input name=\"manual_value[]\" class=\"form-control controls-row span2 advanced-filter\" size=\"16\" type=\"text\" value=\"" . (!is_null($value) && $type == 'date' ? $value : "") . "\"" . ($type == 'date' ? "" : " disabled") . ">
							<span class=\"add-on\"><i class=\"icon-th\"></i></span>
						</div>";

		$output.="<a class=\"remove-filter\" href=\"#\">
							<i class=\"glyphicon glyphicon-minus-sign\"></i>
						</a>
						<a class=\"add-filter\" href=\"#\">
							<i class=\"glyphicon glyphicon-plus-sign\"></i>
						</a>
					</div>";
		return $output;
	}
	
	/**
	 * Is the manual filter activated
	 * @param type $param
	 */
	public static function isManualFilter($session) {
		return isset($session->manual_value) && count($session->manual_value)>0 && $session->manual_value[0]!='' && $session->manual_key[0]!='';
	}

}
