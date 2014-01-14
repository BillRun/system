<?php

class Lines {

	public static function getFilterRow($key = null, $type = null, $operator = null, $value = null) {
		$operators = array(
			'equals' => 'Equals',
			'ne' => 'Not equals',
			'lt' => 'Less than',
			'lte' => 'Less than or equals',
			'gt' => 'Greater than',
			'gte' => 'Greater than or equals',
			'starts_with' => 'Starts with',
			'ends_with' => 'Ends with',
			'like' => 'Like',
		);
		$keys = array(
			'type' => 'Type',
			'called_number' => 'Called number',
			'calling_number' => 'Calling number',
			'aprice' => 'Aprice',
			'usagev' => 'Usagev',
			'usaget' => 'Usaget',
			'file' => 'File name',
		);

		$types = Billrun_Factory::config()->getConfigValue('admin.advancedOptions.types');
		$output = "<div class=\"controls controls-row\">
                               <select onchange='add_type(this, " . json_encode($types) . ")' name=\"manual_key[]\" class=\"span2\">";
		foreach ($keys as $manual_key => $manual_key_display) {
			$output.= "<option value=\"" . $manual_key . "\"" . ($key == $manual_key ? " selected" : "") . ">" . $manual_key_display . "</option>";
		}
		$output.= "</select>
                                <select name=\"manual_operator[]\" class=\"span2\">";
		foreach ($operators as $operator_key => $operator_display) {
			$output.="<option value=\"" . $operator_key . "\"" . ($operator == $operator_key ? " selected" : "") . ">" . $operator_display . "</option>";
		}
		$output.="</select>
			<input name=\"manual_value[]\" class=\"span2\" type=\"text\" placeholder=\"value\" value=\"" . (!is_null($value) && $type != 'date' ? $value : "") . "\"" . ($type == 'date' ? " style=\"display:none;\" disabled" : "") . ">
			<div class=\"input-append date\" id=\"datetimepicker_manual_operator\" data-date=\"" . (!is_null($value) && $type == 'date' ? $value : "") . "\" data-date-format=\"yyyy-MM-dd hh:mm:ss\"" . ($type == 'date' ? "" : " style=\"display:none;\"") . ">
							<input name=\"manual_value[]\" class=\"controls-row span2\" size=\"16\" type=\"text\" value=\"" . (!is_null($value) && $type == 'date' ? $value : "") . "\"" . ($type == 'date' ? "" : " disabled") . ">
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
		return isset($session->manual_value) && count($session->manual_value) > 0 && $session->manual_value[0] != '' && $session->manual_key[0] != '';
	}

	public static function getCsvFile($params) {

		$data_output = array();
		$daperator = ',';
		$c = $params['offset'];

		$data_output[] = '#' . $daperator;
		foreach ($params['columns'] as $value) {
			$data_output[] = $value . $daperator;
		}
		$data_output[] = PHP_EOL;
		foreach ($params['data'] as $item) {
			$data_output[] = '#' . ($c + 1) . $daperator;
			$c++;
			foreach ($params['columns'] as $h => $columnName) {
				$data = $item->get($h);

				if (($h == 'from' || $h == 'to' || $h == 'urt' || $h == 'notify_time') && $data) {
					// TODO: move he_IL to config 
					if (!empty($item["tzoffset"])) {
						// TODO change this to regex
						$timsetamp = $item['urt']->sec;
						$tzoffset = $item['tzoffset'];
						$sign = substr($tzoffset, 0, 1);
						$hours = substr($tzoffset, 1, 2);
						$minutes = substr($tzoffset, 3, 2);
						$time = $hours . ' hours ' . $minutes . ' minutes';
						if ($sign == "-") {
							$time .= ' ago';
						}
						$timsetamp = strtotime($time, $timsetamp);
						$zend_date = new Zend_Date($timsetamp);
						$zend_date->setTimezone('UTC');
						$data_output[] = $zend_date->toString("d/M/Y H:m:s") . $item['tzoffset'] . $daperator;
					} else {
						$zend_date = new Zend_Date($data->sec);
						$data_output[] = $zend_date->toString("d/M/Y H:m:s") . $daperator;
					}
				} else {
					$data_output[] = $data . $daperator;
				}
			}
			$data_output[] = PHP_EOL;
		}

		$output = implode("", $data_output);
		header("Cache-Control: max-age=0");
		header("Content-type: application/csv");
		header("Content-Disposition: attachment; filename=csv_export.csv");
		die($output);
	}

}
