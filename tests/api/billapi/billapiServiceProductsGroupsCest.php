<?php

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertArrayNotHasKey;

/**
 * BRCD-5430 - services keep products groups (include.products_groups) instead of groups:
 * - each entry references an existing products group by key
 * - an entry is either usage based (value + unit, the group must have a property type)
 *   or monetary based (cost)
 * - after the service was saved the quota of an entry cannot be changed from
 *   usage based to monetary based and vice versa
 */
class billapiServiceProductsGroupsCest
{

    public function _before(ApiTester $I)
    {
        $data = [
            [
                "property_type" => "time",
                "invoice_uom" => "",
                "input_uom" => "",
                "usage_type" => "call",
                "label" => "call"
            ],
            [
                "property_type" => "data",
                "invoice_uom" => "",
                "input_uom" => "",
                "usage_type" => "data",
                "label" => "data"
            ]
        ];
        $category = "usage_types";
        $I->setSettings($category, $data);
    }

    /**
     * create a products group with a property type (usage based eligible)
     */
    protected function createTypedGroup(ApiTester $I)
    {
        $key = $I->uniqueEntityKey('SPG');
        $I->generateProductsGroup([
            'key' => $key,
            'usage_types' => ['call'],
            'rates' => [$I->generateRate([], 'call')],
        ]);
        return $key;
    }

    /**
     * create a products group without activity types (no property type)
     */
    protected function createUntypedGroup(ApiTester $I)
    {
        $key = $I->uniqueEntityKey('SPG_UNTYPED');
        $I->generateProductsGroup([
            'key' => $key,
            'usage_types' => [],
            'rates' => [$I->generateRate([], 'call')],
        ]);
        return $key;
    }

    public function testCreateServiceWithProductsGroups(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $untypedGroup = $this->createUntypedGroup($I);
        $I->generateServiceWithProductsGroups([
            [
                'key' => $typedGroup,
                'account_shared' => true,
                'account_pool' => true,
                'quantity_affected' => true,
                'value' => 200,
                'unit' => 'seconds',
            ],
            [
                'key' => $untypedGroup,
                'account_shared' => false,
                'account_pool' => false,
                'quantity_affected' => false,
                'cost' => 44,
            ],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"status":1');
        $entity = $I->getLastEntity();
        assertEquals($typedGroup, $entity['include']['products_groups'][0]['key']);
        assertEquals(200, $entity['include']['products_groups'][0]['value']);
        assertEquals('seconds', $entity['include']['products_groups'][0]['unit']);
        assertEquals($untypedGroup, $entity['include']['products_groups'][1]['key']);
        assertEquals(44, $entity['include']['products_groups'][1]['cost']);
    }

    public function testCreateServiceWithUnlimitedUsageQuota(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $I->generateServiceWithProductsGroups([
            ['key' => $typedGroup, 'value' => 'UNLIMITED', 'unit' => 'minutes'],
        ]);
        $I->seeResponseContains('"status":1');
        $entity = $I->getLastEntity();
        assertEquals('UNLIMITED', $entity['include']['products_groups'][0]['value']);
    }

    public function testCreateServiceWithUnknownProductsGroupAborted(ApiTester $I)
    {
        $I->generateServiceWithProductsGroups([
            ['key' => 'NO_SUCH_PRODUCTS_GROUP', 'cost' => 10],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('Unknown products group');
    }

    public function testUsageBasedQuotaOnUntypedGroupAborted(ApiTester $I)
    {
        $untypedGroup = $this->createUntypedGroup($I);
        $I->generateServiceWithProductsGroups([
            ['key' => $untypedGroup, 'value' => 200, 'unit' => 'seconds'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('has no property type');
    }

    public function testQuotaWithBothValueAndCostAborted(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $I->generateServiceWithProductsGroups([
            ['key' => $typedGroup, 'value' => 200, 'unit' => 'seconds', 'cost' => 44],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('must have either a value (usage based) or a cost (monetary based)');
    }

    public function testUsageBasedQuotaWithoutUnitAborted(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $I->generateServiceWithProductsGroups([
            ['key' => $typedGroup, 'value' => 200],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('unit is mandatory');
    }

    public function testUsageBasedQuotaWithUnknownUnitAborted(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        // 'mb1000' is a data unit while the group property type is time
        $I->generateServiceWithProductsGroups([
            ['key' => $typedGroup, 'value' => 200, 'unit' => 'mb1000'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('unknown unit');
    }

    /**
     * a products group with a property type can also be included as monetary based -
     * for a monetary quota all products groups are available, typed ones included
     */
    public function testMonetaryQuotaOnTypedGroup(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $I->generateServiceWithProductsGroups([
            [
                'key' => $typedGroup,
                'account_shared' => true,
                'account_pool' => true,
                'quantity_affected' => true,
                'cost' => 44,
            ],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"status":1');
        $entity = $I->getLastEntity();
        $entry = $entity['include']['products_groups'][0];
        assertEquals($typedGroup, $entry['key']);
        assertEquals(44, $entry['cost']);
        // a monetary quota holds no usage based fields even though the group is typed
        assertArrayNotHasKey('value', $entry);
        assertArrayNotHasKey('unit', $entry);
    }

    public function testMonetaryQuotaWithUnitAborted(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $I->generateServiceWithProductsGroups([
            ['key' => $typedGroup, 'cost' => 44, 'unit' => 'seconds'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('unit is not allowed for a monetary based quota');
    }

    public function testMonetaryQuotaWithUnlimitedCostAborted(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $I->generateServiceWithProductsGroups([
            ['key' => $typedGroup, 'cost' => 'UNLIMITED'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cost must be a positive number');
    }


    public function testQuotaTypeCannotBeChangedAfterSave(ApiTester $I)
    {
        $typedGroup = $this->createTypedGroup($I);
        $I->generateServiceWithProductsGroups([
            ['key' => $typedGroup, 'value' => 200, 'unit' => 'seconds'],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
        $recurrence = ['frequency' => 1, 'start' => 1];
        // changing the quota amount is allowed
        $I->sendBillapiUpdate('services', ['_id' => $id], [
            'recurrence' => $recurrence,
            'include' => ['products_groups' => [
                ['key' => $typedGroup, 'value' => 300, 'unit' => 'minutes'],
            ]],
        ]);
        $I->seeResponseContains('"status":1');
        // changing the quota from usage based to monetary based is not allowed
        $I->sendBillapiUpdate('services', ['_id' => $id], [
            'recurrence' => $recurrence,
            'include' => ['products_groups' => [
                ['key' => $typedGroup, 'cost' => 44],
            ]],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed from usage based to monetary based');
    }

}
