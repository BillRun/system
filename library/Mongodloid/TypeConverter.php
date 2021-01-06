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
			case $value instanceof Alcaeus\MongoDbAdapter\TypeInterface://still support mongo - after remove all this usages in the code can remove this.
				return $value->toBSONType();
            case $value instanceof MongoDB\BSON\Type:
                return $value;
            case is_array($value):
            case is_object($value):
                $result = [];

                foreach ($value as $key => $item) {
                    $result[$key] = self::fromMongodloid($item);
                }

                return $result;
            default:
                return $value;
        }
    }
}
