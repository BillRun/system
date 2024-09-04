<?php

defined('APPLICATION_PATH') || define('APPLICATION_PATH', dirname(__DIR__)."/..");
require_once(APPLICATION_PATH. DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

$jsonConf = '{

			"cli":{
					"writerName":"Stream",
					"writerParams":{
						"stream":"php://output"
					},
					"formatterName":"Simple",
					"formatterParams":{ "format":"%timestamp% %priorityName% : %message% \n" }
				}
			}';

$log = Billrun_Log::getInstance(array_merge(Billrun_Factory::config()->getConfigValue('log',array()),json_decode($jsonConf,JSON_OBJECT_AS_ARRAY)));


$billrunKey = $argv[4];
$pdfVerifyExec = Billrun_Factory::config()->getConfigValue('invoice_export.verfiy_pdf_exec','pdfinfo');
$pdfInfoCheck = exec("which ${pdfVerifyExec}");
if (empty($pdfInfoCheck)) {
	$log->log("Couldn't find PDF verification program : ${$pdfVerifyExec}.",Zend_Log::ERR);
	die(-1);
}
$pdfsPath = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('invoice_export.export',"files/ivoices")) . DIRECTORY_SEPARATOR. $billrunKey .DIRECTORY_SEPARATOR. 'pdf'. DIRECTORY_SEPARATOR ;
$brokenPdfs = [];
$log->log("Verifing invoices at path {$pdfsPath}");
$thinkArr = ['-','\\','|','/'];
$thinkIdx=0;
foreach(glob($pdfsPath."*.pdf") as $filePath ) {
	print($thinkArr[$thinkIdx++ %4] ."\r");
	$cmdOutput= null;
	$res = exec("{$pdfVerifyExec} {$filePath} 2>&1", $cmdOutput);
	if(empty($res) || !empty(preg_grep('/Error/',$cmdOutput))) {
		$log->log("{$filePath} is broken {$res}",Zend_Log::WARN);
		$brokenPdfs[] = $filePath;
	}
}
$log->log("Found ".count($brokenPdfs). " broken pdfs.");


$MAX_DPI = 120;
$MIN_DPI = 105;
foreach($brokenPdfs as $brokenFile) {
	$dpi=$MAX_DPI;
	$highestDpi = FALSE;
	$brokenFileName = basename($brokenFile);
	$basePdfGenCmd = 'php -t '.APPLICATION_PATH.'/ '.APPLICATION_PATH.'/public/index.php --env '.Billrun_Factory::config()->getEnv()." --generate --type invoice_export --stamp {$billrunKey}";
	$aid = preg_replace("/\d+_(\d+)_\d+\.pdf$/",'$1',$brokenFileName);
	if($aid == $brokenFileName || empty($aid)) {
		$billrunObj = Billrun_Factory::db()->billrunCollection()->query(['billrun_key' => $billrunKey, 'invoice_file' => (new MongoRegex("/{$brokenFileName}/")) ])->cursor()->limit(1)->next();
		$aid = $billrunObj['aid'] ?: null;
	}
	if(empty($aid)) {
		$log->log("Cloudn't get AID for {$brokenFile}.",Zend_Log::ERR);
		continue;
	}
	for(;$dpi >= $MIN_DPI;$dpi--) {
		$pdfParameters = "--page-size A4 -R 0 -L 0 -T 45 -B 27 --dpi {$dpi} --print-media-type --enable-local-file-access";
		$log->log('Generating Invoice '.basename($brokenFile).' with dpi of : '.$dpi);
		exec("$basePdfGenCmd accounts={$aid},{$aid} exporter_flags='{$pdfParameters}'");
		if(!empty(exec("{$pdfVerifyExec} {$brokenFile} 2>/dev/null"))) {
			$highestDpi = $dpi;
			$log->log('Invoice generation succesful');
			break;
		} else {
		$log->log("Invoice generation failed for : {$brokenFile}",Zend_Log::WARN);
		}
	}
	if(!$highestDpi) {
		$log->log("Failed when trying the fix {$brokenFile} invoice",Zend_Log::CRIT);
	}
}

exit(0);
