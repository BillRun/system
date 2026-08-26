<?php

/**
 * Unit tests for ConfigModel::validateEncryptedFieldTypeUnchanged() - the
 * 'encrypted' custom-field type may only be chosen when a field is created,
 * never changed to/from on an existing field (BRCD-4649 follow-up).
 */
class ConfigCustomFieldsTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var ConfigModel
     */
    protected $model;

    protected function _before()
    {
        // bypass the constructor: it loads config from the DB, which this
        // purely-logical validation does not need.
        $this->model = (new ReflectionClass('ConfigModel'))->newInstanceWithoutConstructor();
    }

    protected function callValidate($fieldName, $field, $prevField)
    {
        $method = new ReflectionMethod('ConfigModel', 'validateEncryptedFieldTypeUnchanged');
        $method->setAccessible(true);
        return $method->invoke($this->model, $fieldName, $field, $prevField);
    }

    public function testNewFieldCanBeEncrypted()
    {
        // no prior field (creation) - any type, including 'encrypted', is allowed
        $result = $this->callValidate('enc_field_demo', array('field_name' => 'enc_field_demo', 'type' => 'encrypted'), false);
        $this->assertNull($result); // reached without throwing
    }

    public function testUnchangedTypeIsAllowed()
    {
        $field = array('field_name' => 'enc_field_demo', 'type' => 'encrypted');
        $result = $this->callValidate('enc_field_demo', $field, $field);
        $this->assertNull($result);
    }

    public function testUnrelatedTypeChangeIsAllowed()
    {
        $prevField = array('field_name' => 'plain_field', 'type' => 'string');
        $field = array('field_name' => 'plain_field', 'type' => 'integer');
        $result = $this->callValidate('plain_field', $field, $prevField);
        $this->assertNull($result);
    }

    public function testCannotChangeTypeToEncrypted()
    {
        $prevField = array('field_name' => 'plain_field', 'type' => 'string');
        $field = array('field_name' => 'plain_field', 'type' => 'encrypted');
        $this->expectException(Exception::class);
        $this->callValidate('plain_field', $field, $prevField);
    }

    public function testCannotChangeTypeFromEncrypted()
    {
        $prevField = array('field_name' => 'enc_field_demo', 'type' => 'encrypted');
        $field = array('field_name' => 'enc_field_demo', 'type' => 'string');
        $this->expectException(Exception::class);
        $this->callValidate('enc_field_demo', $field, $prevField);
    }
}
