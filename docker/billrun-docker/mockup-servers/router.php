<?php
// router.php
if (preg_match('/^\/payment-gateways\/creditguard\//', $_SERVER["REQUEST_URI"])) {
    require 'creditguard.php';
}elseif(preg_match('/^\/crm\//', $_SERVER["REQUEST_URI"])){
    require 'crm.php';
}elseif(preg_match('/^\/ssh\//', $_SERVER["REQUEST_URI"])){
    require 'ssh.php';
}else { 
    //echo '<p>' . print_r($_SERVER,true) . '</p>';
    echo '<p>' . $_SERVER["REQUEST_URI"] . '</p>';
    
}
?>
