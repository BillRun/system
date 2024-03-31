<?php

class Mongodloid_TypeConverter
{
   /**
	 * Converts a BSON type to the Mongodloid types
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function toMongodloid($value) {
		switch (true) {
			case $value instanceof MongoDB\BSON\Type:
				return self::convertBSONObjectToMongodloid($value);
			case $value instanceof MongoDB\Model\IndexInfo:
				return $value->__debugInfo();
			case is_array($value):
			case is_object($value):
				$result = [];

				foreach ($value as $key => $item) {
					$result[$key] = self::toMongodloid($item);
				}

				return $result;
			default:
				return $value;
		}
	}

	/**
	 * Converter method to convert a BSON object to its Mongodloid type
	 *
	 * @param BSON\Type $value
	 * @return mixed
	 */
	private static function convertBSONObjectToMongodloid(MongoDB\BSON\Type $value) {
		if (!$value) {
			return false;
		}
		switch (true) {
			case $value instanceof MongoDB\BSON\ObjectID:
				return new Mongodloid_Id($value);
			case $value instanceof MongoDB\BSON\Regex:
				return new Mongodloid_Regex($value);
			case $value instanceof MongoDB\BSON\UTCDatetime:
				return new Mongodloid_Date($value);
			case $value instanceof MongoDB\BSON\Binary:
				return new Mongodloid_Binary($value);
			case $value instanceof MongoDB\Model\BSONDocument:
			case $value instanceof MongoDB\Model\BSONArray:
				return array_map(
					['self', 'toMongodloid'],
					$value->getArrayCopy()
				);
			default:
				return $value;
		}
	}
	
	/**
     * Converts a Mongodloid type to the new BSON type
     *
     * @param mixed $value
     * @return mixed
     */
    public static function fromMongodloid($value)
    {
        switch (true) {
            case $value instanceof Mongodloid_TypeInterface:
				return $value->toBSONType();
            case $value instanceof MongoDB\BSON\Type:
                return $value;
            case is_array($value):
            case is_object($value):
                $result = [];

                foreach ($value as $key => $item) {
                    $result[$key] = self::fromMongodloid($item);
                }
				
                return self::ensureCorrectType($result, is_object($value));
            default:
                return $value;
        }
    }
	
	
	/**
     * Converts all arrays with non-numeric keys to stdClass
     *
     * @param array $array
     * @param bool $wasObject
     * @return array|Model\BSONArray|Model\BSONDocument
     */
    private static function ensureCorrectType(array $array, $wasObject = false)
    {
        if ($wasObject || ! static::isNumericArray($array)) {
            return new MongoDB\Model\BSONDocument($array);
        }

        return $array;
    }
	
	/**
     * Helper method to find out if an array has numerical indexes
     *
     * For performance reason, this method checks the first array index only.
     * More thorough inspection of the array might be needed.
     * Note: Returns true for empty arrays to preserve compatibility with empty
     * lists.
     *
     * @param array $array
     * @return bool
     */
    public static function isNumericArray(array $array)
    {
        if ($array === []) {
            return true;
        }

        $keys = array_keys($array);
        // array_keys gives us a clean numeric array with keys, so we expect an
        // array like [0 => 0, 1 => 1, 2 => 2, ..., n => n]
        return array_values($keys) === array_keys($keys);
    }
}
