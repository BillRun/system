<?php

/**
 * Generate XML response for setup transaction
 */
function getXmlResponse($token, $url, $total)
{
   
    $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';
    $paymentType = manageTemporaryFiles('read', 'payment_type.txt') ?: 'SinglePayment';
    return '<?xml version="1.0" encoding="ISO-8859-8"?><ashrait><response><command>doDeal</command><dateTime>2025-01-08 18:19</dateTime><requestId></requestId><tranId>112348371</tranId><result>000</result><message>עסקה תקינה</message><userMessage>עסקה תקינה</userMessage><additionalInfo></additionalInfo><version>2000</version><language>Heb</language><doDeal><status>000</status><statusText>עסקה תקינה</statusText><extendedStatus></extendedStatus><extendedStatusText></extendedStatusText><extendedUserMessage></extendedUserMessage><terminalNumber>0883111010</terminalNumber><cardBin>CG</cardBin><cardMask>CGGMPI</cardMask><cardLength>5</cardLength><cardNo>xGMPI</cardNo><cardName></cardName><cardExpiration></cardExpiration><cardType code=""></cardType><extendedCardType code="0">Credit</extendedCardType><blockedCard></blockedCard><lifeStyle></lifeStyle><customCardType></customCardType><creditCompany code=""></creditCompany><cardBrand code=""></cardBrand><cardAcquirer code=""></cardAcquirer><serviceCode></serviceCode><transactionType code="01">RegularDebit</transactionType><creditType code="1">RegularCredit</creditType><currency code="1">ILS</currency><baseCurrency></baseCurrency><baseAmount></baseAmount><transactionCode code="50">Phone</transactionCode><total>' . $total . '</total><firstPayment></firstPayment><periodicalPayment></periodicalPayment><numberOfPayments></numberOfPayments><paymentsInterest></paymentsInterest><mid>13607</mid><uniqueid>1736353163858</uniqueid><mpiValidation>AutoComm</mpiValidation><token>' . $token . '</token><mpiHostedPageUrl>http://ppsuat.mockup' . '?txId=' . $token . '</mpiHostedPageUrl><returnUrl></returnUrl><successUrl>http://billrun-nginx:80/paymentgateways/okpage?name=CreditGuard</successUrl><errorUrl>http://billrun-nginx:80/paymentgateways/okpage</errorUrl><cancelUrl></cancelUrl><clubId></clubId><validation code="106">TxnSetup</validation><idStatus code=""></idStatus><cvvStatus code=""></cvvStatus><authSource code="6">MPIServer</authSource><authNumber></authNumber><fileNumber></fileNumber><slaveTerminalNumber></slaveTerminalNumber><slaveTerminalSequence></slaveTerminalSequence><eci></eci><clientIp></clientIp><email></email><cavv code=""></cavv><user>0000000000044</user><addonData></addonData><supplierNumber></supplierNumber><id></id><shiftId1></shiftId1><shiftId2></shiftId2><shiftId3></shiftId3><shiftTxnDate></shiftTxnDate><cgUid>112348371</cgUid><cardHash></cardHash><customerData><userData1>' . $userData1 . '</userData1><userData2>' . $paymentType . '</userData2></customerData><ashraitEmvData><mti>100</mti></ashraitEmvData><extendedTranCode></extendedTranCode><sendNotification></sendNotification></doDeal></response></ashrait>';
}

function manageTemporaryFiles($action, $filename, $data = null)
{
    $tempDir = 'temp/';  

    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $fullPath = $tempDir . $filename;

    switch ($action) {
        case 'write':
            return file_put_contents($fullPath, $data);
        case 'read':
            return file_exists($fullPath) ? file_get_contents($fullPath) : null;
        case 'clean':
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            break;
        case 'clean_all':
            array_map('unlink', glob($tempDir . "*"));
            break;
    }
}

/**
 * Generate rejection response for transaction
 */
function getRejectResponse()
{
    $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';
    return '<?xml version="1.0" encoding="ISO-8859-8"?><ashrait><response><command>doDeal</command><dateTime>2025-01-08 18:19</dateTime><requestId></requestId><tranId>112348371</tranId><result>003</result><message>סירוב</message><userMessage>סירוב</userMessage><additionalInfo>Transaction Rejected - Amount too high</additionalInfo><version>2000</version><language>Heb</language><doDeal><status>003</status><statusText>סירוב</statusText><extendedStatus></extendedStatus><extendedStatusText></extendedStatusText><terminalNumber>0883111010</terminalNumber><cardId></cardId><cardExpiration></cardExpiration><cardType code=""></cardType><creditType code="1">RegularCredit</creditType><currency code="1">ILS</currency><transactionCode code="50">Phone</transactionCode><total>2000000</total><validation code="3">Reject</validation><authSource code="2">CreditCompany</authSource><customerData><userData1>' . $userData1 . '</userData1><userData2>SinglePayment</userData2></customerData></doDeal></response></ashrait>';
}

