<?php

class Admin_Graphs {

	public static function prepareGraph($wholesale_data, $xTitle, $xField, $yTitles, $yFields, array $params = array(), array $options = array()) {
		$output = new stdclass;

		if ($xField == 'carrier') {
			$output->chart_type = 'ColumnChart';
		} else {
			$output->chart_type = 'LineChart';
		}

		$data = array(array_merge(array($xTitle), $yTitles));
		foreach ($wholesale_data as $wholesale_data_row) {
			$data_row = array(isset($wholesale_data_row['group_by']) ? $wholesale_data_row['group_by'] : '');
			foreach ($yFields as $yField) {
				$data_row[] = isset($wholesale_data_row[$yField]) ? $wholesale_data_row[$yField] : '';
			}
			$data[] = $data_row;
		}
//		$data = array_slice($data, 0, 10);
		$output->data = $data;

		$output->target_div = array(
			"id" => isset($params['graph_id']) ? $params['graph_id'] : "div_graphs",
			"class" => isset($params['graph_class']) ? $params['graph_class'] : "billrun-graph",
		);

		if (empty($options)) {
			$output->options = array(
//				'interpolateNulls' => true,
				'hAxis' => array(
					'showTextEvery' => 1,
					'slantedText' => true,
//					'maxTextLines' => 10,
				),
				'axisTitlesPosition' => 'in',
//				'legend' => array('position' => "none"),
				'chartArea' => array(
//					'top' => 5,
					'left' => 60,
//					'width' => '82%',
				),
//				'legend' => array(
//					'textStyle' => array(
//						'fontSize' => '10',
//					),
//				),
				'width' => 1500,
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
					),
					1 => array(
						'targetAxisIndex' => 1,
					),
				),
				'colors' => array("#3366cc", "#dc3912", "#ff9900", "#109618", "#990099", "#0099c6", "#dd4477", "#66aa00", "#b82e2e", "#316395", "#994499", "#22aa99", "#aaaa11", "#6633cc", "#e67300", "#8b0707", "#651067", "#329262", "#5574a6", "#3b3eac", "#b77322", "#16d620", "#b91383", "#f4359e", "#9c5935", "#a9c413", "#2a778d", "#668d1c", "#bea413", "#0c5922", "#743411")
			);
		} else {
			$output->options = $options;
		}
		return $output;
	}

	public static function printGraph($graph_metadata, $echo_output = true, $data_type = 1) {
		// backword compatability
		if (!isset($graph_metadata->chart_type)) {
			$graph_metadata->chart_type = 'combochart';
		}

		$output = '<div class="' . $graph_metadata->target_div['class'] . '" id="' . $graph_metadata->target_div['id'] . '">'
				. '<div class="graph loading"></div>'
				. '</div>
		<script type="text/javascript">
        drawChart(' . self::outputGoogleData($graph_metadata->data) . ', '
				. json_encode($graph_metadata->options) . ', \'' . $graph_metadata->target_div['id']
				. '\', \'' . $graph_metadata->chart_type . '\', 0, true)';
//		if (isset($graph_metadata->format_options)) {
//			$output.=', ' . json_encode($graph_metadata->format_options);
//		}
//		$output.=');
		$output.='</script>';
		return $output;
	}

	public static function outputGoogleData($data) {
//		$ret = preg_replace("/(('|\")%%|%%(\"|'))/", '', json_encode($data));
		$ret = json_encode($data);
//		str_replace("'undefined'", "undefined", $ret);
		return $ret;
	}

}