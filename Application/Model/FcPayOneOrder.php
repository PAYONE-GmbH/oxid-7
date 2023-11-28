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

namespace Fatchip\PayOne\Application\Model;

use Exception;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\PaymentGateway;
use OxidEsales\Eshop\Application\Model\Shop;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Application\Model\UserPayment;
use OxidEsales\Eshop\Core\Counter;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Email;
use OxidEsales\Eshop\Core\Exception\ArticleInputException;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Exception\NoArticleException;
use OxidEsales\Eshop\Core\Exception\OutOfStockException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Model\ListModel;
use OxidEsales\Eshop\Core\Str;
use OxidEsales\Eshop\Core\ViewConfig;

class FcPayOneOrder extends FcPayOneOrder_parent
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
     * Array with all response parameters from the API order request
     *
     * @var array
     */
    protected array $_aResponse;

    /**
     * Array with all request parameters from API order request
     *
     * @var array
     */
    protected array $_aRequest;

    /**
     * Flag for redirecting after save
     *
     * @var bool
     */
    protected bool $_blIsRedirectAfterSave;

    /**
     * Variable for flagging payment as payone payment
     *
     * @var bool
     */
    protected bool $_blIsPayonePayment;

    /**
     * Appointed error
     *
     * @var bool
     */
    protected bool $_blFcPayoneAppointedError;

    /**
     * List of Payment IDs which need to save workorderid
     *
     * @var array
     */
    protected array $_aPaymentsWorkorderIdSave = ['fcpopo_bill', 'fcpopo_debitnote', 'fcpopo_installment', 'fcpoklarna_invoice', 'fcpoklarna_directdebit', 'fcpoklarna_installments'];

    /**
     * List of Payment IDs which are foreseen for saving clearing reference
     *
     * @var array
     */
    protected array $_aPaymentsClearingReferenceSave = ['fcporp_bill', 'fcpopo_bill', 'fcpopo_debitnote', 'fcpopo_installment', 'fcpoklarna_invoice', 'fcpoklarna_directdebit', 'fcpoklarna_installments'];

    /**
     * List of Payment IDs which are foreseen for saving external shopid
     *
     * @var array
     */
    protected array $_aPaymentsProfileIdentSave = ['fcporp_bill'];

    /**
     * PaymentId of order
     *
     * @var string
     */
    protected string $_sFcpoPaymentId;

    /**
     * Flag for marking order as generally problematic
     *
     * @var bool
     */
    protected bool $_blOrderHasProblems = false;

    /** Flag that indicates that payone payment of this order is flagged as redirect payment
     *
     * @var bool
     */
    protected bool $_blOrderPaymentFlaggedAsRedirect;

    /**
     * Flag for finishing order completely
     *
     * @var bool
     */
    protected bool $_blFinishingSave = true;

    /**
     * Indicator if loading basket from session has been triggered
     *
     * @var bool
     */
    protected bool $_blFcPoLoadFromSession = false;


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
     * Returns user id by given username
     *
     * @param string $sUserName
     * @return string
     * @throws DatabaseConnectionException
     */
    public function fcpoGetIdByUserName(string $sUserName): string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sQuery = "SELECT oxid FROM oxuser WHERE oxusername = " . DatabaseProvider::getDb()->quote($sUserName);

        if (!$oConfig->getConfigParam('blMallUsers')) {
            $sQuery .= " AND oxshopid = '{$oConfig->getShopId()}'";
        }

        return $this->_oFcPoDb->getOne($sQuery);
    }

    /**
     * Returns salutation stored in database by firstname
     *
     * @param string $sFirstname
     * @return string
     * @throws DatabaseConnectionException
     */
    public function fcpoGetSalByFirstName(string $sFirstname): string
    {
        $sQuery = "SELECT oxsal FROM oxuser WHERE oxfname = " . DatabaseProvider::getDb()->quote($sFirstname) . " AND oxsal != '' LIMIT 1";

        return $this->_oFcPoDb->getOne($sQuery);
    }

    /**
     * Checks address database for receiving an address matching to response
     *
     * @param array $aResponse
     * @param string $sStreet
     * @param string $sStreetNr
     * @return mixed
     */
    public function fcpoGetAddressIdByResponse(array $aResponse, string $sStreet, string $sStreetNr): mixed
    {
        $sQuery = " SELECT
                        oxid
                    FROM
                        oxaddress
                    WHERE
                        oxfname = {$this->_oFcPoDb->quote($aResponse['add_paydata[shipping_firstname]'])} AND
                        oxlname = {$this->_oFcPoDb->quote($aResponse['add_paydata[shipping_lastname]'])} AND
                        oxstreet = {$this->_oFcPoDb->quote($sStreet)} AND
                        oxstreetnr = {$this->_oFcPoDb->quote($sStreetNr)} AND
                        oxcity = {$this->_oFcPoDb->quote($aResponse['add_paydata[shipping_city]'])} AND
                        oxzip = {$this->_oFcPoDb->quote($aResponse['add_paydata[shipping_zip]'])} AND
                        oxcountryid = {$this->_oFcPoDb->quote($this->fcpoGetIdByCode($aResponse['add_paydata[shipping_country]']))}";

        return $this->_oFcPoDb->getOne($sQuery);
    }

    /**
     * Returns countryid by given countrycode
     *
     * @param string $sCode
     * @return mixed
     * @throws DatabaseConnectionException
     */
    public function fcpoGetIdByCode(string $sCode): mixed
    {
        $sQuery = "SELECT oxid FROM oxcountry WHERE oxisoalpha2 = " . DatabaseProvider::getDb()->quote($sCode);
        return $this->_oFcPoDb->getOne($sQuery);
    }

    /**
     * Overrides standard oxid finalizeOrder method
     *
     * Order checking, processing and saving method.
     * Before saving performed checking if order is still not executed (checks in
     * database oxorder table for order with know ID), if yes - returns error code 3,
     * if not - loads payment data, assigns all info from basket to new oxorder object
     * and saves full order with error status. Then executes payment. On failure -
     * deletes order and returns error code 2. On success - saves order (oxorder::save()),
     * removes article from wishlist (oxorder::_updateWishlist()), updates voucher data
     * (oxorder::_markVouchers()). Finally sends order confirmation email to customer
     * (oxemail::SendOrderEMailToUser()) and shop owner (oxemail::SendOrderEMailToOwner()).
     * If this is order recalculation, skipping payment execution, marking vouchers as used
     * and sending order by email to shop owner and user
     * Mailing status (1 if OK, 0 on error) is returned.
     *
     * @param Basket $oBasket Shopping basket object
     * @param User $oUser Current user object
     * @param bool $blRecalculatingOrder Order recalculation
     *
     * @return bool|int|string
     * @throws Exception
     */
    public function finalizeOrder(Basket $oBasket, User $oUser, bool $blRecalculatingOrder = false): bool|int|string
    {
        $sPaymentId = $oBasket->getPaymentId();
        $this->_sFcpoPaymentId = $sPaymentId;
        $blPayonePayment = $this->isPayOnePaymentType($sPaymentId);

        // OXID-219 If payone method, the order will be completed by this method
        // If overloading is needed, the _fcpoFinalizeOrder have to be overloaded
        // Otherwise, the execution goes over, to the normal flow from parent class
        if ($blPayonePayment) {
            return $this->_fcpoFinalizeOrder($oBasket, $oUser, $blRecalculatingOrder);
        }

        return parent::finalizeOrder($oBasket, $oUser, $blRecalculatingOrder);
    }

    /**
     * Checks if the selected payment method for this order is a PAYONE payment method
     *
     * @param string|null $sPaymenttype payment id. Default is null
     *
     * @return bool
     */
    public function isPayOnePaymentType(string $sPaymenttype = null): bool
    {
        if (!$sPaymenttype) {
            $sPaymenttype = $this->oxorder__oxpaymenttype->value;
        }
        return $this->_fcpoIsPayonePaymentType($sPaymenttype);
    }

    /**
     * Returns wether given paymentid is of payone type
     *
     * @param string $sId
     * @param bool $blIFrame
     * @return bool
     */
    protected function _fcpoIsPayonePaymentType(string $sId, bool $blIFrame = false): bool
    {
        return $blIFrame ? FcPayOnePayment::fcIsPayOneIframePaymentType($sId) : FcPayOnePayment::fcIsPayOnePaymentType($sId);
    }

    /**
     * Payone handling on finalizing order
     *
     * @param Basket $oBasket Shopping basket object
     * @param User $oUser Current user object
     * @param bool $blRecalculatingOrder Order recalculation
     * @return bool|int|string
     * @throws Exception
     */
    public function _fcpoFinalizeOrder(Basket $oBasket, User $oUser, bool $blRecalculatingOrder): bool|int|string
    {
        $blSaveAfterRedirect = $this->_isRedirectAfterSave();

        $mRet = $this->_fcpoEarlyValidation($blSaveAfterRedirect, $oBasket, $oUser, $blRecalculatingOrder);
        if ($mRet !== null) {
            return $mRet;
        }

        // copies user info
        parent::assignUserInformation($oUser);

        // copies basket info if no basket injection or presave order is inactive
        $this->_fcpoHandleBasket($blSaveAfterRedirect, $oBasket);

        // payment information
        $oUserPayment = $this->setPayment($oBasket->getPaymentId());

        // set folder information, if order is new
        // #M575 in recalculating order case folder must be the same as it was
        if (!$blRecalculatingOrder) {
            $this->setFolder();
        }

        $mRet = $this->_fcpoExecutePayment($blSaveAfterRedirect, $oBasket, $oUserPayment, $blRecalculatingOrder);
        if ($mRet !== null) {
            return $mRet;
        }

        //saving all order data to DB
        $this->_blFinishingSave = true;
        $this->save();

        $this->_fcpoSaveAfterRedirect($blSaveAfterRedirect);

        // deleting remark info only when order is finished
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('ordrem');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('stsprotection');

        $this->updateOrderDate();

        $this->_fcpoSetOrderStatus();

        // store orderid
        $oBasket->setOrderId($this->getId());
        $this->_fcpoAddShadowBasketOrderId();

        // updating wish lists
        $this->updateWishlist($oBasket->getContents(), $oUser);

        // updating users notice list
        $this->updateNoticeList($oBasket->getContents(), $oUser);

        // marking vouchers as used and sets them to $this->_aVoucherList (will be used in order email)
        // skipping this action in case of order recalculation
        $this->_fcpoMarkVouchers($blRecalculatingOrder, $oUser, $oBasket);

        if (!$this->oxorder__oxordernr->value) {
            $this->setNumber();
        } else {
            oxNew(Counter::class)->update($this->getCounterIdent(), $this->oxorder__oxordernr->value);
        }

        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoordernotchecked');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoWorkorderId');

        // send order by email to shop owner and current user
        // skipping this action in case of order recalculation
        return $this->_fcpoFinishOrder($blRecalculatingOrder, $oUser, $oBasket, $oUserPayment);
    }

    /**
     * Returns true if this request is the return to the shop from a payment provider where the user has been
     * redirected to
     *
     * @return bool
     */
    protected function _isRedirectAfterSave(): bool
    {
        if ($this->_blIsRedirectAfterSave === null) {
            $this->_blIsRedirectAfterSave = false;

            $blUseRedirectAfterSave = (
                $this->_oFcPoHelper->fcpoGetRequestParameter('fcposuccess') &&
                $this->_oFcPoHelper->fcpoGetRequestParameter('refnr') &&
                $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoTxid')
            );

            if ($blUseRedirectAfterSave) {
                $this->_blIsRedirectAfterSave = true;
            }
        }
        return $this->_blIsRedirectAfterSave;
    }

    /**
     *
     * @param bool $blSaveAfterRedirect
     * @param Basket $oBasket
     * @param User $oUser
     * @param bool $blRecalculatingOrder
     * @return mixed
     */
    protected function _fcpoEarlyValidation(bool $blSaveAfterRedirect, Basket $oBasket, User $oUser, bool $blRecalculatingOrder): mixed
    {
        // check if this order is already stored
        $sGetChallenge = $this->_oFcPoHelper->fcpoGetSessionVariable('sess_challenge');

        $this->_blFcPoLoadFromSession = (
            $blSaveAfterRedirect &&
            !$blRecalculatingOrder &&
            $sGetChallenge &&
            $oBasket &&
            $oUser &&
            $this->checkOrderExist($sGetChallenge)
        );

        if ($blSaveAfterRedirect === false && $this->checkOrderExist($sGetChallenge)) {
            $oUtils = $this->_oFcPoHelper->fcpoGetUtils();
            $oUtils->logger('BLOCKER');
            // we might use this later, this means that somebody klicked like mad on order button
            return self::ORDER_STATE_ORDEREXISTS;
        }

        // check if basket is still the same as it was before
        if ($blSaveAfterRedirect) {
            $this->_fcCompareBasketAgainstShadowBasket($oBasket);
        }

        // if not recalculating order, use sess_challenge id, else leave old order id
        if (!$blRecalculatingOrder) {
            // use this ID
            $this->setId($sGetChallenge);

            // validating various order/basket parameters before finalizing
            if (($iOrderState = $this->validateOrder($oBasket, $oUser))) {
                return $iOrderState;
            }
        }

        return null;
    }

    /**
     * Checks if previously saved basket is still the same (valid) as it is now
     *
     * @param Basket $oBasket
     * @return void
     */
    protected function _fcCompareBasketAgainstShadowBasket(Basket $oBasket): void
    {
        $oShadowBasket = $this->fcpoGetShadowBasket();
        $blIsValid = $this->_fcpoCompareBaskets($oBasket, $oShadowBasket);
        if ($blIsValid === false) {
            $this->_fcpoMarkOrderAsProblematic();
            $this->_fcpoAddShadowBasketCheckDate();
        } else {
            $this->_fcpoDeleteShadowBasket();
        }
    }

    /**
     * Returns shadow Basket matching to sessionid
     *
     * @param bool $blByOrderId
     * @return mixed object | bool
     */
    public function fcpoGetShadowBasket(bool $blByOrderId = false): mixed
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $sSessionId = $oSession->getId();
        $oShadowBasket = false;

        $sWhere = "FCPOSESSIONID=" . $oDb->quote($sSessionId);
        if ($blByOrderId) {
            $sWhere = "OXORDERID=" . $oDb->quote($this->getId());
        }

        $sQuery = "
            SELECT
                FCPOBASKET
            FROM 
                fcposhadowbasket
            WHERE
                " . $sWhere . "
            LIMIT 1
        ";

        $sSerializedShadowBasket = $oDb->getOne($sQuery);

        if ($sSerializedShadowBasket) {
            $oShadowBasket = unserialize(base64_decode($sSerializedShadowBasket));
        }

        return $oShadowBasket;
    }

    /**
     * Compares current basket with prior saved basket for avoiding fraud
     *
     * @param Basket $oBasket
     * @param Basket $oShadowBasket
     * @return bool
     */
    protected function _fcpoCompareBaskets(Basket $oBasket, Basket $oShadowBasket): bool
    {
        $blGeneralCheck = (
            $oShadowBasket instanceof Basket &&
            $oBasket instanceof Basket
        );
        if (!$blGeneralCheck) {
            $blReturn = false;
        } else {
            // compare brut sums
            $dBruttoSumBasket = $oBasket->getBruttoSum();
            $dBruttoSumShadowBasket = $oShadowBasket->getBruttoSum();

            $blReturn = ($dBruttoSumBasket == $dBruttoSumShadowBasket);
        }

        return $blReturn;
    }

    /**
     * Mark order as problematic
     *
     * @return void
     */
    protected function _fcpoMarkOrderAsProblematic(): void
    {
        $this->_blOrderHasProblems = true;
    }

    /**
     * Adding checkdate to basket, so we can see how much time has been between
     * creating and checking the shadow basket
     *
     * @return void
     */
    protected function _fcpoAddShadowBasketCheckDate(): void
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $sSessionId = $oSession->getId();

        $sQuery = "
            UPDATE            
                fcposhadowbasket
            SET
              	FCPOCHECKED=NOW()
            WHERE
                FCPOSESSIONID=" . $oDb->quote($sSessionId) . "
            LIMIT 1
        ";
        $oDb->execute($sQuery);
    }

    /**
     * Deleting Shadow-Basket
     *
     * @return void
     */
    protected function _fcpoDeleteShadowBasket(): void
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $sSessionId = $oSession->getId();

        $sQuery = "
            DELETE FROM            
                fcposhadowbasket
            WHERE
                FCPOSESSIONID=" . $oDb->quote($sSessionId) . "
            LIMIT 1
        ";
        $oDb->execute($sQuery);
    }

    /**
     * Handles basket loading into order
     *
     * @param bool $blSaveAfterRedirect
     * @param Basket $oBasket
     * @return void
     */
    protected function _fcpoHandleBasket(bool $blSaveAfterRedirect, Basket $oBasket): void
    {
        $sGetChallenge = $this->_oFcPoHelper->fcpoGetSessionVariable('sess_challenge');
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blFCPOPresaveOrder = $oConfig->getConfigParam('blFCPOPresaveOrder');
        if ($blFCPOPresaveOrder === false || $blSaveAfterRedirect === false) {
            $this->loadFromBasket($oBasket);
        } else {
            $this->load($sGetChallenge);
        }
    }

    /**
     * Overloading of basket load method for handling
     * basket loading from session => avoiding loading it twice
     *
     * @param $oBasket
     * @return mixed
     * @see https://integrator.payone.de/jira/browse/OXID-263
     */
    public function loadFromBasket($oBasket): mixed
    {

        $sSessionChallenge =
            $this->_oFcPoHelper->fcpoGetSessionVariable('sess_challenge');

        $blTriggerLoadingFromSession = (
            $this->_blFcPoLoadFromSession &&
            $sSessionChallenge
        );

        if (!$blTriggerLoadingFromSession)
            return parent::loadFromBasket($oBasket);

        return $this->load($sSessionChallenge);
    }

    /**
     * Triggers steps to execute payment
     *
     * @param bool $blSaveAfterRedirect
     * @param Basket $oBasket
     * @param UserPayment $oUserPayment
     * @param bool $blRecalculatingOrder
     * @return mixed
     */
    protected function _fcpoExecutePayment(bool $blSaveAfterRedirect, Basket $oBasket, UserPayment $oUserPayment, bool $blRecalculatingOrder): mixed
    {
        if ($blSaveAfterRedirect === true) {
            $sRefNrCheckResult = $this->_fcpoCheckRefNr();
            $sTxid = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoTxid');

            if ($sRefNrCheckResult != '') {
                return $sRefNrCheckResult;
            }
            $this->_fcpoProcessOrder($sTxid);
        } else {
            if (!$blRecalculatingOrder) {
                $blRet = $this->executePayment($oBasket, $oUserPayment);
                if ($blRet !== true) {
                    return $blRet;
                }
            }
        }

        return null;
    }

    /**
     * Checks the reference number and returns a string in case of check failed
     *
     * @return string
     */
    protected function _fcpoCheckRefNr(): string
    {
        $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $sSessRefNr = $oPORequest->getRefNr(false, true);
        $sRequestRefNr = $this->_oFcPoHelper->fcpoGetRequestParameter('refnr');

        $blValid = ($sRequestRefNr == $sSessRefNr);

        if ($blValid) return '';

        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoRefNr');
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        return $oLang->translateString('FCPO_MANIPULATION');
    }

    /**
     * Summed steps to process a payone order
     *
     * @param string $sTxid
     * @return void
     */
    protected function _fcpoProcessOrder(string $sTxid): void
    {
        $this->_fcpoCheckTxid();
        $iOrderNotChecked = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoordernotchecked');
        if ($iOrderNotChecked != 1) {
            $iOrderNotChecked = 0;
        }
        $this->_fcpoSaveOrderValues($sTxid, $iOrderNotChecked);
        $this->_fcpoCheckUserAgent();
    }

    /**
     * Check Txid against transactionstatus table and set resulting order values
     *
     * @return bool
     */
    protected function _fcpoCheckTxid(): bool
    {
        $blAppointedError = false;
        $sTxid = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoTxid');

        $sTestOxid = '';
        if ($sTxid) {
            $sQuery = "SELECT oxid FROM fcpotransactionstatus WHERE FCPO_TXACTION = 'appointed' AND fcpo_txid = '" . $sTxid . "'";
            $sTestOxid = $this->_oFcPoDb->getOne($sQuery);
        }

        if (!$sTestOxid) {
            $blAppointedError = true;
            $this->oxorder__oxfolder = new Field('ORDERFOLDER_PROBLEMS', OxidEsales\EshopCommunity\Core\Field::T_RAW);
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $sCurrentRemark = $this->oxorder__oxremark->value;
            $sAddErrorRemark = $oLang->translateString('FCPO_REMARK_APPOINTED_MISSING');
            $sNewRemark = $sCurrentRemark . " " . $sAddErrorRemark;
            $this->oxorder__oxremark = new Field($sNewRemark, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        }
        $this->_fcpoSetAppointedError($blAppointedError);

        return $blAppointedError;
    }

    /**
     * Sets appointed error
     *
     * @param bool $blError appointed error indicator
     * @return void
     */
    protected function _fcpoSetAppointedError(bool $blError = false): void
    {
        $this->_blFcPayoneAppointedError = $blError;
    }

    /**
     * Saves payone specific orderlines
     *
     * @param string $sTxid
     * @param int $iOrderNotChecked
     * @return void
     * @throws DatabaseErrorException
     */
    protected function _fcpoSaveOrderValues(string $sTxid, int $iOrderNotChecked): void
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        if ($blPresaveOrder === true) {
            $this->oxorder__oxordernr = new Field($this->_oFcPoHelper->fcpoGetSessionVariable('fcpoOrderNr'), OxidEsales\EshopCommunity\Core\Field::T_RAW);
        }
        $this->oxorder__fcpotxid = new Field($sTxid, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcporefnr = new Field($this->_oFcPoHelper->fcpoGetRequestParameter('refnr'), OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpoauthmode = new Field($this->_oFcPoHelper->fcpoGetSessionVariable('fcpoAuthMode'), OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpomode = new Field($this->_oFcPoHelper->fcpoGetSessionVariable('fcpoMode'), OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpoordernotchecked = new Field($iOrderNotChecked, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $sWorkorderId = $this->_oFcPoHelper->fcpoGetSessionVariable('payolution_workorderid');
        if ($sWorkorderId) {
            $this->oxorder__fcpoworkorderid = new Field($sWorkorderId, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        }
        $this->_oFcPoDb->execute("UPDATE fcporefnr SET fcpo_txid = '" . $sTxid . "' WHERE fcpo_refnr = '" . $this->_oFcPoHelper->fcpoGetRequestParameter('refnr') . "'");
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoOrderNr');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoTxid');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoRefNr');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoAuthMode');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoRedirectUrl');
    }

    /**
     * Compares the HTTP user agent before and after the redirect payment method.
     * If HTTP user agent is different it checks if the remote tokens match.
     * If so, the current user agent is updated in the user session.
     *
     * @return void
     */
    protected function _fcpoCheckUserAgent(): void
    {
        $oUtils = $this->_oFcPoHelper->fcpoGetUtilsServer();

        $sAgent = $oUtils->getServerVar('HTTP_USER_AGENT');
        $sExistingAgent = $this->_oFcPoHelper->fcpoGetSessionVariable('sessionagent');
        $sAgent = $this->_fcProcessUserAgentInfo($sAgent);
        $sExistingAgent = $this->_fcProcessUserAgentInfo($sExistingAgent);

        if ($sAgent && $sAgent !== $sExistingAgent) {
            $oSession = $this->_oFcPoHelper->fcpoGetSession();
            $sInputToken = $this->_oFcPoHelper->fcpoGetRequestParameter('rtoken');
            $sToken = $oSession->getRemoteAccessToken(false);
            $blValid = $this->_fcpoValidateToken($sInputToken, $sToken);
            if ($blValid === true) {
                $this->_oFcPoHelper->fcpoSetSessionVariable("sessionagent", $oUtils->getServerVar('HTTP_USER_AGENT'));
            }
        }
    }

    /**
     * Removes MSIE(\s)?(\S)*(\s) from browser agent information
     *
     * @param string $sAgent browser user agent identifier
     *
     * @return string
     */
    protected function _fcProcessUserAgentInfo(string $sAgent): string
    {
        if ($sAgent) {
            $sAgent = Str::getStr()->preg_replace("/MSIE(\s)?(\S)*(\s)/", "", $sAgent);
        }
        return $sAgent;
    }

    /**
     * Compares tokens and returns if they are valid
     *
     * @param string $sInputToken
     * @param string $sToken
     * @return bool
     */
    protected function _fcpoValidateToken(string $sInputToken, string $sToken): bool
    {
        $blTokenEqual = !strcmp($sInputToken, $sToken);
        return $sInputToken && $blTokenEqual;
    }

    /**
     * Overrides standard oxid save method
     * Save orderarticles only when not already existing
     * Updates/inserts order object and related info to DB
     *
     * @return null
     */
    public function save()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        if ($blPresaveOrder === false || $this->isPayOnePaymentType() === false) {
            return parent::save();
        }

        if ($this->oxorder__oxshopid->value === false) {
            $oShop = $oConfig->getActiveShop();
            $this->oxorder__oxshopid = new Field($oShop->getId());
        }

        if (($blSave = parent::save())) {
            // saving order articles
            $oOrderArticles = $this->getOrderArticles();
            if ($oOrderArticles && count($oOrderArticles) > 0) {
                foreach ($oOrderArticles as $oOrderArticle) {
                    $oOrderArticle->fcpoSetFinishingSave($this->_blFinishingSave);
                    $oOrderArticle->save();
                }
            }
        }

        return $blSave;
    }

    /**
     * Assigns data, stored in oxorderarticles to oxorder object .
     *
     * @param bool $blExcludeCanceled excludes canceled items from list
     *
     * FATCHIP MOD:
     * load articles from db if order already exists
     *
     * @return ListModel
     */
    public function getOrderArticles(bool $blExcludeCanceled = false): ListModel
    {
        $sSessionChallenge =
            $this->_oFcPoHelper->fcpoGetSessionVariable('sess_challenge');

        $blSetArticlesNull = (
            $this->_blFcPoLoadFromSession &&
            $sSessionChallenge
        );

        if ($blSetArticlesNull) {
            //null trigger orderarticles getter from db
            $this->_oArticles = null;
        }

        return parent::getOrderArticles($blExcludeCanceled);
    }

    /**
     * Method triggers saving after redirect if this option has been configured
     *
     * @param bool $blSaveAfterRedirect
     * @return void
     * @throws DatabaseErrorException
     */
    protected function _fcpoSaveAfterRedirect(bool $blSaveAfterRedirect): void
    {
        if ($blSaveAfterRedirect === true) {
            $sQuery = "UPDATE fcpotransactionstatus SET fcpo_ordernr = '{$this->oxorder__oxordernr->value}' WHERE fcpo_txid = '" . $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoTxid') . "'";
            $this->_oFcPoDb->execute($sQuery);
        }
    }

    /**
     * Sets order status depending on having an appointed error
     *
     * @return void
     */
    protected function _fcpoSetOrderStatus(): void
    {
        $blOrderOk = $this->_fcpoValidateOrderAgainstProblems();

        if ($blOrderOk === true) {
            // updating order trans status (success status)
            $this->setOrderStatus('OK');
        } else {
            $this->setOrderStatus('ERROR');
        }
    }

    /**
     * Validates order for checking if there were any occurring problems
     *
     * @return bool
     */
    protected function _fcpoValidateOrderAgainstProblems(): bool
    {
        return (
            $this->_fcpoGetAppointedError() === false &&
            $this->_blOrderHasProblems === false
        );
    }

    /**
     * Returns true if appointed error occurred
     *
     * @return bool
     */
    protected function _fcpoGetAppointedError(): bool
    {
        return $this->_blFcPayoneAppointedError;
    }

    /**
     * Adds orderid to shadowbasket table, so it is possible to analyze
     * differences
     *
     * @return void
     */
    protected function _fcpoAddShadowBasketOrderId(): void
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $sSessionId = $oSession->getId();

        $sQuery = "
            UPDATE            
                fcposhadowbasket
            SET
              	OXORDERID=" . $oDb->quote($this->getId()) . "
            WHERE
                FCPOSESSIONID=" . $oDb->quote($sSessionId) . "
            LIMIT 1
        ";
        $oDb->execute($sQuery);
    }

    /**
     * Method triggers marking vouchers if order hasn't been set for recalculation
     *
     * @param bool $blRecalculatingOrder
     * @param User $oUser
     * @param Basket $oBasket
     * @return void
     */
    protected function _fcpoMarkVouchers(bool $blRecalculatingOrder, User $oUser, Basket $oBasket): void
    {
        if (!$blRecalculatingOrder) {
            $this->markVouchers($oBasket, $oUser);
        }
    }

    /**
     * Finishes order and returns state
     *
     * @param bool $blRecalculatingOrder
     * @param User $oUser
     * @param Basket $oBasket
     * @param UserPayment $oUserPayment
     * @return int
     */
    protected function _fcpoFinishOrder(bool $blRecalculatingOrder, User $oUser, Basket $oBasket, UserPayment $oUserPayment): int
    {
        if (!$blRecalculatingOrder) {
            $iRet = $this->sendOrderByEmail($oUser, $oBasket, $oUserPayment);
        } else {
            $iRet = self::ORDER_STATE_OK;
        }

        return $iRet;
    }

    /**
     * Sends clearing data mail to customer after a capture.
     * This currently is only for payment fcpoinvoice
     *
     * @return void
     */
    public function fcpoSendClearingDataAfterCapture(): void
    {
        $sPaymentId = $this->oxorder__oxpaymenttype->value;
        $sAuthMode = $this->oxorder__fcpoauthmode->value;

        $blSendMail = (
            in_array($sPaymentId, ['fcpoinvoice', 'fcpopayadvance']) &&
            $sAuthMode == 'preauthorization'
        );

        if (!$blSendMail) {
            return;
        }

        $sTo = $this->oxorder__oxbillemail->value;
        $sSubject = $this->_fcpoGetClearingDataEmailSubject();
        $sBody = $this->_fcpoGetClearingDataEmailBody();

        $oEmail = $this->_oFcPoHelper->getFactoryObject(Email::class);
        $oEmail->sendEmail($sTo, $sSubject, $sBody);
    }

    /**
     * Returns translated subject for clearing mail
     *
     * @return string
     */
    protected function _fcpoGetClearingDataEmailSubject(): string
    {
        $oLang = $this->_oFcPoHelper->getFactoryObject(Language::class);
        $oShop = $this->_oFcPoHelper->getFactoryObject(Shop::class);
        $oShop->load($this->oxorder__oxshopid->value);
        $sSubject = $oShop->oxshops__oxname->value . " - ";
        $sSubject .= $oLang->translateString('FCPO_EMAIL_CLEARING_SUBJECT') . " FcPayOneOrder.php";

        return $sSubject . $this->oxorder__oxordernr->value;
    }

    /**
     * Returns translated body content for clearing mail
     *
     * @return string
     */
    protected function _fcpoGetClearingDataEmailBody(): string
    {
        $oLang = $this->_oFcPoHelper->getFactoryObject(Language::class);
        $oShop = $this->_oFcPoHelper->getFactoryObject(Shop::class);
        $oShop->load($this->oxorder__oxshopid->value);
        $sBody = $oLang->translateString('FCPO_EMAIL_CLEARING_BODY_WELCOME');
        $sBody = str_replace('%NAME%', $this->oxorder__oxbillfname->value, $sBody);
        $sBody = str_replace('%SURNAME%', $this->oxorder__oxbilllname->value, $sBody);
        $sBody .= $oLang->translateString("FCPO_BANKACCOUNTHOLDER") . ": " . $this->getFcpoBankaccountholder() . "\n";
        $sBody .= $oLang->translateString("FCPO_EMAIL_BANK") . " FcPayOneOrder.php" . $this->getFcpoBankname() . "\n";
        $sBody .= $oLang->translateString("FCPO_EMAIL_ROUTINGNUMBER") . " FcPayOneOrder.php" . $this->getFcpoBankcode() . "\n";
        $sBody .= $oLang->translateString("FCPO_EMAIL_ACCOUNTNUMBER") . " FcPayOneOrder.php" . $this->getFcpoBanknumber() . "\n";
        $sBody .= $oLang->translateString("FCPO_EMAIL_BIC") . " FcPayOneOrder.php" . $this->getFcpoBiccode() . "\n";
        $sBody .= $oLang->translateString("FCPO_EMAIL_IBAN") . " FcPayOneOrder.php" . $this->getFcpoIbannumber() . "\n";
        $sBody .= $oLang->translateString("FCPO_EMAIL_USAGE") . ": " . $this->oxorder__fcpotxid->value . "\n";
        $sBody .= "\n\n";
        $sThankyou = $oLang->translateString('FCPO_EMAIL_CLEARING_BODY_THANKYOU');

        return $sBody . str_replace('%SHOPNAME%', $oShop->oxshops__oxname->value, $sThankyou);
    }

    /**
     * Get the bankaccount holder of this order out of the response array
     *
     * @return string
     */
    public function getFcpoBankaccountholder(): string
    {
        return $this->getResponseParameter('clearing_bankaccountholder');
    }

    /**
     * Get a certain parameter out of the response array
     *
     * @param string $sParameter
     * @return string
     */
    protected function getResponseParameter(string $sParameter): string
    {
        $aResponse = $this->getResponse();
        return ($aResponse) ? $aResponse[$sParameter] : '';
    }

    /**
     * Get the API log entry from the (pre)authorization request of this order
     *
     * @return array|null
     */
    protected function getResponse(): ?array
    {
        if ($this->_aResponse === null) {
            $sQuery = $this->_fcpoGetResponseQuery();
            $sOxidRequest = $this->_oFcPoDb->getOne($sQuery);
            if ($sOxidRequest) {
                $oRequestLog = $this->_oFcPoHelper->getFactoryObject(FcPoRequestLog::class);
                $oRequestLog->load($sOxidRequest);
                $aResponse = $oRequestLog->getResponseArray();
                if ($aResponse) {
                    $this->_aResponse = $aResponse;
                }
            }
        }
        return $this->_aResponse;
    }

    /**
     * @return string
     */
    protected function _fcpoGetResponseQuery(): string
    {
        $blFetchCaptureResponse = (
            $this->oxorder__fcpoauthmode == 'preauthorization' &&
            $this->oxorder__oxpaymenttype == 'fcpoinvoice'
        );

        if ($blFetchCaptureResponse) {
            $sWhere = "fcpo_request LIKE '%" . $this->oxorder__fcpotxid->value . "%'";
            $sAnd = "
                fcpo_requesttype = 'capture'
            ";
        } else {
            $sWhere = "fcpo_refnr = '{$this->oxorder__fcporefnr->value}' ";
            $sAnd = "
                fcpo_requesttype = 'preauthorization' OR 
                fcpo_requesttype = 'authorization'
            ";
        }

        return "
            SELECT oxid 
            FROM fcporequestlog 
            WHERE $sWhere
            AND (
                $sAnd
            )
        ";
    }

    /**
     * Get the bankname of this order out of the response array
     *
     * @return string
     */
    public function getFcpoBankname(): string
    {
        return $this->getResponseParameter('clearing_bankname');
    }

    /**
     * Get the bankcode of this order out of the response array
     *
     * @return string
     */
    public function getFcpoBankcode(): string
    {
        return $this->getResponseParameter('clearing_bankcode');
    }

    /**
     * Get the banknumber of this order out of the response array
     *
     * @return string
     */
    public function getFcpoBanknumber(): string
    {
        return $this->getResponseParameter('clearing_bankaccount');
    }

    /**
     * Get the BIC code of this order out of the response array
     *
     * @return string
     */
    public function getFcpoBiccode(): string
    {
        return $this->getResponseParameter('clearing_bankbic');
    }

    /**
     * Get the IBAN number of this order out of the response array
     *
     * @return string
     */
    public function getFcpoIbannumber(): string
    {
        return $this->getResponseParameter('clearing_bankiban');
    }

    /**
     * Checks based on the transaction status received by PAYONE whether
     * the capture request is available for this order at the moment.
     *
     * @return bool
     */
    public function allowCapture(): bool
    {
        $blReturn = true;
        if ($this->oxorder__fcpoauthmode->value == 'authorization') {
            $blReturn = false;
        }

        if ($blReturn) {
            $iCount = $this->_oFcPoDb->getOne("SELECT COUNT(*) FROM fcpotransactionstatus WHERE fcpo_txid = '{$this->oxorder__fcpotxid->value}'");
            $blReturn = !(($iCount == 0));
        }

        return $blReturn;
    }

    /**
     * Checks based on the transaction status received by PAYONE whether
     * the debit request is available for this order at the moment.
     *
     * @return bool
     */
    public function allowDebit(): bool
    {
        $blIsAuthorization =
            ($this->oxorder__fcpoauthmode->value == 'authorization');

        if ($blIsAuthorization) return true;

        $sQuery = "
            SELECT 
                COUNT(*) 
            FROM 
                fcpotransactionstatus 
            WHERE 
                fcpo_txid = '{$this->oxorder__fcpotxid->value}' AND 
                fcpo_txaction = 'appointed'
        ";

        $iCount = (int)$this->_oFcPoDb->getOne($sQuery);

        return ($iCount === 1);
    }

    /**
     * Checks based on the payment method whether
     * the settleaccount checkbox should be shown.
     *
     * @return bool
     */
    public function allowAccountSettlement(): bool
    {
        return (
            $this->oxorder__oxpaymenttype->value == 'fcpopayadvance' ||
            FcPayOnePayment::fcIsPayOneOnlinePaymentType($this->oxorder__oxpaymenttype->value)
        );
    }

    /**
     * Checks based on the selected payment method for this order whether
     * the users bank data has to be transferred for the debit request.
     *
     * @return bool
     */
    public function debitNeedsBankData(): bool
    {
        return (
            $this->oxorder__oxpaymenttype->value == 'fcpoinvoice' ||
            $this->oxorder__oxpaymenttype->value == 'fcpopayadvance' ||
            $this->oxorder__oxpaymenttype->value == 'fcpocashondel' ||
            FcPayOnePayment::fcIsPayOneOnlinePaymentType($this->oxorder__oxpaymenttype->value)
        );
    }

    /**
     * Checks based on the payment method whether
     * the detailed product list is needed.
     *
     * @return mixed
     */
    public function isDetailedProductInfoNeeded(): mixed
    {
        $blForcedByPaymentMethod = in_array(
            $this->oxorder__oxpaymenttype->value,
            ['fcpobillsafe', 'fcpoklarna', 'fcpoklarna_invoice', 'fcpoklarna_installments', 'fcpoklarna_directdebit', 'fcpo_secinvoice', 'fcporp_bill', 'fcporp_debitnote', 'fcporp_installment', 'fcpopl_secinvoice', 'fcpopl_secinstallment']
        );

        if ($blForcedByPaymentMethod) return true;

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('blFCPOSendArticlelist');
    }

    public function isCancellationReasonNeeded(): bool
    {
        return in_array(
            $this->oxorder__oxpaymenttype->value,
            [
                'fcpopl_secinvoice',
                'fcpopl_secinstallment',
            ]
        );
    }

    /**
     * Get the current sequence number of the order
     *
     * @return int
     */
    public function getSequenceNumber(): int
    {
        $iCount = $this->_oFcPoDb->getOne("SELECT MAX(fcpo_sequencenumber) FROM fcpotransactionstatus WHERE fcpo_txid = '{$this->oxorder__fcpotxid->value}'");

        return ($iCount === null) ? 0 : $iCount + 1;
    }

    /**
     * Returns shopid used for ratepay payment
     */
    public function getFcpoRatepayShopId(): string
    {
        return $this->getRequestParameter('add_paydata[shop_id]');
    }

    /**
     * @param string $sParameter
     * @return string
     */
    protected function getRequestParameter(string $sParameter): string
    {
        $aRequest = $this->getRequest();
        return (isset($aRequest[$sParameter])) ?
            $aRequest[$sParameter] : '';
    }

    /**
     * Returns request array of last authorization call
     *
     * @param array $aAcceptedStatus
     * @return array|null
     */
    protected function getRequest(array $aAcceptedStatus = ['APPROVED']): ?array
    {
        if ($this->_aRequest === null) {
            array_walk($aAcceptedStatus, function (&$sStatus) {
                $sStatus = "'" . $sStatus . "'";
            });

            $sSelect = "
                SELECT oxid 
                FROM fcporequestlog 
                WHERE fcpo_refnr = '{$this->oxorder__fcporefnr->value}' 
                AND (
                    fcpo_requesttype = 'preauthorization' OR 
                    fcpo_requesttype = 'authorization'
                )
                AND FCPO_RESPONSESTATUS IN (" . join(',', $aAcceptedStatus) . ")
                ORDER BY oxtimestamp DESC
            ";
            $sOxidRequest = $this->_oFcPoDb->getOne($sSelect);

            if ($sOxidRequest) {
                $oRequestLog = $this->_oFcPoHelper->getFactoryObject(FcPoRequestLog::class);
                $oRequestLog->load($sOxidRequest);
                $aRequest = $oRequestLog->getRequestArray();
                if ($aRequest) {
                    $this->_aRequest = $aRequest;
                }
            }
        }

        return $this->_aRequest;
    }

    /**
     * Get the capturable amount left
     * Returns order sum if the was no capture before
     * Returns order sum minus prior captures if there were captures before
     *
     * @return float|int
     */
    public function getFcpoCapturableAmount(): float|int
    {
        $oTransaction = $this->getLastStatus();
        $dReceivable = 0;
        if ($oTransaction !== false) {
            $dReceivable = $oTransaction->fcpotransactionstatus__fcpo_receivable->value;
        }
        return $this->oxorder__oxtotalordersum->value - $dReceivable;
    }

    /**
     * Get the last transaction status the shop received from PAYONE
     *
     * @return object|bool
     */
    public function getLastStatus(): object|bool
    {
        $sOxid = $this->_oFcPoDb->getOne("SELECT * FROM fcpotransactionstatus WHERE fcpo_txid = '{$this->oxorder__fcpotxid->value}' ORDER BY fcpo_sequencenumber DESC, oxtimestamp DESC");
        if ($sOxid) {
            $oStatus = $this->_oFcPoHelper->getFactoryObject(FcPoTransactionStatus::class);
            $oStatus->load($sOxid);
        }

        return (isset($oStatus)) ? $oStatus : false;
    }

    /**
     * Function whitch cheks if article stock is valid.
     * If not displays error and returns false.
     *
     * @param Basket $oBasket basket object
     *
     * @return void
     * @throws Exception
     *
     */
    public function validateStock(Basket $oBasket): void
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blReduceStockBefore = !$oConfig->getConfigParam('blFCPOReduceStock');
        $blCheckProduct = !((
            $blReduceStockBefore &&
            $this->_isRedirectAfterSave()
        ));

        if ($blCheckProduct) {
            parent::validateStock($oBasket);
        }

        foreach ($oBasket->getContents() as $key => $oContent) {
            try {
                $oProd = $oContent->getArticle($blCheckProduct);
            } catch (NoArticleException|ArticleInputException $oEx) {
                $oBasket->removeItem($key);
                throw $oEx;
            }

            if ($blCheckProduct === true) {
                // check if it's still available
                $dArtStockAmount = $oBasket->getArtStockInBasket($oProd->getId(), $key);
                $iOnStock = $oProd->checkForStock($oContent->getAmount(), $dArtStockAmount);
                if ($iOnStock !== true) {
                    $oEx = oxNew(OutOfStockException::class);
                    $oEx->setMessage('EXCEPTION_OUTOFSTOCK_OUTOFSTOCK');
                    $oEx->setArticleNr($oProd->oxarticles__oxartnum->value);
                    $oEx->setProductId($oProd->getId());
                    $oEx->setBasketIndex($key);

                    if (!is_numeric($iOnStock)) {
                        $iOnStock = 0;
                    }
                    $oEx->setRemainingAmount($iOnStock);
                    throw $oEx;
                }
            }
        }
    }

    /**
     * Returns stock of article in basket, including bundle article
     *
     * @param Basket $oBasket basket object
     * @param string $sArtId article id
     * @param string|null $sExpiredArtId item id of updated article
     *
     * @return float|int
     */
    public function fcGetArtStockInBasket(Basket $oBasket, string $sArtId, string $sExpiredArtId = null): float|int
    {
        $dArtStock = 0;

        $aContents = $oBasket->getContents();
        foreach ($aContents as $sItemKey => $oOrderArticle) {
            if ($oOrderArticle && ($sExpiredArtId == null || $sExpiredArtId != $sItemKey)) {
                $oArticle = $oOrderArticle->getArticle(true);
                if ($oArticle->getId() == $sArtId) {
                    $dArtStock += $oOrderArticle->getAmount();
                }
            }
        }

        return $dArtStock;
    }

    /**
     * Returns mandate filename if existing for this order
     *
     * @return mixed
     */
    public function fcpoGetMandateFilename(): mixed
    {
        $sOxid = $this->getId();
        $sQuery = "SELECT fcpo_filename FROM fcpopdfmandates WHERE oxorderid = '$sOxid'";
        return $this->_oFcPoDb->getOne($sQuery);
    }

    /**
     * Returns transaction status of order
     *
     * @return array
     * @throws DatabaseErrorException
     */
    public function fcpoGetStatus(): array
    {
        $sQuery = "SELECT oxid FROM fcpotransactionstatus WHERE fcpo_txid = '{$this->oxorder__fcpotxid->value}' ORDER BY fcpo_sequencenumber ASC";
        $aRows = $this->_oFcPoDb->getAll($sQuery);

        $aStatus = [];
        foreach ($aRows as $aRow) {
            $oTransactionStatus = $this->_oFcPoHelper->getFactoryObject(FcPoTransactionStatus::class);
            $sTransactionStatusOxid = (isset($aRow[0])) ? $aRow[0] : $aRow['oxid'];
            $oTransactionStatus->load($sTransactionStatusOxid);
            $aStatus[] = $oTransactionStatus;
        }

        return $aStatus;
    }

    /**
     * Returns authorization method of order
     *
     * @return string
     */
    public function getAuthorizationMethod(): string
    {
        $aRequest = $this->getRequest(['APPROVED', 'REDIRECT']);
        return (isset($aRequest['request'])) ?
            $aRequest['request'] : '';
    }

    /**
     * Method checks via current paymenttype is of payone PayPal type
     *
     * @return boolean
     */
    public function fcIsPayPalOrder(): bool
    {
        $blReturn = false;
        if ($this->oxorder__oxpaymenttype->value == 'fcpopaypal' || $this->oxorder__oxpaymenttype->value == 'fcpopaypal_express') {
            $blReturn = true;
        }
        return $blReturn;
    }

    /**
     * Handle authorization of current order
     *
     * @param bool $blReturnRedirectUrl
     * @param PaymentGateway|null $oPayGateway
     * @return ?bool
     */
    public function fcHandleAuthorization(bool $blReturnRedirectUrl = false, PaymentGateway $oPayGateway = null): ?bool
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $aDynvalueForm = $this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
        if ($this->oxorder__oxpaymenttype->value === 'fcpoklarna_directdebit' && $this->_oFcPoHelper->fcpoGetSessionVariable('klarna_authorization_token') === '') {
            $this->_oFcPoHelper->fcpoSetSessionVariable('klarna_authorization_token', $aDynvalueForm['klarna_authorization_token']);
        }
        $aDynvalue = $this->_oFcPoHelper->fcpoGetSessionVariable('dynvalue');
        $aDynvalue = $aDynvalue ?: $this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');

        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        if ($blPresaveOrder === true) {
            $sOrderNr = $this->_fcpoGetNextOrderNr();
            $this->oxorder__oxordernr = new Field($sOrderNr, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        }

        $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
        $oPayment->load($this->oxorder__oxpaymenttype->value);
        $sAuthorizationType = $oPayment->oxpayments__fcpoauthmode->value;

        $sRefNr = $oPORequest->getRefNr($this);

        $aResponse = $oPORequest->sendRequestAuthorization($sAuthorizationType, $this, $this->getOrderUser(), $aDynvalue, $sRefNr);
        $sMode = is_array($aDynvalue) ? $oPayment->fcpoGetMode($aDynvalue) : '';

        return $this->_fcpoHandleAuthorizationResponse($aResponse, $oPayGateway, $sRefNr, $sMode, $sAuthorizationType, $blReturnRedirectUrl);
    }

    /**
     * Returns new valid ordernr. Method depends on shop version
     *
     * @return string
     */
    protected function _fcpoGetNextOrderNr(): string
    {
        $oCounter = $this->_oFcPoHelper->getFactoryObject(Counter::class);
        return $oCounter->getNext($this->getCounterIdent());
    }

    /**
     * Cares about validation of authorization request
     *
     * @param array $aResponse
     * @param PaymentGateway $oPayGateway
     * @param string $sRefNr
     * @param string $sMode
     * @param string $sAuthorizationType
     * @param bool $blReturnRedirectUrl
     * @return bool|null
     */
    protected function _fcpoHandleAuthorizationResponse(array $aResponse, PaymentGateway $oPayGateway, string $sRefNr, string $sMode, string $sAuthorizationType, bool $blReturnRedirectUrl): ?bool
    {
        $mReturn = false;

        if ($aResponse['status'] == 'ERROR') {
            $this->_fcpoHandleAuthorizationError($aResponse, $oPayGateway);
        } elseif (in_array($aResponse['status'], ['APPROVED', 'PENDING'])) {
            $this->_fcpoHandleAuthorizationApproved($aResponse, $sRefNr, $sAuthorizationType, $sMode);
            $mReturn = true;
        } elseif ($aResponse['status'] == 'REDIRECT') {
            $this->_fcpoHandleAuthorizationRedirect($aResponse, $sRefNr, $sAuthorizationType, $sMode, $blReturnRedirectUrl);
        }

        return $mReturn;
    }

    /**
     * Handles case of Authorization error
     *
     * @param array $aResponse
     * @param PaymentGateway $oPayGateway
     * @return void
     */
    protected function _fcpoHandleAuthorizationError(array $aResponse, PaymentGateway $oPayGateway): void
    {
        $this->_fcpoFlagOrderPaymentAsRedirect();

        $sResponseErrorCode = trim($aResponse['errorcode']);
        $sResponseCustomerMessage = trim($aResponse['customermessage']);

        $this->_fcpoSetPayoneUserFlagsByAuthResponse($sResponseErrorCode, $sResponseCustomerMessage, $oPayGateway);
    }

    /**
     * Set flag for dynamic set as redirect payment into session
     *
     * @param bool $blFlaggedAsRedirect
     * @return void
     */
    protected function _fcpoFlagOrderPaymentAsRedirect(bool $blFlaggedAsRedirect = true): void
    {
        $this->_oFcPoHelper->fcpoSetSessionVariable('blDynFlaggedAsRedirectPayment', $blFlaggedAsRedirect);
    }

    /**
     * Adds flag to user if there is one matching
     *
     * @param string $sResponseErrorCode
     * @param string $sResponseCustomerMessage
     * @param PaymentGateway $oPayGateway
     * @return void
     */
    protected function _fcpoSetPayoneUserFlagsByAuthResponse(string $sResponseErrorCode, string $sResponseCustomerMessage, PaymentGateway $oPayGateway): void
    {
        $oUserFlag = oxNew(FcPoUserFlag::class);
        $blSuccess = $oUserFlag->fcpoLoadByErrorCode($sResponseErrorCode);

        if ($blSuccess) {
            $oUser = $this->getOrderUser();
            $oUser->fcpoAddPayoneUserFlag($oUserFlag);
        }
        $oPayGateway->fcpoSetLastErrorNr($sResponseErrorCode);
        $oPayGateway->fcpoSetLastError($sResponseCustomerMessage);
    }

    /**
     * Handles case of approved authorization
     *
     * @param array $aResponse
     * @param string $sRefNr
     * @param string $sAuthorizationType
     * @param string $sMode
     * @return void
     * @throws DatabaseErrorException
     */
    protected function _fcpoHandleAuthorizationApproved(array $aResponse, string $sRefNr, string $sAuthorizationType, string $sMode): void
    {
        $this->_fcpoFlagOrderPaymentAsRedirect();
        $iOrderNotChecked = $this->_fcpoGetOrderNotChecked();
        $sPaymentId = $this->oxorder__oxpaymenttype->value;

        $this->oxorder__fcpotxid = new Field($aResponse['txid'], OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcporefnr = new Field($sRefNr, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpoauthmode = new Field($sAuthorizationType, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpomode = new Field($sMode, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpoordernotchecked = new Field($iOrderNotChecked, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->_oFcPoDb->execute("UPDATE fcporefnr SET fcpo_txid = '{$aResponse['txid']}' WHERE fcpo_refnr = '" . $sRefNr . "'");
        if ($sPaymentId == 'fcpobarzahlen' && isset($aResponse['add_paydata[instruction_notes]'])) {
            $sBarzahlenHtml = urldecode($aResponse['add_paydata[instruction_notes]']);
            $this->_oFcPoHelper->fcpoSetSessionVariable('sFcpoBarzahlenHtml', $sBarzahlenHtml);
        }

        $this->_fcpoSaveWorkorderId($sPaymentId, $aResponse);
        $this->_fcpoSaveClearingReference($sPaymentId, $aResponse);
        $this->_fcpoSaveProfileIdent($sPaymentId, $aResponse);
        $this->save();
    }

    /**
     * Returns the numeric code which determines if order has not beeing checked
     *
     * @return int
     */
    protected function _fcpoGetOrderNotChecked(): int
    {
        $iOrderNotChecked = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoordernotchecked');
        if ($iOrderNotChecked != 1) {
            $iOrderNotChecked = 0;
        }

        return $iOrderNotChecked;
    }

    /**
     * For certain payments it's mandatory to save workorderid
     *
     * @param string $sPaymentId
     * @param array $aResponse
     * @return void
     */
    protected function _fcpoSaveWorkorderId(string $sPaymentId, array $aResponse): void
    {
        if (in_array($sPaymentId, $this->_aPaymentsWorkorderIdSave)) {
            $sWorkorderId = (
            isset($aResponse['add_paydata[workorderid]'])) ?
                $aResponse['add_paydata[workorderid]'] :
                $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoWorkorderId');

            if ($sWorkorderId) {
                $this->oxorder__fcpoworkorderid = new Field($sWorkorderId, OxidEsales\EshopCommunity\Core\Field::T_RAW);
                $this->_oFcPoHelper->fcpoDeleteSessionVariable('payolution_workorderid');
                $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoWorkorderId');
                $this->_oFcPoHelper->fcpoDeleteSessionVariable('klarna_workorderid');
            }
        }
    }

    /**
     * For certain payments it's mandatory to save clearing reference
     *
     * @param string $sPaymentId
     * @param array $aResponse
     * @return void
     */
    protected function _fcpoSaveClearingReference(string $sPaymentId, array $aResponse): void
    {
        if (in_array($sPaymentId, $this->_aPaymentsClearingReferenceSave)) {
            $sClearingReference = (isset($aResponse['add_paydata[clearing_reference]'])) ? $aResponse['add_paydata[clearing_reference]'] : false;
            if ($sClearingReference) {
                $this->oxorder__fcpoclearingreference = new Field($sClearingReference, OxidEsales\EshopCommunity\Core\Field::T_RAW);
            }
        }
    }

    /**
     * For certain payments it's mandatory to save (external) shopid/userid (e. g- ratepay payments)
     *
     * @param string $sPaymentId
     * @param array $aResponse
     * @return void
     */
    protected function _fcpoSaveProfileIdent(string $sPaymentId, array $aResponse): void
    {
        if (in_array($sPaymentId, $this->_aPaymentsProfileIdentSave)) {
            $oRatePay = oxNew(FcPoRatePay::class);
            $sProfileId = $this->_oFcPoHelper->fcpoGetSessionVariable('ratepayprofileid');
            $aProfileData = $oRatePay->fcpoGetProfileData($sProfileId);
            $sRatePayShopId = $aProfileData['shopid'];
            $this->oxorder__fcpoprofileident = new Field($sRatePayShopId, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        }
    }

    /**
     * Handles case of redirect type authorization
     *
     * @param array $aResponse
     * @param string $sRefNr
     * @param string $sAuthorizationType
     * @param string $sMode
     * @param bool $blReturnRedirectUrl
     * @return mixed
     */
    protected function _fcpoHandleAuthorizationRedirect(array $aResponse, string $sRefNr, string $sAuthorizationType, string $sMode, bool $blReturnRedirectUrl): mixed
    {
        $this->_fcpoFlagOrderPaymentAsRedirect();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oUtils = $this->_oFcPoHelper->fcpoGetUtils();
        $iOrderNotChecked = $this->_fcpoGetOrderNotChecked();
        $this->fcpoCreateShadowBasket();

        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        if ($blPresaveOrder === true) {
            $this->_blFinishingSave = false;
            $this->save();
        }

        $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoTxid', $aResponse['txid']);
        $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoAuthMode', $sAuthorizationType);
        $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoMode', $sMode);

        $this->oxorder__fcpotxid = new Field($aResponse['txid'], OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcporefnr = new Field($sRefNr, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpoauthmode = new Field($sAuthorizationType, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpomode = new Field($sMode, OxidEsales\EshopCommunity\Core\Field::T_RAW);
        $this->oxorder__fcpoordernotchecked = new Field($iOrderNotChecked, OxidEsales\EshopCommunity\Core\Field::T_RAW);

        if ($blPresaveOrder === true) {
            $this->oxorder__oxtransstatus = new Field('INCOMPLETE');
            $this->oxorder__oxfolder = new Field('ORDERFOLDER_PROBLEMS');
            $this->_blFinishingSave = false;
            $this->save();
            $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoOrderNr', $this->oxorder__oxordernr->value);
            $this->_fcpoCheckReduceBefore();
        }

        if ($blReturnRedirectUrl === true) {
            return $aResponse['redirecturl'];
        } else {
            if ($this->isPayOneIframePayment()) {
                $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoRedirectUrl', $aResponse['redirecturl']);
                $sRedirectUrl = $oConfig->getCurrentShopUrl() . 'index.php?cl=FcPayOneIframe';
            } else {
                $sRedirectUrl = $aResponse['redirecturl'];
            }
            $oUtils->redirect($sRedirectUrl, false);
        }
    }

    /**
     * Creates a copy of basket in shadow table
     *
     * @return void
     */
    public function fcpoCreateShadowBasket(): void
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $sSessionId = $oSession->getId();
        $oDb = $this->_oFcPoHelper->fcpoGetDb();

        $sQuery = "
            REPLACE INTO fcposhadowbasket
            (
              	FCPOSESSIONID,
              	OXORDERID,
              	FCPOBASKET,
              	FCPOCREATED,
              	FCPOCHECKED
            )
            VALUES
            (
              " . $oDb->quote($sSessionId) . ",
              NULL,
              '" . base64_encode(serialize($oBasket)) . "',
              NOW(),
              NULL
            )
        ";

        $oDb->execute($sQuery);
    }

    /**
     * Reduces stock of article before if its configured this way and a redirect payment has been used
     *
     * @return void
     */
    protected function _fcpoCheckReduceBefore(): void
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sPaymentId = $this->oxorder__oxpaymenttype->value;
        $blReduceStockBefore = !$oConfig->getConfigParam('blFCPOReduceStock');
        $blIsRedirectPayment = FcPayOnePayment::fcIsPayOneRedirectType($sPaymentId);

        if ($blReduceStockBefore && $blIsRedirectPayment) {
            $aOrderArticles = $this->getOrderArticles();
            foreach ($aOrderArticles as $oOrderArticle) {
                $oOrderArticle->updateArticleStock($oOrderArticle->oxorderarticles__oxamount->value * (-1), $oConfig->getConfigParam('blAllowNegativeStock'));
            }
        }
    }

    /**
     * Method validates if given payment-type is a payone iframe payment
     *
     * @param string|null $sPaymenttype
     * @return bool
     */
    public function isPayOneIframePayment(string $sPaymenttype = null): bool
    {
        if (!$sPaymenttype) {
            $sPaymenttype = $this->oxorder__oxpaymenttype->value;
        }
        return $this->_fcpoIsPayonePaymentType($sPaymenttype, true);
    }

    /**
     * Method will usually be called at the end of an order and decides whether
     * clearingdata should be offered or not
     *
     * @return bool
     */
    public function fcpoShowClearingData(): bool
    {
        $sPaymentId = $this->oxorder__oxpaymenttype->value;

        return (
            ($this->oxorder__fcpoauthmode == 'authorization' && $sPaymentId == 'fcpoinvoice') ||
            ($sPaymentId === 'fcpopayadvance')

        );
    }

}
