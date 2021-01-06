<?php

class Mongodloid_Result {
	/**
	 * Convert mongodb result to mongodloid result
	 * @param mixed $result - mongodb result
	 * @return mixed -The mongodloid result
	 */
	public static function getResult($result) {
		$callingMethod = self::getCallingMethodName();
		switch ($callingMethod) {
			case 'update':
			case 'updateEntity':
				return self::buildUpdateResult($result);
			case 'remove':
				return self::buildRemoveResult($result);
			case 'save': 
				return (!$result)? false : true;
			default:
				return Mongodloid_TypeConverter::toMongodloid($result);
		}
	}
	
	/**
	 * Build mongodloid result for remove query
	 * @param mixed $result - mongodb remove result
	 * @return mixed - return mongodloid remove result,
	 *  or true if the write was not acknowledged
	 */
	private static function buildRemoveResult($result) {

		if (!$result->isAcknowledged()) {
			return true;
		}

		return [
			'ok' => 1.0,
			'n' => $result->getDeletedCount(),
			'err' => null,
			'errmsg' => null
		];
	}

	/**
	 * Build mongodloid result for uppdate query
	 * @param mixed $result - mongodb uppdate result
	 * @return mixed - return mongodloid uppdate result,
	 *  or true if the write was not acknowledged
	 */
	private static function buildUpdateResult($result) {	

		if (!$result->isAcknowledged()) {
			return true;
		}

		return [
			'ok' => 1.0,
			'nModified' => $result->getModifiedCount(),
			'n' => $result->getMatchedCount(),
			'err' => null,
			'errmsg' => null,
			'updatedExisting' => $result->getUpsertedCount() == 0 && $result->getModifiedCount() > 0,
		];
	}

	private static function getCallingMethodName() {
		return debug_backtrace()[2]['function'];
	}
}
