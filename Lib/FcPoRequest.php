<?php /** @noinspection PhpDocSignatureIsNotCompleteInspection */

/** @noinspection ALL */

namespace Fatchip\PayOne\Lib;

use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Application\Model\FcPoRatePay;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Application\Model\OrderArticleList;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Base;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;

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
 * @version       OXID eShop CEPYD
 */
class FcPoRequest extends Base
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Array or request parameters
     *
     * @var array
     */
    protected $_aParameters = [];

    /*
     * Array of valid countries for addresscheck basic
     *
     * @var array
     */
    protected $_aValidCountrys = [
        'BE',
        'DK',
        'DE',
        'FI',
        'FR',
        'IT',
        'CA',
        'LU',
        'NL',
        'NO',
        'AT',
        'PL',
        'PT',
        'SE',
        'CH',
        'SK',
        'ES',
        'CZ',
        'HU',
        'US',
    ];
    protected $_aStateNeededCountries = [
        'US',
        'CA',
        'CN',
        'JP',
        'MX',
        'BR',
        'AR',
        'ID',
        'TH',
        'IN',
    ];

    /*
     * URL of PAYONE Server API
     *
     * @var string
     */
    protected $_sApiUrl = 'https://api.pay1.de/post-gateway/';

    /*
     * URL of PAYONE Server API
     *
     * @var string
     */
    protected $_sFrontendApiUrl = 'https://secure.pay1.de/frontend/';
    protected $_aFrontendUnsetParams = [
        'mid',
        'integrator_name',
        'integrator_version',
        'solution_name',
        'solution_version',
        'ip',
        'errorurl',
        'salutation',
        'pseudocardpan',
    ];
    protected $_aFrontendHashParams = [
        'aid',
        'amount',
        'backurl',
        'clearingtype',
        'currency',
        'customerid',
        'de',
        'encoding',
        'id',
        'mode',
        'no',
        'portalid',
        'pr',
        'reference',
        'request',
        'successurl',
        'targetwindow',
        'va',
        'key'
    ];

    /**
     * Used api version
     *
     * @var string
     */
    protected $_sApiVersion = '3.10';

    /**
     * List of Ratepay related payment Ids
     *
     * @var array
     */
    protected $_aRatePayPayments = [
        'fcporp_bill',
        'fcporp_debitnote',
        'fcporp_installment',
    ];

    /**
     * Class constructor, sets all required parameters for requests.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $this->addParameter('mid', $oConfig->getConfigParam('sFCPOMerchantID')); //PayOne Merchant ID
        $this->addParameter('portalid', $oConfig->getConfigParam('sFCPOPortalID')); //PayOne Portal ID
        $this->addParameter('key', md5($oConfig->getConfigParam('sFCPOPortalKey'))); //PayOne Portal Key
        $this->addParameter('encoding', 'UTF-8'); //Encoding

        $this->addParameter('integrator_name', 'oxid');
        $this->addParameter('integrator_version', $this->_oFcPoHelper->fcpoGetIntegratorVersion());
        $this->addParameter('solution_name', 'fatchip');
        $this->addParameter('solution_version', $this->_oFcPoHelper->fcpoGetModuleVersion());
    }

    /**
     * Add/Overwrites parameter to request
     *
     * @param string $sKey               parameter key
     * @param string $sValue             parameter value
     * @param bool   $blAddAsNullIfEmpty add parameter with value NULL if empty. Default is false
     */
    public function addParameter($sKey, $sValue, $blAddAsNullIfEmpty = false)
    {
        $blSetNullForEmpty = (
            $blAddAsNullIfEmpty === true &&
            empty($sValue)
        );
        if ($blSetNullForEmpty) {
            $sValue = 'NULL';
        }

        $this->_aParameters[$sKey] = $sValue;
    }

    /**
     * Send request to PAYONE Server-API with request-type "authorization" or "preauthorization"
     *
     * @param object $oOrder    order object
     * @param object $oUser     user object
     * @param array  $aDynvalue form data
     * @param string $sRefNr    payone reference number
     *
     * @return array|false
     */
    public function sendRequestAuthorization($sType, $oOrder, $oUser, $aDynvalue, $sRefNr)
    {
        $this->addParameter('request', $sType); //Request method
        $this->addParameter('mode', $this->getOperationMode($oOrder->oxorder__oxpaymenttype->value)); //PayOne Portal Operation Mode (live or test)

        $blIsPreAuth = $sType == 'preauthorization';

        $blPayMethodIsKnown = $this->setAuthorizationParameters($oOrder, $oUser, $aDynvalue, $sRefNr, $blIsPreAuth);
        if ($blPayMethodIsKnown === true) {
            return $this->send();
        } else {
            return false;
        }
    }

    /**
     * Get get PAYONE operation mode ( live or test ) for given order
     *
     * @param string $sPaymentType
     * @param string $sType subtype for the paymentmethod ( Visa, MC, etc. ) Default is ''
     *
     * @return string
     */
    protected function getOperationMode($sPaymentType, $sType = '')
    {
        $oPayment = oxNew(Payment::class);
        $oPayment->load($sPaymentType);
        return $oPayment->fcpoGetOperationMode($sType);
    }

    /**
     * Set authorization parameters and return true if payment-method is known or false if payment-method is unknown
     *
     * @param object $oOrder    order object
     * @param object $oUser     user object
     * @param array  $aDynvalue form data
     * @param bool   $blIsPreauthorization
     * @param string $sRefNr    payone reference number
     *
     * @return bool
     */
    protected function setAuthorizationParameters($oOrder, $oUser, $aDynvalue, $sRefNr, $blIsPreauthorization = false)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account
        $this->addParameter('reference', $sRefNr);
        $this->addParameter('amount', number_format($oOrder->oxorder__oxtotalordersum->value, 2, '.', '') * 100); //Total order sum in smallest currency unit
        $this->addParameter('currency', $oOrder->oxorder__oxcurrency->value); //Currency

        $this->_addUserDataParameters($oOrder, $oUser);

        $sIp = $this->_fcpoGetRemoteAddress();
        if ($sIp != '') {
            $this->addParameter('ip', $sIp);
        }

        $blIsWalletTypePaymentWithDelAddress = ($oOrder->fcIsPayPalOrder() === true &&
            $this->_oFcPoHelper->fcpoGetConfig()->getConfigParam('blFCPOPayPalDelAddress') === true
        );

        $blIsBNPLPayment = (
            $oOrder->oxorder__oxpaymenttype->value == 'fcpopl_secinvoice'
            || $oOrder->oxorder__oxpaymenttype->value == 'fcpopl_secinstallment'
        );

        if ($oOrder->oxorder__oxdellname->value != '') {
            $oDelCountry = oxNew(Country::class);
            $oDelCountry->load($oOrder->oxorder__oxdelcountryid->value);

            $this->addParameter('shipping_firstname', $oOrder->oxorder__oxdelfname->value);
            $this->addParameter('shipping_lastname', $oOrder->oxorder__oxdellname->value);
            if ($oOrder->oxorder__oxdelcompany->value) {
                $this->addParameter('shipping_company', $oOrder->oxorder__oxdelcompany->value);
            }
            $this->addParameter('shipping_street', trim($oOrder->oxorder__oxdelstreet->value . ' ' . $oOrder->oxorder__oxdelstreetnr->value));
            if ($oOrder->oxorder__oxdeladdinfo->value) {
                $this->addParameter('shipping_addressaddition', $oOrder->oxorder__oxdeladdinfo->value);
            }
            $this->addParameter('shipping_zip', $oOrder->oxorder__oxdelzip->value);
            $this->addParameter('shipping_city', $oOrder->oxorder__oxdelcity->value);
            $this->addParameter('shipping_country', $oDelCountry->oxcountry__oxisoalpha2->value);
            if ($this->_stateNeeded($oDelCountry->oxcountry__oxisoalpha2->value)) {
                $this->addParameter('shipping_state', $this->_getShortState($oOrder->oxorder__oxdelstateid->value));
            }
        } elseif ($blIsWalletTypePaymentWithDelAddress || $blIsBNPLPayment) {
            $oDelCountry = oxNew(Country::class);
            $oDelCountry->load($oOrder->oxorder__oxbillcountryid->value);

            $this->addParameter('shipping_firstname', $oOrder->oxorder__oxbillfname->value);
            $this->addParameter('shipping_lastname', $oOrder->oxorder__oxbilllname->value);
            if ($oOrder->oxorder__oxbillcompany->value) {
                $this->addParameter('shipping_company', $oOrder->oxorder__oxbillcompany->value);
            }
            $this->addParameter('shipping_street', trim($oOrder->oxorder__oxbillstreet->value . ' ' . $oOrder->oxorder__oxbillstreetnr->value));
            if ($oOrder->oxorder__oxbilladdinfo->value) {
                $this->addParameter('shipping_addressaddition', $oOrder->oxorder__oxbilladdinfo->value);
            }
            $this->addParameter('shipping_zip', $oOrder->oxorder__oxbillzip->value);
            $this->addParameter('shipping_city', $oOrder->oxorder__oxbillcity->value);
            $this->addParameter('shipping_country', $oDelCountry->oxcountry__oxisoalpha2->value);
            if ($this->_stateNeeded($oDelCountry->oxcountry__oxisoalpha2->value)) {
                $this->addParameter('shipping_state', $this->_getShortState($oOrder->oxorder__oxbillstateid->value));
            }
        }

        $blPaymentTypeKnown = $this->setPaymentParameters($oOrder, $aDynvalue, $sRefNr);

        $blAddProductInfo = $oOrder->isDetailedProductInfoNeeded();

        if ($blAddProductInfo) {
            $this->addProductInfo($oOrder);
        }

        return $blPaymentTypeKnown;
    }

    /**
     * Add the the user information parameters
     *
     * @param object $oOrder         order object
     * @param object $oUser          user object
     * @param bool   $blIsUpdateUser is update user request? Default is false
     *
     * @return null
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     */
    protected function _addUserDataParameters($oOrder, $oUser, $blIsUpdateUser = false)
    {
        $oCountry = oxNew(Country::class);
        $oCountry->load($oOrder->oxorder__oxbillcountryid->value);

        $this->addParameter('salutation', ($oOrder->oxorder__oxbillsal->value == 'MR' ? 'Herr' : 'Frau'), $blIsUpdateUser);
        $this->addParameter('gender', ($oOrder->oxorder__oxbillsal->value == 'MR' ? 'm' : 'f'), $blIsUpdateUser);
        $this->addParameter('firstname', $oOrder->oxorder__oxbillfname->value, $blIsUpdateUser);
        $this->addParameter('lastname', $oOrder->oxorder__oxbilllname->value, $blIsUpdateUser);
        if ($blIsUpdateUser || $oOrder->oxorder__oxbillcompany->value != '') {
            $this->addParameter('company', $oOrder->oxorder__oxbillcompany->value, $blIsUpdateUser);
        }
        $this->addParameter('street', trim($oOrder->oxorder__oxbillstreet->value . ' ' . $oOrder->oxorder__oxbillstreetnr->value), $blIsUpdateUser);
        if ($blIsUpdateUser || $oOrder->oxorder__oxbilladdinfo->value != '') {
            $this->addParameter('addressaddition', $oOrder->oxorder__oxbilladdinfo->value, $blIsUpdateUser);
        }
        $this->addParameter('zip', $oOrder->oxorder__oxbillzip->value, $blIsUpdateUser);
        $this->addParameter('city', $oOrder->oxorder__oxbillcity->value, $blIsUpdateUser);
        $this->addParameter('country', $oCountry->oxcountry__oxisoalpha2->value, $blIsUpdateUser);
        if ($this->_stateNeeded($oCountry->oxcountry__oxisoalpha2->value)) {
            $this->addParameter('state', $this->_getShortState($oOrder->oxorder__oxbillstateid->value));
        }
        $this->addParameter('email', $oOrder->oxorder__oxbillemail->value, $blIsUpdateUser);
        if ($blIsUpdateUser || $oOrder->oxorder__oxbillfon->value != '') {
            $this->addParameter('telephonenumber', $oOrder->oxorder__oxbillfon->value, $blIsUpdateUser);
        }

        if ((in_array($oOrder->oxorder__oxpaymenttype->value, ['fcpoklarna', 'fcpoklarna_invoice', 'fcpoklarna_installments', 'fcpoklarna_directdebit'])
                && in_array($oCountry->oxcountry__oxisoalpha2->value, ['DE', 'NL', 'AT'])) || ($blIsUpdateUser || ($oUser->oxuser__oxbirthdate != '0000-00-00' && $oUser->oxuser__oxbirthdate != ''))
        ) {
            $this->addParameter('birthday', str_ireplace('-', '', $oUser->oxuser__oxbirthdate->value), $blIsUpdateUser);
        }
        if (in_array($oOrder->oxorder__oxpaymenttype->value, ['fcpoklarna', 'fcpoklarna_invoice', 'fcpoklarna_installments', 'fcpoklarna_directdebit'])) {
            if ($blIsUpdateUser || $oUser->oxuser__fcpopersonalid->value != '') {
                $this->addParameter('personalid', $oUser->oxuser__fcpopersonalid->value, $blIsUpdateUser);
            }
        }
        $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr(), $blIsUpdateUser);
        if ($blIsUpdateUser || $oOrder->oxorder__oxbillustid->value != '') {
            $this->addParameter('vatid', $oOrder->oxorder__oxbillustid->value, $blIsUpdateUser);
        }
    }

    protected function _stateNeeded($sIso2Country)
    {
        if (in_array($sIso2Country, $this->_aStateNeededCountries)) {
            return true;
        }
        return false;
    }

    /**
     * @throws DatabaseConnectionException
     */
    protected function _getShortState($sStateId)
    {
        if ($this->_oFcPoHelper->fcpoGetIntShopVersion() >= 4800) {
            $oDb = DatabaseProvider::getDb();
            $sQuery = "SELECT OXISOALPHA2 FROM oxstates WHERE oxid = " . $oDb->quote($sStateId) . " LIMIT 1";
            $sStateId = $oDb->getOne($sQuery);
        }
        return $sStateId;
    }

    /**
     * @return mixed
     */
    protected function _fcpoGetRemoteAddress()
    {
        $oUtilsServer = $this->_oFcPoHelper->fcpoGetUtilsServer();
        return $oUtilsServer->getRemoteAddress();
    }

    /**
     * Set payment parameters and return true if payment-method is known or false if payment-method is unknown
     *
     * @param object $oOrder    order object
     * @param array  $aDynvalue form data
     * @param string $sRefNr    payone reference number
     *
     * @return bool
     */
    protected function setPaymentParameters($oOrder, $aDynvalue, $sRefNr)
    {
        $blAddRedirectUrls = false;
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;

        switch ($sPaymentId) {
            case 'fcpocreditcard':
                $blAddRedirectUrls = $this->_setPaymentParamsCC($aDynvalue);
                break;
            case 'fcpocashondel':
                $this->addParameter('clearingtype', 'cod'); //Payment method
                $this->addParameter('shippingprovider', 'DHL');
                break;
            case 'fcpodebitnote':
                $blAddRedirectUrls = $this->_setPaymentParamsDebitNote($aDynvalue);
                break;
            case 'fcpopayadvance':
                $this->addParameter('clearingtype', 'vor'); //Payment method
                break;
            case 'fcpoinvoice':
                $this->addParameter('clearingtype', 'rec'); //Payment method
                break;
            case 'fcpo_sofort':
                $this->addParametersOnlineSofort($oOrder, $aDynvalue);
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_eps':
                $this->addParametersOnlineEps($aDynvalue);
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_pf_finance':
                $this->addParametersOnlinePostFinance();
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_pf_card':
                $this->addParametersOnlinePostFinanceCard();
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_ideal':
                $this->addParametersOnlineIdeal($aDynvalue);
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_p24':
                $this->addParametersOnlineP24();
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_bancontact':
                $this->addParametersOnlineBancontact($oOrder);
                $blAddRedirectUrls = true;
                break;
            case 'fcpopaypal':
            case 'fcpopaypal_express':
                $blAddRedirectUrls = $this->_setPaymentParamsPayPal($oOrder, $sRefNr);
                break;
            case 'fcpoklarna':
                $this->addParameter('clearingtype', 'fnc'); //Payment method
                $this->addParameter('financingtype', 'KLV');
                break;
            case 'fcpoklarna_invoice':
            case 'fcpoklarna_installments':
            case 'fcpoklarna_directdebit':
                $blAddRedirectUrls = $this->_setPaymentParamsKlarna($oOrder);
                break;
            case 'fcpobarzahlen':
                $this->addParameter('clearingtype', 'csh'); //Payment method
                $this->addParameter('cashtype', 'BZN');
                $this->addParameter('api_version', '3.10');
                break;
            case 'fcpopo_bill':
            case 'fcpopo_debitnote':
            case 'fcpopo_installment':
                $blAddRedirectUrls = $this->_fcpoAddPayolutionParameters($oOrder);
                break;
            case 'fcporp_bill':
            case 'fcporp_debitnote':
            case 'fcporp_installment':
                $blAddRedirectUrls = $this->_fcpoAddRatePayParameters($oOrder, $aDynvalue);
                break;
            case 'fcpo_secinvoice':
                $blAddRedirectUrls = $this->_fcpoAddSecInvoiceParameters($oOrder);
                break;
            case 'fcpopl_secinvoice':
                $this->_fcpoAddBNPLSecInvoiceParameters($oOrder, $aDynvalue);
                break;
            case 'fcpopl_secinstallment':
                $this->_fcpoAddBNPLSecInstallmentParameters($oOrder, $aDynvalue);
                break;
            case 'fcpo_alipay':
                $this->addParameter('clearingtype', 'wlt'); //Payment method
                $this->addParameter('wallettype', 'ALP');
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_trustly':
                $this->fcpoAddParametersOnlineTrustly($oOrder, $aDynvalue);
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_wechatpay':
                $this->addParameter('clearingtype', 'wlt'); //Payment method
                $this->addParameter('wallettype', 'WCP');
                $blAddRedirectUrls = true;
                break;
            case 'fcpo_apple_pay':
                $tokenData = $oSession = $this->_oFcPoHelper->fcpoGetSessionVariable('applePayTokenData');

                $this->addParameter('clearingtype', 'wlt');
                $this->addParameter('wallettype', 'APL');
                $this->addParameter('cardtype', $tokenData['creditCardType']);

                $this->addParameter('add_paydata[paymentdata_token_version]', $tokenData['paydata']['paymentdata_token_version']);
                $this->addParameter('add_paydata[paymentdata_token_data]', $tokenData['paydata']['paymentdata_token_data']);
                $this->addParameter('add_paydata[paymentdata_token_signature]', $tokenData['paydata']['paymentdata_token_signature']);
                $this->addParameter('add_paydata[paymentdata_token_ephemeral_publickey]', $tokenData['paydata']['paymentdata_token_ephemeral_publickey']);
                $this->addParameter('add_paydata[paymentdata_token_publickey_hash]', $tokenData['paydata']['paymentdata_token_publickey_hash']);
                $this->addParameter('add_paydata[paymentdata_token_transaction_id]', $tokenData['paydata']['paymentdata_token_transaction_id']);
                break;
            default:
                return false;
        }

        if ($blAddRedirectUrls === true) {
            $this->_addRedirectUrls('payment', $sRefNr);
        }
        return true;
    }

    /**
     * Set payment params for creditcard
     *
     * @param array $aDynvalue
     * @return boolean
     */
    protected function _setPaymentParamsCC($aDynvalue)
    {
        $this->addParameter('clearingtype', 'cc'); //Payment method
        $this->addParameter('pseudocardpan', $aDynvalue['fcpo_pseudocardpan']);
        // Override mode for creditcard-type
        $this->addParameter('mode', $aDynvalue['fcpo_ccmode']);
        $this->addParameter('cardholder', $aDynvalue['fcpo_kkcardholder']);

        return true;
    }

    /**
     * Set payment params for debitnote
     *
     * @param array $aDynvalue
     * @return boolean
     */
    protected function _setPaymentParamsDebitNote($aDynvalue)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blFCPODebitBICMandatory = $oConfig->getConfigParam('blFCPODebitBICMandatory');

        $this->addParameter('clearingtype', 'elv'); //Payment method
        $this->addParameter('bankcountry', $aDynvalue['fcpo_elv_country']);

        $blBICConfirmed = (
            (
                isset($aDynvalue['fcpo_elv_bic']) &&
                $aDynvalue['fcpo_elv_bic'] != ''
            ) ||
            !$blFCPODebitBICMandatory
        );

        if (isset($aDynvalue['fcpo_elv_iban']) && $aDynvalue['fcpo_elv_iban'] != '' && $blBICConfirmed) {
            $this->addParameter('iban', $aDynvalue['fcpo_elv_iban']);
            if ($blFCPODebitBICMandatory) {
                $this->addParameter('bic', $aDynvalue['fcpo_elv_bic']);
            }
        } elseif (isset($aDynvalue['fcpo_elv_ktonr']) && $aDynvalue['fcpo_elv_ktonr'] != '' && isset($aDynvalue['fcpo_elv_blz']) && $aDynvalue['fcpo_elv_blz'] != '') {
            $this->addParameter('bankaccount', $aDynvalue['fcpo_elv_ktonr']);
            $this->addParameter('bankcode', $aDynvalue['fcpo_elv_blz']);
        }

        $aMandate = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoMandate');
        if ($aMandate && array_key_exists('mandate_identification', $aMandate) !== false && $aMandate['mandate_status'] == 'pending') {
            $this->addParameter('mandate_identification', $aMandate['mandate_identification']);
        }

        return false;
    }

    /**
     * Add parameters needed for sofort
     *
     * @param $oOrder
     * @param $aDynvalue
     * @return void
     */
    protected function addParametersOnlineSofort($oOrder, $aDynvalue)
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'PNT');

        $blUseDeprecatedAccountData = (
            isset($aDynvalue['fcpo_ou_ktonr']) &&
            $aDynvalue['fcpo_ou_ktonr'] != '' &&
            isset($aDynvalue['fcpo_ou_blz']) &&
            $aDynvalue['fcpo_ou_blz'] != ''
        );

        $blUseSepaData = (
            isset($aDynvalue['fcpo_ou_iban']) &&
            $aDynvalue['fcpo_ou_iban'] != '' &&
            isset($aDynvalue['fcpo_ou_bic']) &&
            $aDynvalue['fcpo_ou_bic'] != ''
        );

        if ($blUseDeprecatedAccountData) {
            $this->addParameter('bankaccount', $aDynvalue['fcpo_ou_ktonr']);
            $this->addParameter('bankcode', $aDynvalue['fcpo_ou_blz']);
        } elseif ($blUseSepaData) {
            $this->addParameter('iban', $aDynvalue['fcpo_ou_iban']);
            $this->addParameter('bic', $aDynvalue['fcpo_ou_bic']);
        }

        $oBillCountry = oxNew(Country::class);
        $oBillCountry->load($oOrder->oxorder__oxbillcountryid->value);
        $this->addParameter('bankcountry', $oBillCountry->oxcountry__oxisoalpha2->value);
    }

    /**
     * Add parameters needed for eps
     *
     * @param $aDynvalue
     * @return void
     */
    protected function addParametersOnlineEps($aDynvalue)
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'EPS');
        $this->addParameter('bankcountry', 'AT');
        $this->addParameter('bankgrouptype', $aDynvalue['fcpo_so_bankgrouptype_eps']);
    }

    /**
     * Add parameters needed for post finance financing
     *
     * @return void
     */
    protected function addParametersOnlinePostFinance()
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'PFF');
        $this->addParameter('bankcountry', 'CH');
    }

    /**
     * Add parameters needed for post finance card
     *
     * @return void
     */
    protected function addParametersOnlinePostFinanceCard()
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'PFC');
        $this->addParameter('bankcountry', 'CH');
    }

    /**
     * Add parameters needed for Ideal
     *
     * @param $aDynvalue
     * @return void
     */
    protected function addParametersOnlineIdeal($aDynvalue)
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'IDL');
        $this->addParameter('bankcountry', 'NL');
        $this->addParameter('bankgrouptype', $aDynvalue['fcpo_so_bankgrouptype_idl']);
    }

    /**
     * Add parameters needed for P24
     *
     * @return void
     */
    protected function addParametersOnlineP24()
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'P24');
        $this->addParameter('bankcountry', 'PL');
    }

    /**
     * Add parameters needed for Bancontact
     *
     * @param $oOrder
     * @return void
     */
    protected function addParametersOnlineBancontact($oOrder)
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'BCT');
        $oBillCountry = oxNew(Country::class);
        $oBillCountry->load($oOrder->oxorder__oxbillcountryid->value);
        $this->addParameter('bankcountry', $oBillCountry->oxcountry__oxisoalpha2->value);
    }

    /**
     * Set payment params paypal
     *
     * @param object $oOrder
     * @param string $sRefNr
     * @return boolean
     */
    protected function _setPaymentParamsPayPal($oOrder, $sRefNr)
    {
        $this->addParameter('clearingtype', 'wlt'); //Payment method
        $this->addParameter('wallettype', 'PPE');
        $this->addParameter('narrative_text', 'Ihre Bestellung Nr. ' . $sRefNr . ' bei ' . $this->_oFcPoHelper->fcpoGetShopName());

        if ($oOrder->oxorder__oxpaymenttype->value == 'fcpopaypal_express') {
            $this->addParameter('workorderid', $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoWorkorderId'));
        }

        return true;
    }

    /**
     * Set payment params for klarna.
     *
     * @param $oOrder
     * @return bool
     */
    protected function _setPaymentParamsKlarna($oOrder)
    {
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        $sKlarnaAuthToken =
            $this->_oFcPoHelper->fcpoGetSessionVariable('klarna_authorization_token');
        $this->addParameter('add_paydata[authorization_token]', $sKlarnaAuthToken);
        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('financingtype', $this->_fcpoGetKlarnaFinancingType($sPaymentId));
        $this->addDeliveryAddressParams(true);

        if ($oOrder->oxorder__oxbillcompany->value) {
            $this->addParameter(
                'add_paydata[organization_registry_id]',
                $oOrder->oxorder__oxustid->value
            );
            $this->addParameter('add_paydata[organization_entity_type]', 'OTHER');
        }

        return true;
    }

    /**
     * Returning klarna financingtype by paymentid
     *
     * @param string $sPaymentId
     * @return string
     */
    protected function _fcpoGetKlarnaFinancingType($sPaymentId)
    {
        $aMap = ['fcpoklarna_installments' => 'KIS', 'fcpoklarna_invoice' => 'KIV', 'fcpoklarna_directdebit' => 'KDD'];

        return (isset($aMap[$sPaymentId])) ? $aMap[$sPaymentId] : '';
    }

    /**
     * Add shipping params received by current set delivery address
     *
     * @param bool $blFallbackBillAddress
     * @return void
     */
    public function addDeliveryAddressParams($blFallbackBillAddress = false)
    {
        $sDelAddressId = $this->_oFcPoHelper->fcpoGetSessionVariable('deladrid');
        $oAddress = $this->_oFcPoHelper->getFactoryObject(Address::class);
        $sKey = 'shipping';
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        $oParamsParser = $this->_oFcPoHelper->getFactoryObject(FcPoParamsParser::class);

        if (!$oAddress->load($sDelAddressId)) {
            if (!$blFallbackBillAddress) {
                return;
            }

            $oAddress = $oUser;
            $sKey = 'bill';
        }

        $aMap = ['countryid' => ['bill' => 'oxuser__oxcountryid', 'shipping' => 'oxaddress__oxcountryid'], 'shipping_firstname' => ['bill' => 'oxuser__oxfname', 'shipping' => 'oxaddress__oxfname'], 'shipping_lastname' => ['bill' => 'oxuser__oxlname', 'shipping' => 'oxaddress__oxlname'], 'shipping_company' => ['bill' => 'oxuser__oxcompany', 'shipping' => 'oxaddress__oxcompany'], 'shipping_street' => ['bill' => 'oxuser__oxstreet', 'shipping' => 'oxaddress__oxstreet'], 'shipping_streetnr' => ['bill' => 'oxuser__oxstreetnr', 'shipping' => 'oxaddress__oxstreetnr'], 'shipping_zip' => ['bill' => 'oxuser__oxzip', 'shipping' => 'oxaddress__oxzip'], 'shipping_city' => ['bill' => 'oxuser__oxcity', 'shipping' => 'oxaddress__oxcity'], 'stateid' => ['bill' => 'oxuser__oxstateid', 'shipping' => 'oxaddress__oxstateid'], 'shipping_title' => ['bill' => 'oxuser__oxsal', 'shipping' => 'oxaddress__oxsal'], 'shipping_telephonenumber' => ['bill' => 'oxuser__oxfon', 'shipping' => 'oxaddress__oxfon']];

        $oDelCountry = $this->_oFcPoHelper->getFactoryObject(Country::class);
        $oDelCountry->load($oAddress->{$aMap['countryid'][$sKey]}->value);
        $this->addParameter('shipping_firstname', $oAddress->{$aMap['shipping_firstname'][$sKey]}->value);
        $this->addParameter('shipping_lastname', $oAddress->{$aMap['shipping_lastname'][$sKey]}->value);
        # TODO: may be the reason why doesnt work
        if ($oAddress->{$aMap['shipping_company'][$sKey]}->value) {
            $this->addParameter('shipping_company', $oAddress->{$aMap['shipping_company'][$sKey]}->value);
        }
        $this->addParameter('shipping_street', trim($oAddress->{$aMap['shipping_street'][$sKey]}->value . ' ' . $oAddress->{$aMap['shipping_streetnr'][$sKey]}->value));
        $this->addParameter('shipping_zip', $oAddress->{$aMap['shipping_zip'][$sKey]}->value);
        $this->addParameter('shipping_city', $oAddress->{$aMap['shipping_city'][$sKey]}->value);
        $this->addParameter('shipping_country', $oDelCountry->oxcountry__oxisoalpha2->value);
        if ($this->_stateNeeded($oDelCountry->oxcountry__oxisoalpha2->value)) {
            $this->addParameter(
                'shipping_state',
                $this->_getShortState($oAddress->{$aMap['stateid'][$sKey]}->value)
            );
        }

        $sShippingTitle =
            $oParamsParser->fcpoGetTitle($oAddress->{$aMap['shipping_title'][$sKey]}->value);

        $this->addParameter('add_paydata[shipping_title]', $sShippingTitle);
        $this->addParameter('add_paydata[shipping_telephonenumber]', $oAddress->{$aMap['shipping_telephonenumber'][$sKey]}->value);
        $this->addParameter('add_paydata[shipping_email]', $oUser->oxuser__oxusername->value);
    }

    /**
     * Adds needed parameters for payolution
     *
     * @param Order $oOrder
     * @return bool
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     */
    protected function _fcpoAddPayolutionParameters($oOrder)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        $oUser = $oOrder->getOrderUser();
        $sWorkorderId = $this->_oFcPoHelper->fcpoGetSessionVariable('payolution_workorderid');
        $aBankData = $this->_oFcPoHelper->fcpoGetSessionVariable('payolution_bankdata');
        $sInstallmentDuration = $this->_oFcPoHelper->fcpoGetSessionVariable('payolution_installment_duration');
        $sFieldNameAddition = str_replace("fcpopo_", "", $sPaymentId);

        $this->addParameter('clearingtype', 'fnc');
        $sPaymentType = $this->_fcpoGetPayolutionPaymentTypeById($sPaymentId);
        $sFinancignType = $this->_fcpoGetFinancingTypeByPaymentId($sPaymentId);
        $this->addParameter('financingtype', $sFinancignType);
        $this->addParameter('add_paydata[payment_type]', $sPaymentType);
        $this->addParameter('api_version', '3.10');
        $this->addParameter('mode', $this->getOperationMode($oOrder->oxorder__oxpaymenttype->value));

        $this->_fcpoAddPayolutionUserData($oUser, $sPaymentId);

        if ($sWorkorderId !== null) {
            $this->addParameter('workorderid', $sWorkorderId);
        }

        $blValidBankData = (
            isset($aBankData) &&
            is_array($aBankData) &&
            count($aBankData) == 3 &&
            $aBankData['fcpo_payolution_' . $sFieldNameAddition . '_accountholder'] &&
            $aBankData['fcpo_payolution_' . $sFieldNameAddition . '_iban'] &&
            $aBankData['fcpo_payolution_' . $sFieldNameAddition . '_bic']
        );

        if ($blValidBankData) {
            $this->addParameter('iban', $aBankData['fcpo_payolution_' . $sFieldNameAddition . '_iban']);
            $this->addParameter('bic', $aBankData['fcpo_payolution_' . $sFieldNameAddition . '_bic']);
        }

        $this->addParameter('encoding', 'UTF-8');

        $sIp = $this->_fcpoGetRemoteAddress();
        if ($sIp != '') {
            $this->addParameter('ip', $sIp);
        }

        $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr());

        if ($sInstallmentDuration) {
            $this->addParameter('add_paydata[installment_duration]', $sInstallmentDuration);
        }

        $this->_oFcPoHelper->fcpoDeleteSessionVariable('payolution_workorderid');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('payolution_bankdata');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('payolution_installment_duration');

        return false;
    }

    /**
     * Returns matching payolution payment type for given paymentid
     *
     * @param string $sPaymentId
     * @return string
     */
    protected function _fcpoGetPayolutionPaymentTypeById($sPaymentId)
    {
        $aPayolutionPaymentMap = ['fcpopo_bill' => 'Payolution-Invoicing', 'fcpopo_debitnote' => 'Payolution-Debit', 'fcpopo_installment' => 'Payolution-Installment'];

        if (isset($aPayolutionPaymentMap[$sPaymentId])) {
            $sPaymentType = $aPayolutionPaymentMap[$sPaymentId];
        } else {
            $sPaymentType = '';
        }

        return $sPaymentType;
    }

    /**
     * Method returns matching financing type for a given payment id
     *
     * @param string $sPaymentId
     * @return string
     */
    protected function _fcpoGetFinancingTypeByPaymentId($sPaymentId)
    {
        $aMap = ['fcpopo_bill' => 'PYV', 'fcpopo_debitnote' => 'PYD', 'fcpopo_installment' => 'PYS', 'fcporp_bill' => 'RPV', 'fcporp_debitnote' => 'RPD', 'fcporp_installment' => 'RPS'];

        $blPaymentIdMatch = isset($aMap[$sPaymentId]);

        $sReturn = '';
        if ($blPaymentIdMatch) {
            $sReturn = $aMap[$sPaymentId];
        }

        return $sReturn;
    }

    /**
     * Adds userdata by offering a user object
     *
     * @param object $oUser
     * @param string $sPaymentId
     * @return void
     */
    protected function _fcpoAddPayolutionUserData($oUser, $sPaymentId)
    {
        $this->addParameter('email', $oUser->oxuser__oxusername->value);
        $this->addParameter('firstname', $oUser->oxuser__oxfname->value);
        $this->addParameter('lastname', $oUser->oxuser__oxlname->value);
        $this->addParameter('street', $oUser->oxuser__oxstreet->value . " " . $oUser->oxuser__oxstreetnr->value); // and number
        $this->addParameter('zip', $oUser->oxuser__oxzip->value);
        $this->addParameter('city', $oUser->oxuser__oxcity->value);
        $blAddCompanyData = $this->_fcpoCheckAddCompanyData($oUser, $sPaymentId);
        if ($blAddCompanyData) {
            $this->addParameter('company', $oUser->oxuser__oxcompany->value);
            $this->addParameter('add_paydata[company_uid]', $oUser->oxuser__oxustid->value);
            $this->addParameter('add_paydata[b2b]', 'yes');
        }

        if ($oUser->oxuser__oxbirthdate->value != '0000-00-00') {
            $sBirthday = str_replace('-', '', $oUser->oxuser__oxbirthdate->value);
            $this->addParameter('birthday', $sBirthday);
        }

        $sCountry = '';
        $oCountry = oxNew(Country::class);
        if ($oCountry->load($oUser->oxuser__oxcountryid->value)) {
            $sCountry = $oCountry->oxcountry__oxisoalpha2->value;
        }
        $this->addParameter('country', strtoupper($sCountry));
    }

    /**
     * Returns if company data should be added to call deepending on settings and payment type
     *
     * @param User   $oUser
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoCheckAddCompanyData($oUser, $sPaymentId)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blB2BModeActive = $oConfig->getConfigParam('blFCPOPayolutionB2BMode');
        $blValidPaymentForCompanyData = in_array($sPaymentId, ['fcpopo_bill']);
        return ($blB2BModeActive && $oUser->oxuser__oxcompany->value && $blValidPaymentForCompanyData);
    }

    /**
     * Method adds all bunch of ratepay-params
     *
     * @param Order $oOrder
     * @param array $aDynvalue
     * @return false => no redirect params
     */
    protected function _fcpoAddRatePayParameters($oOrder, $aDynvalue)
    {
        // needed objects and data
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oRatePay = oxNew(FcPoRatePay::class);
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        $oUser = $oOrder->getOrderUser();
        $sProfileId = $this->_oFcPoHelper->fcpoGetSessionVariable('ratepayprofileid');
        $aProfileData = $oRatePay->fcpoGetProfileData($sProfileId);
        $sRatePayShopId = $aProfileData['shopid'];
        $sDeviceFingerprint = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoRatepayDeviceFingerPrint');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoRatepayDeviceFingerPrint');
        $sFinancignType = $this->_fcpoGetFinancingTypeByPaymentId($sPaymentId);
        $oCur = $oConfig->getActShopCurrencyObject();
        $sCountry = '';
        $oCountry = oxNew(Country::class);
        if ($oCountry->load($oUser->oxuser__oxcountryid->value)) {
            $sCountry = $oCountry->oxcountry__oxisoalpha2->value;
        }
        $oCountry = oxNew(Country::class);
        $sShippingCountry = '';
        if ($oCountry->load($oOrder->oxuser__oxdelcountryid->value)) {
            $sShippingCountry = $oCountry->oxcountry__oxisoalpha2->value;
        }
        if (!$sShippingCountry) {
            $sShippingCountry = $sCountry;
        }

        $sShippingFirstName = ($oOrder->oxorder__oxdelfname->value) ? $oOrder->oxorder__oxdelfname->value : $oUser->oxuser__oxfname->value;
        $sShippingLastName = ($oOrder->oxorder__oxdellname->value) ? $oOrder->oxorder__oxdellname->value : $oUser->oxuser__oxlname->value;
        $sShippingStreet = ($oOrder->oxorder__oxdelstreet->value) ? $oOrder->oxorder__oxdelstreet->value . " " . $oOrder->oxorder__oxdelstreetnr->value : $oUser->oxuser__oxstreet->value . ' ' . $oUser->oxuser__oxstreetnr->value;
        $sShippingZip = ($oOrder->oxorder__oxdelzip->value) ? $oOrder->oxorder__oxdelzip->value : $oUser->oxuser__oxzip->value;
        $sShippingCity = ($oOrder->oxorder__oxdelcity->value) ? $oOrder->oxorder__oxdelcity->value : $oUser->oxuser__oxcity->value;

        $this->addParameter('encoding', 'UTF-8');

        // set common params
        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('currency', $oCur->name);
        $this->addParameter('financingtype', $sFinancignType);

        // set ratepay params
        $this->addParameter('add_paydata[shop_id]', $sRatePayShopId);
        $this->addParameter('add_paydata[device_token]', $sDeviceFingerprint);
        $this->addParameter('add_paydata[customer_allow_credit_inquiry]', 'yes');
        $this->addParameter('add_paydata[vat_id]', $oOrder->oxorder__oxbillustid->value);
        $this->addParameter('customer_is_present', 'yes');
        $this->addParameter('api_version', '3.10');
        $this->addParameter('param', 'session-1');
        $this->addParameter('shop_id', $sRatePayShopId);
        $this->addParameter('data', $sRatePayShopId);
        $this->addParameter('email', $oUser->oxuser__oxusername->value);
        $this->addParameter('firstname', $oUser->oxuser__oxfname->value);
        $this->addParameter('lastname', $oUser->oxuser__oxlname->value);
        $this->addParameter('street', $oUser->oxuser__oxstreet->value . ' ' . $oUser->oxuser__oxstreetnr->value);
        $this->addParameter('zip', $oUser->oxuser__oxzip->value);
        $this->addParameter('city', $oUser->oxuser__oxcity->value);
        $this->addParameter('company', $oUser->oxuser__oxcompany->value);
        $this->addParameter('shipping_firstname', $sShippingFirstName);
        $this->addParameter('shipping_lastname', $sShippingLastName);
        $this->addParameter('shipping_street', $sShippingStreet);
        $this->addParameter('shipping_zip', $sShippingZip);
        $this->addParameter('shipping_city', $sShippingCity);
        $this->addParameter('shipping_country', strtoupper($sShippingCountry));

        if ($sPaymentId == 'fcporp_debitnote') {
            $this->addParameter('iban', $aDynvalue['fcpo_ratepay_debitnote_iban']);
            $this->addParameter('bic', $aDynvalue['fcpo_ratepay_debitnote_bic']);
        }

        if ($sPaymentId == 'fcporp_installment') {
            if ($aDynvalue['fcporp_installment_settlement_type'] == 'debit') {
                $this->addParameter('iban', $aDynvalue['fcpo_ratepay_installment_iban']);
                $this->addParameter('add_paydata[debit_paytype]', 'DIRECT-DEBIT');
            } else {
                $this->addParameter('add_paydata[debit_paytype]', 'BANK-TRANSFER');
            }

            $iInstallmentAmount = number_format($aDynvalue['fcporp_installment_amount'], 2, '.', '') * 100;
            $iInstallmentLastAmount = number_format($aDynvalue['fcporp_installment_last_amount'], 2, '.', '') * 100;
            $iInterestRate = number_format($aDynvalue['fcporp_installment_interest_rate'], 2, '.', '') * 100;
            $iTotalAmount = number_format($aDynvalue['fcporp_installment_total_amount'], 2, '.', '') * 100;

            $this->addParameter('add_paydata[installment_number]', $aDynvalue['fcporp_installment_number']);
            $this->addParameter('add_paydata[installment_amount]', $iInstallmentAmount);
            $this->addParameter('add_paydata[last_installment_amount]', $iInstallmentLastAmount);
            $this->addParameter('add_paydata[interest_rate]', $iInterestRate);
            $this->addParameter('add_paydata[amount]', $iTotalAmount);
        }

        $sWorkorderId = $this->_oFcPoHelper->fcpoGetSessionVariable('ratepay_workorderid');
        if ($sWorkorderId !== null) {
            $this->addParameter('workorderid', $sWorkorderId);
        }

        $this->_fcpoAddBasketItemsFromSession();

        return false;
    }

    /**
     * Adding products from basket session into call
     * Adding products from basket session into call
     *
     * @param string $sDeliverySetId
     * @return void
     * @return object
     */
    protected function _fcpoAddBasketItemsFromSession($sDeliverySetId = false)
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $iIndex = 1;
        foreach ($oBasket->getContents() as $oBasketItem) {
            $oArticle = $oBasketItem->getArticle();
            $sArticleIdent =
                ($oArticle->oxarticles__oxean->value) ?
                    $oArticle->oxarticles__oxean->value :
                    $oArticle->oxarticles__oxartnum->value;
            $this->addParameter('it[' . $iIndex . ']', 'goods');
            $this->addParameter('id[' . $iIndex . ']', $sArticleIdent);
            $this->addParameter('pr[' . $iIndex . ']', $this->_fcpoGetCentPrice($oBasketItem));
            $this->addParameter('no[' . $iIndex . ']', $oBasketItem->getAmount());
            $this->addParameter('de[' . $iIndex . ']', $oBasketItem->getTitle());
            $this->addParameter('va[' . $iIndex . ']', $this->_fcpoGetCentPrice($oBasketItem->getPrice()->getVat()));
            $iIndex++;
        }

        if ($sDeliverySetId) {
            $oBasket->setShipping($sDeliverySetId);
            $oDeliveryCosts = $oBasket->fcpoCalcDeliveryCost();
            $oBasket->setCost('oxdelivery', $oDeliveryCosts);
        }

        $oLang = $this->_oFcPoHelper->fcpoGetLang();

        $sDeliveryCosts = $this->_fcpoFetchCostsFromBasket($oBasket, 'oxdelivery');
        $sDeliveryCosts = (double)str_replace(',', '.', $sDeliveryCosts);
        if ($sDeliveryCosts > 0) {
            $this->addParameter('it[' . $iIndex . ']', 'shipment');
            $this->addParameter('id[' . $iIndex . ']', 'delivery');
            $this->addParameter('pr[' . $iIndex . ']', $this->_fcpoGetCentPrice($sDeliveryCosts));
            $this->addParameter('no[' . $iIndex . ']', '1');
            $this->addParameter('de[' . $iIndex . ']', $oLang->translateString('FCPO_SHIPPINGCOST', null, false));
            $this->addParameter('va[' . $iIndex . ']', $this->_fcpoFetchVatCostsFromBasket($oBasket, 'oxdelivery'));
            $iIndex++;
        }

        $sWrappingCosts = $this->_fcpoFetchCostsFromBasket($oBasket, 'oxwrapping');
        $sWrappingCosts = (double)str_replace(',', '.', $sWrappingCosts);
        if ($sWrappingCosts > 0) {
            $this->addParameter('it[' . $iIndex . ']', 'goods');
            $this->addParameter('id[' . $iIndex . ']', 'wrapping');
            $this->addParameter('pr[' . $iIndex . ']', $this->_fcpoGetCentPrice($sWrappingCosts));
            $this->addParameter('no[' . $iIndex . ']', '1');
            $this->addParameter('de[' . $iIndex . ']', $oLang->translateString('FCPO_WRAPPING', null, false));
            $this->addParameter('va[' . $iIndex . ']', '0');
            $iIndex++;
        }

        $sWrappingCosts = $this->_fcpoFetchCostsFromBasket($oBasket, 'oxgiftcard');
        $sWrappingCosts = (double)str_replace(',', '.', $sWrappingCosts);
        if ($sWrappingCosts > 0) {
            $this->addParameter('it[' . $iIndex . ']', 'goods');
            $this->addParameter('id[' . $iIndex . ']', 'giftcard');
            $this->addParameter('pr[' . $iIndex . ']', $this->_fcpoGetCentPrice($sWrappingCosts));
            $this->addParameter('no[' . $iIndex . ']', '1');
            $this->addParameter('de[' . $iIndex . ']', $oLang->translateString('FCPO_GIFTCARD', null, false));
            $this->addParameter('va[' . $iIndex . ']', $this->_fcpoFetchVatCostsFromBasket($oBasket, 'oxgiftcard') * 100);
            $iIndex++;
        }

        $sPaymentCosts = $this->_fcpoFetchCostsFromBasket($oBasket, 'oxpayment');
        $sPaymentCosts = (double)str_replace(',', '.', $sPaymentCosts);
        if ($sPaymentCosts != 0) {
            $sPayDesc = '';
            if ($sPaymentCosts > 0) {
                $sPayDesc .= $oLang->translateString('FCPO_SURCHARGE', null, false);
            } else {
                $sPayDesc .= $oLang->translateString('FCPO_DEDUCTION', null, false);
            }
            $sPayDesc .= ' ' . str_replace(':', '', $oLang->translateString('FCPO_PAYMENTTYPE', null, false));
            $this->addParameter('it[' . $iIndex . ']', 'handling');
            $this->addParameter('id[' . $iIndex . ']', 'payment');
            $this->addParameter('pr[' . $iIndex . ']', $this->_fcpoGetCentPrice($sPaymentCosts));
            $this->addParameter('no[' . $iIndex . ']', '1');
            $this->addParameter('de[' . $iIndex . ']', $sPayDesc);
            $this->addParameter('va[' . $iIndex . ']', $this->_fcpoFetchVatCostsFromBasket($oBasket, 'oxpayment'));
            $iIndex++;
        }

        foreach ($oBasket->getVouchers() as $oVoucher) {
            $this->addParameter('it[' . $iIndex . ']', 'voucher');
            $this->addParameter('id[' . $iIndex . ']', $oVoucher->sVoucherNr);
            $this->addParameter('pr[' . $iIndex . ']', $this->_fcpoGetCentPrice($oVoucher->dVoucherdiscount * -1));
            $this->addParameter('no[' . $iIndex . ']', '1');
            $this->addParameter('de[' . $iIndex . ']', $oLang->translateString('FCPO_VOUCHER', null, false));
            $this->addParameter('va[' . $iIndex . ']', '0');
            $iIndex++;
        }
        // discounts
        $aDiscounts = is_null($oBasket->getDiscounts()) ? [] : $oBasket->getDiscounts();
        foreach ($aDiscounts as $oDiscount) {
            $this->addParameter('it[' . $iIndex . ']', 'voucher');
            $this->addParameter('id[' . $iIndex . ']', 'discount');
            $this->addParameter('pr[' . $iIndex . ']', $this->_fcpoGetCentPrice($oDiscount->dDiscount * -1));
            $this->addParameter('no[' . $iIndex . ']', '1');
            $this->addParameter('de[' . $iIndex . ']', $oLang->translateString('FCPO_DISCOUNT', null, false));
            $this->addParameter('va[' . $iIndex . ']', '0');
            $iIndex++;
        }

        return $oBasket;
    }

    /**
     * Item price in smallest available unit
     *
     * @param double| BasketItem $mValue
     * @return float|int
     */
    protected function _fcpoGetCentPrice(float|BasketItem $mValue): float|int
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        if ($mValue instanceof BasketItem) {
            $oPrice = $mValue->getPrice();
            $dBruttoPricePosSum = $oPrice->getBruttoPrice();
            $dAmount = $mValue->getAmount();
            $dBruttoPrice = round($dBruttoPricePosSum / $dAmount, 2);
        } else {
            $dBruttoPrice = round($mValue, 2);
        }

        $oCur = $oConfig->getActShopCurrencyObject();
        $dFactor = (double)pow(10, $oCur->decimal);

        $dReturnPrice = $dBruttoPrice * $dFactor;


        return $this->_fcpoCutDecimalPlaces($dReturnPrice);
    }

    /**
     * Remove all decimal places
     * Typecast to int was used before, but that returned wrong results in some cases
     *
     * @param float $dValue
     * @return int
     */
    protected function _fcpoCutDecimalPlaces($dValue)
    {
        if (strpos($dValue, '.') !== false) {
            $aExplode = explode(".", $dValue);
            return $aExplode[0];
        }
        return $dValue;
    }

    /**
     * Returns delivery costs of given basket object
     *
     * @param $oBasket
     * @param $costType // e.g. oxdelivery, oxwrapping, oxgiftcard
     * @return mixed float|string
     */
    protected function _fcpoFetchCostsFromBasket($oBasket, $costType)
    {
        $costs = $oBasket->getCosts($costType);
        if ($costs === null) {
            return 0.0;
        }

        return $costs->getBruttoPrice();
    }

    /**
     * Returns delivery costs of given basket object
     *
     * @param $oBasket
     * @param $costType // e.g. oxdelivery, oxwrapping, oxgiftcard
     * @return mixed float|string
     */
    protected function _fcpoFetchVatCostsFromBasket($oBasket, $costType)
    {
        $vatCosts = $oBasket->getCosts($costType);
        if ($vatCosts === null) {
            return 0.0;
        }

        return $vatCosts->getVat();
    }

    /**
     * Adds additional parameters for secure invoice payment rec/POV
     *
     * @param $oOrder
     * @return  boolean
     */
    protected function _fcpoAddSecInvoiceParameters($oOrder)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        $sSecinvoicePortalId = $oConfig->getConfigParam('sFCPOSecinvoicePortalId');
        $sSecinvoicePortalKeyHash = md5($oConfig->getConfigParam('sFCPOSecinvoicePortalKey'));
        $this->addParameter('portalid', $sSecinvoicePortalId);
        $this->addParameter('key', $sSecinvoicePortalKeyHash);

        $this->addParameter('clearingtype', 'rec');
        $this->addParameter('clearingsubtype', 'POV');

        $blIsB2B = $this->_fcpoIsOrderB2B($oOrder);
        $sBusinessRelation = ($blIsB2B) ? 'b2b' : 'b2c';
        $this->addParameter('businessrelation', $sBusinessRelation);

        return true;
    }

    /**
     * Method that determines if order is B2B
     *
     * @return bool
     */
    protected function _fcpoIsOrderB2B($oOrder)
    {
        return (bool)$oOrder->oxorder__oxbillcompany->value;
    }

    /**
     * Adds additional parameters for BNPL secure invoice payment fnc/PIV
     *
     * @param       $oOrder
     * @param array $aDynvalue
     * @return  boolean
     */
    protected function _fcpoAddBNPLSecInvoiceParameters($oOrder, $aDynvalue = [])
    {
        $this->_fcpoAddBNPLPortalParameters();

        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('financingtype', 'PIV');

        $blIsB2B = $this->_fcpoIsOrderB2B($oOrder);
        $sBusinessRelation = ($blIsB2B) ? 'b2b' : 'b2c';
        $this->addParameter('businessrelation', $sBusinessRelation);

        if (isset($aDynvalue['fcpopl_device_token'])) {
            $this->addParameter('add_paydata[device_token]', $aDynvalue['fcpopl_device_token']);
        }

        return true;
    }

    /**
     * Adds specific portal/key information for BNPL
     */
    protected function _fcpoAddBNPLPortalParameters()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        $sPortalId = $oConfig->getConfigParam('sFCPOPLPortalId');
        $sPortalKeyHash = md5($oConfig->getConfigParam('sFCPOPLPortalKey'));
        $this->addParameter('portalid', $sPortalId);
        $this->addParameter('key', $sPortalKeyHash);
    }

    /**
     * Adds additional parameters for BNPL secure installment payment fnc/PIN
     *
     * @param       $oOrder
     * @param array $aDynvalue
     * @return  boolean
     */
    protected function _fcpoAddBNPLSecInstallmentParameters($oOrder, $aDynvalue = [])
    {
        $this->_fcpoAddBNPLPortalParameters();

        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('financingtype', 'PIN');

        $blIsB2B = $this->_fcpoIsOrderB2B($oOrder);
        $sBusinessRelation = ($blIsB2B) ? 'b2b' : 'b2c';
        $this->addParameter('businessrelation', $sBusinessRelation);

        if (isset($aDynvalue['fcpopl_secinstallment_iban'])) {
            $this->addParameter('iban', $aDynvalue['fcpopl_secinstallment_iban']);
        }

        if (isset($aDynvalue['fcpopl_secinstallment_account_holder'])) {
            $this->addParameter('bankaccountholder', $aDynvalue['fcpopl_secinstallment_account_holder']);
        }

        if (isset($aDynvalue['fcpopl_device_token'])) {
            $this->addParameter('add_paydata[device_token]', $aDynvalue['fcpopl_device_token']);
        }

        if (isset($aDynvalue['fcpopl_secinstallment_plan'])) {
            $this->addParameter('add_paydata[installment_option_id]', $aDynvalue['fcpopl_secinstallment_plan']);
        }

        $sWorkorderId = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpopl_secinstallment_workorderid');
        if ($sWorkorderId !== null) {
            $this->addParameter('workorderid', $sWorkorderId);
        }

        return true;
    }

    /**
     * Add parameters needed for Bancontact
     *
     * @param $oOrder
     * @param $aDynvalue
     * @return void
     */
    protected function fcpoAddParametersOnlineTrustly($oOrder, $aDynvalue)
    {
        $this->addParameter('clearingtype', 'sb'); //Payment method
        $this->addParameter('onlinebanktransfertype', 'TRL');

        $blUseSepaData = (
            isset($aDynvalue['fcpo_ou_iban']) &&
            $aDynvalue['fcpo_ou_iban'] != '' &&
            isset($aDynvalue['fcpo_ou_bic']) &&
            $aDynvalue['fcpo_ou_bic'] != ''
        );

        if ($blUseSepaData) {
            $this->addParameter('iban', $aDynvalue['fcpo_ou_iban']);
            $this->addParameter('bic', $aDynvalue['fcpo_ou_bic']);
        }

        $oBillCountry = oxNew(Country::class);
        $oBillCountry->load($oOrder->oxorder__oxbillcountryid->value);
        $this->addParameter('bankcountry', $oBillCountry->oxcountry__oxisoalpha2->value);
    }

    /**
     * Adding redirect urls
     *
     * @param       $sAbortClass
     * @param bool  $sRefNr
     * @param mixed $mRedirectFunction
     * @param bool  $sToken
     * @param bool  $sDeliveryMD5
     * @return void
     */
    protected function _addRedirectUrls($sAbortClass, $sRefNr = false, $mRedirectFunction = false, $sToken = false, $sDeliveryMD5 = false)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $sShopURL = $oConfig->getCurrentShopUrl();

        $sRToken = '';
        if ($this->_oFcPoHelper->fcpoGetIntShopVersion() >= 4310) {
            $sRToken = '&rtoken=' . $oSession->getRemoteAccessToken();
        }

        $sSid = $oSession->sid(true);
        if ($sSid != '') {
            $sSid = '&' . $sSid;
        }

        $sAddParams = '';

        if ($sRefNr) {
            $sAddParams .= '&refnr=' . $sRefNr;
        }

        if (is_string($mRedirectFunction)) {
            $sAddParams .= '&fnc=' . $mRedirectFunction;
        } else {
            $sAddParams .= '&fnc=execute';
        }


        if ($sDeliveryMD5) {
            $sAddParams .= '&sDeliveryAddressMD5=' . $sDeliveryMD5;
        } elseif ($this->_oFcPoHelper->fcpoGetRequestParameter('sDeliveryAddressMD5')) {
            $sAddParams .= '&sDeliveryAddressMD5=' . $this->_oFcPoHelper->fcpoGetRequestParameter('sDeliveryAddressMD5');
        }

        $blDownloadableProductsAgreement = $this->_oFcPoHelper->fcpoGetRequestParameter('oxdownloadableproductsagreement');
        if ($blDownloadableProductsAgreement) {
            $sAddParams .= '&fcdpa=1'; // rewrite for oxdownloadableproductsagreement-param because of length-restriction
        }

        $blServiceProductsAgreement = $this->_oFcPoHelper->fcpoGetRequestParameter('oxserviceproductsagreement');
        if ($blServiceProductsAgreement) {
            $sAddParams .= '&fcspa=1'; // rewrite for oxserviceproductsagreement-param because of length-restriction
        }

        if (!$sToken) {
            $sToken = $this->_oFcPoHelper->fcpoGetRequestParameter('stoken');
        }

        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sPaymentErrorTextParam = "&payerrortext=" . urlencode($oLang->translateString('FCPO_PAY_ERROR_REDIRECT', null, false));
        $sPaymentErrorParam = '&payerror=-20'; // see source/modules/fc/fcpayone/out/blocks/fcpo_payment_errors.tpl
        $sSuccessUrl = $sShopURL . 'index.php?cl=order&fcposuccess=1&ord_agb=1&stoken=' . $sToken . $sSid . $sAddParams . $sRToken;
        $sErrorUrl = $sShopURL . 'index.php?type=error&cl=' . $sAbortClass . $sRToken . $sPaymentErrorParam . $sPaymentErrorTextParam;
        $sBackUrl = $sShopURL . 'index.php?type=cancel&cl=' . $sAbortClass . $sRToken;

        $this->addParameter('successurl', $sSuccessUrl);
        $this->addParameter('errorurl', $sErrorUrl);
        $this->addParameter('backurl', $sBackUrl);
    }

    /**
     * Add product information for module invoicing
     *
     * @param object     $oOrder order object
     * @param array|bool $aPositions
     * @param bool       $blDebit
     *
     * @return null
     * @throws DatabaseConnectionException
     */
    public function addProductInfo($oOrder, $aPositions = false, $blDebit = false)
    {
        $dAmount = 0;

        /** @var OrderArticleList $aOrderArticleListe */
        $aOrderArticleListe = $oOrder->getOrderArticles();
        $i = 1;

        /** @var OrderArticle $oOrderarticle */
        foreach ($aOrderArticleListe->getArray() as $oOrderarticle) {
            if ($aPositions === false || array_key_exists($oOrderarticle->getId(), $aPositions) !== false) {
                if ($aPositions !== false && array_key_exists($oOrderarticle->getId(), $aPositions) !== false) {
                    $dItemAmount = $aPositions[$oOrderarticle->getId()]['amount'];
                } else {
                    $dItemAmount = $oOrderarticle->oxorderarticles__oxamount->value;
                }

                $dPrice = $this->fcpoGetPosPr(
                    $oOrderarticle->oxorderarticles__oxbprice->value,
                    $oOrder->oxorder__oxpaymenttype->value,
                    $blDebit
                );

                $this->addParameter('id[' . $i . ']', $oOrderarticle->oxorderarticles__oxartnum->value);
                $this->addParameter('pr[' . $i . ']', number_format($dPrice, 2, '.', '') * 100);
                $dAmount += $oOrderarticle->oxorderarticles__oxbprice->value * $dItemAmount;
                $this->addParameter('it[' . $i . ']', 'goods');
                $this->addParameter('no[' . $i . ']', $dItemAmount);
                $this->addParameter('de[' . $i . ']', $oOrderarticle->oxorderarticles__oxtitle->value);
                $this->addParameter('va[' . $i . ']', number_format($oOrderarticle->oxorderarticles__oxvat->value * 100, 0, '.', ''));
                $i++;
            }
        }

        $sQuery = "SELECT IF(SUM(fcpocapturedamount) = 0, 1, 0) AS b FROM oxorderarticles WHERE oxorderid = '{$oOrder->getId()}' GROUP BY oxorderid";
        $blFirstCapture = (bool)DatabaseProvider::getDb()->getOne($sQuery);

        if ($aPositions === false || $blFirstCapture === true || $blDebit === true) {
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            if ($oOrder->oxorder__oxdelcost->value != 0 && ($aPositions === false || ($blDebit === false || array_key_exists('oxdelcost', $aPositions) !== false))) {
                $sDelDesc = '';
                if ($oOrder->oxorder__oxdelcost->value > 0) {
                    $sDelDesc .= $oLang->translateString('FCPO_SURCHARGE', null, false);
                } else {
                    $sDelDesc .= $oLang->translateString('FCPO_DEDUCTION', null, false);
                }
                $sDelDesc .= ' ' . str_replace(':', '', $oLang->translateString('FCPO_SHIPPINGCOST', null, false));

                $dPrice = $this->fcpoGetPosPr(
                    $oOrder->oxorder__oxdelcost->value,
                    $oOrder->oxorder__oxpaymenttype->value,
                    $blDebit
                );

                $this->addParameter('id[' . $i . ']', 'delivery');
                $this->addParameter('pr[' . $i . ']', number_format($dPrice, 2, '.', '') * 100);
                $dAmount += $oOrder->oxorder__oxdelcost->value;
                $this->addParameter('it[' . $i . ']', 'shipment');
                $this->addParameter('no[' . $i . ']', 1);
                $this->addParameter('de[' . $i . ']', $sDelDesc);
                $this->addParameter('va[' . $i . ']', number_format($oOrder->oxorder__oxdelvat->value * 100, 0, '.', ''));
                $i++;
            }
            if ($oOrder->oxorder__oxpaycost->value != 0 && ($aPositions === false || ($blDebit === false || array_key_exists('oxpaycost', $aPositions) !== false))) {
                $sPayDesc = '';
                if ($oOrder->oxorder__oxpaycost->value > 0) {
                    $sPayDesc .= $oLang->translateString('FCPO_SURCHARGE', null, false);
                } else {
                    $sPayDesc .= $oLang->translateString('FCPO_DEDUCTION', null, false);
                }
                $sPayDesc .= ' ' . str_replace(':', '', $oLang->translateString('FCPO_PAYMENTTYPE', null, false));

                $dPrice = $this->fcpoGetPosPr(
                    $oOrder->oxorder__oxpaycost->value,
                    $oOrder->oxorder__oxpaymenttype->value,
                    $blDebit
                );

                $this->addParameter('id[' . $i . ']', 'payment');
                $this->addParameter('pr[' . $i . ']', number_format($dPrice, 2, '.', '') * 100);
                $dAmount += $oOrder->oxorder__oxpaycost->value;
                $this->addParameter('it[' . $i . ']', 'handling');
                $this->addParameter('no[' . $i . ']', 1);
                $this->addParameter('de[' . $i . ']', $sPayDesc);
                $this->addParameter('va[' . $i . ']', number_format($oOrder->oxorder__oxpayvat->value * 100, 0, '.', ''));
                $i++;
            }
            if ($oOrder->oxorder__oxwrapcost->value != 0 && ($aPositions === false || ($blDebit === false || array_key_exists('oxwrapcost', $aPositions) !== false))) {

                $dPrice = $this->fcpoGetPosPr(
                    $oOrder->oxorder__oxwrapcost->value,
                    $oOrder->oxorder__oxpaymenttype->value,
                    $blDebit
                );

                $this->addParameter('id[' . $i . ']', 'wrapping');
                $this->addParameter('pr[' . $i . ']', number_format($dPrice, 2, '.', '') * 100);
                $dAmount += $oOrder->oxorder__oxwrapcost->value;
                $this->addParameter('it[' . $i . ']', 'goods');
                $this->addParameter('no[' . $i . ']', 1);
                $this->addParameter('de[' . $i . ']', $oLang->translateString('FCPO_WRAPPING', null, false));
                // Workaround for wrong vat: oxid saves 18.95... use 0 instead
                $this->addParameter('va[' . $i . ']', '0');
                $i++;
            }
            if ($oOrder->oxorder__oxgiftcardcost->value != 0 && ($aPositions === false || ($blDebit === false || array_key_exists('oxgiftcardcost', $aPositions) !== false))) {

                $dPrice = $this->fcpoGetPosPr(
                    $oOrder->oxorder__oxgiftcardcost->value,
                    $oOrder->oxorder__oxpaymenttype->value,
                    $blDebit
                );

                $this->addParameter('id[' . $i . ']', 'giftcard');
                $this->addParameter('pr[' . $i . ']', number_format($dPrice, 2, '.', '') * 100);
                $dAmount += $oOrder->oxorder__oxgiftcardcost->value;
                $this->addParameter('it[' . $i . ']', 'goods');
                $this->addParameter('no[' . $i . ']', 1);
                $this->addParameter('de[' . $i . ']', $oLang->translateString('FCPO_GIFTCARD', null, false));
                $this->addParameter('va[' . $i . ']', number_format($oOrder->oxorder__oxgiftcardvat->value * 100, 0, '.', ''));
                $i++;
            }
            $oSession = $this->_oFcPoHelper->fcpoGetSession();
            $oBasket = $oSession->getBasket();
            if ($oBasket && count($oBasket->getVouchers()) > 0) {
                foreach ($oBasket->getVouchers() as $oVoucher) {
                    $this->addParameter('it[' . $i . ']', 'voucher');
                    $this->addParameter('id[' . $i . ']', $oVoucher->sVoucherNr);
                    $this->addParameter('pr[' . $i . ']', $this->_fcpoGetCentPrice($oVoucher->dVoucherdiscount * -1));
                    $this->addParameter('no[' . $i . ']', '1');
                    $this->addParameter('de[' . $i . ']', $oLang->translateString('FCPO_VOUCHER', null, false));
                    $this->addParameter('va[' . $i . ']', '0');
                    $i++;
                }
            } elseif ($oOrder->oxorder__oxvoucherdiscount->value != 0 && ($aPositions === false || ($blDebit === false || array_key_exists('oxvoucherdiscount', $aPositions) !== false))) {
                $this->addParameter('id[' . $i . ']', 'voucher');
                $this->addParameter('pr[' . $i . ']', $oOrder->oxorder__oxvoucherdiscount->value * -100);
                $dAmount += ($oOrder->oxorder__oxvoucherdiscount->value * -1);
                $this->addParameter('it[' . $i . ']', 'voucher');
                $this->addParameter('no[' . $i . ']', 1);
                $this->addParameter('de[' . $i . ']', $oLang->translateString('FCPO_VOUCHER', null, false));
                $this->addParameter('va[' . $i . ']', '0');
                $i++;
            }
            if ($oOrder->oxorder__oxdiscount->value != 0 && ($aPositions === false || ($blDebit === false || array_key_exists('oxdiscount', $aPositions) !== false))) {
                $this->addParameter('id[' . $i . ']', 'discount');
                $this->addParameter('pr[' . $i . ']', round($oOrder->oxorder__oxdiscount->value, 2) * -100);
                $dAmount += (round($oOrder->oxorder__oxdiscount->value, 2) * -1);
                $this->addParameter('it[' . $i . ']', 'voucher');
                $this->addParameter('no[' . $i . ']', 1);
                $this->addParameter('de[' . $i . ']', $oLang->translateString('FCPO_DISCOUNT', null, false));
                $this->addParameter('va[' . $i . ']', '0');
            }
        }
        return $dAmount;
    }

    /**
     * Returns the price as negative if situation meets the criteria
     *
     * @param double $dInitialPr original price
     * @param string $sPaymentId payment method
     * @param bool   $blDebit
     * @return double
     */
    protected function fcpoGetPosPr($dInitialPr, $sPaymentId, $blDebit = false)
    {
        if (!$blDebit || !in_array($sPaymentId, ['fcpopl_secinvoice', 'fcpopl_secinstallment'])) {
            return $dInitialPr;
        }

        return -abs($dInitialPr);
    }

    /**
     * Send the previously prepared request, log request and response into the database and return the response
     *
     * @return array;
     */
    protected function send($blOnlyGetUrl = false)
    {
        ksort($this->_aParameters);

        $iErrorNumber = '';
        $sErrorString = '';

        if ($this->getParameter('mid') === false || $this->getParameter('portalid') === false
            || $this->getParameter('key') === false || $this->getParameter('mode') === false
        ) {
            $aOutput['errormessage'] = "Payone API Setup Data not complete (API-URL, MID, AID, PortalID, Key, Mode)";
            return $aOutput;
        }

        $sRequestUrl = '';
        foreach ($this->_aParameters as $sKey => $sValue) {
            if (is_array($sValue)) {
                foreach ($sValue as $i => $val1) {
                    $sRequestUrl .= "&" . $sKey . "[" . $i . "]=" . urlencode($val1);
                }
            } else {
                $sRequestUrl .= "&" . $sKey . "=" . urlencode($sValue);
            }
        }
        $sRequestUrl = $this->_sApiUrl . "?" . substr($sRequestUrl, 1);

        if ($blOnlyGetUrl === true) {
            return $sRequestUrl;
        }

        $aUrlArray = parse_url($sRequestUrl);

        $aResponse = $this->_getResponseForParsedRequest($aUrlArray);

        if (is_array($aResponse)) {
            $aOutput = $this->_getResponseOutput($aResponse);
            $aOutput = $this->_addMappedErrorIfAvailable($aOutput);
        }

        $sResponse = serialize($aOutput);
        $this->_logRequest($sResponse, $aOutput['status']);

        return $aOutput;
    }

    /**
     * Get parameter from request or return false if parameter was not set
     *
     * @param string $sKey parameter key
     *
     * @return string
     */
    public function getParameter($sKey)
    {
        if (array_key_exists($sKey, $this->_aParameters)) {
            return $this->_aParameters[$sKey];
        }
        return false;
    }

    /**
     * Checks available methods for contacting request target and triggers request with found method
     *
     * @param array $aUrlArray
     * @return array $aResponse
     */
    protected function _getResponseForParsedRequest($aUrlArray)
    {
        $aResponse = [];

        if (function_exists("curl_init")) {
            // php native curl exists so we gonna use it for requesting
            $aResponse = $this->_getCurlPhpResponse($aUrlArray);
        } else if (file_exists("/usr/local/bin/curl") || file_exists("/usr/bin/curl")) {
            // cli version of curl exists on server
            $sCurlPath = (file_exists("/usr/local/bin/curl")) ? "/usr/local/bin/curl" : "/usr/bin/curl";
            $aResponse = $this->_getCurlCliResponse($aUrlArray, $sCurlPath);
        } else {
            // last resort => via sockets
            $aResponse = $this->_getSocketResponse($aUrlArray);
        }

        return $aResponse;
    }

    /**
     * Using native php curl to perform request
     *
     * @param array $aUrlArray
     * @return array $aResponse
     */
    protected function _getCurlPhpResponse($aUrlArray)
    {
        $aResponse = [];

        $oCurl = curl_init($aUrlArray['scheme'] . "://" . $aUrlArray['host'] . $aUrlArray['path']);
        curl_setopt($oCurl, CURLOPT_POST, 1);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $aUrlArray['query']);

        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, true);  // force SSL certificate check
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 2);  // check hostname in SSL certificate

        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($oCurl, CURLOPT_TIMEOUT, 45);

        $result = curl_exec($oCurl);
        if (curl_error($oCurl)) {
            $aResponse[] = "connection-type: 1 - errormessage=" . curl_errno($oCurl) . ": " . curl_error($oCurl);
        } else {
            $aResponse = explode("\n", $result);
        }
        curl_close($oCurl);

        return $aResponse;
    }

    /**
     * Using installed CLI version of curl by building the command
     *
     * @param array  $aUrlArray
     * @param string $sCurlPath
     * @return array
     */
    protected function _getCurlCliResponse($aUrlArray, $sCurlPath)
    {
        $aResponse = [];

        $sPostUrl = $aUrlArray['scheme'] . "://" . $aUrlArray['host'] . $aUrlArray['path'];
        $sPostData = $aUrlArray['query'];

        $sCommand = $sCurlPath . " -m 45 -k -d \"" . $sPostData . "\" " . $sPostUrl;
        $iSysOut = -1;
        $sTemp = exec($sCommand, $aResponse, $iSysOut);
        if ($iSysOut != 0) {
            $aResponse[] = "connection-type: 2 - errormessage=curl error(" . $iSysOut . ")";
        }

        return $aResponse;
    }

    /**
     * Tries to fetch a response via network socket
     *
     * @param array $aUrlArray
     * @return array $aResponse
     */
    protected function _getSocketResponse($aUrlArray)
    {
        $aResponse = [];

        switch ($aUrlArray['scheme']) {
            case 'https':
                $sScheme = 'ssl://';
                $iPort = 443;
                break;
            case 'http':
            default:
                $sScheme = '';
                $iPort = 80;
        }

        $oFsockOpen = fsockopen($sScheme . $aUrlArray['host'], $iPort, $iErrorNumber, $sErrorString, 45);
        if (!$oFsockOpen) {
            $aResponse[] = "errormessage=fsockopen:Failed opening http socket connection: " . $sErrorString . " (" . $iErrorNumber . ")";
        } else {
            $sRequestHeader = "POST " . $aUrlArray['path'] . " HTTP/1.1\r\n";
            $sRequestHeader .= "Host: " . $aUrlArray['host'] . "\r\n";
            $sRequestHeader .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $sRequestHeader .= "Content-Length: " . strlen($aUrlArray['query']) . "\r\n";
            $sRequestHeader .= "Connection: close\r\n\r\n";
            $sRequestHeader .= $aUrlArray['query'];

            fwrite($oFsockOpen, $sRequestHeader);

            $sResponseHeader = "";
            do {
                $sResponseHeader .= fread($oFsockOpen, 1);
            } while (!preg_match("/\\r\\n\\r\\n$/", $sResponseHeader) && !feof($oFsockOpen));

            while (!feof($oFsockOpen)) {
                $aResponse[] = fgets($oFsockOpen, 1024);
            }
            if (count($aResponse) == 0) {
                $aResponse[] = 'connection-type: 3 - ' . $sResponseHeader;
            }
        }

        return $aResponse;
    }

    /**
     * Parses request respond and format it to needed form
     *
     * @param array $aResponse
     * @return array
     */
    protected function _getResponseOutput($aResponse)
    {
        $aOutput = [];
        foreach ($aResponse as $iLinenum => $sLine) {
            $iPos = strpos($sLine, "=");
            if ($iPos > 0) {
                $aOutput[substr($sLine, 0, $iPos)] = trim(substr($sLine, $iPos + 1));
            } elseif (strlen($sLine) > 0) {
                $aOutput[$iLinenum] = $sLine;
            }
        }

        return $aOutput;
    }

    /**
     * Adds mapped error message to response if available
     *
     * @param array $aInput
     * @return array
     */
    protected function _addMappedErrorIfAvailable($aInput)
    {
        $aOutput = $aInput;

        if ($aInput['status'] == 'ERROR') {
            $sErrorCode = $aInput['errorcode'];
            $oErrorMapping = oxNew(FcPoErrorMapping::class);
            $sMappedErrorMessage = $oErrorMapping->fcpoFetchMappedErrorMessage($sErrorCode);
            if ($sMappedErrorMessage) {
                $aOutput['origincustomermessage'] = $aInput['customermessage'];
                $aOutput['customermessage'] = $sMappedErrorMessage;
            }
        }

        return $aOutput;
    }

    /**
     * @throws DatabaseErrorException
     * @throws DatabaseConnectionException
     */
    protected function _logRequest($sResponse, $sStatus = '')
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oDb = DatabaseProvider::getDb();
        $sRequest = serialize($this->_aParameters);
        $sQuery = " INSERT INTO fcporequestlog (
                        FCPO_REFNR, FCPO_REQUESTTYPE, FCPO_RESPONSESTATUS, FCPO_REQUEST, FCPO_RESPONSE, FCPO_PORTALID, FCPO_AID
                    ) VALUES (
                        '{$this->getParameter('reference')}', 
                        '{$this->getParameter('request')}', 
                        '{$sStatus}', 
                        " . $oDb->quote($sRequest) . ", 
                        " . $oDb->quote($sResponse) . ", 
                        '{$oConfig->getConfigParam('sFCPOPortalID')}', 
                        '{$oConfig->getConfigParam('sFCPOSubAccountID')}'
                    )";
        $oDb->execute($sQuery);
    }

    /**
     * Template getter for checking which kind of field should be shown
     *
     * @param User $oUser
     * @return bool
     */
    public function fcpoIsB2B($oUser)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blB2BModeActive = $oConfig->getConfigParam('blFCPOPayolutionB2BMode');

        if ($blB2BModeActive) {
            $blCompany = (bool)$oUser->oxuser__oxcompany->value;
            $blReturn = $blCompany;
            // check if we already have ustid, then showing is not needed
            if ($blCompany) {
                $blReturn = !$oUser->oxuser__oxustid->value;
            }
        } else {
            $blReturn = false;
        }

        return $blReturn;
    }

    /**
     * Performs a refund_anouncement call
     *
     * @param Order $oOrder
     * @return array
     */
    public function sendRequestPayolutionRefundAnnouncement($oOrder)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $sTxid = $oOrder->oxorder__fcpotxid->value;
        $sWorkorderId = $oOrder->oxorder__fcpoworkorderid->value;
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;

        $this->addParameter('request', 'genericpayment'); //Request method
        $this->addParameter('mode', $this->getOperationMode($sPaymentId)); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account
        $this->addParameter('clearingtype', 'fnc');
        $oCurr = $oConfig->getActShopCurrencyObject();
        $this->addParameter('currency', $oCurr->name);
        $this->addParameter('add_paydata[action]', 'refund_announcement');
        $this->addParameter('api_version', '3.10');
        $this->addParameter('txid', $sTxid);
        $this->addParameter('workorderid', $sWorkorderId);

        return $this->send();
    }

    /**
     *
     * @param string $sPaymentId
     * @param User   $oUser
     * @param null   $aBankData
     * @param string $sAction
     * @param null   $sWorkorderId
     * @param null   $sDuration
     * @return array
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     */
    public function sendRequestPayolutionInstallment($sPaymentId, $oUser, $aBankData = null, $sAction = 'calculation', $sWorkorderId = null, $sDuration = null)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();

        $sRequestMethod = ($sAction == 'preauthorization') ? 'preauthorization' : 'genericpayment';
        $this->addParameter('request', $sRequestMethod); //Request method
        $this->addParameter('mode', $this->getOperationMode($sPaymentId)); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account

        $this->addParameter('clearingtype', 'fnc');

        $oBasket = $oSession->getBasket();
        $oPrice = $oBasket->getPrice();
        $this->addParameter('amount', number_format($oPrice->getBruttoPrice(), 2, '.', '') * 100);

        $oCurr = $oConfig->getActShopCurrencyObject();
        $this->addParameter('currency', $oCurr->name);
        $sFinancingType = $this->_fcpoGetFinancingTypeByPaymentId($sPaymentId);
        $this->_fcpoAddPayolutionUserData($oUser, $sPaymentId);
        $this->addParameter('financingtype', $sFinancingType);
        $this->addParameter('add_paydata[action]', $sAction);
        $this->addParameter('api_version', '3.10');

        if ($sWorkorderId !== null) {
            $this->addParameter('workorderid', $sWorkorderId);
        }

        $this->addParameter('encoding', 'UTF-8');

        $sIp = $this->_fcpoGetRemoteAddress();
        if ($sIp != '') {
            $this->addParameter('ip', $sIp);
        }

        $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr());

        $blValidBankData = (
            isset($aBankData) &&
            is_array($aBankData) &&
            count($aBankData) == 3 &&
            $aBankData['fcpo_payolution_installment_accountholder'] &&
            $aBankData['fcpo_payolution_installment_iban'] &&
            $aBankData['fcpo_payolution_installment_bic']
        );

        if ($blValidBankData) {
            $this->addParameter('iban', $aBankData['fcpo_payolution_installment_iban']);
            $this->addParameter('bic', $aBankData['fcpo_payolution_installment_bic']);
        }

        return $this->send();
    }

    /**
     * Sends a payolution precheck request to
     *
     * @param        $sPaymentId
     * @param object $oUser
     * @param        $aBankData
     * @param null   $sWorkorderId
     * @return array
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     */
    public function sendRequestPayolutionPreCheck($sPaymentId, $oUser, $aBankData, $sWorkorderId = null)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();

        $this->addParameter('request', 'genericpayment'); //Request method
        $this->addParameter('mode', $this->getOperationMode($sPaymentId)); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account

        $this->addParameter('clearingtype', 'fnc');

        $oBasket = $oSession->getBasket();
        $oPrice = $oBasket->getPrice();
        $this->addParameter('amount', number_format($oPrice->getBruttoPrice(), 2, '.', '') * 100);

        $oCurr = $oConfig->getActShopCurrencyObject();
        $this->addParameter('currency', $oCurr->name);

        $sPaymentType = $this->_fcpoGetPayolutionPaymentTypeById($sPaymentId);
        $sFinancignType = $this->_fcpoGetFinancingTypeByPaymentId($sPaymentId);
        $this->_fcpoAddPayolutionUserData($oUser, $sPaymentId);

        $this->addParameter('financingtype', $sFinancignType);
        $this->addParameter('add_paydata[action]', 'pre_check');
        $this->addParameter('add_paydata[payment_type]', $sPaymentType);
        $this->addParameter('api_version', '3.10');

        if ($sWorkorderId !== null) {
            $this->addParameter('workorderid', $sWorkorderId);
        }

        $this->addParameter('encoding', 'UTF-8');

        $sIp = $this->_fcpoGetRemoteAddress();
        if ($sIp != '') {
            $this->addParameter('ip', $sIp);
        }

        $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr());

        $blValidBankData = (
            isset($aBankData) &&
            is_array($aBankData) &&
            count($aBankData) == 3 &&
            $aBankData['fcpo_payolution_accountholder'] &&
            $aBankData['fcpo_payolution_iban'] &&
            $aBankData['fcpo_payolution_bic']
        );

        if ($blValidBankData) {
            $this->addParameter('iban', $aBankData['fcpo_payolution_iban']);
            $this->addParameter('bic', $aBankData['fcpo_payolution_bic']);
        }

        return $this->send();
    }

    /**
     * Performs a installment calculation call
     *
     * @return array
     */
    public function sendRequestBNPLInstallmentOptions()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();

        $oBasket = $oSession->getBasket();
        $oPrice = $oBasket->getPrice();
        $iAmount = number_format($oPrice->getBruttoPrice(), 2, '.', '') * 100; //Total order sum in smallest currency unit

        $this->addParameter('request', 'genericpayment'); //Request method
        $this->addParameter('mode', $this->getOperationMode('fcpopl_secinstallment')); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account
        $this->_fcpoAddBNPLPortalParameters();

        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('financingtype', 'PIN');

        $this->addParameter('amount', $iAmount);
        $oCurr = $oConfig->getActShopCurrencyObject();
        $this->addParameter('currency', $oCurr->name);

        $this->addParameter('add_paydata[action]', 'installment_options');

        return $this->send();
    }

    /**
     * Send profile request to PAYONE Server-API with request-type "genericpayment"
     *
     * @return array
     */
    public function sendRequestRatePayProfile($aRatePayData, $sWorkorderId = false)
    {
        $sPaymentId = $aRatePayData['OXPAYMENTID'];
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        /**
         * @todo: create method that fetches all saved params from profile
         * $aRatePayParams = $this->_fcpoGetRatePayParams($sRatePayShopId);
         */
        $sFinancingType = $this->_fcpoGetFinancingTypeByPaymentId($sPaymentId);

        $this->addParameter('request', 'genericpayment'); //Request method
        $this->addParameter('mode', $this->getOperationMode('fcpopaypal_express')); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account

        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('financingtype', $sFinancingType);

        if ($sWorkorderId !== false) {
            $this->addParameter('workorderid', $sWorkorderId);
        }
        $this->addParameter('add_paydata[action]', 'profile');
        $this->addParameter('add_paydata[shop_id]', $aRatePayData['shopid']);
        $this->addParameter('currency', $aRatePayData['currency']);

        return $this->send();
    }

    public function sendRequestRatepayCalculation($sCalculationType, $aRatePayData)
    {
        $sPaymentId = $aRatePayData['OXPAYMENTID'];
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();

        $oBasket = $oSession->getBasket();
        $oPrice = $oBasket->getPrice();
        $iAmount = number_format($oPrice->getBruttoPrice(), 2, '.', '') * 100; //Total order sum in smallest currency unit
        $sFinancingType = $this->_fcpoGetFinancingTypeByPaymentId($sPaymentId);

        $this->addParameter('request', 'genericpayment'); //Request method
        $this->addParameter('mode', $this->getOperationMode($sPaymentId)); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account

        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('financingtype', $sFinancingType);
        $this->addParameter('amount', $iAmount);
        $this->addParameter('currency', $aRatePayData['currency']);

        $this->addParameter('add_paydata[action]', 'calculation');
        $this->addParameter('add_paydata[calculation_type]', $sCalculationType);
        if ($sCalculationType == 'calculation-by-time') {
            $this->addParameter('add_paydata[month]', $aRatePayData['duration']);
        } else {
            $this->addParameter('add_paydata[rate]', $aRatePayData['installment']);
        }

        $this->addParameter('add_paydata[shop_id]', $aRatePayData['shopid']);
        $this->addParameter('add_paydata[customer_allow_credit_inquiry]', 'yes');

        return $this->send();
    }

    /**
     * Get the next reference number for the upcoming PAYONE transaction
     *
     * @param bool $oOrder order object
     * @param bool $blAddPrefixToSession
     * @return string
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function getRefNr($oOrder = false, $blAddPrefixToSession = false)
    {
        $sRawPrefix = (string)$this->_oFcPoHelper->fcpoGetConfig()->getConfigParam('sFCPORefPrefix');
        $sSessionRefNr = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoRefNr');
        $blUseSessionRefNr = ($sSessionRefNr && !$oOrder);
        if ($blUseSessionRefNr) {
            return ($blAddPrefixToSession) ?
                $sRawPrefix . $sSessionRefNr : $sSessionRefNr;
        }

        $oDb = DatabaseProvider::getDb();
        $sPrefix = $oDb->quote($sRawPrefix);

        if ($oOrder && !empty($oOrder->oxorder__oxordernr->value)) {
            $sRefNr = $oOrder->oxorder__oxordernr->value;
        } else {
            $sQuery = "SELECT MAX(fcpo_refnr) FROM fcporefnr WHERE fcpo_refprefix = {$sPrefix}";
            $iMaxRefNr = $oDb->getOne($sQuery);
            $sRefNr = (int)$iMaxRefNr + 1;
            $sQuery = "INSERT INTO fcporefnr (fcpo_refnr, fcpo_txid, fcpo_refprefix)  VALUES ('{$sRefNr}', '', {$sPrefix})";

            $oDb->execute($sQuery);
        }

        $sRefNrComplete = $sRawPrefix . $sRefNr;
        $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoRefNr', $sRefNr);

        return $sRefNrComplete;
    }

    /**
     * Sending start session call
     *
     * @param $sPaymentId
     * @return array
     */
    public function sendRequestKlarnaStartSession($sPaymentId)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        $sShippingId = $oBasket->getShippingId();

        $this->addParameter('request', 'genericpayment'); //Request method
        $this->addParameter('mode', $this->getOperationMode($sPaymentId)); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account

        $this->addParameter('add_paydata[action]', 'start_session');
        $this->addParameter('clearingtype', 'fnc');
        $this->addParameter('financingtype', $this->_fcpoGetKlarnaFinancingType($sPaymentId));

        $oCurr = $oConfig->getActShopCurrencyObject();
        $this->addParameter('currency', $oCurr->name);

        $this->addAddressParamsByUser($oUser);
        $this->addDeliveryAddressParams(true);
        $this->_fcpoAddBasketItemsFromSession($sShippingId);
        $oPrice = $oBasket->getPrice();
        $this->addParameter('amount', number_format($oPrice->getBruttoPrice(), 2, '.', '') * 100);

        return $this->send();
    }

    /**
     * Add address parameters by user object
     *
     * @param object $oUser user object
     *
     * @return null
     */
    protected function addAddressParamsByUser($oUser)
    {
        $oCountry = oxNew(Country::class);
        $oCountry->load($oUser->oxuser__oxcountryid->value);

        $this->addParameter('firstname', $oUser->oxuser__oxfname->value);
        $this->addParameter('lastname', $oUser->oxuser__oxlname->value);
        $this->addParameter('title', $this->_fcpoGetKlarnaTitleParam());

        if ($oUser->oxuser__oxcompany->value != '') {
            $this->addParameter('company', $oUser->oxuser__oxcompany->value);
            $this->addParameter('add_paydata[organization_entity_type]', 'OTHER');
            $this->addParameter('add_paydata[organization_registry_id]', $oUser->oxuser__oxustid->value);
        }
        $this->addParameter('street', trim($oUser->oxuser__oxstreet->value . ' ' . $oUser->oxuser__oxstreetnr->value));
        $this->addParameter('zip', $oUser->oxuser__oxzip->value);
        $this->addParameter('city', $oUser->oxuser__oxcity->value);
        $this->addParameter('country', $oCountry->oxcountry__oxisoalpha2->value);
        if ($this->_stateNeeded($oCountry->oxcountry__oxisoalpha2->value)) {
            $this->addParameter('state', $this->_getShortState($oUser->oxuser__oxstateid->value));
        }

        $this->addParameter('telephonenumber', $oUser->oxuser__oxfon->value);

        if ($oUser->oxuser__fcpopersonalid->value != '' && $oUser->oxuser__oxcompany->value != '') {
            $this->addParameter('personalid', $oUser->oxuser__fcpopersonalid->value);
        }
    }

    /**
     * Returns title param for klarna widget
     *
     * @return string
     */
    protected function _fcpoGetKlarnaTitleParam()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        $sGender = ($oUser->oxuser__oxsal->value == 'MR') ? 'male' : 'female';
        $sCountryIso2 = $oUser->fcpoGetUserCountryIso();
        switch ($sCountryIso2) {
            case 'AT':
            case 'CH':
            case 'DE':
                $sTitle = ($sGender === 'male') ? 'Herr' : 'Frau';
                break;
            case 'GB':
            case 'US':
                $sTitle = ($sGender === 'male') ? 'Mr' : 'Ms';
                break;
            case 'DK':
            case 'FI':
            case 'SE':
            case 'NL':
            case 'NO':
                $sTitle = ($sGender === 'male') ? 'Dhr.' : 'Mevr.';
                break;
        }
        return $sTitle;
    }

    /**
     * Send request to PAYONE Server-API with request-type "genericpayment"
     *
     * @return array
     * @todo  : This was historical foreseen for paypalexpress and is currently only
     *        used for this. We need to fetch identical params for genereic request and
     *        make this a generic part of each generic call dor deduplication of code
     */
    public function sendRequestGenericPayment($sWorkorderId = false)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();

        $this->addParameter('request', 'genericpayment'); //Request method
        $this->addParameter('mode', $this->getOperationMode('fcpopaypal_express')); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account

        $this->addParameter('clearingtype', 'wlt');
        $this->addParameter('wallettype', 'PPE');

        $oBasket = $oSession->getBasket();
        $oPrice = $oBasket->getPrice();
        $this->addParameter('amount', number_format($oPrice->getBruttoPrice(), 2, '.', '') * 100);

        $oCurr = $oConfig->getActShopCurrencyObject();
        $this->addParameter('currency', $oCurr->name);

        $this->addParameter('narrative_text', 'Test');

        if ($sWorkorderId !== false) {
            $this->addParameter('workorderid', $sWorkorderId);
            $this->addParameter('add_paydata[action]', 'getexpresscheckoutdetails');
        } else {
            $this->addParameter('add_paydata[action]', 'setexpresscheckout');
        }

        $this->_addRedirectUrls('basket', false, 'fcpoHandlePayPalExpress');

        return $this->send();
    }

    /**
     * Send request to PAYONE Server-API with request-type "capture"
     *
     * @param object $oOrder  order object
     * @param double $dAmount capture amount
     * @param bool   $blSettleAccount
     * @param bool   $aPositions
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function sendRequestCapture($oOrder, $dAmount, $blSettleAccount = true, $aPositions = false)
    {
        $this->_fcpoSetPortal($oOrder);
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        $this->addParameter('request', 'capture'); //Request method
        $sMode = $oOrder->oxorder__fcpomode->value;
        if ($sMode == '') {
            $sMode = $this->getOperationMode($oOrder->oxorder__oxpaymenttype->value);
        }
        $this->addParameter('mode', $sMode); //PayOne Portal Operation Mode (live or test)

        $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr());
        $this->addParameter('txid', $oOrder->oxorder__fcpotxid->value); //PayOne Transaction ID
        $this->addParameter('sequencenumber', $oOrder->getSequenceNumber());
        $this->addParameter('amount', number_format($dAmount, 2, '.', '') * 100); //Total order sum in smallest currency unit
        $this->addParameter('currency', $oOrder->oxorder__oxcurrency->value); //Currency

        if ($oOrder->allowAccountSettlement() === true && $blSettleAccount === false) {
            $sSettleAccount = 'no';
        } else {
            $sSettleAccount = 'auto';
        }

        $this->addParameter('settleaccount', $sSettleAccount);

        if ($this->_oFcPoHelper->fcpoGetRequestParameter('capture_completeorder') == '1') {
            $this->addParameter('capturemode', 'completed');
        }

        $blAddProductInfo = $oOrder->isDetailedProductInfoNeeded();

        if ($blAddProductInfo) {
            $dAmount = $this->addProductInfo($oOrder, $aPositions);
            if ($aPositions !== false) {
                //partial-amount
                $this->addParameter('amount', number_format($dAmount, 2, '.', '') * 100); //Total order sum in smallest currency unit
            }
        }

        $this->_fcpoAddCaptureAndDebitRatePayParams($oOrder);

        if ($sPaymentId == 'fcpo_secinvoice') {
            $this->_fcpoAddSecInvoiceParameters($oOrder);
        }
        if ($sPaymentId == 'fcpopl_secinvoice') {
            $this->_fcpoAddBNPLSecInvoiceParameters($oOrder);
        }
        if ($sPaymentId == 'fcpopl_secinstallment') {
            $this->_fcpoAddBNPLSecInstallmentParameters($oOrder);
        }

        $aResponse = $this->send();

        if ($aPositions && $aResponse && array_key_exists('status', $aResponse) !== false && $aResponse['status'] == 'APPROVED') {
            foreach ($aPositions as $sOrderArtId => $aPos) {
                $sQuery = "UPDATE oxorderarticles SET fcpocapturedamount = fcpocapturedamount + {$aPos['amount']} WHERE oxid = '{$sOrderArtId}'";
                DatabaseProvider::getDb()->execute($sQuery);
            }
        }

        return $aResponse;
    }

    /**
     * Method takes care for eventually other payment protal for fulfilling process
     *
     * @param $oOrder
     * @return void
     */
    protected function _fcpoSetPortal($oOrder)
    {
        $this->_fcpoSetSecurePayPortal($oOrder);
    }

    /**
     * If payment is Secure Invoice (rec/POV) or BNPL other portal data
     * has to be set for upcoming call
     *
     * @param $oOrder
     * @return void
     */
    protected function _fcpoSetSecurePayPortal($oOrder)
    {
        $sPaymentId =
            (string)$oOrder->oxorder__oxpaymenttype->value;
        $blPaymentMatches = ($sPaymentId === 'fcpo_secinvoice' || $sPaymentId === 'fcpopl_secinvoice' || $sPaymentId === 'fcpopl_secinstallment');

        if (!$blPaymentMatches) return;

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        if ($sPaymentId === 'fcpopl_secinvoice' || $sPaymentId === 'fcpopl_secinstallment') {
            $sFCPOSecinvoicePortalId = $oConfig->getConfigParam('sFCPOPLPortalId');
            $sFCPOSecinvoicePortalKey = $oConfig->getConfigParam('sFCPOPLPortalKey');
        } else {
            $sFCPOSecinvoicePortalId = $oConfig->getConfigParam('sFCPOSecinvoicePortalId');
            $sFCPOSecinvoicePortalKey = $oConfig->getConfigParam('sFCPOSecinvoicePortalKey');
        }

        $this->addParameter('portalid', $sFCPOSecinvoicePortalId);
        $this->addParameter('key', md5($sFCPOSecinvoicePortalKey));
    }

    /**
     * Adds Ratepay specific parameters
     *
     * @param object $oOrder
     * @return void
     * @todo: currently only shop id will be fetched
     */
    protected function _fcpoAddCaptureAndDebitRatePayParams($oOrder)
    {
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        if (in_array($sPaymentId, $this->_aRatePayPayments)) {
            $sRatePayShopId = $oOrder->getFcpoRatepayShopId();
            $this->addParameter('add_paydata[shop_id]', $sRatePayShopId);
        }
    }

    /**
     * Send request to PAYONE Server-API with request-type "debit"
     *
     * @param object $oOrder             order object
     * @param double $dAmount            capture amount
     * @param bool   $sBankCountry       ISO2 of the country of the bank. Default is false
     * @param bool   $sBankAccount       bank account number. Default is false
     * @param string $sBankCode          bank code. Default is false
     * @param string $sBankaccountholder bank account holder. Default is false
     * @param bool   $aPositions
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function sendRequestDebit($oOrder, $dAmount, $sBankCountry = false, $sBankAccount = false, $sBankCode = '', $sBankaccountholder = '', $aPositions = false)
    {
        $this->_fcpoSetPortal($oOrder);
        $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        $this->_fcpoAddCaptureAndDebitRatePayParams($oOrder);
        $this->addParameter('request', 'debit'); //Request method
        $sMode = $oOrder->oxorder__fcpomode->value;
        if ($sMode == '') {
            $sMode = $this->getOperationMode($oOrder->oxorder__oxpaymenttype->value);
        }
        $this->addParameter('mode', $sMode); //PayOne Portal Operation Mode (live or test)

        $this->addParameter('txid', $oOrder->oxorder__fcpotxid->value); //PayOne Transaction ID
        $this->addParameter('sequencenumber', $oOrder->getSequenceNumber());
        $this->addParameter('amount', number_format($dAmount, 2, '.', '') * 100); //Total order sum in smallest currency unit
        $this->addParameter('currency', $oOrder->oxorder__oxcurrency->value); //Currency

        $this->addParameter('transactiontype', 'GT');

        if ($sBankAccount !== false && $sBankCountry !== false) {
            $this->addParameter('bankcountry', $sBankCountry);
            $this->addParameter('bankaccount', $sBankAccount);
            $this->addParameter('bankcode', $sBankCode);
            $this->addParameter('bankaccountholder', $sBankaccountholder);
        }

        // Bedingung $amount == $oOrder->oxorder__oxorder__oxtotalordersum->value nur solange wie Artikelliste nicht f?r Multi-Capture m?glich
        if ($oOrder->isDetailedProductInfoNeeded()) {
            $dAmount = $this->addProductInfo($oOrder, $aPositions, true);
            // amount for credit entry has to be negative
            $dAmount = (double)$dAmount * -1;
            if ($aPositions !== false) {
                //partial-amount
                $this->addParameter('amount', number_format($dAmount, 2, '.', '') * 100); //Total order sum in smallest currency unit
            }
        }

        if ($sPaymentId == 'fcpo_secinvoice') {
            $this->_fcpoAddSecInvoiceParameters($oOrder);
        }
        if ($sPaymentId == 'fcpopl_secinvoice') {
            $this->_fcpoAddBNPLSecInvoiceParameters($oOrder);
        }
        if ($sPaymentId == 'fcpopl_secinstallment') {
            $this->_fcpoAddBNPLSecInstallmentParameters($oOrder);
        }

        $aResponse = $this->send();

        if ($aPositions && $aResponse && array_key_exists('status', $aResponse) !== false && $aResponse['status'] == 'APPROVED') {
            foreach ($aPositions as $sOrderArtId => $aPos) {
                switch ($sOrderArtId) {
                    case 'oxdelcost':
                        $sQuery = "UPDATE oxorder SET fcpodelcostdebited = 1 WHERE oxid = '{$oOrder->getId()}'";
                        break;
                    case 'oxpaycost':
                        $sQuery = "UPDATE oxorder SET fcpopaycostdebited = 1 WHERE oxid = '{$oOrder->getId()}'";
                        break;
                    case 'oxwrapcost':
                        $sQuery = "UPDATE oxorder SET fcpowrapcostdebited = 1 WHERE oxid = '{$oOrder->getId()}'";
                        break;
                    case 'oxgiftcardcost':
                        $sQuery = "UPDATE oxorder SET fcpogiftcardcostdebited = 1 WHERE oxid = '{$oOrder->getId()}'";
                        break;
                    case 'oxvoucherdiscount':
                        $sQuery = "UPDATE oxorder SET fcpovoucherdiscountdebited = 1 WHERE oxid = '{$oOrder->getId()}'";
                        break;
                    case 'oxdiscount':
                        $sQuery = "UPDATE oxorder SET fcpodiscountdebited = 1 WHERE oxid = '{$oOrder->getId()}'";
                        break;
                    default:
                        $sQuery = "UPDATE oxorderarticles SET fcpodebitedamount = fcpodebitedamount + {$aPos['amount']} WHERE oxid = '{$sOrderArtId}'";
                        break;
                }
                DatabaseProvider::getDb()->execute($sQuery);
            }
        }

        return $aResponse;
    }

    /**
     * This is the wrapper for address checks that has been called from the admin
     *
     * @param      $oUser
     * @param bool $blCheckDeliveryAddress
     * @return mixed
     */
    public function sendRequestAddresscheck($oUser, $blCheckDeliveryAddress = false)
    {
        $mReturn = $this->sendStandardRequestAddresscheck($oUser, $blCheckDeliveryAddress);
        if (is_array($mReturn) && isset($mReturn['personstatus'])) {
            $this->setPayoneMalus($oUser, $mReturn);
        }
        return $mReturn;
    }

    /**
     * Send request to PAYONE Server-API with request-type "addresscheck"
     * Returns array of the response if the address was checked
     * OR
     * Return true if address-check was skipped because the address has been checked before
     *
     * @param object $oUser                  user object
     * @param bool   $blCheckDeliveryAddress check delivery address? Default is false
     *
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     */
    public function sendStandardRequestAddresscheck($oUser, $blCheckDeliveryAddress = false)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $this->addParameter('request', 'addresscheck');
        $this->addParameter('mode', $oConfig->getConfigParam('sFCPOBoniOpMode')); //Operationmode live or test
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account
        $sAddresschecktype = $this->_fcpoGetAddressCheckType();
        $this->addParameter('addresschecktype', $sAddresschecktype);

        if ($sAddresschecktype == 'PE' && $this->getCountryIso2($oUser->oxuser__oxcountryid->value) != 'DE') {
            //AddressCheck Person nur in Deutschland
            //Erfolgreichen Check simulieren
            return ['fcWrongCountry' => true];
        } elseif ($sAddresschecktype == 'BA' && !in_array($this->getCountryIso2($oUser->oxuser__oxcountryid->value), $this->_aValidCountrys)) {
            //AddressCheck Basic nur in bestimmten L?ndern
            //Erfolgreichen Check simulieren
            return ['fcWrongCountry' => true];
        } else {
            $oAddress = oxNew(Address::class);
            if ($blCheckDeliveryAddress === true) {
                $sDeliveryAddressId = $oUser->getSelectedAddressId();
                if ($sDeliveryAddressId) {
                    $oAddress->load($sDeliveryAddressId);
                } else {
                    return false;
                }
                $this->addAddressParamsByAddress($oAddress);
            } else {
                $this->addAddressParamsByUser($oUser);
            }

            $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr());

            if ($this->_wasAddressCheckedBefore() === false) {
                $aResponse = $this->send();

                if ($this->_fcpoCheckAddressCanBeSaved($aResponse)) {
                    $this->_saveCheckedAddress($aResponse);
                }

                return $aResponse;
            }
            return true;
        }
    }

    /**
     * Check, correct and return addresschecktype
     *
     * @return string
     */
    protected function _fcpoGetAddressCheckType()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOAddresscheck');
    }

    /**
     * Get ISO2 country code by given country ID
     *
     * @param string $sCountryId country ID
     *
     * @return string
     */
    protected function getCountryIso2($sCountryId)
    {
        $oCountry = oxNew(Country::class);
        $oCountry->load($sCountryId);
        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Add address parameters by delivery address object
     *
     * @param object $oAddress delivery address object
     *
     * @return null
     */
    protected function addAddressParamsByAddress($oAddress)
    {
        $oCountry = oxNew(Country::class);
        $oCountry->load($oAddress->oxaddress__oxcountryid->value);

        $this->addParameter('firstname', $oAddress->oxaddress__oxfname->value);
        $this->addParameter('lastname', $oAddress->oxaddress__oxlname->value);

        if ($oAddress->oxaddress__oxcompany->value != '') {
            $this->addParameter('company', $oAddress->oxaddress__oxcompany->value);
        }
        $this->addParameter('street', trim($oAddress->oxaddress__oxstreet->value . ' ' . $oAddress->oxaddress__oxstreetnr->value));
        $this->addParameter('zip', $oAddress->oxaddress__oxzip->value);
        $this->addParameter('city', $oAddress->oxaddress__oxcity->value);
        $this->addParameter('country', $oCountry->oxcountry__oxisoalpha2->value);
        if ($this->_stateNeeded($oCountry->oxcountry__oxisoalpha2->value)) {
            $this->addParameter('state', $this->_getShortState($oAddress->oxaddress__oxstateid->value));
        }

        if ($oAddress->oxaddress__oxfon->value != '') {
            $this->addParameter('telephonenumber', $oAddress->oxaddress__oxfon->value);
        }
    }

    /**
     * Check and return if this exact address has been checked before
     *
     * @return bool
     * @throws DatabaseConnectionException
     * @throws DatabaseConnectionException
     */
    protected function _wasAddressCheckedBefore()
    {
        $sCheckHash = $this->_getAddressHash();
        $sQuery = "SELECT oxtimestamp FROM fcpocheckedaddresses WHERE fcpo_address_hash = '{$sCheckHash}'";
        $sDate = DatabaseProvider::getDb()->getOne($sQuery);
        if ($sDate) {
            return true;
        }
        return false;
    }

    /**
     * Create a unique hash of the valid address
     *
     * @param array $aResponse response from the address-check request
     * @return string
     */
    protected function _getAddressHash($aResponse = false)
    {
        $sHash = false;

        $aAddressParameters = ['firstname', 'lastname', 'company', 'street', 'streetname', 'streetnumber', 'zip', 'city', 'country', 'state'];

        $sAddress = '';
        foreach ($aAddressParameters as $sParamKey) {
            $sParamValue = $this->getParameter($sParamKey);
            if ($sParamValue) {
                $blCorrectAddressParam = $this->_fcpoCorrectAddressParam($sParamKey, $sParamValue, $aResponse);
                if ($blCorrectAddressParam) {
                    //take the corrected value from the address-check
                    $sParamValue = $aResponse[$sParamKey];
                }
                $sAddress .= $sParamValue;
            }
        }
        return md5($sAddress);
    }

    /**
     * Check response against current addressdata
     *
     * @param $sParamKey
     * @param $sParamValue
     * @param $aResponse
     * @return bool
     */
    protected function _fcpoCorrectAddressParam($sParamKey, $sParamValue, $aResponse)
    {
        return (
            $aResponse !== false &&
            array_key_exists($sParamKey, $aResponse) !== false &&
            $aResponse[$sParamKey] != $sParamValue
        );
    }

    /**
     * Method checks if current address can be saved after call for address check
     *
     * @param  $aResponse
     * @return bool
     */
    protected function _fcpoCheckAddressCanBeSaved($aResponse)
    {
        return (
            $aResponse['status'] == 'VALID' &&
            $this->_fcpoNotBlockingPersonstatus($aResponse)
        );
    }

    /**
     * Method checks if personstatus and settings block saving former addresschecks
     *
     * @param  $aResponse
     * @return bool
     */
    protected function _fcpoNotBlockingPersonstatus($aResponse)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $oConfig->getConfigParam('sFCPOAddresscheck');
        $sResponsePersonstatus = $aResponse['personstatus'];

        $aBlockingPersonStatus = [];
        $aPersonStatusToCheck = ['PPF', 'UKN', 'PUG', 'PNZ', 'PNP'];

        foreach ($aPersonStatusToCheck as $sPersonstatusToCheck) {
            $blBlocking = $oConfig->getConfigParam('blFCPOAddCheck' . $sPersonstatusToCheck);
            if ($blBlocking) {
                $aBlockingPersonStatus[] = $sPersonstatusToCheck;
            }
        }

        $blReturn = true;
        if ($sFCPOAddresscheck == 'PE') {
            $blReturn = (
            !in_array($sResponsePersonstatus, $aBlockingPersonStatus)
            );
        }

        return $blReturn;
    }

    /**
     * Save the hash of a concatenated string with all address information to the DB table fcpocheckedaddresses
     *
     * @param array $aResponse response from the address-check request
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function _saveCheckedAddress($aResponse)
    {
        $sCheckHash = $this->_getAddressHash($aResponse);
        $sQuery = "REPLACE INTO fcpocheckedaddresses ( fcpo_address_hash ) VALUES ( '{$sCheckHash}' )";
        DatabaseProvider::getDb()->execute($sQuery);
    }

    /**
     * Method sets malus depending on addresscheck
     *
     * @param  $oUser
     * @param  $aResponse
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function setPayoneMalus($oUser, $aResponse)
    {
        if (isset($aResponse['personstatus'])) {
            $iNewMalus = $oUser->getConfig()->getConfigParam('sFCPOMalus' . strtoupper($aResponse['personstatus']));
            if ($iNewMalus !== null) {// null comes if personstatus is unkown
                $iOldMalus = (int)$oUser->oxuser__fcpocurrmalus->value;

                //realboni field is used to keep track of the "real" boni, since this calculation cuts of the boni at 0
                //otherwise the customer could gain boni through this
                $iOldBoni = $oUser->oxuser__fcporealboni->value;
                if ($iOldBoni === null) {// real boni not yet calculated
                    $iOldBoni = (int)$oUser->oxuser__oxboni->value;
                }

                $oUser->oxuser__fcpocurrmalus->value = (int)$iNewMalus;

                $iNewBoni = $iOldBoni + $iOldMalus - (int)$iNewMalus;
                $oUser->oxuser__fcporealboni->value = $iNewBoni;

                if ($iNewBoni < 0) {
                    $iNewBoni = 0;
                }
                $oUser->oxuser__oxboni->value = (int)$iNewBoni;
                $oUser->save();

                // setting it somehow is not saved, so save it this way
                $sQuery = "UPDATE oxuser SET oxboni = '{$iNewBoni}' WHERE oxid = '{$oUser->getId()}'";
                DatabaseProvider::getDb()->execute($sQuery);
            }
        }
    }

    /**
     * Send request to PAYONE Server-API with request-type "consumerscore"
     *
     * @param object $oUser user object
     *
     * @return array;
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     */
    public function sendRequestConsumerscore($oUser)
    {
        // Consumerscore only allowed in germany
        if ($this->getCountryIso2($oUser->oxuser__oxcountryid->value) == 'DE') {
            $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
            $this->addParameter('request', 'consumerscore');
            $this->addParameter('mode', $oConfig->getConfigParam('sFCPOBoniOpMode')); //Operationmode live or test
            $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account

            $this->addParameter('addresschecktype', $oConfig->getConfigParam('sFCPOConsumerAddresscheck'));
            $this->addParameter('consumerscoretype', $oConfig->getConfigParam('sFCPOBonicheck'));

            $this->addAddressParamsByUser($oUser);

            if ($oUser->oxuser__oxbirthdate != '0000-00-00' && $oUser->oxuser__oxbirthdate != '') {
                $this->addParameter('birthday', str_ireplace('-', '', $oUser->oxuser__oxbirthdate->value));
            }

            $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr());

            $aResponse = $this->send();
            return $this->_fcpoCheckUseFallbackBoniversum($aResponse);
        } else {
            // Ampel Gruen Response simulieren
            return ['scorevalue' => 500, 'fcWrongCountry' => true];
        }
    }

    /**
     * Parses response and set fallback if conditions match
     *
     * @param $aResponse
     * @return array
     */
    protected function _fcpoCheckUseFallbackBoniversum($aResponse)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sScore = $aResponse['score'];
        $sAddresscheckType = $this->_fcpoGetBoniAddresscheckType();

        $blUseFallBack = (
            $sScore == 'U' &&
            in_array($sAddresscheckType, ['BB', 'PB'])
        );

        if ($blUseFallBack) {
            $sFCPOBoniversumFallback = $oConfig->getConfigParam('sFCPOBoniversumFallback');
            $aResponse['score'] = $sFCPOBoniversumFallback;
            if ($sFCPOBoniversumFallback == 'R' && $aResponse['status'] == 'VALID') {
                $aResponse['status'] = 'ERROR';
            }
        }

        return $aResponse;
    }

    /**
     * Check, correct and return addresschecktype
     *
     * @return string
     */
    protected function _fcpoGetBoniAddresscheckType()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sBoniCheckType = $oConfig->getConfigParam('sFCPOBonicheck');
        $sAddressCheckType = $oConfig->getConfigParam('sFCPOConsumerAddresscheck');

        if ($sBoniCheckType == 'CE') {
            $sAddressCheckType = 'PB';
        }

        return $sAddressCheckType;
    }

    /**
     * Send request to PAYONE Server-API with request-type "managemandate"
     *
     * @param string $sMode     operation-mode ( live/test )
     * @param array  $aDynvalue payment form-data
     * @param object $oUser     user object
     *
     * @return array
     * @throws LanguageNotFoundException
     * @throws LanguageNotFoundException
     */
    public function sendRequestManagemandate($sMode, $aDynvalue, $oUser)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        $this->addParameter('request', 'managemandate'); //Request method
        $this->addParameter('mode', $sMode); //PayOne Portal Operation Mode (live or test)
        $this->addParameter('aid', $oConfig->getConfigParam('sFCPOSubAccountID')); //ID of PayOne Sub-Account
        $this->addParameter('clearingtype', 'elv');

        $sPayOneUserId = $this->_getPayoneUserIdByCustNr($oUser->oxuser__oxcustnr->value);
        if ($sPayOneUserId) {
            $this->addParameter('userid', $sPayOneUserId);
        }
        $this->addAddressParamsByUser($oUser);
        $this->addParameter('email', $oUser->oxuser__oxusername->value);
        $this->addParameter('language', $this->_oFcPoHelper->fcpoGetLang()->getLanguageAbbr());
        $this->addParameter('bankcountry', $aDynvalue['fcpo_elv_country']);
        if ($this->_fcpoAddIban($aDynvalue)) {
            $this->addParameter('iban', $aDynvalue['fcpo_elv_iban']);
            $sBic = (isset($aDynvalue['fcpo_elv_bic'])) ? $aDynvalue['fcpo_elv_bic'] : '';
            $this->addParameter('bic', $sBic);
        }

        $oCur = $oConfig->getActShopCurrencyObject();
        $this->addParameter('currency', $oCur->name);

        $aResponse = $this->send();
        if (is_array($aResponse)) {
            $aResponse['mode'] = $sMode;
        }

        return $aResponse;
    }

    protected function _getPayoneUserIdByCustNr($sCustNr)
    {
        $sQuery = " SELECT 
                        fcpo_userid 
                    FROM 
                        fcpotransactionstatus 
                    WHERE 
                        fcpo_customerid = '{$sCustNr}' 
                    ORDER BY 
                        oxtimestamp DESC 
                    LIMIT 1";
        return DatabaseProvider::getDb()->getOne($sQuery);
    }

    /**
     * Method checks if iban can be added
     *
     * @param array $aDynvalue
     * @return bool
     */
    protected function _fcpoAddIban($aDynvalue)
    {
        return (
        (
            isset($aDynvalue['fcpo_elv_iban']) &&
            $aDynvalue['fcpo_elv_iban'] != ''
        )
        );
    }

    /**
     * Send request to PAYONE Server-API with request-type "getfile"
     *
     * @param string $sOrderId               oxid order id
     * @param string $sMandateIdentification payone mandate identification
     * @param string $sMode                  operation-mode ( live/test )
     *
     * @return string
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function sendRequestGetFile($sOrderId, $sMandateIdentification, $sMode)
    {
        $sReturn = false;
        $sStatus = 'ERROR';
        $sResponse = '';
        $oDb = DatabaseProvider::getDb();

        $this->addParameter('request', 'getfile'); //Request method
        $this->addParameter('file_reference', $sMandateIdentification);
        $this->addParameter('file_type', 'SEPA_MANDATE');
        $this->addParameter('file_format', 'PDF');

        $this->addParameter('mode', $sMode);
        if ($sMode == 'test') {
            $this->removeParameter('integrator_name');
            $this->removeParameter('integrator_version');
            $this->removeParameter('solution_name');
            $this->removeParameter('solution_version');
        }

        $sPath = 'modules/fc/fcpayone/mandates/' . $sMandateIdentification . '.pdf';
        $sDestinationFile = getShopBasePath() . $sPath;

        $aOptions = ['http' => ['header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($this->_aParameters)]];
        $oContext = stream_context_create($aOptions);
        $oContent = file_get_contents($this->_sApiUrl, false, $oContext);
        if ($oContent !== false) {
            file_put_contents($sDestinationFile, $oContent);

            if (file_exists($sDestinationFile)) {
                $sExists = $oDb->getOne("SELECT oxorderid FROM fcpopdfmandates WHERE oxorderid = " . $oDb->quote($sOrderId) . " LIMIT 1");
                if (!$sExists) {
                    $sQuery = "INSERT INTO fcpopdfmandates (OXORDERID, FCPO_FILENAME) VALUES (" . $oDb->quote($sOrderId) . ", " . $oDb->quote(basename($sDestinationFile)) . ")";
                    $oDb->execute($sQuery);
                }

                $sReturn = $this->_oFcPoHelper->fcpoGetConfig()->getShopUrl() . "modules/fc/fcpayone/download.php?id=" . $sOrderId;
                $sStatus = 'SUCCESS';

                $aOutput = ['file' => $sDestinationFile];
                $sResponse = serialize($aOutput);
            }
        }
        $this->_logRequest($sResponse, $sStatus);

        return $sReturn;
    }

    /**
     * Remove parameter from request
     *
     * @param string $sKey parameter key
     */
    public function removeParameter($sKey)
    {
        if (array_key_exists($sKey, $this->_aParameters)) {
            unset($this->_aParameters[$sKey]);
        }
    }

    /**
     * Loads shop version and formats it in a certain way
     *
     * @return string
     */
    protected function getIntegratorId()
    {
        return $this->_oFcPoHelper->fcpoGetIntegratorId();
    }

    /**
     * Set payment parameters for the payment method "Online ?berweisung"
     * and return true if payment-method is known or false if payment-method is unknown
     *
     * @param object $oOrder    order object
     * @param array  $aDynvalue form data
     */
    protected function addParametersOnlineTransaction($oOrder, $aDynvalue)
    {

    }

    /**
     * Returns to basket with optional custom message
     *
     * @param bool $blLogout
     * @return void
     */
    protected function _fcpoReturnToBasket($blLogout = true)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        // @todo: Redirect to basket with message, currently redirect without comment
        $oUtils = $this->_oFcPoHelper->fcpoGetUtils();
        $sShopUrl = $oConfig->getShopUrl();
        $oUtils->redirect($sShopUrl . "index.php?cl=basket?");
    }

    /**
     * @return array
     */
    protected function _handleFrontendApiCall()
    {
        $sFrontendApiUrl = $this->_getFrontendApiUrl();

        return ['status' => 'REDIRECT', 'txid' => '', 'redirecturl' => $sFrontendApiUrl];
    }

    /**
     * @return string
     */
    protected function _getFrontendApiUrl()
    {
        $this->_aParameters['targetwindow'] = 'parent';

        $aHashParams = [];
        foreach ($this->_aParameters as $sKey => $sValue) {
            if (in_array($sKey, $this->_aFrontendUnsetParams)) {
                unset($this->_aParameters[$sKey]);
            } elseif (in_array($sKey, $this->_aFrontendHashParams) || stripos($sKey, '[') !== false) {
                $aHashParams[$sKey] = $sValue;
            }
        }
        $this->_aParameters['hash'] = $this->_getFrontendHash($aHashParams);


        $sUrlParams = '?';
        foreach ($this->_aParameters as $sKey => $sValue) {
            $sUrlParams .= $sKey . '=' . urlencode($sValue) . '&';
        }
        $sUrlParams = rtrim($sUrlParams, '&');
        $sFrontendApiUrl = $this->_sFrontendApiUrl . $sUrlParams;

        $this->_logRequest('NONE - Frontend API Call', 'Frontend API');
        return $sFrontendApiUrl;
    }

    /**
     * @param array $aHashParams
     * @return string
     */
    protected function _getFrontendHash($aHashParams)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        ksort($aHashParams, SORT_STRING);
        unset($aHashParams['key']);
        $aHashParams['key'] = $oConfig->getConfigParam('sFCPOPortalKey');

        $sHashString = '';
        foreach ($aHashParams as $sKey => $sValue) {
            $sHashString .= $sValue;
        }
        return md5($sHashString);
    }

    /**
     * Adding params for getting status
     *
     * @param $sWorkorderId
     * @return void
     */
    protected function _fcpoAddPaydirektGetStatusParams($sWorkorderId)
    {
        $this->addParameter('add_paydata[action]', 'getstatus');
        $this->addParameter('workorderid', $sWorkorderId);
    }

    /**
     * Returns url to shipping terms url
     *
     * @return string
     */
    protected function _fcpoGetPaydirektShippingTermsUrl()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        return (string)$oConfig->getConfigParam('sPaydirektShippingTermsUrl');
    }

}
