<?php

declare(strict_types=1);

namespace PackageVersions;

use Composer\InstalledVersions;
use OutOfBoundsException;
use UnexpectedValueException;

class_exists(InstalledVersions::class);

/**
 * This is a stub class: it is in place only for scenarios where PackageVersions
 * is installed with a `--no-scripts` flag, in which scenarios the Versions class
 * is not being replaced.
 *
 * If you are reading this docBlock inside your `vendor/` dir, then this means
 * that PackageVersions didn't correctly install, and is in "fallback" mode.
 *
 * @deprecated in favor of the Composer\InstalledVersions class provided by Composer 2. Require composer-runtime-api:^2 to ensure it is present.
 */
final class Versions
{
    /**
     * @deprecated please use {@see self::rootPackageName()} instead.
     *             This constant will be removed in version 2.0.0.
     */
    const ROOT_PACKAGE_NAME = 'unknown/root-package@UNKNOWN';

<<<<<<< HEAD
    /**
     * Array of all available composer packages.
     * Dont read this array from your calling code, but use the \PackageVersions\Versions::getVersion() method instead.
     *
     * @var array<string, string>
     * @internal
     */
    const VERSIONS          = array (
  'alcaeus/mongo-php-adapter' => '1.2.0@b828ebc06cd3c270997b13c97dadc94731b36354',
  'bshaffer/oauth2-server-php' => 'v1.11.1@5a0c8000d4763b276919e2106f54eddda6bc50fa',
  'clue/stream-filter' => 'v1.7.0@049509fef80032cb3f051595029ab75b49a3c2f7',
  'composer/package-versions-deprecated' => '1.11.99.5@b4f54f74ef3453349c24a845d22392cd31e65f1d',
  'guzzlehttp/guzzle' => '6.5.8@a52f0440530b54fa079ce76e8c5d196a42cad981',
  'guzzlehttp/promises' => '1.5.3@67ab6e18aaa14d753cc148911d273f6e6cb6721e',
  'guzzlehttp/psr7' => '1.9.1@e4490cabc77465aaee90b20cfc9a770f8c04be6b',
  'jean85/pretty-package-versions' => '1.6.0@1e0104b46f045868f11942aea058cd7186d6c303',
  'league/omnipay' => 'v3.0.2@9e10d91cbf84744207e13d4483e79de39b133368',
  'maennchen/zipstream-php' => '2.1.0@c4c5803cc1f93df3d2448478ef79394a5981cc58',
  'markbaker/complex' => '2.0.3@6f724d7e04606fd8adaa4e3bb381c3e9db09c946',
  'markbaker/matrix' => '2.1.4@469e937dc91aa087e43b21a5266cb7567f482f3e',
  'moneyphp/money' => 'v3.3.3@0dc40e3791c67e8793e3aa13fead8cf4661ec9cd',
  'mongodb/mongodb' => '1.7.2@38b685191c047a57275d6ccd2ea5c50f23638485',
  'myclabs/php-enum' => '1.8.4@a867478eae49c9f59ece437ae7f9506bfaa27483',
  'omnipay/common' => 'v3.2.1@80545e9f4faab0efad36cc5f1e11a184dda22baf',
  'payrexx/omnipay-payrexx' => 'v1.1@ce601e53e53ea78e8ac4630abf270598c94e6115',
  'payrexx/payrexx' => 'v1.7.6@e20791854ce344dbf20ab85d3668cfff7ebc9cca',
  'php-http/discovery' => '1.19.2@61e1a1eb69c92741f5896d9e05fb8e9d7e8bb0cb',
  'php-http/guzzle6-adapter' => 'v2.0.2@9d1a45eb1c59f12574552e81fb295e9e53430a56',
  'php-http/httplug' => '2.4.0@625ad742c360c8ac580fcc647a1541d29e257f67',
  'php-http/message' => '1.16.0@47a14338bf4ebd67d317bf1144253d7db4ab55fd',
  'php-http/message-factory' => '1.1.0@4d8778e1c7d405cbb471574821c1ff5b68cc8f57',
  'php-http/promise' => '1.3.0@2916a606d3b390f4e9e8e2b8dd68581508be0f07',
  'phpoffice/phpspreadsheet' => '1.15.0@a8e8068b31b8119e1daa5b1eb5715a3a8ea8305f',
  'psr/http-client' => '1.0.3@bb5906edc1c324c9a05aa0873d40117941e5fa90',
  'psr/http-factory' => '1.0.2@e616d01114759c4c489f93b099585439f795fe35',
  'psr/http-message' => '1.1@cb6ce4845ce34a8ad9e68117c10ee90a29919eba',
  'psr/simple-cache' => '1.0.1@408d5eafb83c57f6365a3ca330ff23aa4a5fa39b',
  'ralouphie/getallheaders' => '3.0.3@120b605dfeb996808c31b6477290a714d356e822',
  'stripe/stripe-php' => 'v10.5.0@331415b232d60d7c0449de7bde4cb7d4fedf982e',
  'symfony/deprecation-contracts' => 'v2.5.2@e8b495ea28c1d97b5e0c121748d6f9b53d075c66',
  'symfony/http-foundation' => 'v5.4.35@f2ab692a22aef1cd54beb893aa0068bdfb093928',
  'symfony/polyfill-intl-idn' => 'v1.28.0@ecaafce9f77234a6a449d29e49267ba10499116d',
  'symfony/polyfill-intl-normalizer' => 'v1.28.0@8c4ad05dd0120b6a53c1ca374dca2ad0a1c4ed92',
  'symfony/polyfill-mbstring' => 'v1.28.0@42292d99c55abe617799667f454222c54c60e229',
  'symfony/polyfill-php72' => 'v1.28.0@70f4aebd92afca2f865444d30a4d2151c13c3179',
  'symfony/polyfill-php80' => 'v1.28.0@6caa57379c4aec19c0a12a38b59b26487dcfe4b5',
  'behat/gherkin' => 'v4.9.0@0bc8d1e30e96183e4f36db9dc79caead300beff4',
  'codeception/codeception' => '4.2.2@b88014f3348c93f3df99dc6d0967b0dbfa804474',
  'codeception/lib-asserts' => '1.13.2@184231d5eab66bc69afd6b9429344d80c67a33b6',
  'codeception/lib-innerbrowser' => '1.5.1@31b4b56ad53c3464fcb2c0a14d55a51a201bd3c2',
  'codeception/module-asserts' => '1.3.1@59374f2fef0cabb9e8ddb53277e85cdca74328de',
  'codeception/module-cli' => '1.1.1@1f841ad4a1d43e5d9e60a43c4cc9e5af8008024f',
  'codeception/module-db' => '2.1.0@65c5ed9d56825e419ea9954eaf8fdcaf7da5b5ed',
  'codeception/module-mongodb' => '2.0.0@a55da490b16a1252b9eb817695175c10d75e102c',
  'codeception/module-phpbrowser' => '1.0.1@c1962657504a2a476b8dbd1f1ee05e0c912e5645',
  'codeception/module-rest' => '2.0.3@ee4ea06cd8a5057f24f37f8bf25b6815ddc77840',
  'codeception/module-webdriver' => '1.4.1@e22ac7da756df659df6dd4fac2dff9c859e30131',
  'codeception/phpunit-wrapper' => '9.0.9@7439a53ae367986e9c22b2ac00f9d7376bb2f8cf',
  'codeception/stub' => '4.0.2@18a148dacd293fc7b044042f5aa63a82b08bff5d',
  'doctrine/instantiator' => '1.5.0@0a0fa9780f5d4e507415a065172d26a98d02047b',
  'graham-campbell/result-type' => 'v1.1.2@fbd48bce38f73f8a4ec8583362e732e4095e5862',
  'justinrainbow/json-schema' => 'v5.2.13@fbbe7e5d79f618997bc3332a6f49246036c45793',
  'myclabs/deep-copy' => '1.11.1@7284c22080590fb39f2ffa3e9057f10a4ddd0e0c',
  'nikic/php-parser' => 'v5.0.0@4a21235f7e56e713259a6f76bf4b5ea08502b9dc',
  'phar-io/manifest' => '2.0.3@97803eca37d319dfa7826cc2437fc020857acb53',
  'phar-io/version' => '3.2.1@4f7fd7836c6f332bb2933569e566a0d6c4cbed74',
  'php-webdriver/webdriver' => '1.15.1@cd52d9342c5aa738c2e75a67e47a1b6df97154e8',
  'phpoption/phpoption' => '1.9.2@80735db690fe4fc5c76dfa7f9b770634285fa820',
  'phpunit/php-code-coverage' => '9.2.30@ca2bd87d2f9215904682a9cb9bb37dda98e76089',
  'phpunit/php-file-iterator' => '3.0.6@cf1c2e7c203ac650e352f4cc675a7021e7d1b3cf',
  'phpunit/php-invoker' => '3.1.1@5a10147d0aaf65b58940a0b72f71c9ac0423cc67',
  'phpunit/php-text-template' => '2.0.4@5da5f67fc95621df9ff4c4e5a84d6a8a2acf7c28',
  'phpunit/php-timer' => '5.0.3@5a63ce20ed1b5bf577850e2c4e87f4aa902afbd2',
  'phpunit/phpunit' => '9.6.16@3767b2c56ce02d01e3491046f33466a1ae60a37f',
  'psr/container' => '1.1.2@513e0666f7216c7459170d56df27dfcefe1689ea',
  'psr/event-dispatcher' => '1.0.0@dbefd12671e8a14ec7f180cab83036ed26714bb0',
  'sebastian/cli-parser' => '1.0.1@442e7c7e687e42adc03470c7b668bc4b2402c0b2',
  'sebastian/code-unit' => '1.0.8@1fc9f64c0927627ef78ba436c9b17d967e68e120',
  'sebastian/code-unit-reverse-lookup' => '2.0.3@ac91f01ccec49fb77bdc6fd1e548bc70f7faa3e5',
  'sebastian/comparator' => '4.0.8@fa0f136dd2334583309d32b62544682ee972b51a',
  'sebastian/complexity' => '2.0.3@25f207c40d62b8b7aa32f5ab026c53561964053a',
  'sebastian/diff' => '4.0.5@74be17022044ebaaecfdf0c5cd504fc9cd5a7131',
  'sebastian/environment' => '5.1.5@830c43a844f1f8d5b7a1f6d6076b784454d8b7ed',
  'sebastian/exporter' => '4.0.5@ac230ed27f0f98f597c8a2b6eb7ac563af5e5b9d',
  'sebastian/global-state' => '5.0.6@bde739e7565280bda77be70044ac1047bc007e34',
  'sebastian/lines-of-code' => '1.0.4@e1e4a170560925c26d424b6a03aed157e7dcc5c5',
  'sebastian/object-enumerator' => '4.0.4@5c9eeac41b290a3712d88851518825ad78f45c71',
  'sebastian/object-reflector' => '2.0.4@b4f479ebdbf63ac605d183ece17d8d7fe49c15c7',
  'sebastian/recursion-context' => '4.0.5@e75bd0f07204fec2a0af9b0f3cfe97d05f92efc1',
  'sebastian/resource-operations' => '3.0.3@0f4443cb3a1d92ce809899753bc0d5d5a8dd19a8',
  'sebastian/type' => '3.2.1@75e2c2a32f5e0b3aef905b9ed0b179b953b3d7c7',
  'sebastian/version' => '3.0.2@c6c1022351a901512170118436c764e473f6de8c',
  'simpletest/simpletest' => 'v1.2.0@4fb6006517a1428785a0ea704fbedcc675421ec4',
  'softcreatr/jsonpath' => '0.7.6@e04c02cb78bcc242c69d17dac5b29436bf3e1076',
  'symfony/browser-kit' => 'v5.4.35@2f6f979b579ed1c051465c3c2fb81daf5bb4a002',
  'symfony/console' => 'v5.4.35@dbdf6adcb88d5f83790e1efb57ef4074309d3931',
  'symfony/css-selector' => 'v5.4.35@9e615d367e2bed41f633abb383948c96a2dbbfae',
  'symfony/dom-crawler' => 'v5.4.35@e3b4806f88abf106a411847a78619a542e71de29',
  'symfony/event-dispatcher' => 'v5.4.35@7a69a85c7ea5bdd1e875806a99c51a87d3a74b38',
  'symfony/event-dispatcher-contracts' => 'v2.5.2@f98b54df6ad059855739db6fcbc2d36995283fe1',
  'symfony/finder' => 'v5.4.35@abe6d6f77d9465fed3cd2d029b29d03b56b56435',
  'symfony/polyfill-ctype' => 'v1.28.0@ea208ce43cbb04af6867b4fdddb1bdbf84cc28cb',
  'symfony/polyfill-intl-grapheme' => 'v1.28.0@875e90aeea2777b6f135677f618529449334a612',
  'symfony/polyfill-php73' => 'v1.28.0@fe2f306d1d9d346a7fee353d0d5012e401e984b5',
  'symfony/process' => 'v5.4.35@cbc28e34015ad50166fc2f9c8962d28d0fe861eb',
  'symfony/service-contracts' => 'v2.5.2@4b426aac47d6427cc1a1d0f7e2ac724627f5966c',
  'symfony/string' => 'v5.4.35@c209c4d0559acce1c9a2067612cfb5d35756edc2',
  'symfony/yaml' => 'v5.4.35@e78db7f5c70a21f0417a31f414c4a95fe76c07e4',
  'theseer/tokenizer' => '1.2.2@b2ad5003ca10d4ee50a12da31de12a5774ba6b96',
  'vlucas/phpdotenv' => 'v5.6.0@2cf9fb6054c2bb1d59d1f3817706ecdb9d2934c4',
  '__root__' => '1.0.0+no-version-set@',
);
=======
    /** @internal */
    const VERSIONS          = [];
>>>>>>> release-5.14

