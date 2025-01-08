<?php
if (preg_match('/^\/payment-gateways\/creditguard\/xpo\/Relay/', $_SERVER["REQUEST_URI"])) {
	$xml = simplexml_load_string($_POST['int_in']);
	file_put_contents('creditguard.xml',print_r($xml,1));
	//var_dump($xml);
	if ($xml->request->inquireTransactions->mpiTransactionId == 1) {
		echo "<?xml version='1.0' encoding='ISO-8859-8'?><ashrait><response><command>inquireTransactions</command><dateTime>2024-11-25 18:21</dateTime><requestId/><tranId/><result>462</result><message>ערך לא תקין בבקשה</message><userMessage>ערך לא תקין בבקשה</userMessage><additionalInfo>Bad value '' in header field 'version'</additionalInfo><version/><language>Heb</language><inquireTransactions><transactions/><totals><pageNumber/><pagesAmount/><queryResultId/><total>0</total><totalMatch/></totals></inquireTransactions></response></ashrait>";
	}
}
?>
