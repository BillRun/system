<?php

namespace Payrexx\Models\Request;

/**
 * Gateway request class
 *
 * @copyright   Payrexx AG
 * @author      Payrexx Development Team <info@payrexx.com>
 * @package     \Payrexx\Models\Request
 */
class Gateway extends \Payrexx\Models\Base
{

    /**
     * mandatory
     *
     * @access  protected
     * @var     integer
     */
    protected $amount;

    /**
     * optional
     *
     * @access  protected
     * @var     float|null
     */
    protected $vatRate;

    /**
     * optional
     *
     * @access  protected
     * @var     string
     */
    protected $sku;

    /**
     * mandatory
     *
     * @access  protected
     * @var     string
     */
    protected $currency;

    /**
     * optional
     *
     * @access  protected
     * @var     array
     */
    protected $purpose;

    /**
     * optional
     *
     * @access  protected
     * @var     array
     */
    protected $psp;

    /**
     * optional
     *
     * @access  protected
     * @var     array
     */
    protected $pm;

    /**
     * optional
     *
     * @access  protected
     * @var     bool
     */
    protected $preAuthorization = false;

    /**
     * optional
     *
     * @access  protected
     * @var     bool
     */
    protected $reservation = false;

    /**
     * optional
     *
     * @access  protected
     * @var     string
     */
    protected $referenceId;

    /**
     * optional
     *
     * @access  protected
     * @var     array
     */
    protected $fields;

    /**
     * optional
     *
     * @access  protected
     * @var     string
     */
    protected $concardisOrderId;

    /**
     * mandatory
     *
     * @access  protected
     * @var     string
     */
    protected $successRedirectUrl;

    /**
     * mandatory
     *
     * @access  protected
     * @var     string
     */
    protected $failedRedirectUrl;

    /**
     * mandatory
     *
     * @access  protected
     * @var     string
     */
    protected $cancelRedirectUrl;

    /**
     * optional
     *
     * @access  protected
     * @var     boolean
     */
    protected $skipResultPage;

    /**
     * optional
     *
     * @access  protected
     * @var     boolean
     */
    protected $chargeOnAuthorization;

    /**
     * optional: Only for Clearhaus transactions.
     *
     * @access  protected
     * @var     string
     */
    protected $customerStatementDescriptor;

    /**
     * optional: Gateway validity in minutes.
     *
     * @access  protected
     * @var     int
     */
    protected $validity;

    /**
     * optional
     *
     * @access  protected
     * @var     bool
     */
    protected $subscriptionState = false;

    /**
     * optional
     *
     * @access  protected
     * @var     string
     */
    protected $subscriptionInterval = '';

    /**
     * optional
     *
     * @access  protected
     * @var     string
     */
    protected $subscriptionPeriod = '';

    /**
     * optional
     *
     * @access  protected
     * @var     string
     */
    protected $subscriptionPeriodMinAmount = '';

    /**
     * optional
     *
     * @access  protected
     * @var     string
     */
    protected $subscriptionCancellationInterval = '';

    /**
     * optional
     *
     * @access  protected
     * @var     array $buttonText
     */
    protected $buttonText;

    /**
     * optional
     *
     * @access  protected
     * @var     string $lookAndFeelProfile
     */
    protected $lookAndFeelProfile;

    /**
     * optional
     *
     * @access  protected
     * @var     array $successMessage
     */
    protected $successMessage;

    /**
     * optional
     *
     * @access  protected
     * @var     array       $basket
     */
    protected $basket;

    /**
     * optional
     *
     * @access  protected
     * @var     string      $qrCodeSessionId
     */
    protected $qrCodeSessionId;

    /**
     * optional
     *
     * @access  protected
     * @var     string     $returnApp
     */
    protected $returnApp;

    /**
     * optional
     *
     * @access  protected
     * @var     boolean     $spotlightStatus
     */
    protected $spotlightStatus;

    /**
     * optional
     *
     * @access  protected
     * @var     string      $spotlightOrderDetailsUrl
     */
    protected $spotlightOrderDetailsUrl;

