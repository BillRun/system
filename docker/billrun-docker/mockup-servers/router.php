<?php
// router.php
if (preg_match('/^\/payment-gateways\/creditguard\//', $_SERVER["REQUEST_URI"])) {
    require 'creditguard.php';
} else { 
    //echo '<p>' . print_r($_SERVER,true) . '</p>';
    echo '<p>' . $_SERVER["REQUEST_URI"] . '</p>';
    
}
?>
