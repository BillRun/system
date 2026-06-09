<?php

class Mongodloid_Ref
{
    /**
     * @static
     * @var $refKey
     */
    protected static $refKey = '$ref';

    /**
     * @static
     * @var $idKey
     */
    protected static $idKey = '$id';

    /**
     * If no database is given, the current database is used.
     *
     * @static
     * @param string $collection Collection name (without the database name)
     * @param mixed $id The _id field of the object to which to link
     * @param string $database Database name
     * @return array Returns the reference
     */
    public static function create($collection, $id, $database = null)
    {
        $ref = [
            static::$refKey => $collection,
            static::$idKey => new Mongodloid_Id($id)
        ];

        if ($database !== null) {
            $ref['$db'] = $database;
        }

        return $ref;
    }

    /**
     * This not actually follow the reference, so it does not determine if it is broken or not.
     * It merely checks that $ref is in valid database reference format (in that it is an object or array with $ref and $id fields).
     *
     * @static
     * @param mixed $ref Array or object to check
     * @return boolean Returns true if $ref is a reference
     */
    public static function isRef($ref)
    {
        $check = (array) $ref;

        return array_key_exists(static::$refKey, $check) && array_key_exists(static::$idKey, $check);
    }

    /**
     * Fetches the object pointed to by a reference
     * @static
     * @param MongoDB $db Database to use
     * @param array $ref Reference to fetch
     * @return array|null Returns the document to which the reference refers or null if the document does not exist (the reference is broken)
     */
    public static function get($db, $ref)
    {
        if (! static::isRef($ref)) {
            return null;
        }
        return $db->getCollection($ref[static::$refKey])->findOne($ref[static::$idKey]);
    }
}
