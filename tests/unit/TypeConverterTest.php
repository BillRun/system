<?php

class TypeConverterTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testToMongodloidScalars()
    {
        $this->assertEquals(123, Mongodloid_TypeConverter::toMongodloid(123));
        $this->assertEquals(12.5, Mongodloid_TypeConverter::toMongodloid(12.5));
        $this->assertEquals('abc', Mongodloid_TypeConverter::toMongodloid('abc'));
        $this->assertEquals(true, Mongodloid_TypeConverter::toMongodloid(true));
        $this->assertEquals(null, Mongodloid_TypeConverter::toMongodloid(null));
    }

    public function testToMongodloidArrayAndObject()
    {
        $array = ['a' => 1, 'b' => ['c' => 2]];
        $this->assertEquals($array, Mongodloid_TypeConverter::toMongodloid($array));

        $obj = new stdClass();
        $obj->a = 1;
        $obj->b = ['c' => 2];
        $converted = Mongodloid_TypeConverter::toMongodloid($obj);
        $this->assertTrue(is_array($converted));
        $this->assertEquals(['a' => 1, 'b' => ['c' => 2]], $converted);

        $iterable = new ArrayObject(['x' => 5, 'y' => ['z' => 6]]);
        $iterConverted = Mongodloid_TypeConverter::toMongodloid($iterable);
        $this->assertTrue(is_array($iterConverted));
        $this->assertEquals(['x' => 5, 'y' => ['z' => 6]], $iterConverted);

        $obj1 = new stdClass();
        $obj1->a = 1;
        $obj2 = new stdClass();
        $obj2->b = 2;
        $arrayOfObjects = [$obj1, $obj2];
        $convertedArrayOfObjects = Mongodloid_TypeConverter::toMongodloid($arrayOfObjects);
        $this->assertTrue(is_array($convertedArrayOfObjects));
        $this->assertEquals([['a' => 1], ['b' => 2]], $convertedArrayOfObjects);
    }

    public function testToMongodloidBsonTypes()
    {
        if (!class_exists('MongoDB\\BSON\\ObjectID')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $id = new MongoDB\BSON\ObjectID('5aeee57b05e68c02d035e1f6');
        $convertedId = Mongodloid_TypeConverter::toMongodloid($id);
        $this->assertInstanceOf('Mongodloid_Id', $convertedId);

        $regex = new MongoDB\BSON\Regex('abc', 'i');
        $convertedRegex = Mongodloid_TypeConverter::toMongodloid($regex);
        $this->assertInstanceOf('Mongodloid_Regex', $convertedRegex);

        $date = new MongoDB\BSON\UTCDateTime(1700000000000);
        $convertedDate = Mongodloid_TypeConverter::toMongodloid($date);
        $this->assertInstanceOf('Mongodloid_Date', $convertedDate);

        $isoDate = new DateTimeImmutable('2025-01-15T12:34:56+00:00');
        $isoBsonDate = new MongoDB\BSON\UTCDateTime($isoDate);
        $convertedIsoDate = Mongodloid_TypeConverter::toMongodloid($isoBsonDate);
        $this->assertInstanceOf('Mongodloid_Date', $convertedIsoDate);
        $this->assertEquals('2025-01-15T12:34:56+00:00', $convertedIsoDate->toDateTime()->format('c'));

        $binary = new MongoDB\BSON\Binary('abc', MongoDB\BSON\Binary::TYPE_GENERIC);
        $convertedBinary = Mongodloid_TypeConverter::toMongodloid($binary);
        $this->assertInstanceOf('Mongodloid_Binary', $convertedBinary);

        $doc = new MongoDB\Model\BSONDocument(['x' => new MongoDB\BSON\UTCDateTime(1000)]);
        $convertedDoc = Mongodloid_TypeConverter::toMongodloid($doc);
        $this->assertTrue(is_array($convertedDoc));
        $this->assertInstanceOf('Mongodloid_Date', $convertedDoc['x']);

        $arr = new MongoDB\Model\BSONArray([new MongoDB\BSON\Regex('a', '')]);
        $convertedArr = Mongodloid_TypeConverter::toMongodloid($arr);
        $this->assertTrue(is_array($convertedArr));
        $this->assertInstanceOf('Mongodloid_Regex', $convertedArr[0]);

        $indexInfo = new MongoDB\Model\IndexInfo([
            'name' => 'idx',
            'key' => ['a' => 1],
            'ns' => 'db.col',
            'v' => 2,
        ]);
        $convertedIndex = Mongodloid_TypeConverter::toMongodloid($indexInfo);
        $this->assertEquals($indexInfo->__debugInfo(), $convertedIndex);
    }

    public function testToMongodloidDeepRecursiveStructure()
    {
        if (!class_exists('MongoDB\\BSON\\UTCDateTime')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $innerObj = new stdClass();
        $innerObj->when = new MongoDB\BSON\UTCDateTime(1700000000000);

        $midObj = new stdClass();
        $midObj->items = [$innerObj];

        $root = new stdClass();
        $root->list = [$midObj];

        $converted = Mongodloid_TypeConverter::toMongodloid($root);

        $this->assertTrue(is_array($converted));
        $this->assertTrue(is_array($converted['list']));
        $this->assertTrue(is_array($converted['list'][0]));
        $this->assertTrue(is_array($converted['list'][0]['items']));
        $this->assertTrue(is_array($converted['list'][0]['items'][0]));
        $this->assertInstanceOf('Mongodloid_Date', $converted['list'][0]['items'][0]['when']);
    }

    public function testFromMongodloidScalars()
    {
        $this->assertEquals(123, Mongodloid_TypeConverter::fromMongodloid(123));
        $this->assertEquals(12.5, Mongodloid_TypeConverter::fromMongodloid(12.5));
        $this->assertEquals('abc', Mongodloid_TypeConverter::fromMongodloid('abc'));
        $this->assertEquals(false, Mongodloid_TypeConverter::fromMongodloid(false));
        $this->assertEquals(null, Mongodloid_TypeConverter::fromMongodloid(null));
    }

    public function testFromMongodloidArrayAndObject()
    {
        if (!class_exists('MongoDB\\Model\\BSONDocument')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $assoc = ['a' => 1, 'b' => 2];
        $assocConverted = Mongodloid_TypeConverter::fromMongodloid($assoc);
        $this->assertInstanceOf('MongoDB\\Model\\BSONDocument', $assocConverted);
        $this->assertEquals(1, $assocConverted['a']);

        $numeric = [1, 2, 3];
        $numericConverted = Mongodloid_TypeConverter::fromMongodloid($numeric);
        $this->assertTrue(is_array($numericConverted));
        $this->assertEquals($numeric, $numericConverted);

        $obj = (object) ['a' => 1];
        $objConverted = Mongodloid_TypeConverter::fromMongodloid($obj);
        $this->assertInstanceOf('MongoDB\\Model\\BSONDocument', $objConverted);
        $this->assertEquals(1, $objConverted['a']);
    }

    public function testFromMongodloidTypes()
    {
        if (!class_exists('MongoDB\\BSON\\ObjectID')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $id = new Mongodloid_Id('5aeee57b05e68c02d035e1f6');
        $convertedId = Mongodloid_TypeConverter::fromMongodloid($id);
        $this->assertInstanceOf('MongoDB\\BSON\\ObjectID', $convertedId);

        $date = new Mongodloid_Date(1700000000);
        $convertedDate = Mongodloid_TypeConverter::fromMongodloid($date);
        $this->assertInstanceOf('MongoDB\\BSON\\UTCDateTime', $convertedDate);

        $regex = new Mongodloid_Regex('/abc/i');
        $convertedRegex = Mongodloid_TypeConverter::fromMongodloid($regex);
        $this->assertInstanceOf('MongoDB\\BSON\\Regex', $convertedRegex);

        $binary = new Mongodloid_Binary('abc');
        $convertedBinary = Mongodloid_TypeConverter::fromMongodloid($binary);
        $this->assertInstanceOf('MongoDB\\BSON\\Binary', $convertedBinary);

        $bsonType = new MongoDB\BSON\UTCDateTime(1000);
        $convertedBson = Mongodloid_TypeConverter::fromMongodloid($bsonType);
        $this->assertInstanceOf('MongoDB\\BSON\\UTCDateTime', $convertedBson);
    }
}
