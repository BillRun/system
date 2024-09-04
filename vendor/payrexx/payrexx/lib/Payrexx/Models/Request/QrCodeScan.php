<?php

namespace Payrexx\Models\Request;

/**
 * QrCodeScan request class
 *
 * @copyright   Payrexx AG
 * @author      Payrexx Development Team <info@payrexx.com>
 * @package     \Payrexx\Models\Request
 */
class QrCodeScan extends \Payrexx\Models\Base
{
    /**
     * mandatory
     *
     * @access  protected
     * @var     string
     */
    protected $sessionId;

    /**
     * @access  public
     * @return  string
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @access  public
     * @param   string   $sessionId
     * @return  void
     */
    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\QrCodeScan();
    }
}
