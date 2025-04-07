<?php
// router.php
file_put_contents('creditguard.xml',print_r($_SERVER,1));
if (preg_match('/^\/payment-gateways\/creditguard\//', $_SERVER["REQUEST_URI"])) {
    require 'cg.php';
    // require 'creditguard.php';
}elseif(preg_match('/^\/crm\//', $_SERVER["REQUEST_URI"])){
    require 'crm.php';
} elseif(preg_match('/^\/plugins\/israelInvoice\//', $_SERVER["REQUEST_URI"])) {
    require 'israelInvoice.php';
}
 else { 
    //echo '<p>' . print_r($_SERVER,true) . '</p>';
    echo '<p>' . $_SERVER["REQUEST_URI"] . '</p>';
    
}
?>
