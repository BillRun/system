<?php

class Admin_Graphs {

	public static function prepareGraph($wholesale_data, $xTitle, $xField, $yFields, array $params = array(), array $options = array(), $include_filter_div = true) {
		$output = new stdclass;
		$graph_id = isset($params['graph_id']) ? $params['graph_id'] : "div_graphs";

		$output->chart_type = 'ComboChart';

		$output->data['cols'] = array(
			array('id' => $xField == 'carrier' ? 'carrier' : 'date', 'label' => $xTitle, 'type' => $xField == 'carrier' ? 'string' : 'date'),
		);
		foreach ($yFields as $usage_type => $fields) {
			foreach ($fields as $field) {
				$output->data['cols'][] = array('id' => ($usage_type . '_' . $field['value']), 'label' => ($usage_type . ' ' . $field['display']), 'type' => 'number');
			}
		}

		foreach ($wholesale_data as $usage_type => $columns) {
			foreach ($columns as $wholesale_data_row) {
				$values = array();
				if (isset($wholesale_data_row['group_by'])) {
					if ($xField == 'dayofmonth') {
						$day_of_month = DateTime::createFromFormat('Y-m-d', $wholesale_data_row['group_by']);
						$values[] = array('v' => 'Date(' . $day_of_month->format('Y') . ',' . ($day_of_month->format('m') - 1) . ',' . $day_of_month->format('d') . ')');
					} else {
						$values[] = array('v' => $wholesale_data_row['group_by']);
					}
				} else {
					$values[] = array('v' => '');
				}
				foreach ($yFields as $usage_type2 => $columns2) {
					foreach ($columns2 as $yField) {
						if ($usage_type == $usage_type2) {
							$values[] = array('v' => isset($wholesale_data_row[$yField['value']]) ? $wholesale_data_row[$yField['value']] : null);
						} else {
							$values[] = array('v' => null);
						}
					}
				}
				$output->data['rows'][] = array('c' => $values);
			}
		}

		$output->target_div = array(
			"id" => $graph_id,
			"class" => isset($params['graph_class']) ? $params['graph_class'] : "billrun-graph",
		);


		if (empty($options)) {
			$output->options = array(
//				'interpolateNulls' => true,
				'hAxis' => array(
					'showTextEvery' => 1,
					'slantedText' => $xField == 'carrier' ? true : false,
//					'maxTextLines' => 10,
					'textStyle' => array(
						'fontSize' => $xField == 'carrier' ? 9 : 14,
					),
				),
//				'seriesType' => $xField == 'carrier' ? 'ColumnChart' : 'LineChart',
//				'seriesType' => $xField == 'carrier' ? 'bars' : 'line',
				'axisTitlesPosition' => 'in',
//				'legend' => array('position' => "none"),
				'chartArea' => array(
//					'top' => 5,
					'left' => 80,
					'width' => '82%',
					'height' => '50%',
				),
				'legend' => array(
					'textStyle' => array(
						'fontSize' => '14',
					),
				),
				'width' => 1000,
				'vAxes' => array(
					0 => array(
						'textStyle' => array(
							'color' => '#3366cc',
						),
					),
					1 => array(
						'textStyle' => array(
							'color' => '#dc3912',
						),
					),
				),
				'series' => array(
					0 => array(
						'targetAxisIndex' => 0,
						'type' => $xField == 'carrier' ? 'bars' : 'line',
					),
					1 => array(
						'targetAxisIndex' => 1,
						'type' => $xField == 'carrier' ? 'bars' : 'line',
					),
				),
				'colors' => array("#3366cc", "#dc3912", "#ff9900", "#109618", "#990099", "#0099c6", "#dd4477", "#66aa00", "#b82e2e", "#316395", "#994499", "#22aa99", "#aaaa11", "#6633cc", "#e67300", "#8b0707", "#651067", "#329262", "#5574a6", "#3b3eac", "#b77322", "#16d620", "#b91383", "#f4359e", "#9c5935", "#a9c413", "#2a778d", "#668d1c", "#bea413", "#0c5922", "#743411")
			);
		} else {
			$output->options = $options;
		}

		if ($include_filter_div && $xField == 'dayofmonth') {
			$output->dashboard_div = array(
				'id' => $graph_id . '_dashboard_div'
			);

			$output->filter_div = array(
				'id' => $graph_id . '_filter_div',
				'class' => 'graph-filter',
			);

			$output->filter_options = array(
				'controlType' => 'ChartRangeFilter',
				'containerId' => $graph_id . '_filter_div',
				'options' => array(
					// Filter by the date axis.
					'filterColumnIndex' => 0,
					'ui' => array(
						'chartType' => 'ComboChart',
//						'chartOptions' => $output->options,
						'chartOptions' => $output->options,
						'chartView' => array(
							'columns' => array(0, 1, 2),
						),
						// 1 day in milliseconds = 24 * 60 * 60 * 1000 = 86,400,000
						'minRangeSize' => 86400000,
//						'height' => '50'
					)
				)
			);
			if ($xField == 'dayofmonth') {
//				$output->filter_options['options']['ui']['chartOptions']['chartArea']['height'] = '70%';
				$output->filter_options['options']['ui']['chartOptions']['height'] = '80';
			}
		}
		return $output;
	}

