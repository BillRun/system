<?php

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertArrayNotHasKey;

/**
 * BRCD-5428 - products groups billapi entity:
 * - the group property type is derived from the first chosen activity type
 *   and all activity types must share it
 * - a group whose products are exactly the same as an existing group cannot be saved
 * - the property type cannot be changed after the group was saved
 */
class billapiProductgroupsCest
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
                "property_type" => "time",
                "invoice_uom" => "",
                "input_uom" => "",
                "usage_type" => "incoming_call",
                "label" => "incoming call"
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

    protected function uniqueKey($prefix)
    {
        return strtoupper($prefix . '_' . str_replace('.', '', microtime(true) * 10000) . '_' . rand(1000, 9999));
    }

    protected function createProduct(ApiTester $I, $usaget)
    {
        $key = $this->uniqueKey('PG_RATE');
        $I->generateRate([
            'key' => $key,
            'description' => 'products group test rate ' . $key,
            'rates' => [
                $usaget => [
                    'BASE' => [
                        'rate' => [
                            [
                                'from' => 0,
                                'to' => 'UNLIMITED',
                                'interval' => 1,
                                'price' => 1,
                                'uom_display' => [
                                    'range' => 'seconds',
                                    'interval' => 'seconds'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
        ]);
        return $key;
    }

    protected function getEntityId($entity)
    {
        return is_array($entity['_id']) ? $entity['_id']['$id'] : $entity['_id'];
    }

    public function testCreateProductsGroup(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $rate2 = $this->createProduct($I, 'incoming_call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call', 'incoming_call'],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"status":1');
        $entity = $I->getLastEntity();
        assertEquals('time', $entity['property_type']);
        assertEquals(['call', 'incoming_call'], $entity['usage_types']);
        assertEquals([$rate1, $rate2], $entity['rates']);
    }

    public function testCreateGroupWithSameProductsAborted(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $rate2 = $this->createProduct($I, 'incoming_call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseContains('"status":1');
        // the same products in a different order are still the same products set
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate2, $rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already contains exactly the same products');
        // a different products set is allowed
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        // the same products plus an extra one is a different set ($size rules the superset out)
        $rate3 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1, $rate2, $rate3],
        ]);
        $I->seeResponseContains('"status":1');
        // duplicated entries in the request are deduped before the comparison
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate2, $rate1, $rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already contains exactly the same products');
    }

    public function testCreateGroupWithMixedPropertyTypesAborted(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call', 'data'],
            'rates' => [$rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('must share the same property type');
    }

    public function testCreateGroupWithoutActivityTypes(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => [],
            'rates' => [$rate1],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"status":1');
        $entity = $I->getLastEntity();
        assertArrayNotHasKey('property_type', $entity);
        assertEquals([], $entity['usage_types']);
    }

    public function testPropertyTypeCannotBeChangedAfterSave(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $rate2 = $this->createProduct($I, 'incoming_call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        // changing the activity types within the same property type is allowed
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'description' => 'updated products group',
            'usage_types' => ['call', 'incoming_call'],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseContains('"status":1');
        // changing to an activity type with another property type is not allowed
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'usage_types' => ['data'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed');
    }

    public function testUpdateWithoutTouchingActivityTypesAllowed(ApiTester $I)
    {
        // a partial update that does not include usage_types must not trip the
        // property type immutability check
        $rate1 = $this->createProduct($I, 'call');
        $key = $this->uniqueKey('PG');
        $I->generateProductsGroup([
            'key' => $key,
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'description' => 'renamed products group',
        ]);
        $I->seeResponseContains('"status":1');
        // the fields that were not part of the update are untouched
        $I->sendBillapiGet(['key' => $key], 'productgroups');
        $I->seeResponseContainsJson([
            'key' => $key,
            'description' => 'renamed products group',
            'property_type' => 'time',
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
    }

    public function testClientSentPropertyTypeIgnoredWithoutActivityTypes(ApiTester $I)
    {
        // a clone of a typed group carries a stale property_type in the payload even
        // when all activity types were removed - it must not be persisted
        $rate1 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => [],
            'property_type' => 'time',
            'rates' => [$rate1],
        ]);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"status":1');
        $entity = $I->getLastEntity();
        assertArrayNotHasKey('property_type', $entity);
    }

    public function testEmptyingActivityTypesOfTypedGroupAborted(ApiTester $I)
    {
        // the UI echoes the whole item on update - removing all activity types while
        // the old property_type rides along would null the property type, so it is rejected
        $rate1 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'usage_types' => [],
            'property_type' => 'time',
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed');
    }

    public function testCreateGroupWithSameKeyAborted(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $rate2 = $this->createProduct($I, 'call');
        $key = $this->uniqueKey('PG');
        $I->generateProductsGroup([
            'key' => $key,
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        // the same key is rejected even when the products are different
        $I->generateProductsGroup([
            'key' => $key,
            'usage_types' => ['call'],
            'rates' => [$rate2],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already exists');
    }

    public function testUpdateToSameProductsSetAborted(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $rate2 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        // updating the second group to exactly the first group's products is rejected
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'rates' => [$rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already contains exactly the same products');
        // updating to a products set no other group has is allowed
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'rates' => [$rate2],
        ]);
        $I->seeResponseContains('"status":1');
    }

    public function testCloseandnewCannotChangePropertyType(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        $from = date('Y-m-d', strtotime('+1 day'));
        $I->sendBillapiCloseandnew('productgroups', ['_id' => $id], [
            'from' => $from,
            'usage_types' => ['data'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed');
        // a new revision within the same property type is allowed
        $I->sendBillapiCloseandnew('productgroups', ['_id' => $id], [
            'from' => $from,
            'usage_types' => ['call', 'incoming_call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
    }

    public function testPermanentchangeCannotChangePropertyType(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        $from = date('Y-m-d', strtotime('+1 day'));
        $I->sendBillapiPermanentchange('productgroups', ['_id' => $id], [
            'from' => $from,
            'usage_types' => ['data'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed');
        // a permanent change that does not touch the activity types is allowed
        $I->sendBillapiPermanentchange('productgroups', ['_id' => $id], [
            'from' => $from,
            'description' => 'permanently renamed products group',
        ]);
        $I->seeResponseContains('"status":1');
    }

    public function testSameProductsSetAllowedWhenRevisionsDoNotOverlap(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        $I->sendBillapiClose('productgroups', ['_id' => $id], [
            'to' => date('Y-m-d', strtotime('+1 day')),
        ]);
        $I->seeResponseContains('"status":1');
        // the same products set is allowed when the revisions do not overlap in time
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
            'from' => date('Y-m-d', strtotime('+3 days')),
        ]);
        $I->seeResponseContains('"status":1');
    }

    public function testPropertyTypeCannotBeSetAfterSaveWithoutActivityTypes(ApiTester $I)
    {
        $rate1 = $this->createProduct($I, 'incoming_call');
        $I->generateProductsGroup([
            'key' => $this->uniqueKey('PG'),
            'usage_types' => [],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $this->getEntityId($I->getLastEntity());
        // the group was saved with a null property type - it cannot be changed
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'usage_types' => ['call'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed');
    }

}
