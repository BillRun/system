<?php

namespace Payrexx\Models\Request;

use CURLFile;
use Payrexx\Models\Base;

/**
 * Design request class
 *
 * @copyright Payrexx AG
 * @author    Payrexx Development Team <info@payrexx.com>
 * @package   \Payrexx\Models\Request
 */
class Design extends Base
{
    /** @var string $uuid */
    protected $uuid;

    /** @var int $default */
    protected $default = 0;

    /** @var string $name */
    protected $name;

    /** @var string $fontFamily */
    protected $fontFamily;

    /** @var string $fontSize */
    protected $fontSize;

    /** @var string $textColor */
    protected $textColor;

    /** @var string $textColorVPOS */
    protected $textColorVPOS;

    /** @var string $linkColor */
    protected $linkColor;

    /** @var string $linkHoverColor */
    protected $linkHoverColor;

    /** @var string $buttonColor */
    protected $buttonColor;

    /** @var string $buttonHoverColor */
    protected $buttonHoverColor;

    /** @var string $background */
    protected $background;

    /** @var string $backgroundColor */
    protected $backgroundColor;

    /** @var string $headerBackground */
    protected $headerBackground;

    /** @var string $headerBackgroundColor */
    protected $headerBackgroundColor;

    /** @var string $emailHeaderBackgroundColor */
    protected $emailHeaderBackgroundColor;

    /** @var string $headerImageShape */
    protected $headerImageShape;

    /**
     * optional
     *
     * @var array $headerImageCustomLink
     */
    protected $headerImageCustomLink;

    /** @var int $useIndividualEmailLogo */
    protected $useIndividualEmailLogo = 0;

    /** @var string $logoBackgroundColor */
    protected $logoBackgroundColor;

    /** @var string $logoBorderColor */
    protected $logoBorderColor;

    /** @var string $VPOSGradientColor1 */
    protected $VPOSGradientColor1;

    /** @var string $VPOSGradientColor2 */
    protected $VPOSGradientColor2;

    /** @var int $enableRoundedCorners */
    protected $enableRoundedCorners;

    /** @var string $VPOSBackground */
    protected $VPOSBackground;

    /** @var CURLFile $headerImage */
    protected $headerImage;

    /** @var CURLFile $backgroundImage */
    protected $backgroundImage;

    /** @var CURLFile $headerBackgroundImage */
    protected $headerBackgroundImage;

    /** @var CURLFile $emailHeaderImage */
    protected $emailHeaderImage;
    protected $offset;
    protected $limit;

