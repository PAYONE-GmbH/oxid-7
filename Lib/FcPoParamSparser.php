<?php

/**
 * PAYONE OXID Connector is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PAYONE OXID Connector is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with PAYONE OXID Connector.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 *
 * @copyright 2003 - 2016 Payone GmbH
 * @version   OXID eShop CE
 * @link      http://www.payone.de
 */

namespace Fatchip\PayOne\Lib;

class FcPoParamSparser
{
    /**
     * @var FcPoHelper
     */
    protected $_oFcpoHelper;

    /**
     * @var null
     */
    protected $_oUser;

    /**
     * Class constructor, sets all required parameters for requests.
     */
    public function __construct()
    {
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * @param $sClientToken
     * @param $sParamsJson
     */
    public function fcpoGetKlarnaWidgetJS($sClientToken, $sParamsJson): string
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();

        $aParams = json_decode($sParamsJson, true, 512, JSON_THROW_ON_ERROR);
        $aCustomer = $this->_fcpoGetKlarnaCustomerParams();
        $aBilling = $this->_fcpoGetKlarnaBillingParams();

        // set params depending on private person / company
        if ($oUser->oxuser__oxcompany->value != '') {
            $aBilling['organization_name'] = $oUser->oxuser__oxcompany->value;
            $aCustomer['organization_registration_id'] = $oUser->oxuser__oxustid->value;
        }

        $aShipping = $this->_fcpoGetKlarnaShippingParams();
        $aPurchase = $this->_fcpoGetKlarnaPurchaseParams();
        $aOrderlines = $this->_fcpoGetKlarnaOrderlinesParams();
        $aOrder = $this->_fcpoGetKlarnaOrderParams();


        $aKlarnaWidgetSearch = ['%%TOKEN%%', 
    '%%PAYMENT_CONTAINER_ID%%', 
    '%%PAYMENT_CATEGORY%%', 
    '%%KLARNA_CUSTOMER%%', 
    '%%KLARNA_BILLING%%', 
    '%%KLARNA_SHIPPING%%', 
    '%%KLARNA_PURCHASE%%', 
    '%%KLARNA_ORDERLINES%%', 
    '%%KLARNA_ORDER%%'];

        $aKlarnaWidgetReplace = [$sClientToken, $aParams['payment_container_id'], $aParams['payment_category'], json_encode($aCustomer, JSON_UNESCAPED_UNICODE), json_encode($aBilling, JSON_UNESCAPED_UNICODE), json_encode($aShipping, JSON_UNESCAPED_UNICODE), json_encode($aPurchase, JSON_UNESCAPED_UNICODE), json_encode($aOrderlines, JSON_UNESCAPED_UNICODE), json_encode($aOrder, JSON_UNESCAPED_UNICODE)];

        $sKlarnaWidgetJS = file_get_contents($this->_fcpoGetKlarnaWidgetPath());

        return str_replace($aKlarnaWidgetSearch, $aKlarnaWidgetReplace, $sKlarnaWidgetJS);
    }

    /**
     * Returns customer params for klarna widget
     *
     *
     * @return array{date_of_birth: mixed, gender: string, national_identification_number: mixed}
     */
    protected function _fcpoGetKlarnaCustomerParams(): array
    {
        $oUser = $this->_fcpoGetUser();
        $sGender = ($oUser->oxuser__oxsal->value == 'MR') ? 'male' : 'female';

        return ['date_of_birth' => ($oUser->oxuser__oxbirthdate->value === '0000-00-00') ? '' : $oUser->oxuser__oxbirthdate->value, 'gender' => $sGender, 'national_identification_number' => $oUser->oxuser__fcpopersonalid->value];
    }

    /**
     * Returns user object of current logged in user
     *
     * @return mixed
     */
    protected function _fcpoGetUser()
    {
        if ($this->_oUser === null) {
            $oSession = $this->_oFcpoHelper->fcpoGetSession();
            $oBasket = $oSession->getBasket();
            $this->_oUser = $oBasket->getUser();
        }

        return $this->_oUser;
    }

    /**
     * Returns customer billing address params for klarna widget
     *
     *
     * @return array{given_name: mixed, family_name: mixed, email: mixed, title: string, street_address: string, street_address2: mixed, postal_code: mixed, city: mixed, region: mixed, phone: mixed, country: mixed, organization_name: mixed}
     */
    protected function _fcpoGetKlarnaBillingParams(): array
    {
        $oUser = $this->_fcpoGetUser();

        return ['given_name' => $oUser->oxuser__oxfname->value, 'family_name' => $oUser->oxuser__oxlname->value, 'email' => $oUser->oxuser__oxusername->value, 'title' => $this->fcpoGetTitle(), 'street_address' => $oUser->oxuser__oxstreet->value . " " . $oUser->oxuser__oxstreetnr->value, 'street_address2' => $oUser->oxuser__oxaddinfo->value, 'postal_code' => $oUser->oxuser__oxzip->value, 'city' => $oUser->oxuser__oxcity->value, 'region' => $oUser->getStateTitle(), 'phone' => $oUser->oxuser__oxfon->value, 'country' => $oUser->fcpoGetUserCountryIso(), 'organization_name' => $oUser->oxuser__oxcompany->value];
    }

