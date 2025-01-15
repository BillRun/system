<?php



/**
 * Generate XML response for setup transaction
 */
function getXmlResponse($token, $url, $total, $creditType, $numberOfPayments = null)
{
    manageTemporaryFiles('write', 'temp_creditType.txt', $creditType);
    manageTemporaryFiles('write', 'temp_numberOfPayments.txt', $numberOfPayments);
    $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';
    $paymentType = manageTemporaryFiles('read', 'payment_type.txt') ?: 'SinglePayment';
    return '<?xml version="1.0" encoding="ISO-8859-8"?><ashrait><response><command>doDeal</command><dateTime>2025-01-08 18:19</dateTime><requestId></requestId><tranId>112348371</tranId><result>000</result><message>עסקה תקינה</message><userMessage>עסקה תקינה</userMessage><additionalInfo></additionalInfo><version>2000</version><language>Heb</language><doDeal><status>000</status><statusText>עסקה תקינה</statusText><extendedStatus></extendedStatus><extendedStatusText></extendedStatusText><extendedUserMessage></extendedUserMessage><terminalNumber>0883111010</terminalNumber><cardBin>CG</cardBin><cardMask>CGGMPI</cardMask><cardLength>5</cardLength><cardNo>xGMPI</cardNo><cardName></cardName><cardExpiration></cardExpiration><cardType code=""></cardType><extendedCardType code="0">Credit</extendedCardType><blockedCard></blockedCard><lifeStyle></lifeStyle><customCardType></customCardType><creditCompany code=""></creditCompany><cardBrand code=""></cardBrand><cardAcquirer code=""></cardAcquirer><serviceCode></serviceCode><transactionType code="01">RegularDebit</transactionType><creditType code="1">' . $creditType . '</creditType><currency code="1">ILS</currency><baseCurrency></baseCurrency><baseAmount></baseAmount><transactionCode code="50">Phone</transactionCode><total>' . $total . '</total><firstPayment></firstPayment><periodicalPayment></periodicalPayment><numberOfPayments>' . $numberOfPayments . '</numberOfPayments><paymentsInterest></paymentsInterest><mid>13607</mid><uniqueid>1736353163858</uniqueid><mpiValidation>AutoComm</mpiValidation><token>' . $token . '</token><mpiHostedPageUrl>http://ppsuat.mockup' . '?txId=' . $token . '</mpiHostedPageUrl><returnUrl></returnUrl><successUrl>http://billrun-nginx:80/paymentgateways/okpage?name=CreditGuard</successUrl><errorUrl>http://billrun-nginx:80/paymentgateways/okpage</errorUrl><cancelUrl></cancelUrl><clubId></clubId><validation code="106">TxnSetup</validation><idStatus code=""></idStatus><cvvStatus code=""></cvvStatus><authSource code="6">MPIServer</authSource><authNumber></authNumber><fileNumber></fileNumber><slaveTerminalNumber></slaveTerminalNumber><slaveTerminalSequence></slaveTerminalSequence><eci></eci><clientIp></clientIp><email></email><cavv code=""></cavv><user>0000000000044</user><addonData></addonData><supplierNumber></supplierNumber><id></id><shiftId1></shiftId1><shiftId2></shiftId2><shiftId3></shiftId3><shiftTxnDate></shiftTxnDate><cgUid>112348371</cgUid><cardHash></cardHash><customerData><userData1>' . $userData1 . '</userData1><userData2>' . $paymentType . '</userData2></customerData><ashraitEmvData><mti>100</mti></ashraitEmvData><extendedTranCode></extendedTranCode><sendNotification></sendNotification></doDeal></response></ashrait>';
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
function getErrorXmlResponse($userData1, $message = '', $errorCode = '401')
{
    $dateTime = date('Y-m-d H:i');
    return "<?xml version='1.0' encoding='ISO-8859-8'?>" .
        "<ashrait>" .
        "<response>" .
        "<command>doDeal</command>" .
        "<dateTime>{$dateTime}</dateTime>" .
        "<requestId/>" .
        "<tranId>112436464</tranId>" .
        "<result>{$errorCode}</result>" .
        "<message>תווים אסורים במחרוזת INT_IN</message>" .
        "<userMessage>נא לפנות למנהל המערכת ולמסור את קוד התשובה</userMessage>" .
        "<additionalInfo>{$message}</additionalInfo>" .
        "<version>2000</version>" .
        "<language>Heb</language>" .
        "<doDeal>" .
        "<status>{$errorCode}</status>" .
        "<statusText>תווים אסורים במחרוזת INT_IN</statusText>" .
        "<extendedStatus/>" .
        "<extendedStatusText/>" .
        "<extendedUserMessage/>" .
        "<terminalNumber>0883111010</terminalNumber>" .
        "<cardBin>CG</cardBin>" .
        "<cardMask>CGGMPI</cardMask>" .
        "<cardLength>5</cardLength>" .
        "<cardName/>" .
        "<cardExpiration/>" .
        "<cardType code=\"\"/>" .
        "<creditCompany code=\"\"/>" .
        "<cardBrand code=\"\"/>" .
        "<cardAcquirer code=\"\"/>" .
        "<serviceCode/>" .
        "<transactionType code=\"01\">RegularDebit</transactionType>" .
        "<creditType code=\"1\">RegularCredit</creditType>" .
        "<currency code=\"1\">ILS</currency>" .
        "<baseCurrency/>" .
        "<baseAmount/>" .
        "<transactionCode code=\"50\">Phone</transactionCode>" .
        "<total/>" .
        "<firstPayment/>" .
        "<periodicalPayment/>" .
        "<numberOfPayments/>" .
        "<clubId/>" .
        "<validation code=\"106\">TxnSetup</validation>" .
        "<idStatus code=\"\"/>" .
        "<cvvStatus code=\"\"/>" .
        "<authSource code=\"\"/>" .
        "<authNumber/>" .
        "<fileNumber/>" .
        "<slaveTerminalNumber/>" .
        "<slaveTerminalSequence/>" .
        "<eci/>" .
        "<clientIp/>" .
        "<email/>" .
        "<cavv code=\"\"/>" .
        "<user>0000000000007</user>" .
        "<addonData/>" .
        "<supplierNumber/>" .
        "<id/>" .
        "<shiftId1/>" .
        "<shiftId2/>" .
        "<shiftId3/>" .
        "<shiftTxnDate/>" .
        "<cgUid/>" .
        "<cardHash/>" .
        "<customerData>" .
        "<userData1>{$userData1}</userData1>" .
        "<userData2>SinglePayment</userData2>" .
        "</customerData>" .
        "<ashraitEmvData>" .
        "<mti>100</mti>" .
        "</ashraitEmvData>" .
        "<extendedTranCode/>" .
        "<sendNotification/>" .
        "</doDeal>" .
        "</response>" .
        "</ashrait>";
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

function calculateFirstPayment($total, $numberOfPayments)
{
    if ($numberOfPayments <= 0) {
        return $total;
    }
    $regularPayment = ceil($total / $numberOfPayments);
    $firstPayment = $total - ($regularPayment * ($numberOfPayments - 1));
    return $firstPayment;
}

function getTransactionDetailsResponse($token, $total)
{
    $creditType = manageTemporaryFiles('read', 'temp_creditType.txt')?: 'RegularCredit';
    $numberOfPayments = manageTemporaryFiles('read', 'temp_numberOfPayments.txt')?:0;
    $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';
    $paymentType = manageTemporaryFiles('read', 'payment_type.txt') ?: 'SinglePayment';
    $cardExpiration = date('my', strtotime('+3 years'));
    
    $paymentDetails = '';
    if($numberOfPayments > 0){
       $firstPayment = calculateFirstPayment($total, $numberOfPayments);
       $numberOfPayments = $numberOfPayments - 1;
       $paymentDetails = "                                <creditType code=\"8\">{$creditType}</creditType>\n";
       $paymentDetails .= "                                <firstPayment>{$firstPayment}</firstPayment>\n";
       $paymentDetails .= "                                <periodicalPayment>100</periodicalPayment>\n";
       $paymentDetails .= "                                <numberOfPayments>{$numberOfPayments}</numberOfPayments>\n";
    } else {
       $paymentDetails = "                                <creditType code=\"1\">{$creditType}</creditType>\n";
    }
    
    manageTemporaryFiles('clean', 'temp_creditType.txt'); 
    manageTemporaryFiles('clean', 'temp_numberOfPayments.txt'); 
   
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
{$paymentDetails}                                <cardName>יורוקרד מסטרקרד</cardName>
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
function getTokenTransactionDetailsResponseJ5($token, $terminalNumber)
{
    $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';

    $xmlResponse = <<<EOT
<?xml version='1.0' encoding='ISO-8859-8'?>
<ashrait>
   <response>
       <command>doDeal</command>
       <dateTime>2025-01-14 09:47</dateTime>
       <requestId/>
       <tranId>112448750</tranId>
       <result>000</result>
       <message>עסקה תקינה</message>
       <userMessage>עסקה תקינה</userMessage>
       <additionalInfo/>
       <version>2000</version>
       <language>Heb</language>
       <doDeal>
           <status>000</status>
           <statusText>עסקה תקינה</statusText>
           <extendedStatus/>
           <extendedStatusText/>
           <extendedUserMessage/>
           <terminalNumber>{$terminalNumber}</terminalNumber>
           <cardBin>CG</cardBin>
           <cardMask>CGGMPI</cardMask>
           <cardLength>5</cardLength>
           <cardNo>xGMPI</cardNo>
           <cardName/>
           <cardExpiration/>
           <cardType code=""/>
           <extendedCardType code="0">Credit</extendedCardType>
           <blockedCard/>
           <lifeStyle/>
           <customCardType/>
           <creditCompany code=""/>
           <cardBrand code=""/>
           <cardAcquirer code=""/>
           <serviceCode/>
           <transactionType code="11">RecurringDebit</transactionType>
           <creditType code="1">RegularCredit</creditType>
           <currency code="1">ILS</currency>
           <baseCurrency/>
           <baseAmount/>
           <transactionCode code="50">Phone</transactionCode>
           <total>100</total>
           <firstPayment/>
           <periodicalPayment/>
           <numberOfPayments/>
           <paymentsInterest/>
           <mid>13607</mid>
           <uniqueid>1736840874688</uniqueid>
           <mpiValidation>Verify</mpiValidation>
           <token>{$token}</token>
           <mpiHostedPageUrl>https://ppsuat.creditguard.co.il?txId={$token}</mpiHostedPageUrl>
           <returnUrl/>
           <successUrl>http://localhost:8074/paymentgateways/OkPage?name=CreditGuard</successUrl>
           <errorUrl/>
           <cancelUrl/>
           <clubId/>
           <validation code="106">TxnSetup</validation>
           <idStatus code=""/>
           <cvvStatus code=""/>
           <authSource code="6">MPIServer</authSource>
           <authNumber/>
           <fileNumber/>
           <slaveTerminalNumber/>
           <slaveTerminalSequence/>
           <eci/>
           <clientIp/>
           <email/>
           <cavv code=""/>
           <user/>
           <addonData/>
           <supplierNumber/>
           <id/>
           <shiftId1/>
           <shiftId2/>
           <shiftId3/>
           <shiftTxnDate/>
           <cgUid>112448750</cgUid>
           <cardHash/>
           <customerData>
               <userData1>{$userData1}</userData1>
           </customerData>
           <ashraitEmvData>
               <recurringTotalNo>999</recurringTotalNo>
               <recurringFrequency>04</recurringFrequency>
               <mti>100</mti>
           </ashraitEmvData>
           <extendedTranCode/>
           <sendNotification/>
       </doDeal>
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


function getTransactionDetailsResponseJ5($token, $total, $terminalNumber, $cardId = '1022273188555606')
{
    $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';
    $cardExpiration = date('my', strtotime('+3 years'));
    $currentDateTime = date('Y-m-d H:i');
    $uid = '25011409500308831107753';
    $authNumber = '1382028';

    $xmlResponse = <<<EOT
 <?xml version='1.0' encoding='ISO-8859-8'?>
 <ashrait>
    <response>
        <command>inquireTransactions</command>
        <dateTime>{$currentDateTime}</dateTime>
        <requestId/>
        <tranId>112448777</tranId>
        <result>000</result>
        <message>עסקה תקינה</message>
        <userMessage>עסקה תקינה</userMessage>
        <additionalInfo/>
        <version>2000</version>
        <language>Heb</language>
        <inquireTransactions>
            <row>
                <mpiTransactionId>{$token}</mpiTransactionId>
                <uniqueid>1736840874688</uniqueid>
                <amount>{$total}</amount>
                <currency>ILS</currency>
                <authNumber>{$authNumber}</authNumber>
                <cardId>{$cardId}</cardId>
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
                            <dateTime>{$currentDateTime}</dateTime>
                            <requestId/>
                            <tranId>112448775</tranId>
                            <result>000</result>
                            <message>עסקה תקינה</message>
                            <userMessage>עסקה תקינה</userMessage>
                            <additionalInfo>Host Result Remote 00-SUCCESS </additionalInfo>
                            <version>2000</version>
                            <language>Heb</language>
                            <doDeal>
                                <status>000</status>
                                <statusText>עסקה תקינה</statusText>
                                <extendedStatus/>
                                <extendedStatusText/>
                                <extendedUserMessage/>
                                <terminalNumber>{$terminalNumber}</terminalNumber>
                                <cardId>{$cardId}</cardId>
                                <cardBin>532610</cardBin>
                                <cardMask>532610******5606</cardMask>
                                <cardLength>16</cardLength>
                                <cardNo>XXXXXXXXXXXX5606</cardNo>
                                <cardName>יורוקרד מסטרקרד</cardName>
                                <cardExpiration>{$cardExpiration}</cardExpiration>
                                <cardType code="00">Local</cardType>
                                <extendedCardType code="0">Credit</extendedCardType>
                                <blockedCard/>
                                <lifeStyle/>
                                <customCardType/>
                                <creditCompany code="1">Isracard</creditCompany>
                                <cardBrand code="1">Mastercard</cardBrand>
                                <cardAcquirer code="1">Isracard</cardAcquirer>
                                <serviceCode/>
                                <transactionType code="11">RecurringDebit</transactionType>
                                <creditType code="1">RegularCredit</creditType>
                                <currency code="1">ILS</currency>
                                <baseCurrency/>
                                <baseAmount/>
                                <transactionCode code="50">Phone</transactionCode>
                                <total>{$total}</total>
                                <firstPayment/>
                                <periodicalPayment/>
                                <numberOfPayments>0</numberOfPayments>
                                <clubId/>
                                <validation code="5">Verify</validation>
                                <idStatus code="1">Valid</idStatus>
                                <cvvStatus code="1">Valid</cvvStatus>
                                <authSource code="2">CreditCompany</authSource>
                                <authNumber>{$authNumber}</authNumber>
                                <fileNumber/>
                                <slaveTerminalNumber/>
                                <slaveTerminalSequence/>
                                <eci/>
                                <clientIp>172.16.100.7</clientIp>
                                <email/>
                                <cavv code=""/>
                                <user/>
                                <addonData/>
                                <supplierNumber>0071506</supplierNumber>
                                <id>890108566</id>
                                <shiftId1/>
                                <shiftId2/>
                                <shiftId3/>
                                <shiftTxnDate/>
                                <cgUid>112448750</cgUid>
                                <cardHash/>
                                <customerData>
                                    <userData1>{$userData1}</userData1>
                                </customerData>
                                <acquirerData>
                                    <gateway>AshraitEmv</gateway>
                                    <acquirerTranType>11</acquirerTranType>
                                    <mcc>0242</mcc>
                                    <acquirerResponseId>501409382028</acquirerResponseId>
                                    <avsResponse code="0">Absent</avsResponse>
                                    <acquirerTranCode>50</acquirerTranCode>
                                </acquirerData>
                                <ashraitEmvData>
                                    <recurringTotalNo>999</recurringTotalNo>
                                    <recurringFrequency>04</recurringFrequency>
                                    <recurringNo>000</recurringNo>
                                    <recurringUniqueRef>112448775</recurringUniqueRef>
                                    <uid>{$uid}</uid>
                                    <authCodeCreditCompany code="1">CreditCompanyAuthorized</authCodeCreditCompany>
                                    <idFlag>1</idFlag>
                                    <manufId>CGD</manufId>
                                    <cvvFlag>1</cvvFlag>
                                    <manufUse>001101</manufUse>
                                    <ashVersion>x</ashVersion>
                                    <ashTermType>0</ashTermType>
                                    <emvResponseCode>00</emvResponseCode>
                                    <deviceStatus>1111000000</deviceStatus>
                                    <ashReasonText>KARTIS_HASUM, BAKASHA_LEISHUR_LELO_ISKA, ITCHUL_HORAAT_KEVA</ashReasonText>
                                    <authCodeAcquirer code="0">NoAuthNumber</authCodeAcquirer>
                                    <isDoReverseDeal>0</isDoReverseDeal>
                                    <mti>100</mti>
                                </ashraitEmvData>
                                <extendedTranCode/>
                                <sendNotification/>
                            </doDeal>
                        </response>
                    </ashrait>
                </cgGatewayResponseXML>
                <cgGatewayInvoiceResponseXML/>
                <queryErrorCode>00</queryErrorCode>
                <queryErrorText>הצלחה</queryErrorText>
                <xRem/>
                <personalId>890108566</personalId>
                <cardExpiration>{$cardExpiration}</cardExpiration>
            </row>
            <totals>
                <pageNumber/>
                <pagesAmount/>
                <queryResultId/>
                <total/>
                <totalMatch/>
            </totals>
        </inquireTransactions>
    </response>
 </ashrait>
 EOT;
    return $xmlResponse;
}
function validateChargeRequest($xml) {
    try {
        // Basic structure validation
        if (!isset($xml->request) || !isset($xml->request->command)) {
            return createErrorResponse("Invalid XML structure", "003");
        }

        $doDeal = $xml->request->doDeal;
        if (!$doDeal) {
            return createErrorResponse("Missing doDeal element", "003");
        }

        // Required fields validation
        $requiredFields = ['terminalNumber', 'cardId', 'cardExpiration', 'creditType', 
                          'currency', 'transactionCode', 'transactionType', 'total', 
                          'validation', 'authNumber'];
        
        foreach ($requiredFields as $field) {
            if (!isset($doDeal->$field) || empty((string)$doDeal->$field)) {
                return createErrorResponse("Missing required field: $field", "003");
            }
        }

        // Version validation
        if ((string)$xml->request->version !== "2000") {
            return createErrorResponse("Invalid version", "002");
        }

        // Basic format validations
        if (strlen((string)$doDeal->cardId) !== 16) {
            return createErrorResponse("Invalid card token length", "033");
        }

        // Card expiration format and validation (MMYY)
        $expiration = (string)$doDeal->cardExpiration;
        if (!preg_match("/^(0[1-9]|1[0-2])\d{2}$/", $expiration)) {
            return createErrorResponse("Invalid card expiration format", "033");
        }

        // Validate amount is numeric and positive
        if (!is_numeric((string)$doDeal->total) || (int)$doDeal->total <= 0) {
            return createErrorResponse("Invalid amount", "003");
        }

        return null; // No validation errors
    } catch (Exception $e) {
        return createErrorResponse("Validation error: " . $e->getMessage());
    }
}

function createErrorResponse($message, $code = "003") {
    $currentTime = date('Y-m-d H:i');
    $response = <<<XML
<?xml version="1.0" encoding="ISO-8859-8"?>
<ashrait>
    <response>
        <command>doDeal</command>
        <dateTime>{$currentTime}</dateTime>
        <result>{$code}</result>
        <message>{$message}</message>
        <userMessage>{$message}</userMessage>
        <status>{$code}</status>
        <statusText>{$message}</statusText>
    </response>
</ashrait>
XML;
    return iconv("UTF-8", "ISO-8859-8", $response);
}
function chargeCommandResponse($xml) {
    try {

        //return wrong respone (for test unknwon response )
        if($xml->request->doDeal->cardId=="unknwon"){
            echo "חחחחחחחחח";
            return;
        }
        
        // First validate the request
        $validationError = validateChargeRequest($xml);
        if ($validationError !== null) {
            return $validationError;
        }

        // Extract values
        $command = (string)$xml->request->command;
        $requestId = (string)$xml->request->requestId;
        $version = (string)$xml->request->version;
        $language = (string)$xml->request->language;
        $doDeal = $xml->request->doDeal;
        
        // Extract doDeal values
        $terminalNumber = (string)$doDeal->terminalNumber;
        $cardId = (string)$doDeal->cardId;
        $cardExpiration = (string)$doDeal->cardExpiration;
        $creditType = (string)$doDeal->creditType;
        $currency = (string)$doDeal->currency;
        $transactionCode = (string)$doDeal->transactionCode;
        $transactionType = (string)$doDeal->transactionType;
        $total = (string)$doDeal->total;
        $authNumber = (string)$doDeal->authNumber;
        $user = (string)$doDeal->user;
        $validation = (string)$doDeal->validation;

        // Get userData1 if exists
        $userData1 = "";
        if (isset($doDeal->customerData) && isset($doDeal->customerData->userData1)) {
            $userData1 = (string)$doDeal->customerData->userData1;
        }

        // Set timezone and get current time
        date_default_timezone_set('Asia/Jerusalem');
        $currentTime = date('Y-m-d H:i');

        $response = <<<EOT
<?xml version='1.0' encoding='ISO-8859-8'?>
<ashrait>
    <response>
        <command>{$command}</command>
        <dateTime>{$currentTime}</dateTime>
        <requestId>{$requestId}</requestId>
        <tranId>112454130</tranId>
        <result>000</result>
        <message>עסקה תקינה</message>
        <userMessage>עסקה תקינה</userMessage>
        <additionalInfo>Host Result Remote 00-SUCCESS</additionalInfo>
        <version>{$version}</version>
        <language>{$language}</language>
        <doDeal>
            <status>000</status>
            <statusText>עסקה תקינה</statusText>
            <terminalNumber>{$terminalNumber}</terminalNumber>
            <cardId>{$cardId}</cardId>
            <cardBin>532610</cardBin>
            <cardMask>532610******5606</cardMask>
            <cardLength>16</cardLength>
            <cardNo>xxxxxxxxxxxx5606</cardNo>
            <cardName>יורוקרד מסטרקרד</cardName>
            <cardExpiration>{$cardExpiration}</cardExpiration>
            <cardType code="00">Local</cardType>
            <extendedCardType code="0">Credit</extendedCardType>
            <creditCompany code="1">Isracard</creditCompany>
            <cardBrand code="1">Mastercard</cardBrand>
            <cardAcquirer code="1">Isracard</cardAcquirer>
            <transactionType code="11">{$transactionType}</transactionType>
            <creditType code="1">{$creditType}</creditType>
            <currency code="1">{$currency}</currency>
            <transactionCode code="50">{$transactionCode}</transactionCode>
            <total>{$total}</total>
            <validation code="4">{$validation}</validation>
            <cvvStatus code="0">Absent</cvvStatus>
            <authSource code="2">CreditCompany</authSource>
            <authNumber>{$authNumber}</authNumber>
            <fileNumber>36</fileNumber>
            <slaveTerminalNumber>003</slaveTerminalNumber>
            <slaveTerminalSequence>399</slaveTerminalSequence>
            <user>{$user}</user>
            <supplierNumber>0071506</supplierNumber>
            <customerData>
                <userData1>{$userData1}</userData1>
            </customerData>
            <acquirerData>
                <gateway>AshraitEmv</gateway>
                <acquirerTranType>11</acquirerTranType>
                <mcc>0242</mcc>
                <acquirerResponseId>501414383687</acquirerResponseId>
                <avsResponse code="0">Absent</avsResponse>
                <acquirerTranCode>50</acquirerTranCode>
            </acquirerData>
            <ashraitEmvData>
                <recurringNo>2</recurringNo>
                <recurringTotalNo>999</recurringTotalNo>
                <orgAuthCodeCreditCompany>1</orgAuthCodeCreditCompany>
                <orgAuthCodeAcquirer>0</orgAuthCodeAcquirer>
                <orgTranDate>0114</orgTranDate>
                <orgTranTime>142138</orgTranTime>
                <orgAuthNo>{$authNumber}</orgAuthNo>
                <orgUid>25011414213808831100829</orgUid>
                <recurringUniqueRef>112454130</recurringUniqueRef>
                <uid>25011414241108831111307</uid>
                <authCodeCreditCompany code="1">CreditCompanyAuthorized</authCodeCreditCompany>
                <manufId>CGD</manufId>
                <manufUse>001101</manufUse>
                <ashVersion>x</ashVersion>
                <ashTermType>0</ashTermType>
                <emvResponseCode>00</emvResponseCode>
                <deviceStatus>1111000000</deviceStatus>
                <ashReasonText>KARTIS_HASUM</ashReasonText>
                <authCodeAcquirer code="0">NoAuthNumber</authCodeAcquirer>
                <isDoReverseDeal>0</isDoReverseDeal>
                <mti>100</mti>
            </ashraitEmvData>
            <extendedTranCode/>
            <sendNotification/>
        </doDeal>
    </response>
</ashrait>
EOT;

        return $response;
        
    } catch (Exception $e) {
        return createErrorResponse("Error processing request: " . $e->getMessage());
    }
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

    //Handle Charge command/API
    if (isset($xml->request->mayBeDuplicate)) {
        echo chargeCommandResponse($xml);
        return;
    }

    $total = $xml->request->doDeal->total;


    if (isset($xml->request->doDeal->successUrl) && isset($xml->request->doDeal->errorUrl)) {

        // Check if amount is valid
        if (!isset($total) || empty($total) || !is_numeric((int) $total) || $total > 2000000000) {
            manageTemporaryFiles('write', '350.txt', 350);

            $errorMessage = "Invalid value: $total for field: total, should be number";
            $userData1 = manageTemporaryFiles('read', 'temp_userData1.txt') ?: '1';

            echo getErrorXmlResponse($userData1, $errorMessage);

            return;
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


        }

        //first response for getRequest (singelPayment | singelPaymentToken | payments)
        $creditType = $xml->request->doDeal->creditType;
        $numberOfPayments = $xml->request->doDeal->numberOfPayments;
        echo getXmlResponse($token, $successUrl, $total, $creditType, $numberOfPayments);
        // return;
    } elseif (isset($xml->request->doDeal->successUrl) && !isset($xml->request->doDeal->errorUrl)) {
        //Handle J5 only token without singel payment
        file_put_contents('j5', print_r($xml, 1), FILE_APPEND);
        $token = 100;
        $terminalNumber = $xml->request->doDeal->terminalNumber;
        $userData1 = $xml->request->doDeal->userData1;
        manageTemporaryFiles('write', 'temp_userData1.txt', $userData1);
        manageTemporaryFiles('write', 'iiiiii.txt', $userData1);
        echo getTokenTransactionDetailsResponseJ5($token, $total);
    }
    // Handle transaction query
    elseif (
        $xml->request->command == 'inquireTransactions' &&
        $xml->request->inquireTransactions->mpiValidation == 'Token'
    ) {

        $token = $xml->request->inquireTransactions->mpiTransactionId;
        $total = $token * 100;
        //Handle respone for only J5
        if ($token == 100) {
            $terminalNumber = (string) $xml->request->doDeal->terminalNumber;
            echo getTransactionDetailsResponseJ5(100, 100, $terminalNumber);
            return;
        }

        //Handle respone for regular singel payment 
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