/**
 * Generate error response for failed validation
 */
function getEnableResponse()
{
    return '<?xml version="1.0" encoding="ISO-8859-8"?><ashrait><response><command>inquireTransactions</command><dateTime>2024-11-25 18:21</dateTime><requestId/><tranId/><result>462</result><message>ערך לא תקין בבקשה</message><userMessage>ערך לא תקין בבקשה</userMessage><additionalInfo>Bad value \'\' in header field \'version\'</additionalInfo><version/><language>Heb</language><inquireTransactions><transactions/><totals><pageNumber/><pagesAmount/><queryResultId/><total>0</total><totalMatch/></totals></inquireTransactions></response></ashrait>';
}

/**
 * Generate transaction details response
 */
function getTransactionDetailsResponse($token, $total)
{
    
    $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';
    $paymentType = manageTemporaryFiles('read', 'payment_type.txt') ?: 'SinglePayment';
    $cardExpiration = date('my', strtotime('+3 years'));

    $xmlResponse = <<<EOT
 <?xml version='1.0' encoding='ISO-8859-8'?>
 <ashrait>
    <response>
        <command>inquireTransactions</command>
        <dateTime>2025-01-09 17:28</dateTime>
        <requestId/>
        <tranId>{$token}</tranId>
        <result>000</result>
        <message>עסקה תקינה</message>
        <userMessage>עסקה תקינה</userMessage>
        <additionalInfo/>
        <version>2000</version>
        <language>Heb</language>
        <inquireTransactions>
            <row>
                <mpiTransactionId>{$token}</mpiTransactionId>
                <uniqueid>1736436324285</uniqueid>
                <amount>{$total}</amount>
                <currency>ILS</currency>
                <authNumber>1368022</authNumber>
                <cardId>1022273188555607</cardId>
                <cardExpiration>{$cardExpiration}</cardExpiration>
                <languageCode>HE</languageCode>
                <statusCode>0</statusCode>
                <statusText>SUCCEEDED</statusText>
                <errorCode>00</errorCode>
                <errorText>הצלחה</errorText>
                <cgGatewayResponseCode>000</cgGatewayResponseCode>
                <cgGatewayResponseText>עסקה תקינה</cgGatewayResponseText>
                <cgGatewayResponseXML>
                    <ashrait>
                        <response>
                            <command>doDeal</command>
                            <dateTime>2025-01-10 12:39</dateTime>
                            <requestId/>
                            <tranId>{$token}</tranId>
                            <result>000</result>
                            <message>עסקה תקינה</message>
                            <userMessage>עסקה תקינה</userMessage>
                            <additionalInfo>Host Result Remote 00-SUCCESS </additionalInfo>
                            <version>2000</version>
                            <language>Heb</language>
                            <doDeal>
                                <status>000</status>
                                <statusText>עסקה תקינה</statusText>
                                <terminalNumber>0883111010</terminalNumber>
                                <cardId>1022273188555608</cardId>
                                <cardBin>532610</cardBin>
                                <cardMask>532610******5606</cardMask>
                                <cardLength>16</cardLength>
                                <cardNo>XXXXXXXXXXXX5606</cardNo>
                                <cardName>יורוקרד מסטרקרד</cardName>
                                <cardExpiration>1228</cardExpiration>
                                <total>{$total}</total>
                                <customerData>
                                    <userData1>{$userData1}</userData1>
                                    <userData2>{$paymentType}</userData2>
                                </customerData>
                            </doDeal>
                        </response>
                    </ashrait>
                </cgGatewayResponseXML>
            </row>
        </inquireTransactions>
    </response>
 </ashrait>
 EOT;
    return $xmlResponse;
}



