<?php

namespace Helper;

/**
 * ResponseWrapper Helper for Codeception tests
 * warp to Codeception method grabResponse
 */
class ResponseWrapper extends \Codeception\Module
{
    protected $response;

    /**
     * Get entity from response
     * @return array|null
     */
    public function getEntity()
    {
        $response = $this->getDecodedResponse();
        $this->reset();
        return $response['entity'] ?? null;
    }

    public function getPayResponse()
    {
        $response = $this->getDecodedResponse();
        $this->reset();
        return $response ?? null;
    }

    public function getPayInput()
    {
        $response = $this->getDecodedResponse();
        $this->reset();
        return json_decode($response['input']['payments'],true) ?? null;
    }

    /**
     * Get decoded response
     * @return array
     */
    protected function getDecodedResponse()
    {
        if (!$this->response) {
            // Get the response from the API module
            $apiModule = $this->getModule('REST');
            $rawResponse = $apiModule->grabResponse();
            $this->response = json_decode($rawResponse, true);
        }
        return $this->response;
    }

    /**
     * Reset the cached response
     * @return void
     */
    public function reset()
    {
        $this->response = null;
    }

    /**
     * Get specific field from entity
     * @param string $field
     * @return mixed
     */
    public function getEntityField(string $field)
    {
        $entity = $this->getEntity();
        return $entity[$field] ?? null;
    }

    /**
     * Check if entity exists
     * @return bool
     */
    public function hasEntity(): bool
    {
        return $this->getEntity() !== null;
    }

    /**
     * Get the raw response
     * @return string
     */
    protected function getRawResponse()
    {
        return $this->getModule('REST')->grabResponse();
    }
}