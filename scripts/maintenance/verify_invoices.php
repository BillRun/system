<?php

defined('APPLICATION_PATH') || define('APPLICATION_PATH', dirname(__DIR__)."/..");
require_once(APPLICATION_PATH. DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

$log = Billrun_Log::getInstance(Billrun_Factory::config()->getConfigValue('log',array()));


$billrunKey = $argv[4];
$pdfVerifyExec = Billrun_Factory::config()->getConfigValue('invoice_export.verfiy_pdf_exec','pdfinfo');
$pdfsPath = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('invoice_export.export',"files/insddvoices")) . DIRECTORY_SEPARATOR. $billrunKey .DIRECTORY_SEPARATOR. 'pdf'. DIRECTORY_SEPARATOR ;
$brokenPdfs = [];
print("Received Paths {$pdfsPath}\n");
$log->log("Received Paths {$pdfsPath}");
foreach(glob($pdfsPath."*.pdf") as $filePath ) {
	$log->log("Verifing {$filePath}");
	$res = exec("{$pdfVerifyExec} {$filePath} 2>/dev/null");
	if(empty($res)) {
		$log->log("{$filePath} is borken {$res}");
		print("{$filePath} is borken retuned result {$res} \n");
		$brokenPdfs[] = $filePath;
	}
}


$MAX_DPI = 120;
$MIN_DPI = 105;
foreach($brokenPdfs as $brokenFile) {
	print("Received Paths {$brokenFile}\n");
	$dpi=$MIN_DPI;
	$highestDpi = FALSE;
	$basePdfGenCmd = 'php -t '.APPLICATION_PATH.'/ '.APPLICATION_PATH.'/public/index.php --env '.Billrun_Factory::config()->getEnv()." --generate --type invoice_export --stamp {$billrunKey}";
	$aid = preg_replace("/\d+_(\d+)_\d+.*/",'$1',basename($brokenFile));
	for(;$dpi <= $MAX_DPI;$dpi++) {
		$pdfParameters = "--page-size A4 -R 0 -L 0 -T 45 -B 27 --dpi {$dpi} --print-media-type";
		$log->log('Generating Invoice '.basename($brokenFile).' with dpi of :'.$dpi);
		exec("$basePdfGenCmd accounts={$aid},{$aid} exporter_flags='{$pdfParameters}' 2>/dev/null");
		if(!empty(exec("{$pdfVerifyExec} {$brokenFile} 2>/dev/null"))) {
			$highestDpi = $dpi;
		}
	}
	if($highestDpi) {
		$pdfParameters = "--page-size A4 -R 0 -L 0 -T 45 -B 27 --dpi {$highestDpi} --print-media-type";
		exec("$basePdfGenCmd accounts={$aid},{$aid} exporter_flags='{$pdfParameters}'");
		print("$basePdfGenCmd accounts={$aid},{$aid} exporter_flags='{$pdfParameters}' \n");
	} else {
		$log->log("Failed when trying the fix {$brokenFile} invoice",Zend_Log::CRIT);
	}
}

exit(0);