// for J5
function getRecurringResponse($cardId, $cardExpiration, $terminalNumber)
{
    $cardExpiration = date('my', strtotime('+3 years'));
    $response = '<?xml version="1.0" encoding="ISO-8859-8"?>' .
        '<ashrait>' .
        '<response>' .
        '<command>doDeal</command>' .
        '<dateTime>' . date('Y-m-d H:i') . '</dateTime>' .
        '<requestId/>' .
        '<tranId>112426820</tranId>' .
        '<result>000</result>' .
        '<message>Permitted transaction</message>' .
        '<userMessage>Permitted transaction</userMessage>' .
        '<additionalInfo>Host Result Remote 00-SUCCESS</additionalInfo>' .
        '<version>2000</version>' .
        '<language>Eng</language>' .
        '<doDeal>' .
        '<status>000</status>' .
        '<statusText>Permitted transaction</statusText>' .
        '<extendedStatus/>' .
        '<extendedStatusText/>' .
        '<extendedUserMessage/>' .
        '<terminalNumber>' . $terminalNumber . '</terminalNumber>' .
        '<cardId>' . $cardId . '</cardId>' .
        '<cardBin>532610</cardBin>' .
        '<cardMask>532610******5606</cardMask>' .
        '<cardLength>16</cardLength>' .
        '<cardNo>xxxxxxxxxxxx5606</cardNo>' .
        '<cardName/>' .
        '<cardExpiration>' . $cardExpiration . '</cardExpiration>' .
        '<cardType code="00">Local</cardType>' .
        '<extendedCardType code="0">Credit</extendedCardType>' .
        '<blockedCard/>' .
        '<lifeStyle/>' .
        '<customCardType/>' .
        '<creditCompany code="1">Isracard</creditCompany>' .
        '<cardBrand code="1">Mastercard</cardBrand>' .
        '<cardAcquirer code="1">Isracard</cardAcquirer>' .
        '<serviceCode/>' .
        '<transactionType code="11">RecurringDebit</transactionType>' .
        '<creditType code="1">RegularCredit</creditType>' .
        '<currency code="1">ILS</currency>' .
        '<baseCurrency/>' .
        '<baseAmount/>' .
        '<transactionCode code="50">Phone</transactionCode>' .
        '<total>100</total>' .
        '<firstPayment/>' .
        '<periodicalPayment/>' .
        '<numberOfPayments/>' .
        '<clubId/>' .
        '<validation code="5">Verify</validation>' .
        '<idStatus code="0">Absent</idStatus>' .
        '<cvvStatus code="0">Absent</cvvStatus>' .
        '<authSource code="2">CreditCompany</authSource>' .
        '<authNumber>1376556</authNumber>' .
        '<fileNumber/>' .
        '<slaveTerminalNumber/>' .
        '<slaveTerminalSequence/>' .
        '<eci/>' .
        '<clientIp/>' .
        '<email/>' .
        '<cavv code=""/>' .
        '<user/>' .
        '<addonData/>' .
        '<supplierNumber>0071506</supplierNumber>' .
        '<id/>' .
        '<shiftId1/>' .
        '<shiftId2/>' .
        '<shiftId3/>' .
        '<shiftTxnDate/>' .
        '<cgUid>112426820</cgUid>' .
        '<cardHash/>' .
        '<acquirerData>' .
        '<gateway>AshraitEmv</gateway>' .
        '<acquirerTranType>11</acquirerTranType>' .
        '<mcc>0242</mcc>' .
        '<acquirerResponseId>501309376556</acquirerResponseId>' .
        '<avsResponse code="0">Absent</avsResponse>' .
        '<acquirerTranCode>50</acquirerTranCode>' .
        '</acquirerData>' .
        '<ashraitEmvData>' .
        '<recurringTotalNo>999</recurringTotalNo>' .
        '<recurringFrequency>04</recurringFrequency>' .
        '<recurringNo>000</recurringNo>' .
        '<recurringUniqueRef>112426820</recurringUniqueRef>' .
        '<uid>25011309020608831108205</uid>' .
        '<authCodeCreditCompany code="1">CreditCompanyAuthorized</authCodeCreditCompany>' .
        '<idFlag>0</idFlag>' .
        '<manufId>CGD</manufId>' .
        '<cvvFlag>0</cvvFlag>' .
        '<manufUse>001101</manufUse>' .
        '<ashVersion>x</ashVersion>' .
        '<ashTermType>0</ashTermType>' .
        '<emvResponseCode>00</emvResponseCode>' .
        '<deviceStatus>1111000000</deviceStatus>' .
        '<ashReasonText>KARTIS_HASUM, BAKASHA_LEISHUR_LELO_ISKA, ITCHUL_HORAAT_KEVA</ashReasonText>' .
        '<authCodeAcquirer code="0">NoAuthNumber</authCodeAcquirer>' .
        '<isDoReverseDeal>0</isDoReverseDeal>' .
        '<mti>100</mti>' .
        '</ashraitEmvData>' .
        '<extendedTranCode/>' .
        '<sendNotification/>' .
        '</doDeal>' .
        '</response>' .
        '</ashrait>';

    return iconv('UTF-8', 'ISO-8859-8', $response);
}
/**
 * Handle payment gateway relay requests
 */
