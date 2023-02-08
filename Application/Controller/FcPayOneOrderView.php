<?php

namespace Fatchip\PayOne\Application\Controller;

use Exception;
use Fatchip\PayOne\Application\Model\FcPayOnePayment;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\DeliverySetList;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;

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
class FcPayOneOrderView extends FcPayOneOrderView_parent
{

    const FCPO_AMAZON_ERROR_TRANSACTION_TIMED_OUT = 980;
    const FCPO_AMAZON_ERROR_INVALID_PAYMENT_METHOD = 981;
    const FCPO_AMAZON_ERROR_REJECTED = 982;
    const FCPO_AMAZON_ERROR_PROCESSING_FAILURE = 983;
    const FCPO_AMAZON_ERROR_BUYER_EQUALS_SELLER = 984;
    const FCPO_AMAZON_ERROR_PAYMENT_NOT_ALLOWED = 985;
    const FCPO_AMAZON_ERROR_PAYMENT_PLAN_NOT_SET = 986;
    const FCPO_AMAZON_ERROR_SHIPPING_ADDRESS_NOT_SET = 987;
    const FCPO_AMAZON_ERROR_900 = 900;


    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoHelper = null;

    /**
     * Database instance
     *
     * @var object
     */
    protected $_oFcPoDb = null;

    /**
     * Boolean of option "blConfirmAGB" error
     *
     * @var bool
     */
    protected $_blFcpoConfirmMandateError = null;


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
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
        $sFcpoMandateCheckbox = $this->_oFcPoHelper->fcpoGetRequestParameter('fcpoMandateCheckbox');
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');
        $blIsRedirectPayment = FcPayOnePayment::fcIsPayOneRedirectType($sPaymentId);

        $blConfirmMandateError = (
            (!$sFcpoMandateCheckbox || $sFcpoMandateCheckbox == 'false') &&
            $this->_fcpoMandateAcceptanceNeeded()
        );

        if ($blConfirmMandateError) {
            $this->_blFcpoConfirmMandateError = 1;
            return;
        }

