<?php

/**
 * Unit tests for CF (calculated_fields) computed value resolution -
 * Billrun_EntityGetter_Filters_Base::getComputedValue().
 *
 * Covers expressing a "use A, otherwise B" fall-back with the existing
 * "condition" mapping (no dedicated regex fall-back is needed):
 *
 *   type: condition, operator: $regex, line_keys: [ {key: A}, {key: /^$/} ],
 *   must_met: false, projection: { on_true: {key: B}, on_false: {key: A} }
 *
 * The first key (A) is matched against the "empty" regex /^$/, so when A is
 * empty or missing the condition is true and the projection returns B
 * (on_true); otherwise it returns A itself (on_false).
 */
class CalculatedFieldsTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * Build a "use $primary, otherwise $fallback" condition mapping.
     */
    protected function fallbackConfig($primary, $fallback)
    {
        return [
            'target_field' => 'calling_subs_first_lac',
            'type' => 'condition',
            'line_keys' => [
                ['key' => $primary],
                ['key' => '/^$/'],
            ],
            'operator' => '$regex',
            'must_met' => false,
            'projection' => [
                'on_true' => ['key' => $fallback],
                'on_false' => ['key' => $primary],
            ],
        ];
    }

    protected function computed($config, $row)
    {
        $filter = new Billrun_EntityGetter_Filters_Base(['computed' => $config]);
        return $filter->getComputedValue($row);
    }

    public function testConditionFallbackWithFlatKeys()
    {
        $config = $this->fallbackConfig('lac', 'tac');

        // primary present -> use primary (on_false)
        $this->assertEquals('250', $this->computed($config, ['lac' => '250', 'tac' => '987654']));

        // primary present but empty string -> fall back to tac (on_true)
        $this->assertEquals('987654', $this->computed($config, ['lac' => '', 'tac' => '987654']));

        // primary missing altogether -> fall back to tac (on_true)
        $this->assertEquals('987654', $this->computed($config, ['tac' => '987654']));

        // "0" is a real value (not empty) -> use primary
        $this->assertEquals('0', $this->computed($config, ['lac' => '0', 'tac' => '987654']));
    }

    public function testConditionFallbackWithNestedKeys()
    {
        $config = $this->fallbackConfig('user_location_information.lac', 'user_location_information.tac');

        // lac present -> use lac
        $this->assertEquals('42', $this->computed($config, [
            'user_location_information' => ['lac' => '42', 'tac' => '987654'],
        ]));

        // lac present but empty -> fall back to tac
        $this->assertEquals('987654', $this->computed($config, [
            'user_location_information' => ['lac' => '', 'tac' => '987654'],
        ]));

        // lac missing -> fall back to tac
        $this->assertEquals('987654', $this->computed($config, [
            'user_location_information' => ['tac' => '987654'],
        ]));
    }
}