    /**
     * Returns country specific title.
     *
     * @return string
     */
    public function fcpoGetTitle()
    {
        $sTitle = null;
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        $sGender = ($oUser->oxuser__oxsal->value == 'MR') ? 'male' : 'female';
        $sCountryIso2 = $oUser->fcpoGetUserCountryIso();
        return match ($sCountryIso2) {
            'AT', 
    'DE' => ($sGender === 'male') ? 'Herr' : 'Frau',
            'CH' => ($sGender === 'male') ? 'Herr' : 'Frau',
            'GB', 
    'US' => ($sGender === 'male') ? 'Mr' : 'Ms',
            'DK', 
    'FI', 
    'SE', 
    'NL', 
    'NO' => ($sGender === 'male') ? 'Dhr.' : 'Mevr.',
            default => $sTitle,
        };
    }

    /**
     * Returns customer billing address params for klarna widget
     *
     * 
     * @return array
     */
    protected function _fcpoGetKlarnaShippingParams()
    {
        $oUser = $this->_fcpoGetUser();

        $oShippingAddress = $this->_fcpoGetShippingAddress();
        $blHasShipping = (bool) $oShippingAddress;

        if ($blHasShipping) {
            return ['given_name' => $oShippingAddress->oxaddress__oxfname->value, 'family_name' => $oShippingAddress->oxaddress__oxlname->value, 'email' => $oUser->oxuser__oxusername->value, 'title' => $this->fcpoGetTitle(), 'street_address' => $oShippingAddress->oxaddress__oxstreet->value . " " . $oShippingAddress->oxaddress__oxstreetnr->value, 'street_address2' => $oShippingAddress->oxaddress__oxaddinfo->value, 'postal_code' => $oShippingAddress->oxaddress__oxzip->value, 'city' => $oShippingAddress->oxaddress__oxcity->value, 'region' => "", 'phone' => $oShippingAddress->oxaddress__oxfon->value, 'country' => $oShippingAddress->fcpoGetUserCountryIso(), 'organization_name' => $oShippingAddress->oxaddress__oxcompany->value];
        } else {
            return $this->_fcpoGetKlarnaBillingParams();
        }
    }

    /**
     * Returns an object with the shipping address.
     *
     * 
     * @return mixed false|object
     */
    protected function _fcpoGetShippingAddress()
    {
        if (!($sAddressId = $this->_oFcpoHelper->fcpoGetRequestParameter('deladrid'))) {
            $sAddressId = $this->_oFcpoHelper->fcpoGetSessionVariable('deladrid');
        }

        if (!$sAddressId) {
            return false;
        }

        $oShippingAddress = oxNew('oxaddress');
        $oShippingAddress->load($sAddressId);

        return $oShippingAddress;
    }

    /**
     * Return needed data for performing authorization
     *
     *
     * @return array{purchase_country: mixed, purchase_currency: mixed}
     */
    protected function _fcpoGetKlarnaPurchaseParams(): array
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oUser = $this->_fcpoGetUser();
        $actShopCurrencyObject = $oConfig->getActShopCurrencyObject();