    /** @var CURLFile $VPOSBackgroundImage */
    protected $VPOSBackgroundImage;

    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\Design();
    }

    /**
     * @param string $uuid
     */
    public function setUuid($uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return (bool)$this->default;
    }

    /**
     * @param bool $default
     */
    public function setDefault(bool $default): void
    {
        $this->default = (int)$default;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getFontFamily(): string
    {
        return $this->fontFamily;
    }

    /**
     * @param string $fontFamily
     */
    public function setFontFamily(string $fontFamily): void
    {
        $this->fontFamily = $fontFamily;
    }

    /**
     * @return string
     */
    public function getFontSize(): string
    {
        return $this->fontSize;
    }

    /**
     * @param string $fontSize
     */
    public function setFontSize(string $fontSize): void
    {
        $this->fontSize = $fontSize;
    }

    /**
     * @return string
     */
    public function getTextColor(): string
    {
        return $this->textColor;
    }

    /**
     * @param string $textColor
     */
    public function setTextColor(string $textColor): void
    {
        $this->textColor = $textColor;
    }

    /**
     * @return string
     */
    public function getTextColorVPOS(): string
    {
        return $this->textColorVPOS;
    }

    /**
     * @param string $textColorVPOS
     */
    public function setTextColorVPOS(string $textColorVPOS): void
    {
        $this->textColorVPOS = $textColorVPOS;
    }

    /**
     * @return string
     */
    public function getLinkColor(): string
    {
        return $this->linkColor;
    }

    /**
     * @param string $linkColor
     */
    public function setLinkColor(string $linkColor): void
    {
        $this->linkColor = $linkColor;
    }

    /**
     * @return string
     */
    public function getLinkHoverColor(): string
    {
        return $this->linkHoverColor;
    }

    /**
     * @param string $linkHoverColor
     */
    public function setLinkHoverColor(string $linkHoverColor): void
    {
        $this->linkHoverColor = $linkHoverColor;
    }

    /**
     * @return string
     */
    public function getButtonColor(): string
    {
        return $this->buttonColor;
    }

    /**
     * @param string $buttonColor
     */
    public function setButtonColor(string $buttonColor): void
    {
        $this->buttonColor = $buttonColor;
    }

    /**
     * @return string
     */
    public function getButtonHoverColor(): string
    {
        return $this->buttonHoverColor;
    }

    /**
     * @param string $buttonHoverColor
     */
    public function setButtonHoverColor(string $buttonHoverColor): void
    {
        $this->buttonHoverColor = $buttonHoverColor;
    }

    /**
     * @return string
     */
    public function getBackground(): string
    {
        return $this->background;
    }

    /**
     * @param string $background
     */
    public function setBackground(string $background): void
    {
        $this->background = $background;
    }

    /**
     * @return string
     */
    public function getBackgroundColor(): string
    {
        return $this->backgroundColor;
    }

    /**
     * @param string $backgroundColor
     */
    public function setBackgroundColor(string $backgroundColor): void
    {
        $this->backgroundColor = $backgroundColor;
    }

    /**
     * @return string
     */
    public function getHeaderBackground(): string
    {
        return $this->headerBackground;
    }

    /**
     * @param string $headerBackground
     */
    public function setHeaderBackground(string $headerBackground): void
    {
        $this->headerBackground = $headerBackground;
    }

    /**
     * @return string
     */
    public function getHeaderBackgroundColor(): string
    {
        return $this->headerBackgroundColor;
    }

    /**
     * @param string $headerBackgroundColor
     */
    public function setHeaderBackgroundColor(string $headerBackgroundColor): void
    {
        $this->headerBackgroundColor = $headerBackgroundColor;
    }

    /**
     * @return string
     */
    public function getEmailHeaderBackgroundColor(): string
    {
        return $this->emailHeaderBackgroundColor;
    }

    /**
     * @param string $emailHeaderBackgroundColor
     */
    public function setEmailHeaderBackgroundColor(string $emailHeaderBackgroundColor): void
    {
        $this->emailHeaderBackgroundColor = $emailHeaderBackgroundColor;
    }

    /**
     * @return string
     */
    public function getHeaderImageShape(): string
    {
        return $this->headerImageShape;
    }

    /**
     * @param string $headerImageShape
     */
    public function setHeaderImageShape(string $headerImageShape): void
    {
        $this->headerImageShape = $headerImageShape;
    }

    /**
     * @return array
     */
    public function getHeaderImageCustomLink(): array
    {
        return $this->headerImageCustomLink;
    }

    /**
     * Use language ID as array key. Array key 0 or datatype 'string' will be handled as the default value (Will be used
     * for each activated frontend language).
     *
     * @param string|array $headerImageCustomLink
     */
    public function setHeaderImageCustomLink($headerImageCustomLink): void
    {
        if (is_string($headerImageCustomLink)) {
            $this->headerImageCustomLink = [$headerImageCustomLink];
        } elseif(is_array($headerImageCustomLink)) {
            $this->headerImageCustomLink = $headerImageCustomLink;
        }
    }

    /**
     * @return bool
     */
    public function isUseIndividualEmailLogo(): bool
    {
        return (bool)$this->useIndividualEmailLogo;
    }

    /**
     * @param bool $useIndividualEmailLogo
     */
    public function setUseIndividualEmailLogo(bool $useIndividualEmailLogo): void
    {
        $this->useIndividualEmailLogo = (int)$useIndividualEmailLogo;
    }

    /**
     * @return string
     */
    public function getLogoBackgroundColor(): string
    {
        return $this->logoBackgroundColor;
    }

    /**
     * @param string $logoBackgroundColor
     */
    public function setLogoBackgroundColor(string $logoBackgroundColor): void
    {
        $this->logoBackgroundColor = $logoBackgroundColor;
    }

    /**
     * @return string
     */
    public function getLogoBorderColor(): string
    {
        return $this->logoBorderColor;
    }

    /**
     * @param string $logoBorderColor
     */
    public function setLogoBorderColor(string $logoBorderColor): void
    {
        $this->logoBorderColor = $logoBorderColor;
    }

    /**
     * @return string
     */
    public function getVPOSGradientColor1(): string
    {
        return $this->VPOSGradientColor1;
    }

    /**
     * @param string $VPOSGradientColor1
     */
    public function setVPOSGradientColor1(string $VPOSGradientColor1): void
    {
        $this->VPOSGradientColor1 = $VPOSGradientColor1;
    }

    /**
     * @return string
     */
    public function getVPOSGradientColor2(): string
    {
        return $this->VPOSGradientColor2;
    }

    /**
     * @param string $VPOSGradientColor2
     */
    public function setVPOSGradientColor2(string $VPOSGradientColor2): void
    {
        $this->VPOSGradientColor2 = $VPOSGradientColor2;
    }

    /**
     * @return bool
     */
    public function isEnableRoundedCorners(): bool
    {
        return (bool)$this->enableRoundedCorners;
    }

    /**
     * @param bool $enableRoundedCorners
     */
    public function setEnableRoundedCorners(bool $enableRoundedCorners): void
    {
        $this->enableRoundedCorners = (int)$enableRoundedCorners;
    }

    /**
     * @return string
     */
    public function getHeaderImage()
    {
        return $this->headerImage;
    }

    /**
     * @param CURLFile $headerImage
     */
    public function setHeaderImage($headerImage)
    {
        $this->headerImage = $headerImage;
    }

    /**
     * @return string
     */
    public function getBackgroundImage()
    {
        return $this->backgroundImage;
    }

    /**
     * @param CURLFile $backgroundImage
     */
    public function setBackgroundImage($backgroundImage)
    {
        $this->backgroundImage = $backgroundImage;
    }

    /**
     * @return string
     */
    public function getHeaderBackgroundImage()
    {
        return $this->headerBackgroundImage;
    }

    /**
     * @param CURLFile $headerBackgroundImage
     */
    public function setHeaderBackgroundImage($headerBackgroundImage)
    {
        $this->headerBackgroundImage = $headerBackgroundImage;
    }

    /**
     * @return string
     */
    public function getEmailHeaderImage()
    {
        return $this->emailHeaderImage;
    }

    /**
     * @param CURLFile $emailHeaderImage
     */
    public function setEmailHeaderImage($emailHeaderImage)
    {
        $this->emailHeaderImage = $emailHeaderImage;
    }

    /**
     * @return string
     */
    public function getVPOSBackground()
    {
        return $this->VPOSBackground;
    }

    /**
     * @param string $VPOSBackground
     */
    public function setVPOSBackground($VPOSBackground)
    {
        $this->VPOSBackground = $VPOSBackground;
    }

    /**
     * @return CURLFile
     */
    public function getVPOSBackgroundImage()
    {
        return $this->VPOSBackgroundImage;
    }

    /**
     * @param CURLFile $VPOSBackgroundImage
     */
    public function setVPOSBackgroundImage($VPOSBackgroundImage)
    {
        $this->VPOSBackgroundImage = $VPOSBackgroundImage;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param int $offset
     */
    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}
