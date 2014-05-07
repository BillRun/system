function drawChart(data, options, target_div, chart_type, convert, immediate, dashboard_div, filter_control_options, formatOptions) {
	var real_chart_type, chart, dashboard, filter_control;
	// default convert is LineChart
	chart_type = typeof chart_type !== 'undefined' ? chart_type : 'LineChart';
	// default convert is false
	convert = typeof convert !== 'undefined' ? convert : 0;
	immediate = typeof immediate !== 'undefined' ? immediate : false;
	var filter = typeof dashboard_div !== 'undefined';
	var format = typeof formatOptions !== 'undefined';



	var graph_data;
	if (convert == 0) {
		graph_data = google.visualization.arrayToDataTable(data);
	} else if (convert == 1) {
		graph_data = new google.visualization.DataTable(data);
	} else if (convert == 2) {
		graph_data = data; // expects "data" to be of type "google.visualization.DataView"
	}

	if (format && convert!=2) { // Formatters can only be used with a DataTable; they cannot be used with a DataView (DataView objects are read-only). 
		for (var i=0; i<formatOptions.length; i++) {
			if (formatOptions[i][1]==0) { // "percent format"
				var formatter = new google.visualization.NumberFormat(
				{
					pattern:'#,###.##%'
				});
				formatter.format(graph_data, formatOptions[i][0]); // Apply formatter to column
			}
		}
	}

	
	switch (chart_type.toLowerCase()) {
		/* https://google-developers.appspot.com/chart/interactive/docs/gallery/piechart */
		case 'piechart':
			real_chart_type = "PieChart";
			break;
		/* https://google-developers.appspot.com/chart/interactive/docs/gallery/barchart */
		case 'barchart':
			real_chart_type = "BarChart";
			break;
		/* https://google-developers.appspot.com/chart/interactive/docs/gallery/columnchart */
		case 'columnchart':
			real_chart_type = "ColumnChart";
			break;
		/* https://google-developers.appspot.com/chart/interactive/docs/gallery/areachart */
		case 'areachart':
			real_chart_type = "AreaChart";
			break;
		/* https://google-developers.appspot.com/chart/interactive/docs/gallery/combochart */
		case 'combochart':
			real_chart_type = "ComboChart";
			break;
		/* https://developers.google.com/chart/interactive/docs/gallery/scatterchart */
		case 'scatterchart':
			real_chart_type = "ScatterChart";
			break;
		/* https://developers.google.com/chart/interactive/docs/gallery/annotatedtimeline */
		case 'annotatedtimeline':
			real_chart_type = "AnnotatedTimeLine";
			break;
		/* https://developers.google.com/chart/interactive/docs/gallery/candlestickchart */
		case 'candlestickchart':
			real_chart_type = "CandlestickChart";
			break;
		/* https://google-developers.appspot.com/chart/interactive/docs/gallery/linechart */
		case 'linechart':
		default:
			real_chart_type = "LineChart";
			break;
	}

	if (filter) {
		dashboard = new google.visualization.Dashboard(document.getElementById(dashboard_div));
		filter_control = new google.visualization.ControlWrapper(filter_control_options);
		chart = new google.visualization.ChartWrapper({
			'chartType': real_chart_type,
			'containerId': target_div,
			'options': options
		});
		dashboard.bind(filter_control, chart);
	}
	else {
		chart = eval("new google.visualization." + real_chart_type + "(document.getElementById(target_div))");
	}

	var draw = function() {
		if (filter) {
			dashboard.draw(graph_data);
		}
		else {
			chart.draw(graph_data, options);	
		}
	};

	if (google.visualization &&  eval("google.visualization." + real_chart_type)) {
		draw();
	}
	else {
		google.setOnLoadCallback(function() {
			draw();
		});
	}

}