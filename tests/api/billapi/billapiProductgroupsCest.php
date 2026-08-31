<?php

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertArrayNotHasKey;

/**
 * BRCD-5428 - products groups billapi entity:
 * - the group property type is derived from the first chosen activity type
 *   and all activity types must share it
 * - a group whose products and property type are exactly the same as an existing group cannot be saved
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

    public function testCreateProductsGroup(ApiTester $I)
    {
        $rate1 = $I->generateRate([], 'call');
        $rate2 = $I->generateRate([], 'incoming_call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
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
        // priced for both property types, so that the same products set can be held by a
        // "time" group and by a "data" group - a product must match the group property type
        $rate1 = $I->generateRate([], ['call', 'data']);
        $rate2 = $I->generateRate([], ['incoming_call', 'data']);
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseContains('"status":1');
        // the same products in a different order are still the same products set
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate2, $rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already contains exactly the same products');
        // a different products set is allowed
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        // the same products plus an extra one is a different set ($size rules the superset out)
        $rate3 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1, $rate2, $rate3],
        ]);
        $I->seeResponseContains('"status":1');
        // duplicated entries in the request are deduped before the comparison
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate2, $rate1, $rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already contains exactly the same products');
        // the same products under a different property type are a different group
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['data'],
            'rates' => [$rate2, $rate1],
        ]);
        $I->seeResponseContains('"status":1');
        // the same products with no activity types (null property type) are a different group
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => [],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseContains('"status":1');
        // but two groups without a property type and the same products do collide
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => [],
            'rates' => [$rate2, $rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already contains exactly the same products');
    }

    public function testCreateGroupWithMixedPropertyTypesAborted(ApiTester $I)
    {
        $rate1 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call', 'data'],
            'rates' => [$rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('must share the same property type');
    }

    public function testCreateGroupWithoutActivityTypes(ApiTester $I)
    {
        $rate1 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
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
        $rate1 = $I->generateRate([], 'call');
        $rate2 = $I->generateRate([], 'incoming_call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
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
        $rate1 = $I->generateRate([], 'call');
        $key = $I->uniqueEntityKey('PG');
        $I->generateProductsGroup([
            'key' => $key,
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
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
        $rate1 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
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
        $rate1 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'usage_types' => [],
            'property_type' => 'time',
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed');
    }

    public function testUniquegetReturnsAllGroups(ApiTester $I)
    {
        // uniqueget must group by key - a wrong group field collapses all groups into one
        $rate1 = $I->generateRate([], 'call');
        $rate2 = $I->generateRate([], 'call');
        $key1 = $I->uniqueEntityKey('PG');
        $key2 = $I->uniqueEntityKey('PG');
        $I->generateProductsGroup([
            'key' => $key1,
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $I->generateProductsGroup([
            'key' => $key2,
            'usage_types' => ['call'],
            'rates' => [$rate2],
        ]);
        $I->seeResponseContains('"status":1');
        $I->sendBillapiUniqueget([], 'productgroups');
        $I->seeResponseContains('"status":1');
        $I->seeResponseContains($key1);
        $I->seeResponseContains($key2);
    }

    public function testCreateGroupWithSameKeyAborted(ApiTester $I)
    {
        $rate1 = $I->generateRate([], 'call');
        $rate2 = $I->generateRate([], 'call');
        $key = $I->uniqueEntityKey('PG');
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
        $rate1 = $I->generateRate([], 'call');
        $rate2 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
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

    public function testRatesOnlyUpdateComparedUnderStoredPropertyType(ApiTester $I)
    {
        // an update that changes only the products carries no usage_types, so the
        // duplicate check must use the group's stored property type - not null
        $rate1 = $I->generateRate([], 'call');
        $rate2 = $I->generateRate([], 'call');
        $rate3 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1, $rate2],
        ]);
        $I->seeResponseContains('"status":1');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate3],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
        // a rates-only update into the exact products set of another group with the
        // same property type must not succeed
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'rates' => [$rate2, $rate1],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('already contains exactly the same products');
        // while the same products set held only by a group without a property type
        // is not a duplicate for this "time" group
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => [],
            'rates' => [$rate1, $rate3],
        ]);
        $I->seeResponseContains('"status":1');
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'rates' => [$rate3, $rate1],
        ]);
        $I->seeResponseContains('"status":1');
    }

    public function testCloseandnewCannotChangePropertyType(ApiTester $I)
    {
        $rate1 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
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
        $rate1 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
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
        $rate1 = $I->generateRate([], 'call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
        $I->sendBillapiClose('productgroups', ['_id' => $id], [
            'to' => date('Y-m-d', strtotime('+1 day')),
        ]);
        $I->seeResponseContains('"status":1');
        // the same products set is allowed when the revisions do not overlap in time
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$rate1],
            'from' => date('Y-m-d', strtotime('+3 days')),
        ]);
        $I->seeResponseContains('"status":1');
    }

    public function testPropertyTypeCannotBeSetAfterSaveWithoutActivityTypes(ApiTester $I)
    {
        $rate1 = $I->generateRate([], 'incoming_call');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => [],
            'rates' => [$rate1],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
        // the group was saved with a null property type - it cannot be changed
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'usage_types' => ['call'],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('cannot be changed');
    }

    public function testProductsOfAnotherPropertyTypeAborted(ApiTester $I)
    {
        $timeRate = $I->generateRate([], 'call');
        $dataRate = $I->generateRate([], 'data');
        // a group cannot be created with products of another property type
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$timeRate, $dataRate],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('not of the products group property type');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => ['call'],
            'rates' => [$timeRate],
        ]);
        $I->seeResponseContains('"status":1');
        $id = $I->getEntityId();
        // the GUI products filter is bypassed by clearing the activity types, adding a
        // product of another property type and choosing the activity types again - the
        // saved update keeps the property type and must still be rejected
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'usage_types' => ['call'],
            'rates' => [$timeRate, $dataRate],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('not of the products group property type');
        // a products only update cannot smuggle it in either
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'rates' => [$timeRate, $dataRate],
        ]);
        $I->dontSeeResponseContains('"status":1');
        $I->seeResponseContains('not of the products group property type');
        // another product of the group property type is allowed
        $otherTimeRate = $I->generateRate([], 'incoming_call');
        $I->sendBillapiUpdate('productgroups', ['_id' => $id], [
            'rates' => [$timeRate, $otherTimeRate],
        ]);
        $I->seeResponseContains('"status":1');
    }

    public function testGroupWithoutActivityTypesHoldsAllPropertyTypes(ApiTester $I)
    {
        // a group with no activity types has no property type, so it is not limited
        $timeRate = $I->generateRate([], 'call');
        $dataRate = $I->generateRate([], 'data');
        $I->generateProductsGroup([
            'key' => $I->uniqueEntityKey('PG'),
            'usage_types' => [],
            'rates' => [$timeRate, $dataRate],
        ]);
        $I->seeResponseContains('"status":1');
        $entity = $I->getLastEntity();
        assertArrayNotHasKey('property_type', $entity);
    }

}
