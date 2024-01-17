<?php

use Payrexx\Models\Request\Design;
use Payrexx\Payrexx;
use Payrexx\PayrexxException;

spl_autoload_register(function ($class) {
    $root = dirname(__DIR__);
    $classFile = $root . '/lib/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// $instanceName is a part of the url where you access your payrexx installation.
// https://{$instanceName}.payrexx.com
$instanceName = 'YOUR_INSTANCE_NAME';

// $secret is the payrexx secret for the communication between the applications
// if you think someone got your secret, just regenerate it in the payrexx administration
$secret = 'YOUR_SECRET';

$payrexx = new Payrexx($instanceName, $secret);

$design = new Design();
$design->setUuid('YOUR_UUID');
$design->setDefault(false);
$design->setName('Example Design (via API)');
$design->setHeaderImageShape('square'); // square, rectangular or round
$design->setLogoBackgroundColor('FFFFFF'); // Hex code
$design->setLogoBorderColor('DDDDDD'); // Hex code
$design->setBackground('color'); // color or image
$design->setBackgroundColor('F9FAFA'); // Hex code
$design->setHeaderBackground('color'); // color or image
$design->setHeaderBackgroundColor('00AFF0'); // Hex code
$design->setHeaderImageCustomLink('https://payrexx.com/en'); // link for all languages
$design->setVPOSGradientColor1('00AFF0'); // Hex code
$design->setVPOSGradientColor2('00AFF0'); // Hex code
$design->setFontFamily('Arial');
$design->setFontSize('14'); // In pixel
$design->setTextColor('24363A'); // Hex code
$design->setTextColorVPOS('24363A'); // Hex code
$design->setLinkColor('0074D6'); // Hex code
$design->setLinkHoverColor('2A6496'); // Hex code
$design->setButtonColor('99CC33'); // Hex code
$design->setButtonHoverColor('19B8F2'); // Hex code
$design->setEnableRoundedCorners(true);
$design->setUseIndividualEmailLogo(false);
$design->setEmailHeaderBackgroundColor('FAFAFA'); // Hex code

$design->setHeaderImage(new CURLFile(__DIR__ . '/payrexx-logo.png'));

try {
    $response = $payrexx->update($design);
    var_dump($response);
} catch (PayrexxException $e) {
    print $e->getMessage();
}
