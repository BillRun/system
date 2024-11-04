<?php

namespace Payrexx\Models\Response;

class QrCode extends \Payrexx\Models\Request\QrCode
{
    /** @var string */
    protected $qrCode;

    /** @var string */
    protected $uuid;

    /** @var string */
    protected $png;

    /** @var string */
    protected $svg;

    /**
     * @param string $png
     */
    public function setPng($png): void
    {
        $this->png = $png;
    }

    /**
     * @return string
     */
    public function getPng()
    {
        return $this->png;
    }

    /**
     * @param string $svg
     */
    public function setSvg($svg): void
    {
        $this->svg = $svg;
    }

    /**
     * @return string
     */
    public function getSvg()
    {
        return $this->svg;
    }

    /**
     * @return string
     */
    public function getQrCode(): string
    {
        return $this->qrCode;
    }

    /**
     * @param string $qrCode
     */
    public function setQrCode(string $qrCode): void
    {
        $this->qrCode = $qrCode;
    }
}
