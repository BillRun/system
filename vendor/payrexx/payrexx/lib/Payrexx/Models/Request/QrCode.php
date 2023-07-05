<?php

namespace Payrexx\Models\Request;

/**
 * QrCode request class
 *
 * @copyright   Payrexx AG
 * @author      Payrexx Development Team <info@payrexx.com>
 * @package     \Payrexx\Models\Request
 */
class QrCode extends \Payrexx\Models\Base
{
    /**
     * mandatory
     *
     * @access  protected
     * @var     string
     */
    protected $webshopUrl;

    /**
     * @access  public
     * @return  string
     */
    public function getWebshopUrl(): string
    {
        return $this->webshopUrl;
    }

    /**
     * @access  public
     * @param   string   $webshopUrl
     * @return  void
     */
    public function setWebshopUrl(string $webshopUrl): void
    {
        $this->webshopUrl = $webshopUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\QrCode();
    }
}