	public static function printGraph($graph_metadata, $echo_output = true, $data_type = 1, $tabId = null, $popupId = null) {
		$output = '';
		if (isset($graph_metadata->dashboard_div)) {
			$output.='<div id="' . $graph_metadata->dashboard_div['id'] . '">';
		}
		$output .= '<div id="' . $graph_metadata->target_div['id'] . '" class="' . $graph_metadata->target_div['class'] . '">'
			. '<div class="graph loading"></div>'
			. '</div>';
		if (isset($graph_metadata->filter_div)) {
			$output.= '<div id="' . $graph_metadata->filter_div['id'] . '" class="' . $graph_metadata->filter_div['class'] . '"></div></div>';
		}
		$output .= '<script type="text/javascript">';
		$output .= '$(function() {';
		if (!is_null($tabId)) {
			$output.='$(\'#' . $tabId . '\').on("shown.bs.tab", function(e) {if ($(".graph.loading",$($(e.delegateTarget).attr("href"))).length)';
		} else if (!is_null($popupId)) {
			$output .= '$("#' . $popupId . '").on("shown.bs.modal", function(e) {$("#' . $popupId . '").off("shown.bs.modal");';
		}
		$output.='drawChart(' . (isset($graph_metadata->ajax_url) ? "data" : self::outputGoogleData($graph_metadata->data)) . ', '
			. json_encode($graph_metadata->options) . ', \'' . $graph_metadata->target_div['id']
			. '\', \'' . $graph_metadata->chart_type . '\', ' . ($data_type ? 1 : 0) . ', ' . (isset($graph_metadata->ajax_url) ? 'true' : 'false');
		if (isset($graph_metadata->dashboard_div) && isset($graph_metadata->filter_options)) {
			$output.=', \'' . $graph_metadata->dashboard_div['id'] . '\', ' . json_encode($graph_metadata->filter_options);
		} else {
			$output.=', undefined, undefined';
		}
		if (isset($graph_metadata->format_options)) {
			$output.=', ' . json_encode($graph_metadata->format_options);
		}
		$output.=');';
		if (!is_null($tabId) || !is_null($popupId)) {
			$output.='});';
		}
		$output.='})';
		$output.='</script>';

		if ($echo_output) {
			echo $output;
			return;
		}
		return $output;
	}

	public static function outputGoogleData($data) {
//		$ret = preg_replace("/(('|\")%%|%%(\"|'))/", '', json_encode($data));
		$ret = json_encode($data);
//		str_replace("'undefined'", "undefined", $ret);
		return $ret;
	}

}
