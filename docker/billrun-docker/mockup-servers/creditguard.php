<?php

file_put_contents('redirect', "lin 38 at CG" . print_r($_SERVER["REQUEST_URI"], 1));



function getXml($token, $url, $total)
{
	return '<?xml version="1.0" encoding="ISO-8859-8"?><ashrait><response><command>doDeal</command><dateTime>2025-01-08 18:19</dateTime><requestId></requestId><tranId>112348371</tranId><result>000</result><message>עסקה תקינה</message><userMessage>עסקה תקינה</userMessage><additionalInfo></additionalInfo><version>2000</version><language>Heb</language><doDeal><status>000</status><statusText>עסקה תקינה</statusText><extendedStatus></extendedStatus><extendedStatusText></extendedStatusText><extendedUserMessage></extendedUserMessage><terminalNumber>0883111010</terminalNumber><cardBin>CG</cardBin><cardMask>CGGMPI</cardMask><cardLength>5</cardLength><cardNo>xGMPI</cardNo><cardName></cardName><cardExpiration></cardExpiration><cardType code=""></cardType><extendedCardType code="0">Credit</extendedCardType><blockedCard></blockedCard><lifeStyle></lifeStyle><customCardType></customCardType><creditCompany code=""></creditCompany><cardBrand code=""></cardBrand><cardAcquirer code=""></cardAcquirer><serviceCode></serviceCode><transactionType code="01">RegularDebit</transactionType><creditType code="1">RegularCredit</creditType><currency code="1">ILS</currency><baseCurrency></baseCurrency><baseAmount></baseAmount><transactionCode code="50">Phone</transactionCode><total>' . $total . '</total><firstPayment></firstPayment><periodicalPayment></periodicalPayment><numberOfPayments></numberOfPayments><paymentsInterest></paymentsInterest><mid>13607</mid><uniqueid>1736353163858</uniqueid><mpiValidation>AutoComm</mpiValidation><token>' . $token . '</token><mpiHostedPageUrl>' . $url . '</mpiHostedPageUrl><returnUrl></returnUrl><successUrl>http://localhost:8074/paymentgateways/okpage?name=CreditGuard</successUrl><errorUrl>http://localhost:8074/paymentgateways/okpage</errorUrl><cancelUrl></cancelUrl><clubId></clubId><validation code="106">TxnSetup</validation><idStatus code=""></idStatus><cvvStatus code=""></cvvStatus><authSource code="6">MPIServer</authSource><authNumber></authNumber><fileNumber></fileNumber><slaveTerminalNumber></slaveTerminalNumber><slaveTerminalSequence></slaveTerminalSequence><eci></eci><clientIp></clientIp><email></email><cavv code=""></cavv><user>0000000000044</user><addonData></addonData><supplierNumber></supplierNumber><id></id><shiftId1></shiftId1><shiftId2></shiftId2><shiftId3></shiftId3><shiftTxnDate></shiftTxnDate><cgUid>112348371</cgUid><cardHash></cardHash><customerData><userData1>1</userData1><userData2>SinglePayment</userData2></customerData><ashraitEmvData><mti>100</mti></ashraitEmvData><extendedTranCode></extendedTranCode><sendNotification></sendNotification></doDeal></response></ashrait>';
}
file_put_contents('creditguard.xml', "line 2 at CG" . print_r($_SERVER["REQUEST_URI"], 1));
if (preg_match('/^\/payment-gateways\/creditguard\/xpo\/Relay/', $_SERVER["REQUEST_URI"])) {
	$xml = simplexml_load_string($_POST['int_in']);
	file_put_contents('creditguard.xml', "line 5 at CG" . print_r($xml, 1));

	if ($xml->request->inquireTransactions->mpiTransactionId == 1) {
		echo "<?xml version='1.0' encoding='ISO-8859-8'?><ashrait><response><command>inquireTransactions</command><dateTime>2024-11-25 18:21</dateTime><requestId/><tranId/><result>462</result><message>ערך לא תקין בבקשה</message><userMessage>ערך לא תקין בבקשה</userMessage><additionalInfo>Bad value '' in header field 'version'</additionalInfo><version/><language>Heb</language><inquireTransactions><transactions/><totals><pageNumber/><pagesAmount/><queryResultId/><total>0</total><totalMatch/></totals></inquireTransactions></response></ashrait>";
	} elseif ($xml->request->doDeal->total > 0) {
		file_put_contents('creditguard1.xml', "line 18 at CG" . print_r($xml, 1));

		$mpiHostedPageUrl = $xml->request->doDeal->mpiHostedPageUrl;
		$token = $xml->request->doDeal->uniqueid;
		$total = $xml->request->doDeal->total;

		$successUrl = $xml->request->doDeal->successUrl;
		file_put_contents('params', "lin 20 at CG" . $successUrl);

		$errorUrl = $xml->request->doDeal->errorUrl;

		file_put_contents('creditguard.xml', "line 20 at CG" . print_r(getXml($token, $mpiHostedPageUrl, $total), 1));

		echo getXml($token, $successUrl, $total);

	} else {
		echo "ddddddddddddddddddddddddddddddddddddd";
	}

} elseif (preg_match('/payment-gateways\/creditguard\/iframe/', $_SERVER["REQUEST_URI"])) {
	// file_put_contents('redirect', " Matched iframe condition\n" . $redirectUrl, FILE_APPEND);
	// // file_put_contents('debug.log', "Matched iframe condition\n", FILE_APPEND);
	// $param = file_get_contents('CG_iframe/test1.json');
	// $paramData = json_decode($param, true);
	// $queryParams = http_build_query($paramData);
	// $redirectUrl = "http://10.103.0.0/paymentgateways/okpage?" . $queryParams;
	// file_put_contents('redirect', "lin 39 at CG" . $redirectUrl);
	// header("Location: " . $redirectUrl);
	// Debug logging
    file_put_contents('redirect.log', "Entered iframe condition\n", FILE_APPEND);
    
    // Read and decode JSON parameters
    $param = file_get_contents('CG_iframe/test1.json');
    if ($param === false) {
        file_put_contents('redirect.log', "Error reading JSON file\n", FILE_APPEND);
        exit("Error reading parameters");
    }
    
    $paramData = json_decode($param, true);
    if ($paramData === null) {
        file_put_contents('redirect.log', "Error decoding JSON: " . json_last_error_msg() . "\n", FILE_APPEND);
        exit("Error parsing parameters");
    }
    
    // Build redirect URL - using localhost instead of IP
    $queryParams = http_build_query($paramData);
    $redirectUrl = "http://10.103.0.0/paymentgateways/okpage?" . $queryParams;
    
    // Log the redirect URL
    file_put_contents('redirect.log', "Redirecting to: " . $redirectUrl . "\n", FILE_APPEND);
    
    // Set necessary headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: " . $redirectUrl);
    // exit();

} else {
	echo "ddddddddddddddddddddddddddddddddddddd";
	// file_put_contents('debug.log', "Not matched any condition\n", FILE_APPEND);
	// file_put_contents('redirect', "Not matched any condition\n",FILE_APPEND);
	// echo "Not matched any condition";
}
?>