<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
<<<<<<<< HEAD:vendor/phpunit/phpunit/src/Framework/Exception/NoChildTestSuiteException.php
final class NoChildTestSuiteException extends Exception
========
final class InvalidDataProviderException extends Exception
>>>>>>>> release-5.13:vendor/phpunit/phpunit/src/Framework/Exception/InvalidDataProviderException.php
{
}