    /**
     * @access  public
     * @return  int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set the payment amount.
     * Make sure the amount is multiplied by 100!
     *
     * @access  public
     * @param   integer $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return float|null
     */
    public function getVatRate()
    {
        return $this->vatRate;
    }

    /**
     * @param float|null $vatRate
     */
    public function setVatRate($vatRate)
    {
        $this->vatRate = $vatRate;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @param string $sku
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the corresponding payment currency for the amount (use ISO codes).
     *
     * @access  public
     * @param   string  $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @access  public
     * @return  array
     */
    public function getPurpose()
    {
        return $this->purpose;
    }

    /**
     * Set the purpose of this gateway. Will be displayed as transaction purpose in merchant backend.
     * Use language ID as array key. Use key 0 as default purpose. Will be used for each activated frontend language.
     *
     * @access  public
     * @param   array   $purpose
     */
    public function setPurpose($purpose)
    {
        $this->purpose = $purpose;
    }

    /**
     * @access  public
     * @return  array
     */
    public function getPsp()
    {
        return $this->psp;
    }

    /**
     * Set payment service providers to use.
     * A list of available payment service providers
     * can be found here: http://developers.payrexx.com/docs/miscellaneous
     * All available psp will be used on payment page if none have been defined.
     *
     * @access  public
     * @param   array   $psp
     */
    public function setPsp($psp)
    {
        $this->psp = $psp;
    }

    /**
     * @access  public
     * @return  array
     */
    public function getPm()
    {
        return $this->pm;
    }

    /**
     * Set payment mean to use.
     *
     * @access  public
     * @param   array   $pm
     */
    public function setPm($pm)
    {
        $this->pm = $pm;
    }

    /**
     * @access  public
     * @return  bool
     */
    public function getPreAuthorization()
    {
        return $this->preAuthorization;
    }

    /**
     *  Whether charge payment manually at a later date (type authorization).
     *
     * @access  public
     * @param   bool    $preAuthorization
     */
    public function setPreAuthorization($preAuthorization)
    {
        $this->preAuthorization = $preAuthorization;
    }

    /**
     * @access  public
     * @return  bool
     */
    public function getReservation()
    {
        return $this->reservation;
    }

    /**
     *  Whether charge payment manually at a later date (type reservation).
     *
     * @access  public
     * @param   bool    $reservation
     */
    public function setReservation($reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getReferenceId()
    {
        return $this->referenceId;
    }

    /**
     * Set the reference id which you will get in Webhook.
     * This reference id won't be shown to customers.
     *
     * @access  public
     * @param   string  $referenceId
     */
    public function setReferenceId($referenceId)
    {
        $this->referenceId = $referenceId;
    }

    /**
     * @access  public
     * @return  array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Add a new field of the payment page
     *
     * @access  public
     * @param   string  $type           Type of field
     *                                  Available types: title, forename, surname, company, street,
     *                                  postcode, place, country, phone, email, date_of_birth,
     *                                  custom_field_1, custom_field_2, custom_field_3, custom_field_4, custom_field_5
     * @param   string  $value          Value of field
     *                                  For field of type "title" use value "mister" or "miss"
     *                                  For field of type "country" pass the 2 letter ISO code
     * @param   array   $name           Name of the field (only available for fields of type "custom_field_1-5"
     */
    public function addField($type, $value, $name = array())
    {
        $this->fields[$type] = array(
            'value' => $value,
            'name' => $name,
        );
    }

    /**
     * @access  public
     * @return  string
     */
    public function getConcardisOrderId()
    {
        return $this->concardisOrderId;
    }

    /**
     * Set a custom order ID for the Concardis PSPs
     *
     * @access  public
     * @param   string  $concardisOrderId
     */
    public function setConcardisOrderId($concardisOrderId)
    {
        $this->concardisOrderId = $concardisOrderId;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getSuccessRedirectUrl()
    {
        return $this->successRedirectUrl;
    }

    /**
     * Set the URL to redirect to after a successful payment.
     *
     * @access  public
     * @param   string  $successRedirectUrl
     */
    public function setSuccessRedirectUrl($successRedirectUrl)
    {
        $this->successRedirectUrl = $successRedirectUrl;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getFailedRedirectUrl()
    {
        return $this->failedRedirectUrl;
    }

    /**
     * Set the url to redirect to after a failed payment.
     *
     * @param   string  $failedRedirectUrl
     */
    public function setFailedRedirectUrl($failedRedirectUrl)
    {
        $this->failedRedirectUrl = $failedRedirectUrl;
    }

    /**
     * @access  public
     * @return  string
     */
    public function getCancelRedirectUrl()
    {
        return $this->cancelRedirectUrl;
    }

    /**
     * Set the url to redirect to after cancelled payment.
     *
     * @param   string  $cancelRedirectUrl
     */
    public function setCancelRedirectUrl($cancelRedirectUrl)
    {
        $this->cancelRedirectUrl = $cancelRedirectUrl;
    }

    /**
     * @return bool
     */
    public function isSkipResultPage()
    {
        return $this->skipResultPage;
    }

    /**
     * @param bool $skipResultPage
     */
    public function setSkipResultPage($skipResultPage)
    {
        $this->skipResultPage = $skipResultPage;
    }

    /**
     * @return bool
     */
    public function isChargeOnAuthorization()
    {
        return $this->chargeOnAuthorization;
    }

    /**
     * @param bool $chargeOnAuthorization
     */
    public function setChargeOnAuthorization($chargeOnAuthorization)
    {
        $this->chargeOnAuthorization = $chargeOnAuthorization;
    }

    /**
     * @return string
     */
    public function getCustomerStatementDescriptor()
    {
        return $this->customerStatementDescriptor;
    }

    /**
     * @param string $customerStatementDescriptor
     */
    public function setCustomerStatementDescriptor(string $customerStatementDescriptor): void
    {
        $this->customerStatementDescriptor = $customerStatementDescriptor;
    }

    /**
     * Validity in minutes.
     * @return int
     */
    public function getValidity()
    {
        return $this->validity;
    }

    /**
     * Validity in minutes.
     * @param int $validity
     */
    public function setValidity($validity)
    {
        $this->validity = $validity;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\Gateway();
    }

    /**
     * @return boolean
     */
    public function isSubscriptionState()
    {
        return $this->subscriptionState;
    }

    /**
     * Set whether the payment should be a recurring payment (subscription)
     * If you set to TRUE, you should provide a
     * subscription interval, period and cancellation interval
     * Note: Subscription and pre-authorization can not be combined.
     *
     * @param boolean $subscriptionState
     */
    public function setSubscriptionState($subscriptionState)
    {
        $this->subscriptionState = $subscriptionState;
    }

    /**
     * @return string
     */
    public function getSubscriptionInterval()
    {
        return $this->subscriptionInterval;
    }

    /**
     * Set the payment interval, this should be a string formatted like ISO 8601
     * (PnYnMnDTnHnMnS)
     *
     * Use case:
     * If you set this value to P6M the customer will pay every 6 months on this
     * subscription.
     *
     * It is possible to define XY years / months or days.
     *
     * For further information see http://php.net/manual/en/class.dateinterval.php
     *
     * @param string $subscriptionInterval
     */
    public function setSubscriptionInterval($subscriptionInterval)
    {
        $this->subscriptionInterval = $subscriptionInterval;
    }

    /**
     * @return string
     */
    public function getSubscriptionPeriod()
    {
        return $this->subscriptionPeriod;
    }

    /**
     * Set the subscription period after how many years / months or days the subscription
     * will get renewed.
     *
     * This should be a string formatted like ISO 8601 (PnYnMnDTnHnMnS)
     *
     * Use case:
     * If you set this value to P1Y the subscription will be renewed every year.
     *
     * It is possible to define XY years / months or days.
     *
     * For further information see http://php.net/manual/en/class.dateinterval.php
     *
     * @param string $subscriptionPeriod
     */
    public function setSubscriptionPeriod($subscriptionPeriod)
    {
        $this->subscriptionPeriod = $subscriptionPeriod;
    }

    /**
     * @return string
     */
    public function getSubscriptionCancellationInterval()
    {
        return $this->subscriptionCancellationInterval;
    }

    /**
     * Set the cancellation interval, it means you can define how many days or months
     * the client has to cancel the subscription before the end of subscription period.
     *
     * This should be a string formatted like ISO 8601 (PnYnMnDTnHnMnS)
     *
     * Use case:
     * If you set this value to P1M the subscription has to be cancelled one month
     * before end of subscription period.
     *
     * It is possible to define XY months or days. Years are not supported here.
     *
     * For further information see http://php.net/manual/en/class.dateinterval.php
     *
     * @param string $subscriptionCancellationInterval
     */
    public function setSubscriptionCancellationInterval($subscriptionCancellationInterval)
    {
        $this->subscriptionCancellationInterval = $subscriptionCancellationInterval;
    }

    /**
     * @return array
     */
    public function getButtonText()
    {
        return $this->buttonText;
    }

    /**
     * Use language ID as array key. Use key 0 as default purpose. Will be used for each activated frontend language.
     *
     * @param array $buttonText
     */
    public function setButtonText($buttonText)
    {
        $this->buttonText = $buttonText;
    }

    /**
     * @return string
     */
    public function getLookAndFeelProfile()
    {
        return $this->lookAndFeelProfile;
    }

    /**
     * @param string $lookAndFeelProfile
     */
    public function setLookAndFeelProfile($lookAndFeelProfile)
    {
        $this->lookAndFeelProfile = $lookAndFeelProfile;
    }

    /**
     * @return array
     */
    public function getSuccessMessage()
    {
        return $this->successMessage;
    }

    /**
     * Use language ID as array key. Use key 0 as default purpose. Will be used for each activated frontend language.
     *
     * @param array $successMessage
     */
    public function setSuccessMessage($successMessage)
    {
        $this->successMessage = $successMessage;
    }

    /**
     * @return array
     */
    public function getBasket(): array
    {
        return $this->basket;
    }

    /**
     * It is a multidimensional array to parse each product as an array
     *
     * @param array $basket         Available product values:
     *                              name => Can be an array with the key as language ID
     *                              description => Can be an array with the key as language ID
     *                              quantity => quantity of the product
     *                              amount => Product amount
     */
    public function setBasket(array $basket): void
    {
        $this->basket = $basket;
    }

    /**
     * @return string
     */
    public function getQrCodeSessionId(): string
    {
        return $this->qrCodeSessionId;
    }

    /**
     * @param string $qrCodeSessionId
     * @return void
     */
    public function setQrCodeSessionId(string $qrCodeSessionId): void
    {
        $this->qrCodeSessionId = $qrCodeSessionId;
    }

    /**
     * @return string
     */
    public function getReturnApp(): ?string
    {
        return $this->returnApp;
    }

    /**
     * @param string $returnApp
     * @return void
     */
    public function setReturnApp(string $returnApp): void
    {
        $this->returnApp = $returnApp;
    }

    /**
     * @return boolean
     */
    public function getSpotlightStatus(): ?bool
    {
        return $this->spotlightStatus;
    }

    /**
     * @param boolean $spotlightStatus
     * @return void
     */
    public function setSpotlightStatus(bool $spotlightStatus): void
    {
        $this->spotlightStatus = $spotlightStatus;
    }

    /**
     * @return string
     */
    public function getSpotlightOrderDetailsUrl(): ?string
    {
        return $this->spotlightOrderDetailsUrl;
    }

    /**
     * @param string $spotlightOrderDetailsUrl
     * @return void
     */
    public function setSpotlightOrderDetailsUrl(string $spotlightOrderDetailsUrl): void
    {
        $this->spotlightOrderDetailsUrl = $spotlightOrderDetailsUrl;
    }
}
