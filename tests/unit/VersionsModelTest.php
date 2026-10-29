<?php

/**
 * Unit tests for VersionsModel::isValidPathPart (BRCD-5545)
 *
 * The collection and file name received by the version save/load API are used as
 * single path segments under the versions directory, so anything that could leave
 * that directory must be rejected.
 */
class VersionsModelTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testAcceptsPlainNames()
    {
        $names = [
            'rates',
            'carriers',
            'rates_20261006-1200.export',
            'a.b-c_d',
            'UPPER_lower-123',
            '-leading-dash',
            '_leading_underscore',
            'trailing.',
        ];
        foreach ($names as $name) {
            $this->assertTrue(VersionsModel::isValidPathPart($name), "'$name' should be accepted");
        }
    }

    public function testRejectsDirectoryTraversal()
    {
        $names = [
            '..',
            '../',
            '../../../../etc/passwd',
            'rates/../../etc',
            '/etc/passwd',
            'a/b',
            'a\\b',
            '..\\..\\windows',
        ];
        foreach ($names as $name) {
            $this->assertFalse(VersionsModel::isValidPathPart($name), "'$name' should be rejected");
        }
    }

    public function testRejectsDotAndHiddenNames()
    {
        $names = [
            '.',
            '.hidden',
            '.htaccess',
            '...',
        ];
        foreach ($names as $name) {
            $this->assertFalse(VersionsModel::isValidPathPart($name), "'$name' should be rejected");
        }
    }

    public function testRejectsUnexpectedCharacters()
    {
        $names = [
            '',
            ' ',
            'my file',
            "a\0b",
            "a\nb",
            'name;rm -rf',
            'שם',
        ];
        foreach ($names as $name) {
            $this->assertFalse(VersionsModel::isValidPathPart($name), json_encode($name) . ' should be rejected');
        }
    }

    public function testRejectsNonStrings()
    {
        $values = [
            null,
            123,
            1.5,
            true,
            ['rates'],
            new stdClass(),
        ];
        foreach ($values as $value) {
            $this->assertFalse(VersionsModel::isValidPathPart($value), gettype($value) . ' should be rejected');
        }
    }

}
