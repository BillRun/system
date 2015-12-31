<?php

include('httpful.phar');

define('BILLRUN_URL', 'http://billrun/api/realtimeevent');

if(isset($_GET['imsi']) && !empty($_GET['imsi'])){
	$imsi = $_GET['imsi'];
} else {
	$imsi = '425030002438039';
}
if(isset($_GET['type']) && !empty($_GET['type'])){
	$type = $_GET['type'];
} else {
	//$type = 'call';
	$type = 'data';
}
$sid = array();

// connect to mongodb and select a database
$m = new MongoClient();
$db = $m->billing;
$db_subscribers = $db->subscribers;
$db_lines = $db->lines;
$db_lines->remove(array(),array("justOne" => false));


// Get Subscriber
$searchQuery = ['imsi' => $imsi];
$cursor = $db_subscribers->find($searchQuery);
foreach ($cursor as $document) {
	$sid[] = $document['sid'];
	echoLine("SID : " . $document['sid']);
}
$balance_before = getBalance();
echoLine("Balance before : " . $balance_before);




if($type == 'call') {
	$start = '<?xml version = "1.0" encoding = "UTF-8"?><request><api_name>start_call</api_name><calling_number>972502145131</calling_number><call_reference>013D221003</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>0390222222</dialed_digits><connected_number>0390222222</connected_number><event_type>2</event_type><service_key>61</service_key><vlr>972500000701</vlr><location_mcc>425</location_mcc><location_mnc>03</location_mnc><location_area>7201</location_area><location_cell>53643</location_cell><time_date>2015/08/13 11:59:03</time_date><call_type>x</call_type></request>';
	sendRequest($type, $start);
	echoLine("start call");
	$answer = '<?xml version = "1.0" encoding = "UTF-8"?><request><api_name>answer_call</api_name><calling_number>972502145131</calling_number><call_reference>013D221003</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>0390222222</dialed_digits><connected_number>0390222222</connected_number><time_date>2015/08/13 11:59:03.325</time_date><call_type>x</call_type></request>';
	sendRequest($type, $answer);
	echoLine("answer call");
	$reserve = '<?xml version = "1.0" encoding = "UTF-8"?><request><api_name>reservation_time</api_name><calling_number>972502145131</calling_number><call_reference>013D221003</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><time_date>2015/08/13 11:59:03.423</time_date></request>';
	sendRequest($type, $reserve);
	echoLine("reserve call");
	$release = '<?xml version = "1.0" encoding = "UTF-8"?><request><api_name>release_call</api_name><calling_number>972502145131</calling_number><call_reference>013D221003</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><time_date>2015/08/13 11:59:03.543</time_date><duration>4000</duration><scp_release_cause>mmm</scp_release_cause><isup_release_cause>nnn</isup_release_cause><call_leg>x</call_leg></request>';
	sendRequest($type, $release);
	echoLine("release call");
}

if($type == 'data') {
	$request = array(
		//"requestType" => "1",
		//"requestNum" => 1,
		"sessionId"	=>	"111",
		"eventTimeStamp" => "20151122",
		"imsi" => $imsi,
		"imei" => "3542010614744704",
		"msisdn" => "9725050500",
		"msccData" => array(
			array(
				"event" => "initial",
				"reportingReason" => "0",
				"serviceId" => "400700",
				"ratingGroup" => "92",
				"requestedUnits" => 1000,
				//"usedUnits" => 1000
			),
			"Service" => array(
				"PdnConnectionId" => "0",
				"PdpAddress" => "10.161.48.3",
				"CalledStationId" => "test-sacc.labpelephone.net.il",
				"MccMnc" => "42503",
				"GgsnAddress" => "91.135.99.226",
				"SgsnAddress" => "91.135.96.3",
				"ChargingId" => "0",
				"GPRSNegQoSProfile" => "0",
				"ChargingCharacteristics" => "0800",
				"PDPType" => "0",
				"SGSNMCCMNC" => "42503",
				"GGSNMCCMNC" => "0",
				"CGAddress" => "0.0.0.0",
				"NSAPI" => "5",
				"SessionStopIndicator" => "0",
				"SelectionMode" => "1",
				"RATType" => array("1"),
				"MSTimeZone" => array("128","0"),
				"ChargingRuleBaseName" => "0",
				"FilterId" => "0"
			)
		)
	);

		
	$request['requestType'] = "1";
	$request['requestNum'] = "1";
	sendRequest($type, json_encode($request));
	echoLine("Ini data");
	
	$request['requestType'] = "2";
	$request['requestNum'] = "2";
	$request['msccData'][0]['usedUnits'] = "10000";
	sendRequest($type, json_encode($request));
	echoLine("update data");
	
	$request['requestType'] = "3";
	$request['requestNum'] = "3";
	$request['msccData'][0]['usedUnits'] = "800";
	sendRequest($type, json_encode($request));
	echoLine("Final data");
}


$searchQuery = ['imsi' => $imsi];
$cursor = $db_lines->find($searchQuery);
$amount = 0;
$lines = array();
foreach ($cursor as $document) {
	$amount += $document['aprice'];
	$lines[] = "Line aprice : " . $document['aprice'];
}
echoList($lines, 'Lines:');
echoLine("Total aprice : " . $amount);


$balance_after = getBalance();
echoLine("Balance after : " . $balance_after);
echoLine("Balance Diff : " . ($balance_after - $balance_before));




function getBalance(){
	global $db, $sid;
	$amount = 0;
	// Get Balance
	$db_lines = $db->balances;
	$searchQuery = ["sid" => ['$in' => $sid]];
	$cursor = $db_lines->find($searchQuery);
	foreach ($cursor as $document) {
		$amount += $document['balance']['cost'];
	}
	return $amount;
}

function sendRequest($type, $data){
	$query = array();
	$query['XDEBUG_SESSION_START'] = 'netbeans-xdebug';
	$query['usaget'] = $type;
	$query['request'] = $data;
    $params = http_build_query ( $query );
    $URL = BILLRUN_URL . '?' . $params;
    $response = \Httpful\Request::get($URL)->send();
	if (strpos($response->headers->toArray()['content-type'], 'json') !== FALSE){
		$content = json_decode($response->body);
	} else {
		$content = $response->body;
	}
	//error_log(__LINE__ . ":" . __FUNCTION__ . ":: Data : " . print_r($response->hasErrors(), 1));
    return (!$response->hasErrors()) ? $content : 'ERROR' ;
}

function has_error($response){
		//error_log(__LINE__ . ":" . __FUNCTION__ . ":: Data : " . print_r($response, 1));
	if(empty($response)){
	}
	
	return (isset($response->success) && $response->success == FALSE) ? TRUE : FALSE;
}

function echoLine($line){
	echo "<p>" . $line . "</p>";
}

function echoList($items, $title = ''){
	if(!empty($title)){
		"<p>" . $title . "</p>";
	}
	echo "<ul>";
	foreach ($items as $item) {
		echo  "<li>" . $item . "</li>";
	}
	echo "<p>" . $line . "</p>";
	echo "</ul>";
}
?>