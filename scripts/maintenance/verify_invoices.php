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
$pdfsPath = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('invoice_export.export',"files/insddvoices")) . DIRECTORY_SEPARATOR. $billrunKey .DIRECTORY_SEPARATOR. 'pdf'. DIRECTORY_SEPARATOR ;
$brokenPdfs = [];
$log->log("Verifing invoices at path {$pdfsPath}");
$thinkArr = ['-','\\','|','/'];
$thinkIdx=0;
foreach(glob($pdfsPath."*.pdf") as $filePath ) {
	print($thinkArr[$thinkIdx++ %4] ."\r");
	$res = exec("{$pdfVerifyExec} {$filePath} 2>/dev/null");
	if(empty($res)) {
		$log->log("{$filePath} is borken {$res}",Zend_Log::WARN);
		$brokenPdfs[] = $filePath;
	}
}


$MAX_DPI = 120;
$MIN_DPI = 105;
foreach($brokenPdfs as $brokenFile) {
	$dpi=$MIN_DPI;
	$highestDpi = FALSE;
	$basePdfGenCmd = 'php -t '.APPLICATION_PATH.'/ '.APPLICATION_PATH.'/public/index.php --env '.Billrun_Factory::config()->getEnv()." --generate --type invoice_export --stamp {$billrunKey}";
	$aid = preg_replace("/\d+_(\d+)_\d+.*/",'$1',basename($brokenFile));
	for(;$dpi <= $MAX_DPI;$dpi++) {
		$pdfParameters = "--page-size A4 -R 0 -L 0 -T 45 -B 27 --dpi {$dpi} --print-media-type";
		$log->log('Generating Invoice '.basename($brokenFile).' with dpi of : '.$dpi);
		exec("$basePdfGenCmd accounts={$aid},{$aid} exporter_flags='{$pdfParameters}' 2>/dev/null");
		if(!empty(exec("{$pdfVerifyExec} {$brokenFile} 2>/dev/null"))) {
			$highestDpi = $dpi;
			$log->log('Invoice generation succesful');
		} else {
		$log->log("Invoice generation failed for : {$brokenFile}",Zend_Log::WARN);
		}
	}
	if($highestDpi) {
		$pdfParameters = "--page-size A4 -R 0 -L 0 -T 45 -B 27 --dpi {$highestDpi} --print-media-type";
		$log->log("Generating final Invoice {$brokenFile} with DPI of :{$highestDpi}");
		exec("$basePdfGenCmd accounts={$aid},{$aid} exporter_flags='{$pdfParameters}'");
	} else {
		$log->log("Failed when trying the fix {$brokenFile} invoice",Zend_Log::CRIT);
	}
}

exit(0);
