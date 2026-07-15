<?php
// router.php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if (preg_match('/^\/payment-gateways\/creditguard\//', $path)) {
    require 'cg.php';
    // require 'creditguard.php';
}elseif(preg_match('/^\/crm\//', $path)){
    require 'crm.php';
}elseif(preg_match('/^\/auth-mock\//', $path) || preg_match('/^\/(token|api)$/', $path)){
    require 'auth.php';
}elseif(preg_match('/^\/plugins\/israel-tax\//', $path)) {
    require 'israelInvoice.php';
}
 else { 
    //echo '<p>' . print_r($_SERVER,true) . '</p>';
    echo '<p>' . $_SERVER["REQUEST_URI"] . '</p>';
    
}
?>