function handlePaymentGatewayRelay($xml)
{
    //manageTemporaryFiles('clean_all', ''); 

    // Enable CG terminal configuration
    if ($xml->request->inquireTransactions->mpiTransactionId == 1) {
        echo getEnableResponse();
    }
    // Handle doDeal request
    elseif ($xml->request->doDeal->total > 100) {
        $total = $xml->request->doDeal->total;
        $token = $total / 100;
        $successUrl = $xml->request->doDeal->successUrl;

        // Check for tokenize request
        if (isset($xml->request->doDeal->paymentPageData)) {
            $paymentType = isset($xml->request->doDeal->customerData->userData2) ?
                (string) $xml->request->doDeal->customerData->userData2 : 'SinglePayment';
            manageTemporaryFiles('write', 'payment_type.txt', $paymentType);
        }

        //TODO make it work 
        // Check if amount is over limit
        if ($total > 200000000) {
            echo getRejectResponse();
            return;
        }

        echo getXmlResponse($token, $successUrl, $total);
    }
    // Handle transaction query
    elseif (
        $xml->request->command == 'inquireTransactions' &&
        $xml->request->inquireTransactions->mpiValidation == 'Token'
    ) {
        $token = $xml->request->inquireTransactions->mpiTransactionId;
        $total = $token * 100;
        echo getTransactionDetailsResponse($token, $total);
    }
    // handling for recurring transactions
    elseif ($xml->request->doDeal->transactionType == "RecurringDebit") {
        //  $xml->request->doDeal->transactionType == 'RecurringDebit'

        $cardId = (string) $xml->request->doDeal->cardId;
        $cardExpiration = (string) $xml->request->doDeal->cardExpiration;
        $terminalNumber = (string) $xml->request->doDeal->terminalNumber;
        file_put_contents('yoyo.txt', $xml);

        echo getRecurringResponse($cardId, $cardExpiration, $terminalNumber);
        return;
    } else {
        echo "ddd";
    }
}

/**
 * Handle iframe redirection
 */
function handleIframe()
{
    ob_clean();
    ob_start();
    $paymentType = manageTemporaryFiles('read', 'payment_type.txt') ?: 'SinglePayment';
    manageTemporaryFiles('write', 'temp_userData1.txt', $_GET['aid']);
    //    $paymentType = file_exists('payment_type.txt') ? file_get_contents('payment_type.txt') : 'SinglePayment';

    //    file_put_contents('temp_userData1.txt', $_GET['aid']);
    $params = [
        "name" => "CreditGuard",
        'txId' => $_GET['txid'],
        'uniqueID' => time(),
        'errorCode' => '000',
        'errorText' => urlencode('עסקה תקינה'),
        "ErrorCode" => "000",
        'lang' => 'HE',
        "ErrorText" => urlencode("עסקה תקינה"),
        'cardToken' => '1022273188555607',
        'cardExp' => '1225',
        'personalId' => '',
        'cardMask' => '532610******5606',
        'authNumber' => '1368022',
        'responseMAC' => 'abc123',
        "numberOfPayments" => "0",
        "firstPayment" => "",
        "periodicalPayment" => "",
        "cgUid" => "112358951",
        "userData2" => $paymentType,
        "userData1" => $_GET['aid']
    ];

    $redirectUrl = "http://billrun-nginx:80/paymentgateways/okpage?" . http_build_query($params);

    ob_end_clean();
    header('Location: ' . $redirectUrl);
    exit();
}

// Main routing logic
if (preg_match('/^\/payment-gateways\/creditguard\/xpo\/Relay/', $_SERVER["REQUEST_URI"])) {
    $xml = simplexml_load_string($_POST['int_in']);
    handlePaymentGatewayRelay($xml);
} elseif (preg_match('/payment-gateways\/creditguard\/iframe/', $_SERVER["REQUEST_URI"])) {
    handleIframe();
} else {
    echo "ddddddddddddddddddddddddddddddddddddd";
}