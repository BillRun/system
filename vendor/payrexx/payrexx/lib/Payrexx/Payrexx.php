<?php
/**
 * The Payrexx client API basic class file
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */
namespace Payrexx;

/**
 * All interactions with the API can be done with an instance of this class.
 * @package Payrexx
 */
class Payrexx
{
    /**
     * @var Communicator The object for the communication wrapper.
     */
    protected $communicator;

    /**
     * Generates an API object to use for the whole interaction with Payrexx.
     *
     * @param string $instance             The name of the Payrexx instance.
     * @param string $apiSecret            The API secret which can be found in the Payrexx administration.
     * @param string $communicationHandler The preferred communication handler. Default is cURL.
     * @param string $apiBaseDomain        The base domain of the API URL.
     * @param string $version              The version of the API to use.
     *
     * @throws PayrexxException
     */
    public function __construct($instance, $apiSecret, $communicationHandler = '', $apiBaseDomain = Communicator::API_URL_BASE_DOMAIN, $version = null)
    {
        $this->communicator = new Communicator(
            $instance,
            $apiSecret,
            $communicationHandler ?: Communicator::DEFAULT_COMMUNICATION_HANDLER,
            $apiBaseDomain,
            $version
        );
    }

    /**
     * This method passes header to the request.
     * The format of the elements needs to be like this: 'Content-type: multipart/form-data'
     */
    public function setHttpHeaders(array $header): void
    {
        $this->communicator->httpHeaders = $header;
    }

    /**
     * This method returns the version of the API communicator which is the API version used for this
     * application.
     *
     * @return string The version of the API communicator
     */
    public function getVersion()
    {
        return $this->communicator->getVersion();
    }

    /**
     * This magic method is used to call any method available in communication object.
     *
     * @param string $method The name of the method called.
     * @param array  $args   The arguments passed to the method call. There can only be one argument which is the model.
     *
     * @return \Payrexx\Models\Response\Base[]|\Payrexx\Models\Response\Base
     * @throws \Payrexx\PayrexxException The model argument is missing or the method is not implemented
     */
    public function __call($method, $args)
    {
        if (!$this->communicator->methodAvailable($method)) {
            throw new \Payrexx\PayrexxException('Method ' . $method . ' not implemented');
        }
        if (empty($args)) {
            throw new \Payrexx\PayrexxException('Argument model is missing');
        }
        $model = current($args);
        return $this->communicator->performApiRequest($method, $model);
    }
}
