<?php
/**
 * This class has the definition of the API used for the communication.
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */
namespace Payrexx;

use Payrexx\Models\Request\PaymentMethod;

/**
 * This object handles the communication with the API server
 * @package Payrexx
 */
class Communicator
{
    const VERSIONS = [1.0, 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9];
    const API_URL_FORMAT = 'https://api.%s/%s/%s/%s/%s';
    const API_URL_BASE_DOMAIN = 'payrexx.com';
    const DEFAULT_COMMUNICATION_HANDLER = '\Payrexx\CommunicationAdapter\CurlCommunication';

    /**
     * @var array A set of methods which can be used to communicate with the API server.
     */
    protected static $methods = array(
        'create'       => 'POST',
        'charge'       => 'POST',
        'refund'       => 'POST',
        'capture'      => 'POST',
        'receipt'      => 'POST',
        'preAuthorize' => 'POST',
        'cancel'       => 'DELETE',
        'delete'       => 'DELETE',
        'update'       => 'PUT',
        'getAll'       => 'GET',
        'getOne'       => 'GET',
        'details'      => 'GET',
    );
    /**
     * @var string The Payrexx instance name.
     */
    protected $instance;
    /**
     * @var string The API secret which is used to generate a signature.
     */
    protected $apiSecret;
    /**
     * @var string The base domain of the API URL.
     */
    protected $apiBaseDomain;
    /**
     * @var string The communication handler which handles the HTTP requests. Default cURL Communication handler
     */
    protected $communicationHandler;
    /**
     * @var string The version to use
     */
    protected $version;
    /**
     * @var array The HTTP Headers
     */
    public $httpHeaders;

    /**
     * Generates a communicator object with a communication handler like cURL.
     *
     * @param string $instance The instance name, needed for the generation of the API url.
     * @param string $apiSecret The API secret which is the key to hash all the parameters passed to the API server.
     * @param string $communicationHandler The preferred communication handler. Default is cURL.
     * @param string $apiBaseDomain The base domain of the API URL.
     * @param float $version The version of the API to query.
     *
     * @throws PayrexxException
     */
    public function __construct($instance, $apiSecret, $communicationHandler, $apiBaseDomain, $version = null)
    {
        $this->instance = $instance;
        $this->apiSecret = $apiSecret;
        $this->apiBaseDomain = $apiBaseDomain;

        if ($version && in_array($version, self::VERSIONS)) {
            $this->version = $version;
        } else {
            $versions = self::VERSIONS;
            $this->version = end($versions);
        }

        if (!class_exists($communicationHandler)) {
            throw new PayrexxException('Communication handler class ' . $communicationHandler . ' not found');
        }
        $this->communicationHandler = new $communicationHandler();
    }

    /**
     * Gets the version of the API used.
     *
     * @return string The version of the API
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Perform a simple API request by method name and Request model.
     *
     * @param string                       $method The name of the API method to call
     * @param \Payrexx\Models\Base $model  The model which has the same functionality like a filter.
     *
     * @return \Payrexx\Models\Base[]|\Payrexx\Models\Base An array of models or just one model which
     *                                                                       is the result of the API call
     * @throws \Payrexx\PayrexxException An error occurred during the Payrexx Request
     */
    public function performApiRequest($method, \Payrexx\Models\Base $model)
    {
        $params = $model->toArray($method);
        $paramsWithoutFiles = $params;
        unset($paramsWithoutFiles['headerImage'], $paramsWithoutFiles['backgroundImage'], $paramsWithoutFiles['headerBackgroundImage'], $paramsWithoutFiles['emailHeaderImage'], $paramsWithoutFiles['VPOSBackgroundImage']);
        $params['ApiSignature'] =
            base64_encode(hash_hmac('sha256', http_build_query($paramsWithoutFiles, '', '&'), $this->apiSecret, true));
        $params['instance'] = $this->instance;

        $id = isset($params['id']) ? $params['id'] : 0;
        if ($id === 0 && isset($params['uuid'])) {
            $id = $params['uuid'];
        }

        $act = in_array($method, ['refund', 'capture', 'receipt', 'preAuthorize', 'details']) ? $method : '';
        $apiUrl = sprintf(self::API_URL_FORMAT, $this->apiBaseDomain, 'v' . $this->version, $params['model'], $id, $act);

        $httpMethod = $this->getHttpMethod($method) === 'PUT' && $params['model'] === 'Design'
            ? 'POST'
            : $this->getHttpMethod($method);
        $response = $this->communicationHandler->requestApi(
            $apiUrl,
            $params,
            $httpMethod,
            $this->httpHeaders
        );

        $convertedResponse = array();
        if (!isset($response['body']['data']) || !is_array($response['body']['data'])) {
            if (!isset($response['body']['message'])) {
                throw new \Payrexx\PayrexxException('Payrexx PHP: Configuration is wrong! Check instance name and API secret', $response['info']['http_code']);
            }
            $exception = new \Payrexx\PayrexxException($response['body']['message'], $response['info']['http_code']);
            if (!empty($response['body']['reason'])) {
                $exception->setReason($response['body']['reason']);
            }
            throw $exception;
        }

        $data = $response['body']['data'];
        if ($model instanceof PaymentMethod && $method === 'getOne') {
            $data = [$data];
        }

        foreach ($data as $object) {
            $responseModel = $model->getResponseModel();
            $convertedResponse[] = $responseModel->fromArray($object);
        }
        if ($method !== 'getAll') {
            $convertedResponse = current($convertedResponse);
        }
        return $convertedResponse;
    }

    /**
     * Gets the HTTP method to use for a specific API method
     *
     * @param string $method The API method to check for
     *
     * @return string The HTTP method to use for the queried API method
     * @throws \Payrexx\PayrexxException The method is not implemented yet.
     */
    protected function getHttpMethod($method)
    {
        if (!$this->methodAvailable($method)) {
            throw new \Payrexx\PayrexxException('Method ' . $method . ' not implemented');
        }
        return self::$methods[$method];
    }

    /**
     * Checks whether a method is available and activated in methods array.
     *
     * @param string $method The method name to check
     *
     * @return bool True if the method exists, False if not
     */
    public function methodAvailable($method)
    {
        return array_key_exists($method, self::$methods);
    }
}
