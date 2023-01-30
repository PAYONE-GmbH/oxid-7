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
 * @link          http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version       OXID eShop CE
 */

namespace Fatchip\PayOne\Application\Controller;

use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcPayOneOrderView extends FcPayOneOrderView_parent
{
    public const FCPO_AMAZON_ERROR_TRANSACTION_TIMED_OUT = 980;
    public const FCPO_AMAZON_ERROR_INVALID_PAYMENT_METHOD = 981;
    public const FCPO_AMAZON_ERROR_REJECTED = 982;
    public const FCPO_AMAZON_ERROR_PROCESSING_FAILURE = 983;
    public const FCPO_AMAZON_ERROR_BUYER_EQUALS_SELLER = 984;
    public const FCPO_AMAZON_ERROR_PAYMENT_NOT_ALLOWED = 985;
    public const FCPO_AMAZON_ERROR_PAYMENT_PLAN_NOT_SET = 986;
    public const FCPO_AMAZON_ERROR_SHIPPING_ADDRESS_NOT_SET = 987;
    public const FCPO_AMAZON_ERROR_900 = 900;


    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper;

    /**
     * Database instance
     *
     * @var object
     */
    protected DatabaseInterface $_oFcpoDb;

    /**
     * Boolean of option "blConfirmAGB" error
     *
     * @var bool
     */
    protected $_blFcpoConfirmMandateError;


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
        $this->_oFcpoDb = DatabaseProvider::getDb();
    }


    /**
     * Extends oxid standard method execute()
     * Check if debitnote mandate was accepted
     *
     * Checks for order rules confirmation ("ord_agb", "ord_custinfo" form values)(if no
     * rules agreed - returns to order view), loads basket contents (plus applied
     * price/amount discount if available - checks for stock, checks user data (if no
     * data is set - returns to user login page). Stores order info to database
     * (oxorder::finalizeOrder()). According to sum for items automatically assigns user to
     * special user group ( oxuser::onOrderExecute(); if this option is not disabled in
     * admin). Finally you will be redirected to next page (order::_getNextStep()).
     *
     * @return string
     */
    public function execute()
    {
        $sFcpoMandateCheckbox =
            $this->_oFcpoHelper->fcpoGetRequestParameter('fcpoMandateCheckbox');

        $blConfirmMandateError = (
            (
                !$sFcpoMandateCheckbox ||
                $sFcpoMandateCheckbox == 'false'
            ) &&
            $this->_fcpoMandateAcceptanceNeeded()
        );

        if ($blConfirmMandateError) {
            $this->_blFcpoConfirmMandateError = 1;
            return;
        }

        return null;
    }

    /**
     * Check if mandate acceptance is needed
     */
    private function _fcpoMandateAcceptanceNeeded(): bool
    {
        $aMandate = $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoMandate');
        return $aMandate && array_key_exists('mandate_status', $aMandate) && $aMandate['mandate_status'] == 'pending' && array_key_exists('mandate_text', $aMandate);
    }

    /**
     * Handles paypal express
     *
     * 
     * @return string
     */
    public function fcpoHandlePayPalExpress()
    {
        try {
            $this->_handlePayPalExpressCall();
        } catch (oxException $oExcp) {
            $oUtilsView = $this->_oFcpoHelper->fcpoGetUtilsView();
            $oUtilsView->addErrorToDisplay($oExcp);
            return "basket";
        }
    }

    /**
     * Handles the paypal express call
     *
     */
    private function _handlePayPalExpressCall(): void
    {
        $sWorkorderId = $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoWorkorderId');
        if ($sWorkorderId) {
            $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
            $aOutput = $oRequest->sendRequestGenericPayment($sWorkorderId);
            $this->_oFcpoHelper->fcpoSetSessionVariable('paymentid', "fcpopaypal_express");
            $oUser = $this->_fcpoHandleExpressUser($aOutput);

            if ($oUser) {
                $this->_fcpoUpdateUserOfExpressBasket($oUser, "fcpopaypal_express");
            }
        }
    }

    /**
     * Handles user related things corresponding to API-Response
     *
     * @param array $aResponse
     * @return object
     */
    private function _fcpoHandleExpressUser($aResponse)
    {
        $sEmail = $aResponse['add_paydata[email]'];
        $oCurrentUser = $this->getUser();
        if ($oCurrentUser) {
            $sEmail = $oCurrentUser->oxuser__oxusername->value;
        }

        $sUserId = $this->_fcpoDoesExpressUserAlreadyExist($sEmail);
        if ($sUserId) {
            $oUser = $this->_fcpoValidateAndGetExpressUser($sUserId, $aResponse);
        } else {
            $oUser = $this->_fcpoCreateUserByResponse($aResponse);
        }


        $this->_oFcpoHelper->fcpoSetSessionVariable('usr', $oUser->getId());
        $this->setUser($oUser);

        return $oUser;
    }

    /**
     * Checks if user of this paypal order already exists
     *
     * @param string $sEmail
     * @return mixed
     */
    private function _fcpoDoesExpressUserAlreadyExist($sEmail)
    {
        $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');
        $oOrder = $this->_oFcpoHelper->getFactoryObject('oxOrder');
        $blReturn = $oOrder->fcpoDoesUserAlreadyExist($sEmail);

        $blIsExpressException = (
            $blReturn !== false &&
            (
                $sPaymentId == 'fcpopaypal_express' ||
                $sPaymentId == 'fcpopaydirekt_express'
            )
        );

        if ($blIsExpressException) {
            // always using the address that has been
            // sent by express service is mandatory
            $blReturn = false;
        }

        return $blReturn;
    }

    /**
     * Validate possibly logged in user, comparing exting users
     *
     * @param $sUserId
     * @param $aResponse
     * @return object
     */
    private function _fcpoValidateAndGetExpressUser($sUserId, $aResponse)
    {
        $oCurrentUser = $this->getUser();

        $oUser = $this->_oFcpoHelper->getFactoryObject("oxUser");
        $oUser->load($sUserId);
        $blSameUser = $this->_fcpoIsSameExpressUser($oUser, $aResponse);
        $blNoUserException = (!$oCurrentUser && !$blSameUser);

        if ($blNoUserException) {
            $this->_fcpoThrowException('FCPO_PAYPALEXPRESS_USER_SECURITY_ERROR');
        }

        if (!$blSameUser) {
            $this->_fcpoCreateExpressDelAddress($aResponse, $sUserId);
        } else {
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('deladrid');
        }

        return $oUser;
    }

    /**
     * Method throws an exception with given message
     *
     * @return void
     */
    private function _fcpoThrowException(string $sMessage): never
    {
        // user is not logged in and the address is different
        $oEx = oxNew('oxException');
        $oEx->setMessage($sMessage);
        throw $oEx;
    }

    /**
     * Creating paypal delivery address
     *
     * @param array  $aResponse
     * @param string $sUserId
     */
    private function _fcpoCreateExpressDelAddress($aResponse, $sUserId): void
    {
        if ($sAddressId = $this->_fcpoGetExistingPayPalAddressId($aResponse)) {
            $this->_oFcpoHelper->fcpoSetSessionVariable("deladrid", $sAddressId);
        } else {
            [$sStreet, $sStreetNr] = $this->_fcpoFetchStreetAndNumber($aResponse, true);

            $sAddInfo = '';
            if (array_key_exists('add_paydata[shipping_addressaddition]', $aResponse)) {
                $sAddInfo = $aResponse['add_paydata[shipping_addressaddition]'];
            }

            $oAddress = oxNew("oxAddress");
            $oAddress->oxaddress__oxuserid = new oxField($sUserId);
            $oAddress->oxaddress__oxfname = new oxField($aResponse['add_paydata[shipping_firstname]']);
            $oAddress->oxaddress__oxlname = new oxField($aResponse['add_paydata[shipping_lastname]']);
            $oAddress->oxaddress__oxstreet = new oxField($sStreet);
            $oAddress->oxaddress__oxstreetnr = new oxField($sStreetNr);
            $oAddress->oxaddress__oxaddinfo = new oxField($sAddInfo);
            $oAddress->oxaddress__oxcity = new oxField($aResponse['add_paydata[shipping_city]']);
            $oAddress->oxaddress__oxzip = new oxField($aResponse['add_paydata[shipping_zip]']);
            $oAddress->oxaddress__oxcountryid = new oxField($this->_fcpoGetIdByCode($aResponse['add_paydata[shipping_country]']));
            $oAddress->oxaddress__oxstateid = new oxField('');
            $oAddress->oxaddress__oxfon = new oxField('');
            $oAddress->oxaddress__oxsal = new oxField($this->_fcpoGetSal($aResponse['add_paydata[shipping_firstname]']));
            $oAddress->save();

            $this->_oFcpoHelper->fcpoSetSessionVariable("deladrid", $oAddress->getId());
        }
    }

    /**
     * Searches an existing addressid by extracting response of payone
     *
     * @param array $aResponse
     * @return mixed
     */
    private function _fcpoGetExistingPayPalAddressId($aResponse)
    {
        [$sStreet, $sStreetNr] = $this->_fcpoSplitAddress($aResponse['add_paydata[shipping_street]']);

        $oOrder = $this->_oFcpoHelper->getFactoryObject('oxOrder');
        $sAddressId = $oOrder->fcpoGetAddressIdByResponse($aResponse, $sStreet, $sStreetNr);

        return $sAddressId ?: false;
    }

    /**
     * Splits street and number from concatenated combofield
     *
     * @param string $sPayPalStreet
     */
    private function _fcpoSplitAddress($sPayPalStreet): array
    {
        $sStreetNr = '';
        if (preg_match('/\s\d/', $sPayPalStreet, $match)) {
            $iEndOfStreetPos = strpos($sPayPalStreet, $match[0]);
            $iStartOfStreetNrPos = $iEndOfStreetPos + 1; // skip space between street and street nr
            $sStreetNr = substr($sPayPalStreet, $iStartOfStreetNrPos);
            $sPayPalStreet = substr($sPayPalStreet, 0, $iEndOfStreetPos);
        }

        return [$sPayPalStreet, $sStreetNr];
    }

    /**
     * Fetches streetname and number depending by payment
     *
     * @param $aResponse
     * @return array
     */
    private function _fcpoFetchStreetAndNumber($aResponse, bool $blShipping = false)
    {
        $sPrefix = ($blShipping) ? 'shipping' : 'billing';

        $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');

        return match ($sPaymentId) {
            'fcpopaypal_express' => $this->_fcpoSplitAddress($aResponse['add_paydata[shipping_street]']),
            default => [$aResponse['add_paydata[' . $sPrefix . '_streetname]'], $aResponse['add_paydata[' . $sPrefix . '_streetnumber]']],
        };
    }

    /**
     * Get CountryID by countrycode
     *
     * @param string $sCode
     * @return string
     */
    private function _fcpoGetIdByCode($sCode)
    {
        $oOrder = $this->_oFcpoHelper->getFactoryObject('oxOrder');
        return (string)$oOrder->fcpoGetIdByCode($sCode);
    }

    /**
     * Returns salutation of customer in the expected form
     *
     * @param string $sFirstname
     * @return string
     */
    private function _fcpoGetSal($sFirstname)
    {
        $oOrder = $this->_oFcpoHelper->getFactoryObject('oxOrder');
        $sSal = $oOrder->fcpoGetSalByFirstName($sFirstname);
        $sSal = $sSal ?: 'MR';

        if ($sSal == 'Herr') {
            $sSal = 'MR';
        } elseif ($sSal == 'Frau') {
            $sSal = 'MRS';
        }

        return $sSal;
    }

    /**
     * Create a user by API response
     *
     * @param array $aResponse
     * @return object
     */
    private function _fcpoCreateUserByResponse($aResponse)
    {
        $oUser = $this->_oFcpoHelper->getFactoryObject("oxUser");
        $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');

        $sEmailIdent =
            ($sPaymentId == 'fcpopaydirekt_express') ?
                'add_paydata[buyer_email]' :
                'add_paydata[email]';

        $sUserId = $this->_fcpoGetIdByUserName($aResponse[$sEmailIdent]);
        if ($sUserId !== '' && $sUserId !== '0') {
            $oUser->load($sUserId);
        }

        [$sStreet, $sStreetNr] = $this->_fcpoFetchStreetAndNumber($aResponse);
        $sAddInfo = '';
        if (array_key_exists('add_paydata[shipping_addressaddition]', $aResponse)) {
            $sAddInfo = $aResponse['add_paydata[shipping_addressaddition]'];
        }

        $sTelephone =
            $aResponse['add_paydata[telephonenumber]'] ?? '';

        $oUser->oxuser__oxactive = new oxField(1);
        $oUser->oxuser__oxusername = new oxField($aResponse[$sEmailIdent]);
        $oUser->oxuser__oxfname = new oxField($aResponse['add_paydata[shipping_firstname]']);
        $oUser->oxuser__oxlname = new oxField($aResponse['add_paydata[shipping_lastname]']);
        $oUser->oxuser__oxfon = new oxField('');
        $oUser->oxuser__oxsal = new oxField($this->_fcpoGetSal($aResponse['add_paydata[shipping_firstname]']));
        $oUser->oxuser__oxcompany = new oxField('');
        $oUser->oxuser__oxstreet = new oxField($sStreet);
        $oUser->oxuser__oxstreetnr = new oxField($sStreetNr);
        $oUser->oxuser__oxaddinfo = new oxField($sAddInfo);
        $oUser->oxuser__oxcity = new oxField($aResponse['add_paydata[shipping_city]']);
        $oUser->oxuser__oxzip = new oxField($aResponse['add_paydata[shipping_zip]']);
        $oUser->oxuser__oxcountryid = new oxField($this->_fcpoGetIdByCode($aResponse['add_paydata[shipping_country]']));
        $oUser->oxuser__oxstateid = new oxField('');
        $oUser->oxuser__oxfon = new oxField($sTelephone);

        if ($oUser->save()) {
            $oUser->addToGroup("oxidnotyetordered");
            $oUser->fcpoUnsetGroups();
        }

        return $oUser;
    }

    /**
     * Get userid by given username
     *
     * @param string $sUserName
     * @return string
     */
    public function _fcpoGetIdByUserName($sUserName)
    {
        $oOrder = $this->_oFcpoHelper->getFactoryObject('oxOrder');
        return $oOrder->fcpoGetIdByUserName($sUserName);
    }

    /**
     * Updates given user into basket
     *
     * @param $oUser
     */
    private function _fcpoUpdateUserOfExpressBasket(object $oUser, string $sPaymentId): void
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oBasket->setBasketUser($oUser);

        // setting PayPal as current active payment
        $oBasket->setPayment($sPaymentId);

        $sActShipSet = $this->_oFcpoHelper->fcpoGetRequestParameter('sShipSet');
        if (!$sActShipSet) {
            $sActShipSet = $this->_oFcpoHelper->fcpoGetSessionVariable('sShipSet');
        }

        // load sets, active set, and active set payment list
        $oDelSets = $this->_oFcpoHelper->getFactoryObject("oxdeliverysetlist");
        [$aAllSets, $sActShipSet, $aPaymentList] =
            $oDelSets->getDeliverySetData($sActShipSet, $this->getUser(), $oBasket);

        $oBasket->setShipping($sActShipSet);
        $oBasket->onUpdate();
        $oBasket->calculateBasket(true);
    }

    /**
     * Handling of paydirekt express
     *
     * 
     * @return string
     */
    public function fcpoHandlePaydirektExpress()
    {
        try {
            $this->_handlePaydirektExpressCall();
        } catch (oxException $oExcp) {
            $oUtilsView = $this->_oFcpoHelper->fcpoGetUtilsView();
            $oUtilsView->addErrorToDisplay($oExcp);
            return "basket";
        }
    }

    /**
     * Handles Paydirekt Express Call
     *
     */
    private function _handlePaydirektExpressCall(): void
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $sWorkorderId = $oSession->getVariable('fcpoWorkorderId');

        if (!$sWorkorderId) {
            return;
        }

        $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aOutput = $oRequest->sendRequestPaydirektCheckout($sWorkorderId);
        $this->_oFcpoHelper->fcpoSetSessionVariable('paymentid', "fcpopaydirekt_express");
        $oUser = $this->_fcpoHandleExpressUser($aOutput);

        if ($oUser) {
            $this->_fcpoUpdateUserOfExpressBasket($oUser, "fcpopaydirekt_express");
        }
    }

    /**
     * Returns target controller if user hits the edit button
     * of billing or shipping address
     *
     * 
     * @return string
     */
    public function fcpoGetEditAddressTargetController()
    {
        $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');

        $aMap = ['fcpopaypal_express' => 'basket', 
    'fcpopaydirekt_express' => 'basket'];

        return $aMap[$sPaymentId] ?? 'user';
    }

    /**
     * Returns target controller action if user hits the edit
     * button of billing or shipping address
     *
     * 
     * @return string
     */
    public function fcpoGetEditAddressTargetAction()
    {
        $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');

        $aMap = ['fcpopaypal_express' => 'fcpoUsePayPalExpress', 
    'fcpopaydirekt_express' => 'fcpoUsePaydirektExpress'];

        return $aMap[$sPaymentId] ?? '';
    }

    /**
     * Template variable getter. Return if the debitnote mandate was not accepted and thus an error has to be shown.
     *
     * @return bool
     */
    public function fcpoIsMandateError()
    {
        return $this->_blFcpoConfirmMandateError;
    }
}