    private function __construct()
    {
    }

    /**
     * @psalm-pure
     *
     * @psalm-suppress ImpureMethodCall we know that {@see InstalledVersions} interaction does not
     *                                  cause any side effects here.
     */
    public static function rootPackageName() : string
    {
        if (!class_exists(InstalledVersions::class, false) || !InstalledVersions::getRawData()) {
            return self::ROOT_PACKAGE_NAME;
        }

        return InstalledVersions::getRootPackage()['name'];
    }

    /**
     * @throws OutOfBoundsException if a version cannot be located.
     * @throws UnexpectedValueException if the composer.lock file could not be located.
     */
    public static function getVersion(string $packageName): string
    {
        if (!self::composer2ApiUsable()) {
            return FallbackVersions::getVersion($packageName);
        }

        /** @psalm-suppress DeprecatedConstant */
        if ($packageName === self::ROOT_PACKAGE_NAME) {
            $rootPackage = InstalledVersions::getRootPackage();

            return $rootPackage['pretty_version'] . '@' . $rootPackage['reference'];
        }

        return InstalledVersions::getPrettyVersion($packageName)
            . '@' . InstalledVersions::getReference($packageName);
    }

    private static function composer2ApiUsable(): bool
    {
        if (!class_exists(InstalledVersions::class, false)) {
            return false;
        }

        if (method_exists(InstalledVersions::class, 'getAllRawData')) {
            $rawData = InstalledVersions::getAllRawData();
            if (count($rawData) === 1 && count($rawData[0]) === 0) {
                return false;
            }
        } else {
            $rawData = InstalledVersions::getRawData();
            if ($rawData === null || $rawData === []) {
                return false;
            }
        }

        return true;
    }
}
