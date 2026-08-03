<?php

/**
 * Unit tests for MongoDB read-preference behavior across the Mongodloid layer.
 * All assertions are client-side (getReadPreference()->getModeString() and the
 * cursor's stored options), so no live Mongo round-trip or seeded data is needed.
 */
class MongodloidReadPreferenceTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * original db-level read preference mode, restored after each test so the
     * process-wide Billrun_Factory::db() singleton does not leak between tests
     * @var int
     */
    private $origMode;

    protected function _before()
    {
        $this->origMode = Billrun_Factory::db()->getDb()->getReadPreference()->getMode();
    }

    protected function _after()
    {
        Billrun_Factory::db()->setReadPreference($this->origMode);
    }

    /**
     * effective read preference mode of the shared db connection
     */
    private function dbMode()
    {
        return Billrun_Factory::db()->getDb()->getReadPreference()->getModeString();
    }

    /**
     * effective read preference mode of a collection (from the native driver object)
     */
    private function collMode(Mongodloid_Collection $collection)
    {
        return $collection->getMongoCollection()->getReadPreference()->getModeString();
    }

    /**
     * read preference the cursor will apply at find() time (stored in its options)
     */
    private function cursorMode(Mongodloid_Cursor $cursor)
    {
        $ref = new ReflectionObject($cursor);
        $prop = $ref->getProperty('_options');
        $prop->setAccessible(true);
        $options = $prop->getValue($cursor);
        return isset($options['readPreference']) ? $options['readPreference']->getModeString() : 'none';
    }

    public function testDbSetReadPreference()
    {
        $db = Billrun_Factory::db();

        $ret = $db->setReadPreference('RP_SECONDARY');
        $this->assertInstanceOf('Mongodloid_Db', $ret, 'setReadPreference should return self for chaining');
        $this->assertEquals('secondary', $this->dbMode(), 'db read preference should be updated');

        // a freshly fetched collection inherits the db-level preference
        $this->assertEquals('secondary', $this->collMode($db->linesCollection()), 'collections fetched after the change should inherit it');

        // an invalid value is a no-op and returns false
        $this->assertFalse($db->setReadPreference('NONSENSE'), 'invalid value should return false');
        $this->assertEquals('secondary', $this->dbMode(), 'invalid value should leave the preference unchanged');
    }

    public function testDbSetReadPreferenceWithTags()
    {
        $db = Billrun_Factory::db();
        $tags = [['dc' => 'ny']];

        $ret = $db->setReadPreference('RP_NEAREST', $tags);
        $this->assertInstanceOf('Mongodloid_Db', $ret, 'setReadPreference with tags should return self for chaining');
        $this->assertEquals('nearest', $this->dbMode(), 'db read preference mode should be nearest');
        $this->assertEquals($tags, $db->getDb()->getReadPreference()->getTagSets(), 'tag sets should be applied on the connection');

        // invalid tags (flat assoc array instead of a list of tag sets) are a no-op and return false
        $this->assertFalse($db->setReadPreference('RP_NEAREST', ['dc' => 'ny']), 'invalid tags should return false');
        $this->assertEquals($tags, $db->getDb()->getReadPreference()->getTagSets(), 'invalid tags should leave the preference unchanged');
    }

    public function testCollectionSetReadPreference()
    {
        $collection = Billrun_Factory::db()->linesCollection();

        $ret = $collection->setReadPreference('RP_SECONDARY');
        $this->assertInstanceOf('Mongodloid_Collection', $ret, 'setReadPreference should return self for chaining');
        $this->assertEquals('secondary', $this->collMode($collection), 'collection read preference should actually take effect');
        $this->assertFalse($collection->setReadPreference('NONSENSE'), 'invalid value should return false');
    }

    public function testCursorSetReadPreference()
    {
        $cursor = Billrun_Factory::db()->linesCollection()->query()->cursor();

        $ret = $cursor->setReadPreference('RP_NEAREST');
        $this->assertInstanceOf('Mongodloid_Cursor', $ret, 'setReadPreference should return self for chaining');
        $this->assertEquals('nearest', $this->cursorMode($cursor), 'cursor read preference should be stored for the query');
    }

    public function testPrecedenceOrder()
    {
        $db = Billrun_Factory::db();

        // db baseline
        $db->setReadPreference('RP_SECONDARY');
        $this->assertEquals('secondary', $this->collMode($db->linesCollection()), 'untouched collection inherits the db baseline');

        // collection override wins over db, and is scoped to that collection only
        $balances = $db->balancesCollection();
        $balances->setReadPreference('RP_PRIMARY');
        $this->assertEquals('primary', $this->collMode($balances), 'collection override should win over the db baseline');
        $this->assertEquals('secondary', $this->collMode($db->linesCollection()), 'a different collection should stay on the db baseline');

        // cursor override wins over collection and db
        $cursor = $balances->query()->cursor()->setReadPreference('RP_NEAREST');
        $this->assertEquals('nearest', $this->cursorMode($cursor), 'cursor override should win over collection and db');
    }
}
