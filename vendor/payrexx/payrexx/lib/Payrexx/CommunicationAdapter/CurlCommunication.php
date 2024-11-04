<?php

/**
 * This is the cURL communication adapter
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */

namespace Payrexx\CommunicationAdapter;

use CURLFile;
use Exception;

// check for php version 5.2 or higher
if (version_compare(PHP_VERSION, '5.2.0', '<')) {
    throw new Exception('Your PHP version is not supported. Minimum version should be 5.2.0');
} else if (!function_exists('json_decode')) {
    throw new Exception('json_decode function missing. Please install the JSON extension');
}

// is the curl extension available?
if (!extension_loaded('curl')) {
    throw new Exception('Please install the PHP cURL extension');
}

/**
 * Class CurlCommunication for the communication with cURL
 * @package Payrexx\CommunicationAdapter
 */
class CurlCommunication extends AbstractCommunication
{
    /**
     * {@inheritdoc}
     */
    public function requestApi($apiUrl, $params = array(), $method = 'POST', $httpHeader = array())
    {
        $curlOpts = array(
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => 'payrexx-php/1.8.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CAINFO => dirname(__DIR__) . '/certs/ca.pem',
        );
        if (defined(PHP_QUERY_RFC3986)) {
            $paramString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        } else {
            // legacy, because the $enc_type has been implemented with PHP 5.4
            $paramString = str_replace(
                array('+', '%7E'),
                array('%20', '~'),
                http_build_query($params, '', '&')
            );
        }
        if ($method == 'GET') {
            if (!empty($params)) {
                $curlOpts[CURLOPT_URL] .= strpos($curlOpts[CURLOPT_URL], '?') === false ? '?' : '&';
                $curlOpts[CURLOPT_URL] .= $paramString;
            }
        } else {
            $curlOpts[CURLOPT_POSTFIELDS] = $paramString;
            $curlOpts[CURLOPT_URL] .= strpos($curlOpts[CURLOPT_URL], '?') === false ? '?' : '&';
            $curlOpts[CURLOPT_URL] .= 'instance=' . $params['instance'];
        }
        if ($httpHeader) {
            $header = [];
            foreach ($httpHeader as $name => $value) {
                $header[] = $name . ': ' . $value;
            }
            $curlOpts[CURLOPT_HTTPHEADER] = $header;
        }
        $hasFile = false;
        $hasCurlFile = class_exists('CURLFile', false);
        foreach ($params as $param) {
            if (is_resource($param)) {
                $hasFile = true;
                break;
            } elseif ($hasCurlFile && $param instanceof CURLFile) {
                $hasFile = true;
                break;
            }
        }
        if ($hasFile) {
            $curlOpts[CURLOPT_HTTPHEADER][] = 'Content-type: multipart/form-data';
            if (empty($params['id'])) {
                unset($params['id']);
            }
            $curlOpts[CURLOPT_POSTFIELDS] = $params;
        }

        $curl = curl_init();
        curl_setopt_array($curl, $curlOpts);
        $responseBody = $this->curlExec($curl);
        $responseInfo = $this->curlInfo($curl);

        if ($responseBody === false) {
            $responseBody = array('status' => 'error', 'message' => $this->curlError($curl));
        }
        curl_close($curl);

        if ($responseInfo['content_type'] === 'application/json') {
            $responseBody = json_decode($responseBody, true);
        }

        return array(
            'info' => $responseInfo,
            'body' => $responseBody
        );
    }

    /**
     * The wrapper method for curl_exec
     *
     * @param resource $curl the cURL resource
     *
     * @return mixed
     */
    protected function curlExec($curl)
    {
        return curl_exec($curl);
    }

    /**
     * The wrapper method for curl_getinfo
     *
     * @param resource $curl the cURL resource
     *
     * @return mixed
     */
    protected function curlInfo($curl)
    {
        return curl_getinfo($curl);
    }

    /**
     * The wrapper method for curl_errno
     *
     * @param resource $curl the cURL resource
     *
     * @return mixed
     */
    protected function curlError($curl)
    {
        return curl_errno($curl);
    }
}