        return parent::execute();
    }

    /**
     * Check if mandate acceptance is needed
     *
     * @return bool
     */
    protected function _fcpoMandateAcceptanceNeeded()
    {
        $aMandate = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoMandate');
        if ($aMandate && array_key_exists('mandate_status', $aMandate) !== false && $aMandate['mandate_status'] == 'pending') {
            if (array_key_exists('mandate_text', $aMandate) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handles paypal express
     *
     * @return string
     */
    public function fcpoHandlePayPalExpress()
    {
        try {
            $this->_handlePayPalExpressCall();
        } catch (Exception $oExcp) {
            $oUtilsView = $this->_oFcPoHelper->fcpoGetUtilsView();
            $oUtilsView->addErrorToDisplay($oExcp);
            return "basket";
        }
    }

    /**
     * Handles the paypal express call
     *
     * @return void
     */
    protected function _handlePayPalExpressCall()
    {
        $sWorkorderId = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoWorkorderId');
        if ($sWorkorderId) {
            $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
            $aOutput = $oRequest->sendRequestGenericPayment($sWorkorderId);
            $this->_oFcPoHelper->fcpoSetSessionVariable('paymentid', "fcpopaypal_express");
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
     * @throws Exception
     */
    protected function _fcpoHandleExpressUser($aResponse)
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


        $this->_oFcPoHelper->fcpoSetSessionVariable('usr', $oUser->getId());
        $this->setUser($oUser);

        return $oUser;
    }

    /**
     * Checks if user of this paypal order already exists
     *
     * @param string $sEmail
     * @return bool
     */
    protected function _fcpoDoesExpressUserAlreadyExist(string $sEmail): bool
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        $blReturn = $oOrder->fcpoDoesUserAlreadyExist($sEmail);

        $blIsExpressException = (
            $blReturn !== false &&
            (
                $sPaymentId == 'fcpopaypal_express'
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
     * @param string $sUserId
     * @param array  $aResponse
     * @return object
     * @throws Exception
     */
    protected function _fcpoValidateAndGetExpressUser(string $sUserId, array $aResponse): object
    {
        $oCurrentUser = $this->getUser();

        $oUser = $this->_oFcPoHelper->getFactoryObject(User::class);
        $oUser->load($sUserId);
        $blSameUser = $this->_fcpoIsSameExpressUser($oUser, $aResponse);
        $blNoUserException = (!$oCurrentUser && !$blSameUser);

        if ($blNoUserException) {
            $this->_fcpoThrowException('FCPO_PAYPALEXPRESS_USER_SECURITY_ERROR');
        }

        if (!$blSameUser) {
            $this->_fcpoCreateExpressDelAddress($aResponse, $sUserId);
        } else {
            $this->_oFcPoHelper->fcpoDeleteSessionVariable('deladrid');
        }

        return $oUser;
    }

    /**
     * Method throws an exception with given message
     *
     * @param string $sMessage
     * @return void
     * @throws Exception
     */
    protected function _fcpoThrowException(string $sMessage): void
    {
        // user is not logged in and the address is different
        $oEx = oxNew(Exception::class);
        $oEx->setMessage($sMessage);
        throw $oEx;
    }

    /**
     * Creating paypal delivery address
     *
     * @param array  $aResponse
     * @param string $sUserId
     * @return void
     * @throws Exception
     */
    protected function _fcpoCreateExpressDelAddress(array $aResponse, string $sUserId): void
    {
        if ($sAddressId = $this->_fcpoGetExistingPayPalAddressId($aResponse)) {
            $this->_oFcPoHelper->fcpoSetSessionVariable("deladrid", $sAddressId);
        } else {
            list($sStreet, $sStreetNr) = $this->_fcpoFetchStreetAndNumber($aResponse, true);

            $sAddInfo = '';
            if (array_key_exists('add_paydata[shipping_addressaddition]', $aResponse)) {
                $sAddInfo = $aResponse['add_paydata[shipping_addressaddition]'];
            }

            $oAddress = oxNew(Address::class);
            $oAddress->oxaddress__oxuserid = new Field($sUserId);
            $oAddress->oxaddress__oxfname = new Field($aResponse['add_paydata[shipping_firstname]']);
            $oAddress->oxaddress__oxlname = new Field($aResponse['add_paydata[shipping_lastname]']);
            $oAddress->oxaddress__oxstreet = new Field($sStreet);
            $oAddress->oxaddress__oxstreetnr = new Field($sStreetNr);
            $oAddress->oxaddress__oxaddinfo = new Field($sAddInfo);
            $oAddress->oxaddress__oxcity = new Field($aResponse['add_paydata[shipping_city]']);
            $oAddress->oxaddress__oxzip = new Field($aResponse['add_paydata[shipping_zip]']);
            $oAddress->oxaddress__oxcountryid = new Field($this->_fcpoGetIdByCode($aResponse['add_paydata[shipping_country]']));
            $oAddress->oxaddress__oxstateid = new Field('');
            $oAddress->oxaddress__oxfon = new Field('');
            $oAddress->oxaddress__oxsal = new Field($this->_fcpoGetSal($aResponse['add_paydata[shipping_firstname]']));
            $oAddress->save();

            $this->_oFcPoHelper->fcpoSetSessionVariable("deladrid", $oAddress->getId());
        }
    }

    /**
     * Searches an existing addressid by extracting response of payone
     *
     * @param array $aResponse
     * @return mixed
     */
    protected function _fcpoGetExistingPayPalAddressId(array $aResponse): mixed
    {
        list($sStreet, $sStreetNr) = $this->_fcpoSplitAddress($aResponse['add_paydata[shipping_street]']);

        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        $sAddressId = $oOrder->fcpoGetAddressIdByResponse($aResponse, $sStreet, $sStreetNr);

        return $sAddressId ?: false;
    }

    /**
     * Splits street and number from concatenated combofield
     *
     * @param string $sPayPalStreet
     * @return array
     */
    protected function _fcpoSplitAddress(string $sPayPalStreet): array
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
     * @param array $aResponse
     * @param bool  $blShipping
     * @return array
     */
    protected function _fcpoFetchStreetAndNumber(array $aResponse, ?bool $blShipping = false)
    {
        $sPrefix = ($blShipping) ? 'shipping' : 'billing';

        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');

        switch ($sPaymentId) {
            case 'fcpopaypal_express':
                $aStreetAndNumber =
                    $this->_fcpoSplitAddress($aResponse['add_paydata[shipping_street]']);
                break;
            default:
                $aStreetAndNumber = array(
                    $aResponse['add_paydata[' . $sPrefix . '_streetname]'],
                    $aResponse['add_paydata[' . $sPrefix . '_streetnumber]'],
                );
        }

        return $aStreetAndNumber;
    }

    /**
     * Get CountryID by countrycode
     *
     * @param string $sCode
     * @return string
     */
    protected function _fcpoGetIdByCode($sCode)
    {
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        return (string)$oOrder->fcpoGetIdByCode($sCode);
    }

    /**
     * Returns salutation of customer in the expected form
     *
     * @param string $sFirstname
     * @return string
     */
    protected function _fcpoGetSal($sFirstname)
    {
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        $sSal = $oOrder->fcpoGetSalByFirstName($sFirstname);
        $sSal = (!$sSal) ? 'MR' : $sSal;

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
    protected function _fcpoCreateUserByResponse($aResponse)
    {
        $oUser = $this->_oFcPoHelper->getFactoryObject(User::class);

        $sEmailIdent = 'add_paydata[email]';

        $sUserId = $this->_fcpoGetIdByUserName($aResponse[$sEmailIdent]);
        if ($sUserId) {
            $oUser->load($sUserId);
        }

        list($sStreet, $sStreetNr) = $this->_fcpoFetchStreetAndNumber($aResponse);
        $sAddInfo = '';
        if (array_key_exists('add_paydata[shipping_addressaddition]', $aResponse)) {
            $sAddInfo = $aResponse['add_paydata[shipping_addressaddition]'];
        }

        $sTelephone =
            (isset($aResponse['add_paydata[telephonenumber]'])) ?
                $aResponse['add_paydata[telephonenumber]'] : '';

        $oUser->oxuser__oxactive = new Field(1);
        $oUser->oxuser__oxusername = new Field($aResponse[$sEmailIdent]);
        $oUser->oxuser__oxfname = new Field($aResponse['add_paydata[shipping_firstname]']);
        $oUser->oxuser__oxlname = new Field($aResponse['add_paydata[shipping_lastname]']);
        $oUser->oxuser__oxfon = new Field('');
        $oUser->oxuser__oxsal = new Field($this->_fcpoGetSal($aResponse['add_paydata[shipping_firstname]']));
        $oUser->oxuser__oxcompany = new Field('');
        $oUser->oxuser__oxstreet = new Field($sStreet);
        $oUser->oxuser__oxstreetnr = new Field($sStreetNr);
        $oUser->oxuser__oxaddinfo = new Field($sAddInfo);
        $oUser->oxuser__oxcity = new Field($aResponse['add_paydata[shipping_city]']);
        $oUser->oxuser__oxzip = new Field($aResponse['add_paydata[shipping_zip]']);
        $oUser->oxuser__oxcountryid = new Field($this->_fcpoGetIdByCode($aResponse['add_paydata[shipping_country]']));
        $oUser->oxuser__oxstateid = new Field('');
        $oUser->oxuser__oxfon = new Field($sTelephone);

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
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        return $oOrder->fcpoGetIdByUserName($sUserName);
    }

    /**
     * Updates given user into basket
     *
     * @param $oUser
     * @return void
     */
    protected function _fcpoUpdateUserOfExpressBasket($oUser, $sPaymentId)
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oBasket->setBasketUser($oUser);

        // setting PayPal as current active payment
        $oBasket->setPayment($sPaymentId);

        $sActShipSet = $this->_oFcPoHelper->fcpoGetRequestParameter('sShipSet');
        if (!$sActShipSet) {
            $sActShipSet = $this->_oFcPoHelper->fcpoGetSessionVariable('sShipSet');
        }

        // load sets, active set, and active set payment list
        $oDelSets = $this->_oFcPoHelper->getFactoryObject(DeliverySetList::class);
        list($aAllSets, $sActShipSet, $aPaymentList) =
            $oDelSets->getDeliverySetData($sActShipSet, $this->getUser(), $oBasket);

        $oBasket->setShipping($sActShipSet);
        $oBasket->onUpdate();
        $oBasket->calculateBasket(true);
    }

    /**
     * Returns target controller if user hits the edit button
     * of billing or shipping address
     *
     * @return string
     */
    public function fcpoGetEditAddressTargetController()
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');

        $aMap = array(
            'fcpopaypal_express' => 'basket'
        );

        $sReturn = (isset($aMap[$sPaymentId])) ?
            $aMap[$sPaymentId] :
            'user';

        return $sReturn;
    }

    /**
     * Returns target controller action if user hits the edit
     * button of billing or shipping address
     *
     * @return string
     */
    public function fcpoGetEditAddressTargetAction()
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');

        $aMap = array(
            'fcpopaypal_express' => 'fcpoUsePayPalExpress'
        );

        $sReturn = (isset($aMap[$sPaymentId])) ?
            $aMap[$sPaymentId] :
            '';

        return $sReturn;
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

    /**
     * Return last calculation values
     *
     * @param string $sParam
     * @return string
     */
    public function fcpoCalculationParameter($sParam)
    {
        $aDynvalue = $this->_oFcPoHelper->fcpoGetSessionVariable('dynvalue');
        $aDynvalue = $aDynvalue ? $aDynvalue : $this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');

        return isset($aDynvalue[$sParam]) ? $aDynvalue[$sParam] : '';
    }

    /**
     * Checks if user of this paypal order already exists
     *
     * @param string $sEmail
     * @return mixed
     */
    protected function _fcpoDoesPaypalUserAlreadyExist($sEmail)
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        $blReturn = $blReturn = $oOrder->fcpoDoesUserAlreadyExist($sEmail);
        $blIsPaypalExpressException = ($blReturn !== false && $sPaymentId == 'fcpopaypal_express');

        if ($blIsPaypalExpressException) {
            // always using the address that has been sent by paypal express is mandatory
            $blReturn = false;
        }

        return $blReturn;
    }

    /**
     * Create paypal user by API response
     *
     * @param array $aResponse
     * @return object
     */
    protected function _fcpoCreatePayPalUser($aResponse)
    {
        $oUser = $this->_oFcPoHelper->getFactoryObject(User::class);

        $sUserId = $this->_fcpoGetIdByUserName($aResponse['add_paydata[email]']);
        if ($sUserId) {
            $oUser->load($sUserId);
        }

        list($sStreet, $sStreetNr) = $this->_fcpoSplitAddress($aResponse['add_paydata[shipping_street]']);
        $sAddInfo = '';
        if (array_key_exists('add_paydata[shipping_addressaddition]', $aResponse)) {
            $sAddInfo = $aResponse['add_paydata[shipping_addressaddition]'];
        }

        $oUser->oxuser__oxactive = new Field(1);
        $oUser->oxuser__oxusername = new Field($aResponse['add_paydata[email]']);
        $oUser->oxuser__oxfname = new Field($aResponse['add_paydata[shipping_firstname]']);
        $oUser->oxuser__oxlname = new Field($aResponse['add_paydata[shipping_lastname]']);
        $oUser->oxuser__oxfon = new Field('');
        $oUser->oxuser__oxsal = new Field($this->_fcpoGetSal($aResponse['add_paydata[shipping_firstname]']));
        $oUser->oxuser__oxcompany = new Field('');
        $oUser->oxuser__oxstreet = new Field($sStreet);
        $oUser->oxuser__oxstreetnr = new Field($sStreetNr);
        $oUser->oxuser__oxaddinfo = new Field($sAddInfo);
        $oUser->oxuser__oxcity = new Field($aResponse['add_paydata[shipping_city]']);
        $oUser->oxuser__oxzip = new Field($aResponse['add_paydata[shipping_zip]']);
        $oUser->oxuser__oxcountryid = new Field($this->_fcpoGetIdByCode($aResponse['add_paydata[shipping_country]']));
        $oUser->oxuser__oxstateid = new Field('');

        if ($oUser->save()) {
            $oUser->addToGroup("oxidnotyetordered");
            $oUser->fcpoUnsetGroups();
        }
        return $oUser;
    }

    /**
     * Compares user object and api response for validating user is the same
     *
     * @param object $oUser
     * @param array  $aResponse
     * @return bool
     */
    protected function _fcpoIsSamePayPalUser($oUser, $aResponse)
    {

        $blIsSamePayPalUser = (
            $oUser->oxuser__oxfname->value == $aResponse['add_paydata[shipping_firstname]'] ||
            $oUser->oxuser__oxlname->value == $aResponse['add_paydata[shipping_lastname]'] ||
            $oUser->oxuser__oxcity->value == $aResponse['add_paydata[shipping_city]'] ||
            stripos($aResponse['add_paydata[shipping_street]'], $oUser->oxuser__oxstreet->value) !== false
        );

        return $blIsSamePayPalUser;
    }

    /**
     * Overwriting next step action if there is some special redirect needed
     *
     * @param $iSuccess
     * @return string
     */
    protected function _getNextStep($iSuccess)
    {
        $sNextStep = parent::_getNextStep($iSuccess);

        $sCustomStep = $this->_fcpoGetRedirectAction($iSuccess);
        if ($sCustomStep) {
            $sNextStep = $sCustomStep;
        }

        return $sNextStep;
    }

    /**
     * Returns action that shall be performed on order::_getNextStep
     *
     * @param $iSuccess
     * @return mixed int|bool
     */
    protected function _fcpoGetRedirectAction($iSuccess)
    {
        $iSuccess = (int)$iSuccess;
        $mReturn = false;
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);

        switch ($iSuccess) {
            case self::FCPO_AMAZON_ERROR_INVALID_PAYMENT_METHOD:
            case self::FCPO_AMAZON_ERROR_PAYMENT_NOT_ALLOWED:
            case self::FCPO_AMAZON_ERROR_PAYMENT_PLAN_NOT_SET:
            case self::FCPO_AMAZON_ERROR_TRANSACTION_TIMED_OUT:
            case self::FCPO_AMAZON_ERROR_REJECTED:
            case self::FCPO_AMAZON_ERROR_PROCESSING_FAILURE:
            case self::FCPO_AMAZON_ERROR_BUYER_EQUALS_SELLER:
            case self::FCPO_AMAZON_ERROR_900:
                $this->_fcpoAmazonLogout();
                $sMessage = $oOrder->fcpoGetAmazonErrorMessage($iSuccess);
                $mReturn = 'basket?fcpoerror=' . urlencode($sMessage);
                break;
            case self::FCPO_AMAZON_ERROR_SHIPPING_ADDRESS_NOT_SET:
                $sMessage = $oOrder->fcpoGetAmazonErrorMessage($iSuccess);
                $mReturn = 'user?fcpoerror=' . urlencode($sMessage);
                break;
        }

        return $mReturn;
    }

    /**
     * Logs out amazon user
     *
     * @return void
     */
    protected function _fcpoAmazonLogout()
    {
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('sAmazonLoginAccessToken');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoAmazonWorkorderId');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoAmazonReferenceId');
        $this->_fcpoDeleteCurrentUser();
    }

    protected function _fcpoDeleteCurrentUser()
    {
        $sUserId = $this->_oFcPoHelper->fcpoGetSessionVariable('usr');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('usr');

        // $oUser = $this->_oFcPoHelper->getFactoryObject(User::class);
        // $oUser->load($sUserId);
        // $oUser->delete();
    }

    /**
     * Extends oxid standard method _validateTermsAndConditions()
     * Validates whether necessary terms and conditions checkboxes were checked.
     *
     * @return bool
     */
    protected function _validateTermsAndConditions()
    {
        if (parent::_validateTermsAndConditions() === true) {
            return true;
        }

        $blValid = true;
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        if ($oConfig->getConfigParam('blConfirmAGB') && !$this->_oFcPoHelper->fcpoGetRequestParameter('ord_agb')) {
            $blValid = false;
        }

        if ($oConfig->getConfigParam('blEnableIntangibleProdAgreement')) {
            $oBasket = $this->getBasket();

            $blDownloadableProductsAgreement = $this->_oFcPoHelper->fcpoGetRequestParameter('fcdpa');
            if ($blValid && $oBasket->hasArticlesWithDownloadableAgreement() && !$blDownloadableProductsAgreement) {
                $blValid = false;
            }

            $blServiceProductsAgreement = $oConfig->getRequestParameter('fcspa');
            if ($blValid && $oBasket->hasArticlesWithIntangibleAgreement() && !$blServiceProductsAgreement) {
                $blValid = false;
            }
        }

        return $blValid;
    }
}
