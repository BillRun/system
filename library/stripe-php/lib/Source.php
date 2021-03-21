<?php

namespace Stripe;

/**
 * Class Source
 *
 * @package Stripe
 */
class Source extends ApiResource
{
    /**
     * @param string $id The ID of the Source to retrieve.
     * @param array|string|null $opts
     *
     * @return Source
     */
    public static function retrieve($id, $opts = null)
    {
        return self::_retrieve($id, $opts);
    }

    /**
     * @param array|null $params
     * @param array|string|null $opts
     *
     * @return Collection of Sources
     */
    public static function all($params = null, $opts = null)
    {
        return self::_all($params, $opts);
    }

    /**
     * @param array|null $params
     * @param array|string|null $opts
     *
     * @return Source The created Source.
     */
    public static function create($params = null, $opts = null)
    {
        return self::_create($params, $opts);
    }

    /**
     * @param string $id The ID of the source to update.
     * @param array|null $params
     * @param array|string|null $options
     *
     * @return Source The updated source.
     */
    public static function update($id, $params = null, $options = null)
    {
        return self::_update($id, $params, $options);
    }

    /**
     * @param array|string|null $opts
     *
     * @return Source The saved source.
     */
    public function save($opts = null)
    {
        return $this->_save($opts);
    }

    /**
     * @param array|null $params
     * @param array|string|null $options
     *
     * @return BankAccount The verified bank account.
     */
    public function verify($params = null, $options = null)
    {
        $url = $this->instanceUrl() . '/verify';
        list($response, $opts) = $this->_request('post', $url, $params, $options);
        $this->refreshFrom($response, $opts);
        return $this;
    }
}