        return ['purchase_country' => $oUser->fcpoGetUserCountryIso(), 'purchase_currency' => $actShopCurrencyObject->name];
    }

    /**
     * Returns and brings basket positions into appropriate form
     *
     *
     * @return array<int, array{reference: mixed, name: mixed, quantity: mixed, unit_price: float|int, tax_rate: float|int, total_amount: float|int}>
     */
    protected function _fcpoGetKlarnaOrderlinesParams(): array
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();

        $aOrderlines = [];
        foreach ($oBasket->getContents() as $oBasketItem) {
            $oArticle = $oBasketItem->getArticle();

            $aOrderline = ['reference' => $oArticle->oxarticles__oxartnum->value, 'name' => $oBasketItem->getTitle(), 'quantity' => $oBasketItem->getAmount(), 'unit_price' => $oBasketItem->getUnitPrice()->getBruttoPrice() * 100, 'tax_rate' => $oBasketItem->getVatPercent() * 100, 'total_amount' => $oBasketItem->getPrice()->getBruttoPrice() * 100 * $oBasketItem->getAmount()];
            $aOrderlines[] = $aOrderline;
        }

        // add shipping information to order
        // ToDO check Datatypes and Conversion
        $sDeliveryCosts = $this->_fcpoFetchDeliveryCostsFromBasket($oBasket);

        $oDelivery = $oBasket->getCosts('oxdelivery');

        $sDeliveryCosts = (double)str_replace(',', 
    '.', $sDeliveryCosts);
        if ($sDeliveryCosts > 0) {
            $aOrderlineShipping = ['reference' => 'delivery', 
    'name' => 'Standard Versand', 
    'quantity' => 1, 'unit_price' => $sDeliveryCosts * 100, 'tax_rate' => (string)$oDelivery->getVat() * 100, 'total_amount' => $sDeliveryCosts * 100];
            $aOrderlines[] = $aOrderlineShipping;
        }

        return $aOrderlines;
    }

    /**
     * Returns delivery costs of given basket object
     *
     * @param $oBasket
     * @return object $oDelivery
     */
    protected function _fcpoFetchDeliveryCostsFromBasket($oBasket)
    {
        $oDelivery = $oBasket->getCosts('oxdelivery');
        if ($oDelivery === null) {
            return 0.0;
        }

        return $oDelivery->getBruttoPrice();
    }

    /**
     * Returns and brings basket positions into appropriate form
     *
     *
     * @return array{order_amount: float, order_tax_amount: float}
     */
    protected function _fcpoGetKlarnaOrderParams(): array
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $dAmount = $oBasket->getPrice()->getBruttoPrice();
        $dTaxAmount = $oBasket->getPrice()->getVat();

        return ['order_amount' => $dAmount * 100, 'order_tax_amount' => $dTaxAmount];
    }

    /**
     * Returns the path to a text file with js for the klarna widget.
     *
     * @return string
     */
    protected function _fcpoGetKlarnaWidgetPath()
    {
        $oViewConf = $this->_oFcpoHelper->getFactoryObject('oxviewconfig');
        return $oViewConf->getModulePath('fcpayone') . '/out/snippets/fcpoKlarnaWidget.txt';
    }

    /**
     * Returns and brings basket positions into appropriate form
     *
     *
     * @return array{orderlines?: array<int, array{reference: mixed, name: mixed, quantity: mixed, unit_price: mixed, tax_rate: mixed, total_amount: mixed}>&mixed[], order_amount: int, order_tax_amount: int}
     */
    protected function _fcpoGetKlarnaOrderdata(): array
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $aOrderData = [];

        foreach ($oBasket->getContents() as $oBasketItem) {
            $oArticle = $oBasketItem->getArticle();
            $aOrderData['orderlines'][] = ['reference' => $oArticle->oxarticles__oxartnum->value, 'name' => $oBasketItem->getTitle(), 'quantity' => $oBasketItem->getAmount(), 'unit_price' => $oBasketItem->getUnitPrice()->getBruttoPrice(), 'tax_rate' => $oBasketItem->getVatPercent(), 'total_amount' => $oBasketItem->getPrice()->getBruttoPrice()];
        }

        $dAmount = $oBasket->getPrice()->getBruttoPrice();
        $dTaxAmount = $oBasket->getPrice()->getVat();

        $aOrderData['order_amount'] =
            $this->_oFcpoHelper->fcpoGetCentPrice($dAmount);
        $aOrderData['order_tax_amount'] =
            $this->_oFcpoHelper->fcpoGetCentPrice($dTaxAmount);

        return $aOrderData;
    }

    /**
     * Removes params that are not used for this country.
     *
     * @param $aKlarnaData
     */
    protected function _fcpoRemoveKlarnaDataForCountry($aKlarnaData)
    {
        $oUser = $this->_fcpoGetUser();
        $sCountryIso2 = $oUser->fcpoGetUserCountryIso();
        switch ($sCountryIso2) {
            case 'AT':
            case 'DE':
                unset(
                    $aKlarnaData['order_amount'],
                    $aKlarnaData['order_tax_amount']
                );
                break;
            case 'DK':
                unset(
                    $aKlarnaData['billing_address']['title'],
                    $aKlarnaData['billing_address']['street_address_2'],
                    $aKlarnaData['shipping_address']['title'],
                    $aKlarnaData['shipping_address']['street_address_2'],
                    $aKlarnaData['customer']['date_of_birth']
                );
                break;
            case 'FI':
            case 'SE':
                unset(
                    $aKlarnaData['billing_address']['street_address_2'],
                    $aKlarnaData['billing_address']['region'],
                    $aKlarnaData['shipping_address']['street_address_2'],
                    $aKlarnaData['shipping_address']['region'],
                    $aKlarnaData['customer']['date_of_birth']
                );
                break;
            case 'GB':
            case 'US':
                break;
            case 'NL':
                unset(
                    $aKlarnaData['billing_address']['street_address_2'],
                    $aKlarnaData['shipping_address']['street_address_2']
                );
                break;
            case 'NO':
                unset(
                    $aKlarnaData['billing_address']['title'],
                    $aKlarnaData['shipping_address_address']['title'],
                    $aKlarnaData['customer']['date_of_birth']
                );
                break;
        }
        return $aKlarnaData;
    }
}
