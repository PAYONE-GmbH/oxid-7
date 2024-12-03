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

use Exception;
use Fatchip\PayOne\Application\Helper\PayPal;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\DeliverySetList;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Field;

class FcPayOneOrderView extends FcPayOneOrderView_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Database instance
     *
     * @var DatabaseInterface
     */
    protected DatabaseInterface $_oFcPoDb;

    /**
     * Boolean of option "blConfirmAGB" error
     *
     * @var bool
     */
    protected bool $_blFcpoConfirmMandateError = false;


    /**
     * init object construction
     *
     * @throws DatabaseConnectionException
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
     * admin). Finally, you will be redirected to next page (order::_getNextStep()).
     *
     * @return ?string
     */
    public function execute(): ?string
    {
        $sFcpoMandateCheckbox = $this->_oFcPoHelper->fcpoGetRequestParameter('fcpoMandateCheckbox');

        $blConfirmMandateError = (
            (!$sFcpoMandateCheckbox || $sFcpoMandateCheckbox == 'false') &&
            $this->_fcpoMandateAcceptanceNeeded()
        );

        if ($blConfirmMandateError) {
            $this->_blFcpoConfirmMandateError = 1;
            return '';
        }

        return parent::execute();
    }

    /**
     * Check if mandate acceptance is needed
     *
     * @return bool
     */
    protected function _fcpoMandateAcceptanceNeeded(): bool
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
     * @return string|null
     */
    public function fcpoHandlePayPalExpress(): string|null
    {
        try {
            $this->_handlePayPalExpressCall(PayPal::PPE_EXPRESS);
            return null;
        } catch (Exception $oEx) {
            $oUtilsView = $this->_oFcPoHelper->fcpoGetUtilsView();
            $oUtilsView->addErrorToDisplay($oEx);
            return "basket";
        }
    }

    /**
     * Handles paypal express
     *
     * @return string|null
     */
    public function fcpoHandlePayPalExpressV2(): string|null
    {
        try {
            $this->_handlePayPalExpressCall(PayPal::PPE_V2_EXPRESS);
            return null;
        } catch (Exception $oEx) {
            $oUtilsView = $this->_oFcPoHelper->fcpoGetUtilsView();
            $oUtilsView->addErrorToDisplay($oEx);
            return "basket";
        }
    }

    /**
     * Handles the PayPal express call
     *
     * @return void
     * @throws Exception
     */
    protected function _handlePayPalExpressCall($sPaymentId): void
    {
        if ($this->_oFcPoHelper->fcpoGetSessionVariable('blFcpoPayonePayPalExpressRetry') === true) {
            $this->_oFcPoHelper->fcpoDeleteSessionVariable('blFcpoPayonePayPalExpressRetry');
            $this->_oFcPoHelper->fcpoGetUtils()->redirect($this->_oFcPoHelper->fcpoGetConfig()->getCurrentShopUrl().'index.php?cl=thankyou', false);
        }

        $sWorkorderId = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoWorkorderId');
        if ($sWorkorderId) {
            $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
            $aOutput = $oRequest->sendRequestGenericPayment($sPaymentId, $sWorkorderId);
            $this->_oFcPoHelper->fcpoSetSessionVariable('paymentid', $sPaymentId);
            $oUser = $this->_fcpoHandleExpressUser($aOutput);

            if ($oUser) {
                $this->_fcpoUpdateUserOfExpressBasket($oUser, $sPaymentId);
            }
        }
    }

    /**
     * Handles user related things corresponding to API-Response
     *
     * @param array $aResponse
     * @return User
     * @throws Exception
     */
    protected function _fcpoHandleExpressUser(array $aResponse): User
    {
        $sEmail = $aResponse['add_paydata[email]'];
        $oCurrentUser = $this->getUser();
        if ($oCurrentUser) {
            $sEmail = $oCurrentUser->oxuser__oxusername->value;
        }

        $sUserId = $this->_fcpoDoesExpressUserAlreadyExist($sEmail);
        if ($sUserId !== false) {
            $oUser = $this->_fcpoValidateAndGetExpressUser($sUserId, $aResponse);
        } else {
            $oUser = $this->_fcpoCreateUserByResponse($aResponse);
        }


        $this->_oFcPoHelper->fcpoSetSessionVariable('usr', $oUser->getId());
        $this->setUser($oUser);

        return $oUser;
    }

    /**
     * Checks if user of this PayPal order already exists
     *
     * @param string $sEmail
     * @return false|string
     */
    protected function _fcpoDoesExpressUserAlreadyExist(string $sEmail): false|string
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');
        $oUser = $this->_oFcPoHelper->getFactoryObject(User::class);

        $blReturn = $oUser->fcpoDoesUserAlreadyExist($sEmail);
        if ($blReturn !== false && in_array($sPaymentId, [PayPal::PPE_EXPRESS, PayPal::PPE_V2_EXPRESS])) {
            // always using the address that has been sent by express service is mandatory
            $blReturn = false;
        }

        return $blReturn;
    }

    /**
     * Validate possibly logged-in user, comparing existing users
     *
     * @param string $sUserId
     * @param array $aResponse
     * @return User
     * @throws Exception
     */
    protected function _fcpoValidateAndGetExpressUser(string $sUserId, array $aResponse): User
    {
        $oCurrentUser = $this->getUser();

        $oUser = $this->_oFcPoHelper->getFactoryObject(User::class);
        $oUser->load($sUserId);
        $blSameUser = $this->_fcpoIsSamePayPalUser($oUser, $aResponse);
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
     * Creating PayPal delivery address
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
     * @return false|string
     */
    protected function _fcpoGetExistingPayPalAddressId(array $aResponse): false|string
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
     * Fetches streetname and number depending on payment
     *
     * @param array $aResponse
     * @param ?bool  $blShipping
     * @return array
     */
    protected function _fcpoFetchStreetAndNumber(array $aResponse, ?bool $blShipping = false): array
    {
        $sPrefix = ($blShipping) ? 'shipping' : 'billing';

        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');

        switch ($sPaymentId) {
            case PayPal::PPE_EXPRESS:
            case PayPal::PPE_V2_EXPRESS:
                $aStreetAndNumber = $this->_fcpoSplitAddress($aResponse['add_paydata[shipping_street]']);
                break;
            default:
                $aStreetAndNumber = [
                    $aResponse['add_paydata[' . $sPrefix . '_streetname]'],
                    $aResponse['add_paydata[' . $sPrefix . '_streetnumber]'],
                ];
        }

        return $aStreetAndNumber;
    }

    /**
     * Get CountryID by country code
     *
     * @param string $sCode
     * @return string
     */
    protected function _fcpoGetIdByCode(string $sCode): string
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
    protected function _fcpoGetSal(string $sFirstname): string
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
     * @return User
     */
    protected function _fcpoCreateUserByResponse(array $aResponse): User
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
    public function _fcpoGetIdByUserName(string $sUserName): string
    {
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        return $oOrder->fcpoGetIdByUserName($sUserName);
    }

    /**
     * Updates given user into basket
     *
     * @param User $oUser
     * @param string $sPaymentId
     * @return void
     */
    protected function _fcpoUpdateUserOfExpressBasket(User $oUser, string $sPaymentId): void
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
    public function fcpoGetEditAddressTargetController(): string
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');

        $aMap = [
            PayPal::PPE_EXPRESS => 'basket'
        ];

        return (isset($aMap[$sPaymentId])) ?
            $aMap[$sPaymentId] :
            'user';
    }

    /**
     * Returns target controller action if user hits the edit
     * button of billing or shipping address
     *
     * @return string
     */
    public function fcpoGetEditAddressTargetAction(): string
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');

        $aMap = [
            PayPal::PPE_EXPRESS => 'fcpoUsePayPalExpress'
        ];

        return (isset($aMap[$sPaymentId])) ?
            $aMap[$sPaymentId] :
            '';
    }

    /**
     * Template variable getter. Return if the debitnote mandate was not accepted and thus an error has to be shown.
     *
     * @return bool
     */
    public function fcpoIsMandateError(): bool
    {
        return $this->_blFcpoConfirmMandateError;
    }

    /**
     * Return last calculation values
     *
     * @param string $sParam
     * @return string
     */
    public function fcpoCalculationParameter(string $sParam): string
    {
        $aDynvalue = $this->_oFcPoHelper->fcpoGetSessionVariable('dynvalue');
        $aDynvalue = $aDynvalue ?: $this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');

        return $aDynvalue[$sParam] ?? '';
    }

    /**
     * Compares user object and api response for validating user is the same
     *
     * @param User $oUser
     * @param array $aResponse
     * @return bool
     */
    protected function _fcpoIsSamePayPalUser(User $oUser, array $aResponse): bool
    {

        return $oUser->oxuser__oxfname->value == $aResponse['add_paydata[shipping_firstname]'] ||
            $oUser->oxuser__oxlname->value == $aResponse['add_paydata[shipping_lastname]'] ||
            $oUser->oxuser__oxcity->value == $aResponse['add_paydata[shipping_city]'] ||
            stripos($aResponse['add_paydata[shipping_street]'], $oUser->oxuser__oxstreet->value) !== false;
    }

    /**
     * Extends oxid standard method _validateTermsAndConditions()
     * Validates whether necessary terms and conditions checkboxes were checked.
     *
     * @return bool
     */
    protected function _validateTermsAndConditions(): bool
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
