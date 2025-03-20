<?php 

//Example call - php scripts/tools/approve_pending_payments.php --env container --dir /billrun/ --txids 0000010157178,0000010157179

$options = getopt('', array('env:', 'dir:', 'txids:'));
$dir = $options['dir'];
$txids = explode(",", $options['txids']);


defined('APPLICATION_PATH') || define('APPLICATION_PATH', $dir);
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);
$app->bootstrap();
Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");

$txids = explode(",", $options['txids']);
foreach ($txids as $txid) {
    $bill2 = Billrun_Bill_Payment::getInstanceByid($txid);
    $bill2->markApproved("Completed");
    $bill2->setPending(false);
    $bill2->updateConfirmation();
    echo 'Confirming transaction ' . $bill2->getId() . PHP_EOL;
}