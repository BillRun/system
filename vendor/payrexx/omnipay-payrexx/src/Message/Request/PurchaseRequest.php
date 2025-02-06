<?php

namespace Omnipay\Payrexx\Message\Request;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Payrexx\Message\Response\PurchaseResponse;
use Payrexx\Models\Request\Gateway;
use Payrexx\Payrexx;
use Payrexx\PayrexxException;

/**
 * @see https://developers.payrexx.com/reference#gateway
 */
class PurchaseRequest extends AbstractRequest
{
    /**
     * @param string $value
     * @return $this
     */
    public function setApiKey($value)
    {
        return $this->setParameter('apiKey', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInstance($value)
    {
        return $this->setParameter('instance', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setVatRate($value)
    {
        return $this->setParameter('vatRate', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setSku($value)
    {
        return $this->setParameter('sku', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setSuccessRedirectUrl($value)
    {
        return $this->setParameter('successRedirectUrl', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setFailedRedirectUrl($value)
    {
        return $this->setParameter('failedRedirectUrl', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setCancelRedirectUrl($value)
    {
        return $this->setParameter('cancelRedirectUrl', $value);
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setSkipResultPage($value)
    {
        return $this->setParameter('skipResultPage', $value);
    }

    /**
     * @param array $value
     * @return $this
     */
    public function setPsp($value)
    {
        return $this->setParameter('psp', $value);
    }

    /**
     * @param array $value
     * @return $this
     */
    public function setPm($value)
    {
        return $this->setParameter('pm', $value);
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setPreAuthorization($value)
    {
        return $this->setParameter('preAuthorization', $value);
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setChargeOnAuthorization($value)
    {
        return $this->setParameter('chargeOnAuthorization', $value);
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setReservation($value)
    {
        return $this->setParameter('reservation', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setReferenceId($value)
    {
        return $this->setParameter('referenceId', $value);
    }

    /**
     * @param array $value
     * @return $this
     */
    public function setButtonText($value)
    {
        return $this->setParameter('buttonText', $value);
    }

    /**
     * @param array $value
     * @return $this
     */
    public function setSuccessMessage($value)
    {
        return $this->setParameter('successMessage', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setTitle($value)
    {
        return $this->setParameter('title', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setForename($value)
    {
        return $this->setParameter('forename', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setSurname($value)
    {
        return $this->setParameter('surname', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setCompany($value)
    {
        return $this->setParameter('copmany', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setStreet($value)
    {
        return $this->setParameter('street', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setPostcode($value)
    {
        return $this->setParameter('postcode', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setPlace($value)
    {
        return $this->setParameter('place', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setCountry($value)
    {
        return $this->setParameter('country', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setPhone($value)
    {
        return $this->setParameter('phone', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setEmail($value)
    {
        return $this->setParameter('email', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setDateOfBirth($value)
    {
        return $this->setParameter('dateOfBirth', $value);
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setTerms($value)
    {
        return $this->setParameter('terms', $value);
    }

    /**
     * @param bool $value
     * @return $this
     */
    public function setPrivacyPolicy($value)
    {
        return $this->setParameter('privacyPolicy', $value);
    }

    /**
     * @return array
     * @throws InvalidRequestException
     */
    public function getData()
    {
        $this->validate('apiKey', 'instance', 'amount', 'currency');

        $data = [];

        $data['apiKey'] = $this->getApiKey();
        $data['instance'] = $this->getInstance();
        $data['amount'] = $this->getAmountInteger();
        $data['currency'] = $this->getCurrency();
        $data['vatRate'] = $this->getVatRate() ?? null;
        $data['sku'] = $this->getSku() ?? null;
        $data['successRedirectUrl'] = $this->getSuccessRedirectUrl() ?? null;
        $data['failedRedirectUrl'] = $this->getFailedRedirectUrl() ?? null;
        $data['cancelRedirectUrl'] = $this->getCancelRedirectUrl() ?? null;
        $data['skipResultPage'] = $this->getSkipResultPage() ?? null;
        $data['psp'] = $this->getPsp() ?? null;
        $data['pm'] = $this->getPm() ?? null;
        $data['preAuthorization'] = $this->getPreAuthorization() ?? null;
        $data['chargeOnAuthorization'] = $this->getChargeOnAuthorization() ?? null;
        $data['reservation'] = $this->getReservation() ?? null;
        $data['referenceId'] = $this->getReferenceId() ?? null;
        $data['buttonText'] = $this->getButtonText() ?? null;
        $data['successMessage'] = $this->getSuccessMessage() ?? null;

        // Contact fields
        $data['title'] = $this->getTitle() ?? null;
        $data['forename'] = $this->getForename() ?? null;
        $data['surname'] = $this->getSurname() ?? null;
        $data['company'] = $this->getCompany() ?? null;
        $data['street'] = $this->getStreet() ?? null;
        $data['postcode'] = $this->getPostcode() ?? null;
        $data['place'] = $this->getPlace() ?? null;
        $data['country'] = $this->getCountry() ?? null;
        $data['phone'] = $this->getPhone() ?? null;
        $data['email'] = $this->getEmail() ?? null;
        $data['dateOfBirth'] = $this->getDateOfBirth() ?? null;
        $data['terms'] = $this->getTerms() ?? null;
        $data['privacyPolicy'] = $this->getPrivacyPolicy() ?? null;

        return $data;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->getParameter('apiKey');
    }

    /**
     * @return string
     */
    public function getInstance()
    {
        return $this->getParameter('instance');
    }

    /**
     * @return string
     */
    public function getVatRate()
    {
        return $this->getParameter('vatRate');
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->getParameter('sku');
    }

    /**
     * @return string
     */
    public function getSuccessRedirectUrl()
    {
        return $this->getParameter('successRedirectUrl');
    }

    /**
     * @return string
     */
    public function getFailedRedirectUrl()
    {
        return $this->getParameter('failedRedirectUrl');
    }

    /**
     * @return string
     */
    public function getCancelRedirectUrl()
    {
        return $this->getParameter('cancelRedirectUrl');
    }

    /**
     * @return bool
     */
    public function getSkipResultPage()
    {
        return $this->getParameter('skipResultPage');
    }

    /**
     * @return array
     */
    public function getPsp()
    {
        return $this->getParameter('psp');
    }

    /**
     * @return array
     */
    public function getPm()
    {
        return $this->getParameter('pm');
    }

    /**
     * @return bool
     */
    public function getPreAuthorization()
    {
        return $this->getParameter('preAuthorization');
    }

    /**
     * @return bool
     */
    public function getChargeOnAuthorization()
    {
        return $this->getParameter('chargeOnAuthorization');
    }

    /**
     * @return bool
     */
    public function getReservation()
    {
        return $this->getParameter('reservation');
    }

    /**
     * @return string
     */
    public function getReferenceId()
    {
        return $this->getParameter('referenceId');
    }

    /**
     * @return array
     */
    public function getButtonText()
    {
        return $this->getParameter('buttonText');
    }

    /**
     * @return array
     */
    public function getSuccessMessage()
    {
        return $this->getParameter('successMessage');
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->getParameter('title');
    }

    /**
     * @return string
     */
    public function getForename()
    {
        return $this->getParameter('forename');
    }

    /**
     * @return string
     */
    public function getSurname()
    {
        return $this->getParameter('surname');
    }

    /**
     * @return string
     */
    public function getCompany()
    {
        return $this->getParameter('copmany');
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->getParameter('street');
    }

    /**
     * @return string
     */
    public function getPostcode()
    {
        return $this->getParameter('postcode');
    }

    /**
     * @return string
     */
    public function getPlace()
    {
        return $this->getParameter('place');
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->getParameter('country');
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->getParameter('phone');
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->getParameter('email');
    }

    /**
     * @return string
     */
    public function getDateOfBirth()
    {
        return $this->getParameter('dateOfBirth');
    }

    /**
     * @return bool
     */
    public function getTerms()
    {
        return $this->getParameter('terms');
    }

    /**
     * @return bool
     */
    public function getPrivacyPolicy()
    {
        return $this->getParameter('privacyPolicy');
    }

    /**
     * @param array $data
     * @return PurchaseResponse
     * @throws InvalidRequestException
     */
    public function sendData($data)
    {
        try {
            $payrexx = new Payrexx($data['instance'], $data['apiKey']);
            $gateway = new Gateway();

            $gateway->setAmount($data['amount']);
            $gateway->setCurrency($data['currency']);
            $gateway->setVatRate($data['vatRate'] ?? null);
            $gateway->setSku($data['sku'] ?? null);
            $gateway->setSuccessRedirectUrl($data['successRedirectUrl'] ?? null);
            $gateway->setFailedRedirectUrl($data['failedRedirectUrl'] ?? null);
            $gateway->setCancelRedirectUrl($data['cancelRedirectUrl'] ?? null);
            $gateway->setSkipResultPage($data['skipResultPage'] ?? null);
            $gateway->setPsp($data['psp'] ?? null);
            $gateway->setPm($data['pm'] ?? null);
            $gateway->setPreAuthorization($data['preAuthorization'] ?? null);
            $gateway->setChargeOnAuthorization($data['chargeOnAuthorization'] ?? null);
            $gateway->setReservation($data['reservation'] ?? null);
            $gateway->setReferenceId($data['referenceId'] ?? null);
            $gateway->setButtonText($data['buttonText'] ?? null);
            $gateway->setSuccessMessage($data['successMessage'] ?? null);

            if (!empty($data['title'])) {
                $gateway->addField('title', $data['title']);
            }
            if (!empty($data['forename'])) {
                $gateway->addField('forename', $data['forename']);
            }
            if (!empty($data['surname'])) {
                $gateway->addField('surname', $data['surname']);
            }
            if (!empty($data['company'])) {
                $gateway->addField('company', $data['company']);
            }
            if (!empty($data['street'])) {
                $gateway->addField('street', $data['street']);
            }
            if (!empty($data['postcode'])) {
                $gateway->addField('postcode', $data['postcode']);
            }
            if (!empty($data['place'])) {
                $gateway->addField('place', $data['place']);
            }
            if (!empty($data['country'])) {
                $gateway->addField('country', $data['country']);
            }
            if (!empty($data['phone'])) {
                $gateway->addField('phone', $data['phone']);
            }
            if (!empty($data['email'])) {
                $gateway->addField('email', $data['email']);
            }
            if (!empty($data['dateOfBirth'])) {
                $gateway->addField('date_of_birth', $data['dateOfBirth']);
            }
            if (!empty($data['terms'])) {
                $gateway->addField('terms', $data['terms']);
            }
            if (!empty($data['privacyPolicy'])) {
                $gateway->addField('privacy_policy', $data['privacyPolicy']);
            }

            $response = $payrexx->create($gateway);
        } catch (PayrexxException $e) {
            throw new InvalidRequestException($e->getMessage());
        }

        return $this->response = new PurchaseResponse($this, $response);
    }
}
