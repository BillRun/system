<?php

class Mongodloid_Result {
	/**
	 * Convert mongodb result to mongodloid result
	 * @param mixed $result - mongodb result
	 * @return mixed -The mongodloid result
	 */
	public static function getResult($result, $resultType= FALSE) {
		$callingMethod =  (empty($resultType) ? debug_backtrace()[1]['function'] : $resultType);
		switch ($callingMethod) {
			case 'update':
			case 'updateEntity':
				return self::buildUpdateResult($result);
			case 'remove':
				return self::buildRemoveResult($result);
			case 'save':
				$error = self::extractError($result);
				return $error ? false : true;
			case 'batchInsert':
				return self::buildBatchInsertResult($result);
			case 'insert':
				return self::buildInsertResult($result);
			case 'current':
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
			Billrun_Factory::log("Write operation was not acknowledged by MongoDB! No confirmation on removed documents", Zend_Log::NOTICE);
			return true;
		}
		$error = self::extractError($result);
		return [
			'ok' => $error ? 0 : 1.0,
			'n' => $result->getDeletedCount(),
			'err' => $error['code'] ?? null,
			'errmsg' =>  $error['message'] ?? null,
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
			Billrun_Factory::log("Write operation was not acknowledged by MongoDB! No confirmation on updated documents", Zend_Log::NOTICE);
			return true;
		}
		$error = self::extractError($result);

		return [
			'ok' => $error ? 0 : 1.0,
			'nModified' => $result->getModifiedCount(),
			'n' => $result->getMatchedCount(),
			'err' => $error['code'] ?? null,
			'errmsg' =>  $error['message'] ?? null,
			'updatedExisting' => $result->getUpsertedCount() == 0 && $result->getModifiedCount() > 0,
		];
	}

	private static function buildBatchInsertResult($result) {
		if (!$result->isAcknowledged()) {
			Billrun_Factory::log("Write operation was not acknowledged by MongoDB! No confirmation on inserted documents", Zend_Log::NOTICE);

			return true;
		}
		$error = self::extractError($result);

		return [
			'ok' => $error ? 0 : 1.0,
			'nInserted' => $result->getInsertedCount(),
			'err' => $error['code'] ?? null,
			'errmsg' =>  $error['message'] ?? null,
		];
	}
	
	private static function buildInsertResult($result){
		if (! $result->isAcknowledged()) {
			Billrun_Factory::log("Write operation was not acknowledged by MongoDB! No confirmation on inserted documents", Zend_Log::NOTICE);
			return true;
		}
		$error = self::extractError($result);

		return [
			'ok' => $error ? 0 : 1.0,
			'n' => $result->getInsertedCount(),
			'err' => $error['code'] ?? null,
			'errmsg' =>  $error['message'] ?? null,
		];
	}

	/**
     * Try to extract error information from the result object if available
     * @param object $result
     * @return array|null ['code' => int, 'message' => string] or null if no error
     */
    private static function extractError($result) {
        // MongoDB PHP library does not always expose error info in the write result object.
        // Usually, errors are thrown as exceptions. But if your result contains error info, add extraction here.
        if (is_object($result) && method_exists($result, 'getWriteErrors')) {
            $errors = $result->getWriteErrors();
            if (!empty($errors)) {
                $firstError = $errors[0];
                return [
                    'code' => $firstError->getCode(),
                    'message' => $firstError->getMessage(),
                ];
            }
        }
        // If no errors found
        return null;
    }
}
