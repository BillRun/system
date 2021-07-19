<?php

/**
 * Wrapper class for MongoDB storage used by OAuth2.0
 * Adding required functionality to the original (vendor) class.
 * 
 * Allows updating the vendor class without affecting the functionality added by BillRun
 *
 * @author Yonatan
 */
class Billrun_OAuth2_Storage_MongoDB extends OAuth2\Storage\MongoDB {
    public function unsetClientDetails($client_id, $client_secret = null) {
		if (is_null($client_id) && is_null($client_secret)) {
			return false;
		}
		if (!is_null($client_id)) {
			return $this->collection('client_table')->deleteOne(array('client_id' => $client_id));
		}
		return $this->collection('client_table')->deleteOne(array('client_secret' => $client_secret));
	}
}