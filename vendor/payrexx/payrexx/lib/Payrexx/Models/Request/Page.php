<?php
/**
 * The page request model
 * @author    Ueli Kramer <ueli.kramer@comvation.com>
 * @copyright 2014 Payrexx AG
 * @since     v1.0
 */
namespace Payrexx\Models\Request;

/**
 * Class Page
 * @package Payrexx\Models\Request
 */
class Page extends \Payrexx\Models\Base
{
    const CURRENCY_CHF = 'CHF';
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';
    const CURRENCY_GBP = 'GBP';

    // mandatory
    protected $title = '';
    protected $description = '';
    protected $psp = 0;

    // optional
    protected $name = '';
    protected $purpose = '';
    protected $amount = 0;
    protected $currency = '';

    protected $subscriptionState = false;
    protected $subscriptionInterval = '';
    protected $subscriptionPeriod = '';
    protected $subscriptionPeriodMinAmount = '';
    protected $subscriptionCancellationInterval = '';

    protected $preAuthorization = false;
    protected $reservation = false;

    protected $fields = array();

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the payment page headline title
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the description text which will be displayed
     * above the payment form
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return int
     */
    public function getPsp()
    {
        return $this->psp;
    }

    /**
     * Set the payment service provider to use, a
     * list of available payment service providers (short psp)
     * can be found here: http://developers.payrexx.com/docs/miscellaneous
     *
     * @param int $psp
     */
    public function setPsp($psp)
    {
        $this->psp = $psp;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the internal name of the form which will be generated.
     * This name will only be shown to administrator of the Payrexx site.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getPurpose()
    {
        return $this->purpose;
    }

    /**
     * Set the payment purpose which will be inserted automatically.
     * This field won't be editable anymore for the client if you predefine it.
     *
     * @param string $purpose
     */
    public function setPurpose($purpose)
    {
        $this->purpose = $purpose;
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Set the payment amount. Make sure the amount is multiplied
     * with 100!
     *
     * @param int $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the corresponding payment currency for the amount.
     * You can use the ISO Code.
     * A list of available currencies you can find on http://developers.payrexx.com/docs/miscellaneous
     *
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
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
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Define a new field of the payment page
     *
     * @param string $type the type of field
     *                     can be: title, forename, surname, company, street, postcode,
     *                     place, phone, country, email, date_of_birth, terms, custom_field_1,
     *                     custom_field_2, custom_field_3, custom_field_4, custom_field_5
     * @param boolean $mandatory TRUE if the field has to be filled out for payment
     * @param string $defaultValue the default value. This value will be editable for the client.
     * @param string $name the name of the field, (this is only available for the fields custom_field_\d
     */
    public function addField($type, $mandatory, $defaultValue = '', $name = '')
    {
        $this->fields[$type] = array(
            'name' => $name,
            'mandatory' => $mandatory,
            'defaultValue' => $defaultValue,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseModel()
    {
        return new \Payrexx\Models\Response\Page();
    }
}
