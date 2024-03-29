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
use Fatchip\PayOne\Application\Model\FcPayOnePayment;
use Fatchip\PayOne\Application\Model\FcPoRatepay;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Core\DatabaseProvider;
use stdClass;

class FcPayOnePaymentView extends FcPayOnePaymentView_parent
{

    public $_aDynValue;
    public $_oPaymentList;
    /**
     * Contains dynvalue list of requested params of payment page (all)
     *
     * @var array
     */
    public $_aFcRequestedValues;
    /**
     * Flag for checking if klarna payment combined payment widget is already present
     *
     * @var bool
     */
    public $_blKlarnaCombinedIsPresent = false;
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected $_oFcpoHelper;
    /**
     * Helper object for dealing with different shop versions
     *
     * @var DatabaseProvider
     */
    protected DatabaseInterface $_oFcpoDb;
    /**
     * bill country id of the user object
     *
     * @var string
     */
    protected $_sUserBillCountryId;
    /**
     * delivery country id if existant
     *
     * @var string
     */
    protected $_sUserDelCountryId;
    /**
     * Contains the sub payment methods that are available for the user ( Visa, MC, etc. )
     *
     * @var array
     */
    protected $_aCheckedSubPayments = [];
    /**
     * Array of isoalpha2-countries for which birthday is needed
     *
     * @var array
     */
    protected $_aKlarnaBirthdayNeededCountries = ['DE', 
    'NL', 
    'AT', 
    'CH'];
    /**
     * Datacontainer for all cc payment meta data
     *
     * @var array
     */
    protected $_aPaymentCCMetaData = [];
    /**
     * Base link for payolution agreement overlay
     *
     * @var string
     */
    protected $_sPayolutionAgreementBaseLink = 'https://payment.payolution.com/payolution-payment/infoport/dataprivacydeclaration';
    /**
     * Mandate link for payolution debitnote
     *
     * @var string
     */
    protected $_sPayolutionSepaAgreement = 'https://payment.payolution.com/payolution-payment/infoport/sepa/mandate.pdf';
    /**
     * Holder for installment calculation data
     *
     * @var array
     */
    protected $_aInstallmentCalculation = [];
    /**
     * Flag which indicates, that functionality is called from outside via ajax
     */
    protected false $_blIsPayolutionInstallmentAjax = false;
    /**
     * Params holder for payolution installment params
     *
     * @var array
     */
    protected $_aAjaxPayolutionParams = [];
    /**
     * Contains profile which matched for ratepay payment
     *
     * @var string
     */
    protected $_aRatePayProfileIds = ['fcporp_bill' => null, 'fcporp_debitnote' => null];
    /**
     * List of countries that need a telephone number for payolution bill payment
     *
     * @var array
     */
    protected $_aPayolutionBillMandatoryTelephoneCountries = ['NL'];
    /**
     * Contains current error message for payolution payment precheck
     *
     * @var string
     */
    protected $_sPayolutionCurrentErrorMessage;

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
     * Wrapper for checking if payment is allowed to be in usual payment
     * selection
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function fcpoShowAsRegularPaymentSelection($sPaymentId)
    {
        $oPayment = $this->_oFcpoHelper->getFactoryObject('oxPayment');
        $oPayment->load($sPaymentId);
        return $oPayment->fcpoShowAsRegularPaymentSelection();
    }

    /**
     * Extends oxid standard method init()
     * Executes parent method parent::init().
     *
     * @return null
     */
    public function init(): void
    {
        if (!$this->_hasFilterDynDataMethod()) {
            $this->_filterDynData();
        }
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        $sOrderId = $this->_oFcpoHelper->fcpoGetSessionVariable('sess_challenge');
        $sType = $this->_oFcpoHelper->fcpoGetRequestParameter('type');
        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        $blReduceStockBefore = !(bool)$oConfig->getConfigParam('blFCPOReduceStock');
        if ($sOrderId && $blPresaveOrder && $blReduceStockBefore && ($sType == 'error' || $sType == 'cancel')) {
            $oOrder = $this->_oFcpoHelper->getFactoryObject('oxorder');
            $oOrder->load($sOrderId);
            if ($oOrder) {
                $oOrder->cancelOrder();
            }
            unset($oOrder);
        }
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('sess_challenge');
    }

    /**
     * Checks whether the oxid version has the _filterDynData method
     * Oxid 4.2 and below dont have the _filterDynData method
     */
    protected function _hasFilterDynDataMethod(): bool
    {
        $sVersion = $this->_oFcpoHelper->fcpoGetShopVersion();

        return version_compare($sVersion, '4.3.0', 
    '>=');
    }

    /**
     * Extends oxid standard method _filterDynData()
     * Unsets the PAYONE form-data fields containing creditcard data
     *
     * Due to legal reasons probably you are not allowed to store or even handle credit card data.
     * In this case we just delete and forget all submited credit card data from this point.
     * Override this method if you actually want to process credit card data.
     *
     * Note: You should override this method as setting blStoreCreditCardInfo to true would
     *       force storing CC data on shop side (what most often is illegal).
     *
     * @return null
     * @extend _filterDynData
     */
    protected function _filterDynData()
    {
        if ($this->_hasFilterDynDataMethod()) {
        }

        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        //in case we actually ARE allowed to store the data
        if ($oConfig->getConfigParam("blStoreCreditCardInfo")) {
            //then do nothing
            return;
        }

        $aDynData = $this->_oFcpoHelper->fcpoGetSessionVariable("dynvalue");

        if ($aDynData && is_array($aDynData)) {
            $aDynData["fcpo_kktype"] = null;
            $aDynData["fcpo_kknumber"] = null;
            $aDynData["fcpo_kkname"] = null;
            $aDynData["fcpo_kkmonth"] = null;
            $aDynData["fcpo_kkyear"] = null;
            $aDynData["fcpo_kkpruef"] = null;
            $aDynData["fcpo_kkcsn"] = null;
            $this->_oFcpoHelper->fcpoSetSessionVariable("dynvalue", $aDynData);
        }

        $aParameters = ['fcpo_kktype', 
    'fcpo_kknumber', 
    'fcpo_kkname', 'fcpo_kkmonth', 'fcpo_kkyear', 'fcpo_kkpruef', 'fcpo_kkcsn'];

        foreach ($aParameters as $aParameter) {
            unset($_REQUEST['dynvalue'][$aParameter]);
            unset($_POST['dynvalue'][$aParameter]);
            unset($_GET['dynvalue'][$aParameter]);
        }
    }

    /**
     * Gets config parameter
     *
     * @param string $sParam config parameter name
     *
     * @return string
     */
    public function getConfigParam($sParam)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam($sParam);
    }

    /**
     * Method checks if user should log into shop for merging
     *
     */
    public function fcpoAmazonUserLogin(): void
    {
        $blAmazonMergeUserMandatory = (bool)$this->_oFcpoHelper->fcpoGetSessionVariable('fcpoAmazonMergeUserMandatory');
        if ($blAmazonMergeUserMandatory) {
            $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
            $oUtils->redirect('index.php?cl=user');
        }

        $this->render();
    }

    /**
     * Returns matched profile
     *
     * @param string $sPaymentId
     * @return string
     */
    public function fcpoGetRatePayMatchedProfile($sPaymentId)
    {
        return $this->_aRatePayProfileIds[$sPaymentId];
    }

    /**
     * Returns matching notiication string if sofo is configured to show iban
     *
     *
     * @return bool
     */
    public function fcpoGetTrustlyShowIban()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blFCPOSofoShowIban = $oConfig->getConfigParam('blFCPOTrustlyShowIban');

        return (bool)$blFCPOSofoShowIban;
    }

    /**
     * Method checks if deprecated bankdata should be requested instead of
     * IBAN/BIC
     *
     */
    public function fcpoForceDeprecatedBankData(): bool
    {
        $oCur = $this->getActCurrency();
        $sCurrencySign = $oCur->sign;
        $sBillCountrySign = $this->fcGetBillCountry();

        return (
            $this->fcpoGetSofoShowIban() &&
            $sCurrencySign == 'CHF' &&
            $sBillCountrySign == 'CH'
        );
    }

    /**
     * Return ISO2 code of bill country
     *
     *
     * @return string
     */
    public function fcGetBillCountry()
    {
        $sBillCountryId = $this->getUserBillCountryId();
        $oCountry = $this->_oFcpoHelper->getFactoryObject('oxcountry');

        return ($oCountry->load($sBillCountryId)) ? $oCountry->oxcountry__oxisoalpha2->value : '';
    }

    /**
     * Get, and set if needed, the bill country id of the user object
     *
     * @return string
     */
    protected function getUserBillCountryId()
    {
        if ($this->_sUserBillCountryId === null) {
            $oUser = $this->getUser();
            $this->_sUserBillCountryId = $oUser->oxuser__oxcountryid->value;
        }
        return $this->_sUserBillCountryId;
    }

    /**
     * Returns matching notiication string if sofo is configured to show iban
     *
     *
     * @return bool
     */
    public function fcpoGetSofoShowIban()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blFCPOSofoShowIban = $oConfig->getConfigParam('blFCPOSofoShowIban');

        return (bool)$blFCPOSofoShowIban;
    }

    /**
     * Returns if given paymentid represents an active payment
     *
     * @param $sPaymentId
     */
    public function fcpoPaymentActive($sPaymentId): bool
    {
        $oPayment = $this->_oFcpoHelper->getFactoryObject('oxPayment');
        $oPayment->load($sPaymentId);
        $blPaymentActive = (bool)($oPayment->oxpayments__oxactive->value);
        $blPaymentAllowed = $this->isPaymentMethodAllowedByBoniCheck($oPayment);
        return ($blPaymentActive && $blPaymentAllowed);
    }

    /**
     * checks if chosen payment method is allowed according to
     * consumer score setting
     *
     * @param $oPayment
     * @return bool
     */
    public function isPaymentMethodAllowedByBoniCheck($oPayment)
    {
        $oUser = $this->_fcpoGetUserFromSession();
        return ((int)$oPayment->oxpayments__oxfromboni->value <= (int)$oUser->oxuser__oxboni->value);
    }

    /**
     * Fetches current user from session and returns user object or false
     *
     *
     * @return mixed object/bool
     */
    protected function _fcpoGetUserFromSession()
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();

        return $oBasket->getBasketUser();
    }

    /**
     * Method decides if certain paymentid is of newer klarna type,
     * the currency and country is supported and
     * the combined widget already has been displayed.
     *
     * @param $sPaymentId
     */
    public function fcpoShowKlarnaCombined($sPaymentId): bool
    {
        $blIsKlarnaCombined = $this->fcpoIsKlarnaCombined($sPaymentId);
        $blIsCountryCurrencyAllowedByKlarna = $this->_fcpoIsCountryCurrencyAllowedByKlarna();
        if (
            $blIsKlarnaCombined &&
            $blIsCountryCurrencyAllowedByKlarna &&
            !$this->_blKlarnaCombinedIsPresent
        ) {
            $this->_blKlarnaCombinedIsPresent = true;
            return true;
        }

        return false;
    }

    /**
     * Checks if given payment id is of type of new klarna
     * implementation
     *
     * @param $sPaymentId
     */
    public function fcpoIsKlarnaCombined($sPaymentId): bool
    {
        return (
        in_array($sPaymentId, ['fcpoklarna_invoice', 'fcpoklarna_directdebit', 'fcpoklarna_installments'])
        );
    }

    /**
     * Checks if klarna support the user's billing country with the active shop currency for payments.
     */
    protected function _fcpoIsCountryCurrencyAllowedByKlarna(): bool
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        $sCountryIso = $oUser->fcpoGetUserCountryIso();

        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oActCurrency = $oConfig->getActShopCurrencyObject();
        $sCurrencyName = $oActCurrency->name;

        $aAllowedCountryCurrencies = ['AT' => 'EUR', 'DK' => 'DKK', 'FI' => 'EUR', 'DE' => 'EUR', 'NL' => 'EUR', 'NO' => 'NOK', 'SE' => 'SEK', 'CH' => 'CHF'];

        return ($aAllowedCountryCurrencies[$sCountryIso] === $sCurrencyName);
    }

    /**
     * Method checks if current ratepay payment has a matching profile based on activity and basket values
     *
     * @param string $sPaymentId
     * @return void
     */
    public function fcpoRatePayAllowed($sPaymentId)
    {
        $aMatchingRatePayProfile = $this->_fcpoGetMatchingProfile($sPaymentId);
        $blReturn = false;
        if ($aMatchingRatePayProfile !== []) {
            $blReturn = true;
        }

        if ($blReturn) {
            $this->_aRatePayProfileIds[$sPaymentId] = $aMatchingRatePayProfile['OXID'];
        }

        return $blReturn;
    }

    /**
     * Will return first matching profile or empty array
     *
     * @param string $sPaymentId
     * @return array
     * @todo: The whole process of matching a profile should be moved into ratepay-model
     */
    protected function _fcpoGetMatchingProfile($sPaymentId)
    {
        $aRatePayProfiles = $this->_fcpoFetchRatePayProfilesByPaymentType($sPaymentId);
        $aReturn = [];

        foreach ($aRatePayProfiles as $aRatePayProfile) {
            $sPaymentStringAddition = $this->_fcpoGetRatePayStringAdditionByPaymentId($sPaymentId);
            if ($sPaymentStringAddition !== '' && $sPaymentStringAddition !== '0') {
                $sProfileBasketMaxIndex = 'tx_limit_' . $sPaymentStringAddition . '_max';
                $sProfileBasketMinIndex = 'tx_limit_' . $sPaymentStringAddition . '_min';
                $sProfileActivationStatusIndex = 'activation_status_' . $sPaymentStringAddition;
                $dProfileBasketValueMax = (double)$aRatePayProfile[$sProfileBasketMaxIndex];
                $dProfileBasketValueMin = (double)$aRatePayProfile[$sProfileBasketMinIndex];
                $sProfileActivationStatus = $aRatePayProfile[$sProfileActivationStatusIndex];
                $sProfileCountryBilling = $aRatePayProfile['country_code_billing'];
                $sProfileCurrency = $aRatePayProfile['currency'];

                $aRatepayMatchData = ['basketvalue_max' => $dProfileBasketValueMax, 'basketvalue_min' => $dProfileBasketValueMin, 'activation_status' => $sProfileActivationStatus, 'country_code_billing' => $sProfileCountryBilling, 'currency' => $sProfileCurrency];

                $blProfileMatches = $this->_fcpoCheckRatePayProfileMatch($aRatepayMatchData);
                if ($blProfileMatches) {
                    $aReturn = $aRatePayProfile;
                    break;
                }
            }
        }

        return $aReturn;
    }

    /**
     * Returns all profiles for given Ratepay payment type
     *
     * @param string $sPaymentId
     * @return array
     */
    protected function _fcpoFetchRatePayProfilesByPaymentType($sPaymentId)
    {
        $oRatePay = oxNew(FcPoRatepay::class);

        return $oRatePay->fcpoGetRatePayProfiles($sPaymentId);
    }

    /**
     * Returns string part, that matches right profile values
     *
     * @param string $sPaymentId
     * @return string
     */
    protected function _fcpoGetRatePayStringAdditionByPaymentId($sPaymentId)
    {
        $aMap = ['fcporp_bill' => 'invoice', 'fcporp_debitnote' => 'elv'];

        $sReturn = '';
        if (isset($aMap[$sPaymentId])) {
            $sReturn = $aMap[$sPaymentId];
        }

        return $sReturn;
    }

    /**
     * Checks values for matching profile data
     *
     * @param array $aRatepayMatchData
     */
    protected function _fcpoCheckRatePayProfileMatch($aRatepayMatchData): bool
    {
        if ($aRatepayMatchData['activation_status'] != '2') {
            return false;
        }

        $decimal = $this->fcpoGetBasketSum();
        $blBasketValueMatches = (
            $decimal <= $aRatepayMatchData['basketvalue_max'] &&
            $decimal >= $aRatepayMatchData['basketvalue_min']
        );
        if (!$blBasketValueMatches) {
            return false;
        }

        $sBillCountry = $this->fcGetBillCountry();
        $blCountryMatches = (
            $sBillCountry == $aRatepayMatchData['country_code_billing']
        );
        if (!$blCountryMatches) {
            return false;
        }

        $oCur = $this->getActCurrency();
        return $oCur->name == $aRatepayMatchData['currency'];
    }

    /**
     * Returns the sum of basket
     *
     *
     * @return decimal
     */
    public function fcpoGetBasketSum(): string
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sShopVersion = $oConfig->getVersion();
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $dBruttoSum = (version_compare($sShopVersion, '4.7.0', '>=')) ? $oBasket->getBruttoSum() : $oBasket->getProductsPrice()->getBruttoSum();

        return number_format($dBruttoSum, 2, ',', '.');
    }

    /**
     * Check if there are available sub payment types for the user
     *
     * @param string $sType payment type PAYONE
     */
    public function hasPaymentMethodAvailableSubTypes($sType): bool
    {
        $aSubtypes = ['cc' => [$this->getVisa(), $this->getMastercard(), $this->getAmex(), $this->getDiners(), $this->getJCB(), $this->getMaestroInternational(), $this->getMaestroUK(), $this->getCarteBleue()], 'sb' => [$this->getSofortUeberweisung(), $this->getGiropay(), $this->getPostFinanceEFinance(), $this->getPostFinanceCard(), $this->getIdeal(), $this->getP24(), $this->getBancontact()]];

        return in_array(true, $aSubtypes[$sType]);
    }

    /**
     * Check if sub payment method Visa is available to the user
     */
    public function getVisa(): bool
    {
        return ($this->getConfigParam('blFCPOVisaActivated') && $this->isPaymentMethodAvailableToUser('V', 'cc'));
    }

    /**
     * Check if the user is allowed to use the given payment method
     *
     * @param string $sSubPaymentId ID of the sub payment method ( Visa, MC, etc. )
     * @param string $sType         payment type PAYONE
     *
     * @return bool
     */
    protected function isPaymentMethodAvailableToUser($sSubPaymentId, $sType)
    {
        if (!array_key_exists($sSubPaymentId . '_' . $sType, $this->_aCheckedSubPayments)) {
            $sUserBillCountryId = $this->getUserBillCountryId();
            $sUserDelCountryId = $this->getUserDelCountryId();
            $oPayment = oxNew('oxPayment');
            $this->_aCheckedSubPayments[$sSubPaymentId . '_' . $sType] = $oPayment->isPaymentMethodAvailableToUser($sSubPaymentId, $sType, $sUserBillCountryId, $sUserDelCountryId);
        }
        return $this->_aCheckedSubPayments[$sSubPaymentId . '_' . $sType];
    }

    /**
     * Get, and set if needed, the delivery country id if existant
     *
     * @return string
     */
    protected function getUserDelCountryId()
    {
        if ($this->_sUserDelCountryId === null) {
            $oOrder = $this->_oFcpoHelper->getFactoryObject('oxorder');
            $oDelAddress = $oOrder->getDelAddressInfo();
            $sUserDelCountryId = false;
            if ($oDelAddress !== null) {
                $sUserDelCountryId = $oDelAddress->oxaddress__oxcountryid->value;
            }
            $this->_sUserDelCountryId = $sUserDelCountryId;
        }
        return $this->_sUserDelCountryId;
    }

    /**
     * Check if sub payment method Mastercard is available to the user
     */
    public function getMastercard(): bool
    {
        return ($this->getConfigParam('blFCPOMastercardActivated') && $this->isPaymentMethodAvailableToUser('M', 'cc'));
    }

    /**
     * Check if sub payment method Amex is available to the user
     */
    public function getAmex(): bool
    {
        return ($this->getConfigParam('blFCPOAmexActivated') && $this->isPaymentMethodAvailableToUser('A', 'cc'));
    }

    /**
     * Check if sub payment method Diners is available to the user
     */
    public function getDiners(): bool
    {
        return ($this->getConfigParam('blFCPODinersActivated') && $this->isPaymentMethodAvailableToUser('D', 'cc'));
    }

    /**
     * Check if sub payment method JCB is available to the user
     */
    public function getJCB(): bool
    {
        return ($this->getConfigParam('blFCPOJCBActivated') && $this->isPaymentMethodAvailableToUser('J', 'cc'));
    }

    /**
     * Check if sub payment method MaestroInternational is available to the user
     */
    public function getMaestroInternational(): bool
    {
        return ($this->getConfigParam('blFCPOMaestroIntActivated') && $this->isPaymentMethodAvailableToUser('O', 'cc'));
    }

    /**
     * Check if sub payment method MaestroUK is available to the user
     */
    public function getMaestroUK(): bool
    {
        return ($this->getConfigParam('blFCPOMaestroUKActivated') && $this->isPaymentMethodAvailableToUser('U', 'cc'));
    }

    /**
     * Check if sub payment method CarteBleue is available to the user
     */
    public function getCarteBleue(): bool
    {
        return ($this->getConfigParam('blFCPOCarteBleueActivated') && $this->isPaymentMethodAvailableToUser('B', 'cc'));
    }

    /**
     * Check if sub payment method SofortUeberweisung is available to the user
     */
    public function getSofortUeberweisung(): bool
    {
        return ($this->getConfigParam('blFCPOSofoActivated') && $this->isPaymentMethodAvailableToUser('PNT', 'sb'));
    }

    /**
     * Check if sub payment method Giropay is available to the user
     */
    public function getGiropay(): bool
    {
        return ($this->getConfigParam('blFCPOgiroActivated') && $this->isPaymentMethodAvailableToUser('GPY', 'sb'));
    }

    /**
     * Check if sub payment method PostFinanceEFinance is available to the user
     */
    public function getPostFinanceEFinance(): bool
    {
        return ($this->getConfigParam('blFCPOPoFiEFActivated') && $this->isPaymentMethodAvailableToUser('PFF', 'sb'));
    }

    /*
     * Return language id
     *
     * @return int
     */
    /**
     * Check if sub payment method PostFinanceCard is available to the user
     */
    public function getPostFinanceCard(): bool
    {
        return ($this->getConfigParam('blFCPOPoFiCaActivated') && $this->isPaymentMethodAvailableToUser('PFC', 'sb'));
    }

    /**
     * Check if sub payment method Ideal is available to the user
     */
    public function getIdeal(): bool
    {
        return ($this->getConfigParam('blFCPOiDealActivated') && $this->isPaymentMethodAvailableToUser('IDL', 'sb'));
    }

    /**
     * Check if sub payment method Przelewy24 is available to the user
     */
    public function getP24(): bool
    {
        return ($this->getConfigParam('blFCPOP24Activated') && $this->isPaymentMethodAvailableToUser('P24', 'sb'));
    }

    /**
     * Check if sub payment method Bancontact is available to the user
     */
    public function getBancontact(): bool
    {
        return ($this->getConfigParam('blFCPOBCTActivated') && $this->isPaymentMethodAvailableToUser('BCT', 'sb'));
    }

    /**
     * Get the basket brut price in the smallest unit of the currency
     *
     * @return int
     */
    public function getAmount()
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oPrice = $oBasket->getPrice();
        $bruttoPrice = $oPrice->getBruttoPrice();

        return number_format($bruttoPrice, 2, '.', '') * 100;
    }

    /**
     * Get the language the user is using in the shop
     *
     * @return string
     */
    public function getTplLang()
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        return $oLang->getLanguageAbbr();
    }

    /**
     * Template getter for delivering all meta information to build input fields in foreach loop
     *
     *
     * @return array
     */
    public function fcpoGetCCPaymentMetaData()
    {
        $this->_aPaymentCCMetaData = [];
        $sPaymentId = 'fcpocreditcard';

        $oPayment = oxNew('oxpayment');
        $oPayment->load($sPaymentId);

        $this->_fcpoSetCCMetaData($oPayment, 'V', 'Visa');
        $this->_fcpoSetCCMetaData($oPayment, 'M', 'Mastercard');
        $this->_fcpoSetCCMetaData($oPayment, 'A', 'American Express');
        $this->_fcpoSetCCMetaData($oPayment, 'D', 'Diners Club');
        $this->_fcpoSetCCMetaData($oPayment, 'J', 'JCB');
        $this->_fcpoSetCCMetaData($oPayment, 'O', 'Maestro International');
        $this->_fcpoSetCCMetaData($oPayment, 'U', 'Maestro UK');
        $this->_fcpoSetCCMetaData($oPayment, 'B', 'Carte Bleue');

        return $this->_aPaymentCCMetaData;
    }

    /**
     * Sets cc meta payment data
     *
     * @param oxPayment $oPayment
     * @param string    $sBrandShortcut
     * @param string    $sBrandName
     */
    protected function _fcpoSetCCMetaData($oPayment, $sBrandShortcut, $sBrandName)
    {
        $aActiveCCBrands = ['V' => $this->getVisa(), 'M' => $this->getMastercard(), 'A' => $this->getAmex(), 'D' => $this->getDiners(), 'J' => $this->getJCB(), 'O' => $this->getMaestroInternational(), 'U' => $this->getMaestroUK(), 'B' => $this->getCarteBleue()];

        if ($aActiveCCBrands[$sBrandShortcut]) {
            $this->_aPaymentCCMetaData[] = $this->_fcpoGetCCPaymentMetaData($oPayment, $sBrandShortcut, $sBrandName);
        }
    }

    /**
     * Returns a payment meta data object for payment method and its payment-tag
     *
     * @param object $oPayment
     * @param string $sPaymentTag
     * @return object
     */
    protected function _fcpoGetCCPaymentMetaData($oPayment, $sPaymentTag, $sPaymentName): stdClass
    {
        $sPaymentId = $oPayment->getId();
        $sHashNamePrefix = "fcpo_hashcc_";
        $OperationModeNamePrefix = "fcpo_mode_";
        $aDynValue = $this->getDynValue();
        $blSelected = $aDynValue['fcpo_kktype'] == $sPaymentTag;

        $oPaymentMetaData = new stdClass();
        $oPaymentMetaData->sHashName = $sHashNamePrefix . $sPaymentTag;
        $oPaymentMetaData->sHashValue = $this->getHashCC($sPaymentTag);
        $oPaymentMetaData->sOperationModeName = $OperationModeNamePrefix . $sPaymentId . "_" . $sPaymentTag;
        $oPaymentMetaData->sOperationModeValue = $oPayment->fcpoGetOperationMode($sPaymentTag);
        $oPaymentMetaData->sPaymentTag = $sPaymentTag;
        $oPaymentMetaData->sPaymentName = $sPaymentName;
        $oPaymentMetaData->blSelected = $blSelected;

        return $oPaymentMetaData;
    }

    /**
     * Template variable getter. Returns dyn values
     *
     *
     * @return array
     */
    public function getDynValue()
    {
        if ((bool)$this->getConfigParam('sFCPOSaveBankdata')) {
            $aPaymentList = $this->getPaymentList();
            if (isset($aPaymentList['fcpodebitnote'])) {
                $this->_assignDebitNoteParams();
            }
        }
        return $this->_aDynValue;
    }

    /**
     * Extends oxid standard method getPaymentList
     * Extends it with the creditworthiness check for the user
     *
     * @return string
     * @extend getPaymentList
     */
    public function getPaymentList()
    {
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoordernotchecked');
        if ($this->_oPaymentList === null) {
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $oUser = $this->getUser();
            $sBoniCheckMoment = $oConfig->getConfigParam('sFCPOBonicheckMoment');

            if ($oUser) {
                $blContinue = $sBoniCheckMoment != 'after' ? $oUser->checkAddressAndScore() : $oUser->checkAddressAndScore(true, false);
            } else {
                $blContinue = true;
            }

            if ($blContinue === true) {
                $this->_fcpoCheckPaypalExpressRemoval();
                $this->_fcpoRemoveForbiddenPaymentsByUser();
            } else {
                $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
                $oUtils->redirect($this->_oFcpoHelper->fcpoGetConfig()->getShopHomeURL() . 'cl=user', false);
            }
        }
        return $this->_oPaymentList;
    }

    /**
     * Checking if paypal express should be removed from payment list
     *
     *
     * @return void
     */
    protected function _fcpoCheckPaypalExpressRemoval()
    {
        $this->_fcpoRemovePaymentFromFrontend('fcpopaypal_express');
        //&& !$this->_oFcpoHelper->fcpoGetSessionVariable('fcpoWorkorderId')
    }

    /**
     * Removes payment from frontend
     *
     * @param $sPaymentId
     * @return void
     */
    protected function _fcpoRemovePaymentFromFrontend($sPaymentId)
    {
        if (array_key_exists($sPaymentId, $this->_oPaymentList)) {
            unset($this->_oPaymentList[$sPaymentId]);
        }
    }

    /**
     * Removes payments that are forbidden by user
     *
     *
     * @return void
     */
    protected function _fcpoRemoveForbiddenPaymentsByUser()
    {
        $oUser = $this->getUser();
        $aForbiddenPaymentIds = $oUser->fcpoGetForbiddenPaymentIds();
        foreach ($aForbiddenPaymentIds as $aForbiddenPaymentId) {
            $this->_fcpoRemovePaymentFromFrontend($aForbiddenPaymentId);
        }
    }

    /**
     * Assign debit note payment values to view data. Loads user debit note payment
     * if available and assigns payment data to $this->_aDynValue
     *
     *
     * @return null
     */
    protected function _assignDebitNoteParams()
    {
        //such info available ?
        if ((bool)$this->getConfigParam('sFCPOSaveBankdata') && ($oUserPayment = $this->_fcGetPaymentByPaymentType($this->getUser(), 'fcpodebitnote'))) {
            $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
            $aAddPaymentData = $oUtils->assignValuesFromText($oUserPayment->oxuserpayments__oxvalue->value);
            //checking if some of values is allready set in session - leave it
            foreach ($aAddPaymentData as $oData) {
                if (!isset($this->_aDynValue[$oData->name]) || (isset($this->_aDynValue[$oData->name]) && !$this->_aDynValue[$oData->name])) {
                    $this->_aDynValue[$oData->name] = $oData->value;
                }
            }
        }
    }

    /**
     * Get user payment by payment id with oxid bugfix for getting last payment
     *
     * @param oxUser $oUser        user object
     * @param string $sPaymentType payment type
     *
     * @return bool
     */
    protected function _fcGetPaymentByPaymentType($oUser = null, $sPaymentType = null)
    {
        $oUserPayment = null;
        $mReturn = false;
        if ($oUser && $sPaymentType != null) {
            $oPayment = $this->_oFcpoHelper->getFactoryObject('oxPayment');
            $sOxid = $oPayment->fcpoGetUserPaymentId($oUser->getId(), $sPaymentType);

            if ($sOxid) {
                $oUserPayment = $this->_oFcpoHelper->getFactoryObject('oxuserpayment');
                $oUserPayment->load($sOxid);
                $mReturn = $oUserPayment;
            }
        }

        return $oUserPayment;
    }

    /**
     * Get verification safety hash for creditcard payment method
     *
     * @return string
     */
    public function getHashCC($sType = '')
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOHashMethod = $oConfig->getConfigParam('sFCPOHashMethod');
        $sKey = $this->getPortalKey();

        $sData =
            $this->getSubAccountId() .
            $this->getEncoding() .
            $this->getMerchantId() .
            $this->_getOperationModeCC($sType) .
            $this->getPortalId() .
            'creditcardcheck' .
            'JSON' .
            'yes';

        $sHashMD5 = md5($sData . $sKey);
        $sHashSha2 = hash_hmac('sha384', $sData, $sKey);

        return ($sFCPOHashMethod == 'sha2-384')
            ? $sHashSha2 : $sHashMD5;
    }

    /**
     * Get config parameter sFCPOPortalKey
     *
     * @return string
     */
    public function getPortalKey()
    {
        return $this->getConfigParam('sFCPOPortalKey');
    }

    /**
     * Get config parameter sFCPOSubAccountID
     *
     * @return string
     */
    public function getSubAccountId()
    {
        return $this->getConfigParam('sFCPOSubAccountID');
    }

    /**
     * Get encoding of the shop
     */
    public function getEncoding(): string
    {
        return 'UTF-8';
    }

    /**
     * Get config parameter sFCPOMerchantID
     *
     * @return string
     */
    public function getMerchantId()
    {
        return $this->getConfigParam('sFCPOMerchantID');
    }

    /**
     * Get configured operation mode ( live or test ) for creditcard
     *
     * @param string $sType sub payment type PAYONE
     *
     * @return string
     */
    protected function _getOperationModeCC($sType = '')
    {
        $oPayment = oxNew('oxpayment');
        $oPayment->load('fcpocreditcard');
        return $oPayment->fcpoGetOperationMode($sType);
    }

    /**
     * Get config parameter sFCPOPortalID
     *
     * @return string
     */
    public function getPortalId()
    {
        return $this->getConfigParam('sFCPOPortalID');
    }

    /**
     * Template getter for delivering payment meta data of online payments
     *
     *
     * @return never[]|object[]
     */
    public function fcpoGetOnlinePaymentMetaData(): array
    {
        $aPaymentMetaData = [];

        if ($this->getSofortUeberweisung()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('PNT');
        }
        if ($this->getGiropay()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('GPY');
        }
        if ($this->getEPS()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('EPS');
        }
        if ($this->getPostFinanceEFinance()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('PFF');
        }
        if ($this->getPostFinanceCard()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('PFC');
        }
        if ($this->getIdeal()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('IDL');
        }
        if ($this->getP24()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('P24');
        }
        if ($this->getBancontact()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('BCT');
        }

        return $aPaymentMetaData;
    }

    /**
     * Returns online payment meta data object for ident
     *
     * @param string $sIdent
     * @return object
     */
    protected function _fcpoGetOnlinePaymentData($sIdent): stdClass
    {
        $aDynValue = $this->getDynValue();
        $blSelected = $aDynValue['fcpo_sotype'] == $sIdent;

        $aCaptions = ['PNT' => 'SOFORT &Uuml;berweisung', 'GPY' => 'giropay', 'EPS' => 'eps - Online-&Uuml;berweisung', 'PFF' => 'PostFinance E-Finance', 'PFC' => 'PostFinance Card', 'IDL' => 'iDeal', 'P24' => 'P24', 'BCT' => 'Bancontact'];

        $sCaption = ($aCaptions[$sIdent] !== '' && $aCaptions[$sIdent] !== '0') ? $aCaptions[$sIdent] : '';

        $oPaymentMetaData = new stdClass();
        $oPaymentMetaData->sShortcut = $sIdent;
        $oPaymentMetaData->sCaption = $sCaption;
        $oPaymentMetaData->blSelected = $blSelected;

        return $oPaymentMetaData;
    }

    /**
     * Check if sub payment method EPS is available to the user
     */
    public function getEPS(): bool
    {
        return ($this->getConfigParam('blFCPOepsActivated') && $this->isPaymentMethodAvailableToUser('EPS', 'sb'));
    }

    /**
     * Method returns active theme path by checking current theme and its parent
     * If theme is not assignable, 'azure' will be the fallback
     *
     *
     * @return string
     */
    public function fcpoGetActiveThemePath()
    {
        $oViewConfig = $this->_oFcpoHelper->getFactoryObject('oxViewConfig');

        return $oViewConfig->fcpoGetActiveThemePath();
    }

    /**
     * Get verification safety hash for debitnote payment method with checktype parameter
     */
    public function getHashELVWithChecktype(): string
    {
        return md5(
            $this->getSubAccountId() .
            $this->getChecktype() .
            $this->getEncoding() .
            $this->getMerchantId() .
            $this->_getOperationModeELV() .
            $this->getPortalId() .
            'bankaccountcheck' .
            'JSON' .
            $this->getPortalKey()
        );
    }

    /**
     * Get config parameter sFCPOPOSCheck
     *
     * @return string
     */
    public function getChecktype()
    {
        return $this->getConfigParam('sFCPOPOSCheck');
    }

    /**
     * Get configured operation mode ( live or test ) for debitnote payment method
     *
     * @return string
     */
    protected function _getOperationModeELV()
    {
        $oPayment = $this->_oFcpoHelper->getFactoryObject('oxpayment');
        $oPayment->load('fcpodebitnote');
        return $oPayment->fcpoGetOperationMode();
    }

    /**
     * Get verification safety hash for debitnote payment method without checktype parameter
     */
    public function getHashELVWithoutChecktype(): string
    {
        return md5(
            $this->getSubAccountId() .
            $this->getEncoding() .
            $this->getMerchantId() .
            $this->_getOperationModeELV() .
            $this->getPortalId() .
            'bankaccountcheck' .
            'JSON' .
            $this->getPortalKey()
        );
    }

    /**
     * Returns if CVC
     *
     *
     * @return bool
     */
    public function fcpoUseCVC()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        return $oConfig->getConfigParam('blFCPOCCUseCvc');
    }

    /**
     * Returns if option for BIC is set mandatory as string to handle it with javascript checks
     *
     *
     * @return string
     */
    public function fcpoGetBICMandatory()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blFCPODebitBICMandatory = $oConfig->getConfigParam('blFCPODebitBICMandatory');

        return ($blFCPODebitBICMandatory) ? 'true' : 'false';
    }

    /**
     * Returns creditcard type
     *
     *
     * @return mixed
     */
    public function fcpoGetCreditcardType()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOCCType');
    }

    /**
     * Method will be triggered by amazon checkout. Will make sure that paymentid is set to
     * amazon payment
     *
     *
     * @return void
     */
    public function validateAmazonPayment(): string
    {
        $oSession = $this->getSession();
        $oBasket = $oSession->getBasket();

        $this->_oFcpoHelper->fcpoDeleteSessionVariable('paymentid');
        $this->_oFcpoHelper->fcpoSetSessionVariable('paymentid', 'fcpoamazonpay');
        $oBasket->setPayment('fcpoamazonpay');

        return 'order';
    }

    /**
     * Extends oxid standard method validatePayment
     * Extends it with the creditworthiness check for the user
     *
     * Validates oxidcreditcard and oxiddebitnote user payment data.
     * Returns null if problems on validating occured. If everything
     * is OK - returns "order" and redirects to payment confirmation
     * page.
     *
     * Session variables:
     * <b>paymentid</b>, <b>dynvalue</b>, <b>payerror</b>
     *
     * @return mixed
     */
    public function validatePayment()
    {
        $sPaymentId = $this->_fcpoGetPaymentId();
        $this->_fcpoCheckKlarnaUpdateUser($sPaymentId);

        $mReturn = null;

        $mReturn = $this->_processParentReturnValue($mReturn);

        return $this->_fcpoProcessValidation($mReturn, $sPaymentId);
    }

    /**
     * Returns paymentid wether from request parameter or session
     *
     *
     * @return mixed
     */
    protected function _fcpoGetPaymentId()
    {
        $sPaymentId = $this->_oFcpoHelper->fcpoGetRequestParameter('paymentid');
        if (!$sPaymentId) {
            $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');
        }

        return $sPaymentId;
    }

    /**
     * Triggers updating user if klarna payment has been recognized
     *
     * @param string $sPaymentId
     * @return void
     */
    protected function _fcpoCheckKlarnaUpdateUser($sPaymentId)
    {
        $oUser = $this->getUser();
        if ($oUser && ($sPaymentId == 'fcpoklarna')) {
            $this->_fcpoKlarnaUpdateUser();
        }
    }

    /**
     * Update klarna user
     *
     *
     * @return void
     */
    protected function _fcpoKlarnaUpdateUser()
    {
        $oUser = $this->getUser();
        $blUserChanged = false;
        $aDynValue = $this->getDynValue();
        $sPaymentId = $this->_fcpoGetPaymentId();
        $sType = $this->_fcpoGetType($sPaymentId);

        $blUserChanged = $this->_fcpoCheckUpdateField($blUserChanged, $sType, $aDynValue, 'oxfon', 'fon', $oUser);
        $blUserChanged = $this->_fcpoCheckUpdateField($blUserChanged, $sType, $aDynValue, 'oxbirthdate', 'birthday', $oUser);
        $blUserChanged = $this->_fcpoCheckUpdateField($blUserChanged, $sType, $aDynValue, 'fcpopersonalid', 'personalid', $oUser);
        $blUserChanged = $this->_fcpoCheckUpdateField($blUserChanged, $sType, $aDynValue, 'oxsal', 'sal', $oUser);
        $blUserChanged = $this->_fcpoCheckUpdateField($blUserChanged, $sType, $aDynValue, 'oxaddinfo', 'addinfo', $oUser);

        if (array_key_exists('fcpo_' . $sType . '_del_addinfo', $aDynValue)) {
            $sDeliveryAddressId = $oUser->getSelectedAddressId();
            if ($sDeliveryAddressId) {
                $oAddress = $this->_oFcpoHelper->getFactoryObject('oxaddress');
                if ($oAddress->load($sDeliveryAddressId)) {
                    $oAddress->oxaddress__oxaddinfo = new oxField($aDynValue['fcpo_' . $sType . '_del_addinfo'], oxField::T_RAW);
                    $oAddress->save();
                }
            }
        }

        if ($blUserChanged) {
            $oUser->save();
        }
    }

    /**
     * Set payment type and process special case of klarna
     *
     */
    protected function _fcpoGetType($sPaymentId): string
    {
        return 'klv';
    }

    /**
     * Adds new value to user object and return the changed status
     *
     * @param boolean $blUserChanged
     * @param string  $sType
     * @param array   $aDynValue
     * @param string  $sDbField
     * @param string  $sDynValueField
     * @param oxUser  $oUser
     * @return boolean
     */
    protected function _fcpoCheckUpdateField($blUserChanged, $sType, $aDynValue, $sDbField, $sDynValueField, $oUser)
    {
        $blAlreadyChanged = $blUserChanged;
        $sCompleteDynValueName = 'fcpo_' . $sType . '_' . $sDynValueField;

        if (array_key_exists($sCompleteDynValueName, $aDynValue)) {
            $sObjAttribute = 'oxuser__' . $sDbField;

            $oUser->$sObjAttribute = new oxField($aDynValue[$sCompleteDynValueName], oxField::T_RAW);
            $blUserChanged = true;
        }

        return $blAlreadyChanged ?: $blUserChanged;
    }

    /**
     * Hook for processing a return value
     *
     * @param string $sReturn
     * @return string
     */
    protected function _processParentReturnValue($sReturn)
    {
        return $sReturn;
    }

    /**
     * Extension of validation, which takes care on specific payone payments
     *
     * @param string $sPaymentId
     * @return mixed
     */
    protected function _fcpoProcessValidation(mixed $mReturn, $sPaymentId)
    {
        if ($sPaymentId == 'fcpoamazonpay') {
            $mReturn = 'order';
        }

        if ($mReturn == 'order') { // success
            $this->_fcpoSetKlarnaCampaigns();

            $oPayment = $this->_oFcpoHelper->getFactoryObject('oxpayment');
            $oPayment->load($sPaymentId);
            $mReturn = $this->_fcpoSecInvoiceSaveRequestedValues($mReturn, $sPaymentId);
            $blContinue = $this->_fcpoCheckBoniMoment($oPayment);

            if (!$blContinue) {
                $this->_fcpoSetBoniErrorValues($sPaymentId);
                $mReturn = 'basket';
            } else {
                $this->_fcpoSetMandateParams($oPayment);
            }

            $this->_fcCleanupSessionFragments($oPayment);

            $mReturn = $this->_fcpoKlarnaCombinedValidate($mReturn, $sPaymentId);

            $mReturn = $this->_fcpoPayolutionPreCheck($mReturn, $sPaymentId);
            if (in_array($sPaymentId, ['fcporp_bill', 'fcporp_debitnote'])) {
                $mReturn = $this->_fcpoCheckRatePayBillMandatoryUserData($mReturn, $sPaymentId);
            }
            $mReturn = $this->_fcpoAdultCheck($mReturn, $sPaymentId);
        }

        return $mReturn;
    }

    /**
     * Sets needed session values if there is corresponding data
     *
     *
     * @return void
     */
    protected function _fcpoSetKlarnaCampaigns()
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('fcpo_klarna_campaign')) {
            $this->_oFcpoHelper->fcpoSetSessionVariable('fcpo_klarna_campaign', $this->_oFcpoHelper->fcpoGetRequestParameter('fcpo_klarna_campaign'));
        } else {
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpo_klarna_campaign');
        }
    }

    /**
     * Save requested values of secure invoice
     *
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoSecInvoiceSaveRequestedValues(mixed $mReturn, $sPaymentId)
    {
        $blIsSecInvoice = ($sPaymentId == 'fcpo_secinvoice');
        if (!$blIsSecInvoice) {
            return $mReturn;
        }

        $aRequestedValues =
            $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');
        $aBirthdayValidation =
            $this->_fcpoValidateBirthdayData($sPaymentId, $aRequestedValues);
        $blBirthdayRequired = $aBirthdayValidation['blBirthdayRequired'];

        $blBirthdayCheckPassed = true;
        if ($blBirthdayRequired) {
            $blBirthdayCheckPassed =
                $this->_fcpoSaveBirthdayData($aRequestedValues, $sPaymentId);
        }

        $this->_fcpoSaveUserData($sPaymentId, 'oxustid');

        return ($blBirthdayCheckPassed) ? $mReturn : false;
    }

    /**
     * Checks request data for valid birthday data
     *
     * @param string $sPaymentId
     * @param        $aRequestedValues
     * @return array{blValidBirthdateData: bool, blBirthdayRequired: bool}
     */
    protected function _fcpoValidateBirthdayData($sPaymentId, $aRequestedValues): array
    {
        $blBirthdayRequired = false;

        // validation
        switch ($sPaymentId) {
            case 'fcpopo_bill':
            case 'fcpopo_debitnote':
            case 'fcpopo_installment':
                $blBirthdayRequired = $this->fcpoShowPayolutionB2C();
                $blValidBirthdateData = $this->_fcpoValidatePayolutionBirthdayData($sPaymentId, $aRequestedValues);
                break;
            case 'fcpo_secinvoice':
                $blBirthdayRequired = !$this->fcpoIsB2BPov();
                $blValidBirthdateData = $this->_fcpoValidateSecInvoiceBirthdayData($sPaymentId, $aRequestedValues);
                break;
        }

        return ['blValidBirthdateData' => $blValidBirthdateData, 'blBirthdayRequired' => $blBirthdayRequired];
    }

    /**
     * Template getter for checking which kind of field should be shown
     *
     *
     * @return bool
     */
    public function fcpoShowPayolutionB2C()
    {
        $blB2BIsShown = $this->fcpoShowPayolutionB2B();

        return !$blB2BIsShown;
    }

    /**
     * Template getter for checking which kind of field should be shown
     *
     */
    public function fcpoShowPayolutionB2B(): bool
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blB2BModeActive = $oConfig->getConfigParam('blFCPOPayolutionB2BMode');

        return $blB2BModeActive && $this->fcpoIsB2B();
    }

    /**
     * Generic method for determine if order is b2b
     * Can be optionally used in strict mode
     *
     * @param $blStrict
     * @return bool
     */
    public function fcpoIsB2B($blStrict = false)
    {
        $oUser = $this->getUser();

        $blStrictConditions = (
            $oUser->oxuser__oxcompany->value &&
            $oUser->oxuser__oxustid->value
        );

        $blNormalConditions = (
            $oUser->oxuser__oxcompany->value ||
            $oUser->oxuser__oxustid->value
        );

        return ($blStrict) ? $blStrictConditions : $blNormalConditions;
    }

    /**
     * Checks request data to be valid birthday data for payolution
     *
     * @param string $sPaymentId
     * @param array  $aRequestedValues
     * @return boolean
     */
    protected function _fcpoValidatePayolutionBirthdayData($sPaymentId, $aRequestedValues)
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sChooseString = $oLang->translateString('FCPO_PAYOLUTION_PLEASE SELECT');
        $sFieldNameAddition = str_replace("fcpopo_", "", $sPaymentId);
        $sBirthdateYear = $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_birthdate_year'];
        $sBirthdateMonth = $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_birthdate_month'];
        $sBirthdateDay = $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_birthdate_day'];
        $blValidPayments = in_array($sPaymentId, ['fcpopo_bill', 'fcpopo_installment', 'fcpopo_debitnote']);
        $blValidRequestYear = ((isset($sBirthdateYear) && !empty($sBirthdateYear) && $sBirthdateYear != $sChooseString));
        $blValidRequestMonth = ((isset($sBirthdateMonth) && !empty($sBirthdateMonth) && $sBirthdateMonth != $sChooseString));
        $blValidRequestDay = ((isset($sBirthdateDay) && !empty($sBirthdateDay) && $sBirthdateDay != $sChooseString));

        $blValidRequestData = ($blValidRequestYear && $blValidRequestMonth && $blValidRequestDay);

        $blReturn = false;

        if ($blValidPayments && $blValidRequestData) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Generic method for determine if order is b2b
     * Used by pov / rec
     *
     * @return bool
     */
    public function fcpoIsB2BPov()
    {
        $oUser = $this->getUser();
        return !empty($oUser->oxuser__oxcompany->value);
    }

    /**
     * Validates birthday for secure invoice payment
     *
     * @param $sPaymentId
     * @param $aRequestedValues
     */
    protected function _fcpoValidateSecInvoiceBirthdayData($sPaymentId, $aRequestedValues): bool
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sChooseString = $oLang->translateString('FCPO_PAYOLUTION_PLEASE SELECT');
        $sBirthdateYear = $aRequestedValues['fcpo_secinvoice_birthdate_year'];
        $sBirthdateMonth = $aRequestedValues['fcpo_secinvoice_birthdate_month'];
        $sBirthdateDay = $aRequestedValues['fcpo_secinvoice_birthdate_day'];
        $blValidRequestYear = ((!empty($sBirthdateYear) && $sBirthdateYear != $sChooseString));
        $blValidRequestMonth = ((!empty($sBirthdateMonth) && $sBirthdateMonth != $sChooseString));
        $blValidRequestDay = ((!empty($sBirthdateDay) && $sBirthdateDay != $sChooseString));

        return $blValidRequestYear && $blValidRequestMonth && $blValidRequestDay;
    }

    /**
     * Method saves birthday data if needed and returns if it has saved data or not
     *
     * @param $aRequestedValues
     * @param $sPaymentId
     * @return bool
     */
    protected function _fcpoSaveBirthdayData($aRequestedValues, $sPaymentId)
    {
        $oUser = $this->_fcpoGetUserFromSession();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $blSavedData = false;

        $aBirthdayValidation = $this->_fcpoValidateBirthdayData($sPaymentId, $aRequestedValues);
        $blValidBirthdateData = $aBirthdayValidation['blValidBirthdateData'];
        $blBirthdayRequired = $aBirthdayValidation['blBirthdayRequired'];

        if (!$blBirthdayRequired) {
            return true;
        }

        if ($blValidBirthdateData) {
            $sRequestBirthdate = $this->_fcpoExtractBirthdateFromRequest($aRequestedValues, $sPaymentId);
            $blRefreshBirthdate = ($sRequestBirthdate != '0000-00-00' && $sRequestBirthdate != '--');
            if ($blRefreshBirthdate) {
                $oUser->oxuser__oxbirthdate = new oxField($sRequestBirthdate, oxField::T_RAW);
                $blSavedData = (bool)$oUser->save();
            }
        } elseif ($blBirthdayRequired) {
            $sMessage = $oLang->translateString('FCPO_PAYOLUTION_BIRTHDATE_INVALID');
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
        }

        return $blSavedData;
    }

    /**
     * Extracts birthdate from dynvalue select fields depending on payment
     *
     * @param $aRequestedValues
     * @param $sPaymentId
     * @return string
     */
    public function _fcpoExtractBirthdateFromRequest($aRequestedValues, $sPaymentId)
    {
        $sRequestBirthdate = '--';
        switch ($sPaymentId) {
            case 'fcpopo_bill':
            case 'fcpopo_debitnote':
            case 'fcpopo_installment':
                $sFieldNameAddition = str_replace("fcpopo_", "", $sPaymentId);
                $sRequestBirthdate = $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_birthdate_year'] .
                    "-" . $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_birthdate_month'] .
                    "-" . $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_birthdate_day'];
                break;
            case 'fcpo_secinvoice':
                $sRequestBirthdate = $aRequestedValues['fcpo_secinvoice_birthdate_year'] .
                    "-" . $aRequestedValues['fcpo_secinvoice_birthdate_month'] .
                    "-" . $aRequestedValues['fcpo_secinvoice_birthdate_day'];
                break;
        }

        return $sRequestBirthdate;
    }

    /**
     * Method checks if given field should be saved and returns if it has saved this data or not
     *
     * @param $sPaymentId
     * @return bool
     */
    protected function _fcpoSaveUserData($sPaymentId, $sDbFieldName)
    {
        $blSavedData = false;

        $sRequestedValue = $this->_fcpoGetRequestedValue($sPaymentId, $sDbFieldName);
        if ($sRequestedValue) {
            $sCurrentValue = $this->fcpoGetUserValue($sDbFieldName);
            $blRefreshValue = ($sCurrentValue != $sRequestedValue);
            if ($blRefreshValue) {
                $this->_fcpoSetUserValue($sDbFieldName, $sRequestedValue);
                $blSavedData = true;
            }
        }

        return $blSavedData;
    }

    /**
     * Returns value depending on payment or false if this hasn't been set
     *
     * @param string $sPaymentId
     * @return mixed string/boolean
     */
    protected function _fcpoGetRequestedValue($sPaymentId, $sDbFieldName)
    {
        $aRequestedValues = $this->_fcpoGetRequestedValues();
        $sFieldNameAddition = str_replace("fcpopo_", "", $sPaymentId);

        $mReturn = false;
        if (isset($aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_' . $sDbFieldName])) {
            $mReturn = $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_' . $sDbFieldName];
        }

        if (isset($aRequestedValues['fcpo_secinvoice_ustid'])) {
            $mReturn = (string)$aRequestedValues['fcpo_secinvoice_ustid'];
        }

        return $mReturn;
    }

    /**
     * Returning requested form data values wether via ajax or
     * direct
     *
     *
     * @return array
     */
    protected function _fcpoGetRequestedValues()
    {
        if ($this->_aFcRequestedValues === null) {
            $aRequestedValues = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');
            if ($this->_blIsPayolutionInstallmentAjax) {
                $aRequestedValues = $this->_aAjaxPayolutionParams;
            }

            $this->_aFcRequestedValues = $aRequestedValues;
        }

        return $this->_aFcRequestedValues;
    }

    /**
     * Returns a value of user object or empty string if value nor available
     *
     * @param string $sField
     * @return string
     */
    public function fcpoGetUserValue($sField)
    {
        $oUser = $this->getUser();
        $sUserField = 'oxuser__' . $sField;

        try {
            $sReturn = $oUser->$sUserField->value;
        } catch (Exception) {
            $sReturn = '';
        }

        return $sReturn;
    }

    /**
     * Method saves a single value to a certain field of user table
     *
     * @param $sField
     * @param $sValue
     * @return void
     */
    protected function _fcpoSetUserValue($sField, $sValue)
    {
        $oUser = $this->getUser();
        $sUserField = 'oxuser__' . $sField;

        if (isset($oUser->$sUserField)) {
            $oUser->$sUserField = new oxField($sValue, oxField::T_RAW);
            $oUser->save();
        }
    }

    /**
     * Check configuration for boni check moment and triggers check if moment has been set to now
     * Method will return if checkout progress can be continued or not
     *
     * @param object $oPayment
     * @return boolean
     */
    protected function _fcpoCheckBoniMoment($oPayment)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blContinue = true;

        if ($oConfig->getConfigParam('sFCPOBonicheckMoment') == 'after') {
            $blContinue = $this->_fcpoCheckAddressAndScore($oPayment);
        }

        return $blContinue;
    }

    /**
     * Checks the address and boni values
     *
     * @param bool      $blApproval
     * @param bool      $blBoniCheckNeeded
     * @param oxPayment $oPayment
     * @return boolean
     */
    protected function _fcpoCheckAddressAndScore($oPayment)
    {
        $oUser = $this->getUser();
        $sPaymentId = $oPayment->getId();
        $aApproval = $this->_oFcpoHelper->fcpoGetRequestParameter('fcpo_bonicheckapproved');
        $blApproval = $this->_fcpoValidateApproval($sPaymentId, $aApproval);
        $blBoniCheckNeeded = $oPayment->fcBoniCheckNeeded();

        if ($blBoniCheckNeeded === true && $blApproval) {
            $blContinue = $oUser->checkAddressAndScore(false);
            $blContinue = $this->_fcpoCheckUserBoni($blContinue, $oPayment);
        } elseif ($blBoniCheckNeeded === true && $blApproval === false) {
            $this->_fcpoSetNotChecked($blBoniCheckNeeded, $blApproval);
            $oUser->fcpoSetScoreOnNonApproval();
            $blContinue = false;
        } else {
            $this->_fcpoSetNotChecked($blBoniCheckNeeded, $blApproval);
            $blContinue = true;
        }

        return $blContinue;
    }

    /**
     * Checks approval data to be valid and returns result
     *
     * @param string $sPaymentId
     * @param array  $aApproval
     * @return boolean
     */
    protected function _fcpoValidateApproval($sPaymentId, $aApproval)
    {
        $blApproval = true;
        if ($aApproval && array_key_exists($sPaymentId, $aApproval) && $aApproval[$sPaymentId] == 'false') {
            $blApproval = false;
        }

        return $blApproval;
    }

    /**
     * Compares user boni which could cause a denial on continuing process
     *
     * @param boolean   $blContinue
     * @param oxPayment $oPayment
     * @return boolean
     */
    protected function _fcpoCheckUserBoni($blContinue, $oPayment)
    {
        $oUser = $this->getUser();
        if ($oUser->oxuser__oxboni->value < $oPayment->oxpayments__oxfromboni->value) {
            $blContinue = false;
        }

        return $blContinue;
    }

    /**
     * Check if session flag fcpoordernotchecked will be set
     *
     * @param bool $blBoniCheckNeeded
     * @param bool $blApproval
     * @return void
     */
    protected function _fcpoSetNotChecked($blBoniCheckNeeded, $blApproval)
    {
        if ($blBoniCheckNeeded && $blApproval === false) {
            $this->_oFcpoHelper->fcpoSetSessionVariable('fcpoordernotchecked', 1);
        }
    }

    /**
     * Takes care of error handling for case that boni check is negative
     *
     * @param string $sPaymentId
     * @return void
     */
    protected function _fcpoSetBoniErrorValues($sPaymentId)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $iLangId = $this->fcGetLangId();

        // $this->_oFcpoHelper->fcpoSetSessionVariable( 'payerror', $oPayment->getPaymentErrorNumber() );
        $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
        $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $oConfig->getConfigParam('sFCPODenialText_' . $iLangId));

        //#1308C - delete paymentid from session, and save selected it just for view
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('paymentid');
        if (!($sPaymentId = $this->_oFcpoHelper->fcpoGetRequestParameter('paymentid'))) {
            $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');
        }
        $this->_oFcpoHelper->fcpoSetSessionVariable('_selected_paymentid', $sPaymentId);
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('stsprotection');

        $this->_oFcpoHelper->fcpoGetSession();
    }

    public function fcGetLangId()
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        if (!$oLang) {
            return 0;
        }
        return (int)$oLang->getTplLanguage();
    }

    /**
     * Takes care of debit specific actions arround mandates
     *
     * @param oxPayment $oPayment
     * @return void
     */
    protected function _fcpoSetMandateParams($oPayment)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        if ($oPayment->getId() == 'fcpodebitnote' && $oConfig->getConfigParam('blFCPOMandateIssuance')) {
            $oUser = $this->getUser();
            $aDynValue = $this->_fcpoGetDynValues();

            $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
            $aResponse = $oPORequest->sendRequestManagemandate($oPayment->fcpoGetOperationMode(), $aDynValue, $oUser);

            $this->_fcpoHandleMandateResponse($aResponse);
        }
    }

    /**
     * Returns dynvalues wether from request or session
     *
     *
     * @return mixed
     */
    protected function _fcpoGetDynValues()
    {
        $aDynvalue = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');
        if (!$aDynvalue) {
            $aDynvalue = $this->_oFcpoHelper->fcpoGetSessionVariable('dynvalue');
        }

        return $aDynvalue;
    }

    /**
     * Handles response for mandate request
     *
     * @param array $aResponse
     * @return mixed
     */
    protected function _fcpoHandleMandateResponse($aResponse)
    {
        if ($aResponse['status'] == 'ERROR') {
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $oLang->translateString('FCPO_MANAGEMANDATE_ERROR'));
            return;
        } elseif (is_array($aResponse) && array_key_exists('mandate_status', $aResponse)) {
            $this->_oFcpoHelper->fcpoSetSessionVariable('fcpoMandate', $aResponse);
        }
    }

    /**
     * Remove all session variables of non selected payment
     *
     * @param object $oPayment
     * @return void
     */
    protected function _fcCleanupSessionFragments($oPayment)
    {
        $sPaymentId = $oPayment->getId();

        $aPayments2SessionVariables = ['fcpodebitnote' => ['fcpoMandate'], 'fcpobarzahlen' => ['sFcpoBarzahlenHtml'], 'fcpoklarna' => ['fcpo_klarna_campaign']];

        // remove own payment from list
        unset($aPayments2SessionVariables[$sPaymentId]);

        // iterate through the rest and delete session variables
        foreach ($aPayments2SessionVariables as $aPayments2SessionVariable) {
            foreach ($aPayments2SessionVariable as $sSessionVariable) {
                $this->_oFcpoHelper->fcpoDeleteSessionVariable($sSessionVariable);
            }
        }
    }

    /**
     * Validating new klarna payment
     *
     * @param $mReturn
     * @param $sPaymentId
     * @return mixed
     */
    protected function _fcpoKlarnaCombinedValidate($mReturn, $sPaymentId)
    {
        if ($this->fcpoIsKlarnaCombined($sPaymentId)) {
            $aDynValues = $this->_fcpoGetDynValues();
            if (!$aDynValues['fcpo_klarna_combined_agreed']) {
                $this->_fcpoSetErrorMessage('FCPO_KLARNA_NOT_AGREED');
                return null;
            }
            if (empty($aDynValues['klarna_authorization_token']) && $sPaymentId !== 'fcpoklarna_directdebit') {
                $this->_fcpoSetErrorMessage('FCPO_KLARNA_NO_AUTHORIZATION');
                return null;
            } else {
                $this->_oFcpoHelper->fcpoSetSessionVariable('klarna_authorization_token', $aDynValues['klarna_authorization_token']);
            }
        }

        return $mReturn;
    }

    /**
     * Sets a payolution error message into session, so it will be displayed in frontend
     *
     * @param  $sLangString
     * @return void
     */
    protected function _fcpoSetErrorMessage($sLangString)
    {
        if ($sLangString) {
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $sTranslatedString = $oLang->translateString($sLangString);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sTranslatedString);
        }
    }

    /**
     * Perform payolution precheck
     *
     * @param string $sPaymentId
     * @return mixed
     */
    protected function _fcpoPayolutionPreCheck(mixed $mReturn, $sPaymentId)
    {
        $blPayolutionPayment = $this->_fcpoIsPayolution($sPaymentId);
        if ($blPayolutionPayment) {
            $mReturn = $this->_fcpoValidatePayolutionPreCheck($mReturn, $sPaymentId);
        }

        return $this->_fcpoGetPayolutionPreCheckReturnValue($mReturn);
    }

    /**
     * Checks if given payment belongs to payone payolution
     *
     * @param string $sPaymentId
     */
    protected function _fcpoIsPayolution($sPaymentId): bool
    {
        $aPayolutionPayments = ['fcpopo_bill', 'fcpopo_debitnote', 'fcpopo_installment'];

        return in_array($sPaymentId, $aPayolutionPayments);
    }

    /**
     * Validates payolution prechecks
     *
     * @param  $sPaymentId
     * @return mixed
     */
    protected function _fcpoValidatePayolutionPreCheck($mReturn, $sPaymentId)
    {
        $blSavedSuccessfully = $this->_fcpoPayolutionSaveRequestedValues($sPaymentId);
        $blAgreedDataUsage = $this->_fcpoCheckAgreedDataUsage($sPaymentId);
        $blValidMandatoryUserData = $this->_fcpoCheckPayolutionMandatoryUserData($sPaymentId);
        $blValidated = ($blSavedSuccessfully && $blAgreedDataUsage && $blValidMandatoryUserData);

        if ($blValidated) {
            $mReturn = $this->_fcpoValidateBankDataRelatedPayolutionPayment($mReturn, $sPaymentId);
            $mReturn = $this->_fcpoFinalValidationPayolutionPreCheck($mReturn, $sPaymentId);
        } else {
            $sErrorMessage = $this->_fcpoGetPayolutionErrorMessage($blAgreedDataUsage, $blValidMandatoryUserData);
            $this->_fcpoSetPayolutionErrorMessage($sErrorMessage);
            $mReturn = null;
        }

        return $mReturn;
    }

    /**
     * Save requested values if there haven't been some before or they have changed
     *
     * @param string $sPaymentId
     * @return void
     */
    protected function _fcpoPayolutionSaveRequestedValues($sPaymentId): bool
    {
        $aRequestedValues = $this->_fcpoGetRequestedValues();
        $blSavedBirthday = $this->_fcpoSaveBirthdayData($aRequestedValues, $sPaymentId);
        $blSavedUstid = $this->_fcpoSaveUserData($sPaymentId, 'oxustid');
        $blSavedTelephone = $this->_fcpoSaveUserData($sPaymentId, 'oxfon');

        return $blSavedBirthday || $blSavedUstid || $blSavedTelephone;
    }

    /**
     * Checks if user confirmed agreement of data usage
     *
     *
     * @return bool
     */
    protected function _fcpoCheckAgreedDataUsage($sPaymentId = 'fcpopo_bill')
    {
        $aParams = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');
        if ($this->_blIsPayolutionInstallmentAjax) {
            $aParams = $this->_aAjaxPayolutionParams;
        }

        $sPaymentIdPart = str_replace('fcpopo_', '', $sPaymentId);
        $sPaymentAgreeIndex = 'fcpo_payolution_' . $sPaymentIdPart . '_agreed';

        $blValidConditions = (isset($aParams[$sPaymentAgreeIndex]) && $aParams[$sPaymentAgreeIndex] == 'agreed');

        $blReturn = false;
        if ($blValidConditions) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Returns if mandatory data has been set or not
     *
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoCheckPayolutionMandatoryUserData($sPaymentId)
    {
        // sum check results up
        $blReturn = $this->_fcpoValidateMandatoryUserDataForPayolutionBill($sPaymentId);

        return $blReturn;
    }

    /**
     * Method validates mandatory user data related to payolution bill payment
     *
     * @param $sPaymentId
     * @return bool
     */
    protected function _fcpoValidateMandatoryUserDataForPayolutionBill($sPaymentId)
    {
        $blValidPayment = $sPaymentId == 'fcpopo_bill';
        $blReturn = true;
        if ($blValidPayment) {
            $blHasTelephone = $this->_fcpoValidatePayolutionBillHasTelephone();
            $blHasUstid = $this->_fcpoValidatePayolutionBillHasUstid();
            $blReturn = ($blHasTelephone && $blHasUstid);
        }

        return $blReturn;
    }

    /**
     * Method checks if user has telephone number and if its needed anyway
     * Will return true if field is not mandatory
     *
     *
     * @return bool
     */
    protected function _fcpoValidatePayolutionBillHasTelephone()
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $blTelephoneRequired = $this->fcpoPayolutionBillTelephoneRequired();
        $blReturn = true;

        if ($blTelephoneRequired) {
            $sCurrentTelephoneNumber = $this->fcpoGetUserValue('oxfon');
            $blReturn = (bool)$sCurrentTelephoneNumber;
        }

        if (!$blReturn) {
            $sMessage = $oLang->translateString('FCPO_PAYOLUTION_PHONE_MISSING');
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
        }

        return $blReturn;
    }

    /**
     * Template getter for checking if a telephone number is required and need to be requested
     * from user
     *
     */
    public function fcpoPayolutionBillTelephoneRequired(): bool
    {
        $sTargetCountry = $this->fcpoGetTargetCountry();
        $sCurrentTelephone = $this->fcpoGetUserValue('oxfon');

        return in_array($sTargetCountry, $this->_aPayolutionBillMandatoryTelephoneCountries) &&
        empty($sCurrentTelephone);
    }

    /**
     * Returns shipping country if set or billing country if not as ISO 2 string
     *
     *
     * @return void
     */
    public function fcpoGetTargetCountry()
    {
        $sBillCountry = $this->fcGetBillCountry();
        $sShippingCountry = $this->fcGetShippingCountry();

        return ($sShippingCountry !== '' && $sShippingCountry !== '0') ? $sShippingCountry : $sBillCountry;
    }

    /**
     * Return ISO2 code of shipping country
     *
     *
     * @return string
     */
    public function fcGetShippingCountry()
    {
        $sShippingCountryId = $this->getUserDelCountryId();
        $oCountry = $this->_oFcpoHelper->getFactoryObject('oxcountry');

        return ($oCountry->load($sShippingCountryId)) ? $oCountry->oxcountry__oxisoalpha2->value : '';
    }

    /**
     * Method checks if user has valid ustid and if its mandatory anyway
     * Will return true if check is not mandatory due to circumstances
     *
     *
     * @return bool
     */
    protected function _fcpoValidatePayolutionBillHasUstid()
    {
        $blReturn = true;
        $oConfig = $this->getConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $blB2BModeActive = $oConfig->getConfigParam('blFCPOPayolutionB2BMode');
        $blIsCompany = (bool)$this->fcpoGetUserValue('oxcompany');

        if ($blIsCompany && $blB2BModeActive) {
            $sUstid = $this->fcpoGetUserValue('oxustid');
            $blReturn = (bool)$sUstid;
        }

        if (!$blReturn) {
            $sMessage = $oLang->translateString('FCPO_PAYOLUTION_NO_USTID');
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
        }

        return $blReturn;
    }

    /**
     * Validates payolution payment which is related to bankdata, namely debitnote and installment
     *
     * @param  $sPaymentId
     * @return mixed
     */
    protected function _fcpoValidateBankDataRelatedPayolutionPayment($mReturn, $sPaymentId)
    {
        $blBankDataRelatedPayolutionPayment = $this->_fcpoCheckIsBankDataRelatedPayolutionPayment($sPaymentId);

        $aBankData = $this->_fcpoGetPayolutionBankData($sPaymentId);

        if ($aBankData) {
            $blBankDataValid = ($this->_blIsPayolutionInstallmentAjax) ? false : $this->_fcpoValidateBankData($aBankData, $sPaymentId);
            if (!$blBankDataValid) {
                $this->_fcpoSetPayolutionErrorMessage('FCPO_PAYOLUTION_BANKDATA_INCOMPLETE');
                $mReturn = null;
            }

            $blAgreedSepa = $this->_fcpoCheckSepaAgreed($sPaymentId);
            if (!$blAgreedSepa && $blBankDataValid) {
                $this->_fcpoSetPayolutionErrorMessage('FCPO_PAYOLUTION_SEPA_NOT_AGREED');
                $mReturn = null;
            }

            if ($sPaymentId == 'fcpopo_installment') {
                $sSelectedInstallmentIndex = $this->_fcpoGetPayolutionSelectedInstallmentIndex();
                if (!$sSelectedInstallmentIndex) {
                    $this->_fcpoSetPayolutionErrorMessage('FCPO_PAYOLUTION_NO_INSTALLMENT_SELECTED');
                    $mReturn = null;
                }
            }
        } elseif ($blBankDataRelatedPayolutionPayment && !$this->_blIsPayolutionInstallmentAjax) {
            $this->_fcpoSetPayolutionErrorMessage('FCPO_PAYOLUTION_BANKDATA_INCOMPLETE');
            $mReturn = null;
        }

        return $mReturn;
    }

    /**
     * Checks if given PaymentId is a bank data relevant payment
     *
     * @param  $sPaymentId
     */
    protected function _fcpoCheckIsBankDataRelatedPayolutionPayment($sPaymentId): bool
    {
        $aBankDataRelatedPaymentIds = ['fcpopo_debitnote', 'fcpopo_installment'];

        return in_array($sPaymentId, $aBankDataRelatedPaymentIds);
    }

    /**
     * Reutrns possible given Bankdata
     *
     *
     * @return mixed
     */
    protected function _fcpoGetPayolutionBankData($sPaymentId)
    {
        $aParams = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');
        $aBankData = [];

        if (is_array($aParams) && $aParams !== []) {
            foreach ($aParams as $sKey => $sParam) {
                $aInstallmentAdditions = ['fcpopo_bill' => '', 'fcpopo_installment' => '_installment', 'fcpopo_debitnote' => '_debitnote'];

                $sInstallmentAddition = $aInstallmentAdditions[$sPaymentId];

                $aMap = ['fcpo_payolution' . $sInstallmentAddition . '_iban', 'fcpo_payolution' . $sInstallmentAddition . '_bic', 'fcpo_payolution' . $sInstallmentAddition . '_accountholder'];

                if (in_array($sKey, $aMap)) {
                    $aBankData[$sKey] = $sParam;
                }
            }
        }

        return (count($aBankData) != 3) ? false : $aBankData;
    }

    /**
     * Validates given Bankdata
     *
     * @param array $aBankData
     * @return bool
     */
    protected function _fcpoValidateBankData($aBankData, $sPaymentId)
    {
        $blReturn = false;

        $blIsPayolutionType = ($sPaymentId == 'fcpopo_installment' || $sPaymentId == 'fcpopo_debitnote');

        if ($blIsPayolutionType) {
            $sPayolutionSubType = str_replace('fcpopo_', '', $sPaymentId);
            $blReturn = (
                is_array($aBankData) &&
                isset($aBankData['fcpo_payolution_' . $sPayolutionSubType . '_iban']) &&
                isset($aBankData['fcpo_payolution_' . $sPayolutionSubType . '_bic']) &&
                !empty($aBankData['fcpo_payolution_' . $sPayolutionSubType . '_iban']) &&
                !empty($aBankData['fcpo_payolution_' . $sPayolutionSubType . '_bic']) &&
                isset($aBankData['fcpo_payolution_' . $sPayolutionSubType . '_accountholder']) &&
                !empty($aBankData['fcpo_payolution_' . $sPayolutionSubType . '_accountholder'])
            );
        }

        return $blReturn;
    }

    /**
     * Sets a payolution error message into session, so it will be displayed in frontend
     *
     * @param  $sLangString
     * @return void
     */
    protected function _fcpoSetPayolutionErrorMessage($sLangString)
    {
        if ($sLangString) {
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $this->_sPayolutionCurrentErrorMessage = $oLang->translateString($sLangString);
            // only put this into session if this is not from ajax
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $this->_sPayolutionCurrentErrorMessage);
        }
    }

    /**
     * Checks if user confirmed agreement of data usage
     *
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoCheckSepaAgreed($sPaymentId)
    {
        $aParams = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');
        $blReturn = false;
        if ($sPaymentId == 'fcpopo_installment') {
            $blReturn = true;
        } elseif ($sPaymentId == 'fcpopo_debitnote') {
            if (isset($aParams['fcpo_payolution_debitnote_sepa_agreed']) && $aParams['fcpo_payolution_debitnote_sepa_agreed'] == 'agreed') {
                $blReturn = true;
            }
        }

        return $blReturn;
    }

    /**
     * Returns selected installment index
     */
    protected function _fcpoGetPayolutionSelectedInstallmentIndex()
    {
        $aParams = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');

        return $aParams['fcpo_payolution_installment_index'] ?? false;
    }

    /**
     * Final check by performing a payolution pre check request after several checks have been passed
     *
     * @param  $mReturn
     * @param  $sPaymentId
     * @return mixed
     */
    protected function _fcpoFinalValidationPayolutionPreCheck($mReturn, $sPaymentId)
    {
        $blPreCheckValid = $mReturn !== null && $this->_fcpoPerformPayolutionPreCheck($sPaymentId);
        if (!$blPreCheckValid) {
            $this->_fcpoSetPayolutionErrorMessage('FCPO_PAYOLUTION_PRECHECK_FAILED');
            $mReturn = null;
        }

        return $mReturn;
    }

    /**
     * Performs a pre check
     *
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoPerformPayolutionPreCheck($sPaymentId, $sWorkOrderId = null)
    {
        $blReturn = null;
        $blPreCheckNeeded = $this->_fcpoCheckIfPrecheckNeeded($sPaymentId);
        if ($blPreCheckNeeded) {
            $oUser = $this->getUser();
            if (!$oUser) {
                // try to fetch user from session
                $oSession = $this->getSession();
                $oBasket = $oSession->getBasket();
                $oUser = $oBasket->getBasketUser();
            }
            $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
            $aBankData = $this->_fcpoGetPayolutionBankData($sPaymentId);
            $sSelectedIndex = $this->_fcpoGetPayolutionSelectedInstallmentIndex();
            $aResponse = $oPORequest->sendRequestPayolutionPreCheck($sPaymentId, $oUser, $aBankData, $sWorkOrderId);
            if ($aResponse['status'] == 'ERROR') {
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
                $blReturn = false;
            } elseif (is_array($aResponse) && array_key_exists('workorderid', $aResponse)) {
                $this->_oFcpoHelper->fcpoSetSessionVariable('payolution_workorderid', $aResponse['workorderid']);
                $this->_oFcpoHelper->fcpoSetSessionVariable('payolution_bankdata', $aBankData);
                $blReturn = true;
            }
        } else {
            $sWorkOrderId = $this->_oFcpoHelper->fcpoGetSessionVariable('payolution_workorderid');
            $blValidCalculation = $this->_fcpoPerformInstallmentCalculation($sPaymentId, $sWorkOrderId);
            if (!$blValidCalculation) {
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
                $blReturn = false;
            } else {
                $sSelectedIndex = $this->_fcpoGetPayolutionSelectedInstallmentIndex();
                $sDuration = $this->_fcpoPayolutionFetchDuration($sSelectedIndex);
                $this->_oFcpoHelper->fcpoSetSessionVariable('payolution_installment_duration', $sDuration);

                // OXID-225 add bank details as they are added in form after precheck is performed
                $aBankData = $this->_fcpoGetPayolutionBankData($sPaymentId);
                $this->_oFcpoHelper->fcpoSetSessionVariable('payolution_bankdata', $aBankData);

                $blReturn = true;
            }
        }

        return $blReturn;
    }

    /**
     * Precheck is not needed for payolution installment payment if its not coming via ajax
     *
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoCheckIfPrecheckNeeded($sPaymentId)
    {
        $blReturn = true;
        $blCheckException = ($sPaymentId == 'fcpopo_installment' && !$this->_blIsPayolutionInstallmentAjax);
        if ($blCheckException) {
            $blReturn = false;
        }

        return $blReturn;
    }

    /**
     * Performs a pre check
     *
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoPerformInstallmentCalculation($sPaymentId, $sWorkOrderId = null)
    {
        $blReturn = null;
        $oUser = $this->getUser();
        $aBankData = $this->_fcpoGetPayolutionBankData($sPaymentId);
        $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oPORequest->sendRequestPayolutionInstallment($sPaymentId, $oUser, $aBankData, 'calculation', $sWorkOrderId);
        if ($aResponse['status'] == 'ERROR') {
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $blReturn = false;
        } elseif (is_array($aResponse) && array_key_exists('workorderid', $aResponse)) {
            $this->_oFcpoHelper->fcpoSetSessionVariable('payolution_workorderid', $aResponse['workorderid']);
            $this->_fcpoSetInstallmentOptionsByResponse($aResponse);
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Fetches needed installment details from response and prepares data so it can be interpreted easier
     *
     * @param array $aResponse
     * @return void
     */
    protected function _fcpoSetInstallmentOptionsByResponse($aResponse)
    {
        // cleanup before atempt
        $this->_aInstallmentCalculation = [];
        foreach (array_keys($aResponse) as $sKey) {
            $iInstallmentIndex = $this->_fcpoFetchCurrentRatepayInstallmentIndex($sKey);
            if ($iInstallmentIndex === false) {
                continue;
            }

            $this->_fcpoSetinstallmentOptionIfIsset('Duration', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('Currency', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('StandardCreditInformationUrl', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('Usage', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('EffectiveInterestRate', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('InterestRate', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('OriginalAmount', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('TotalAmount', $iInstallmentIndex, $aResponse);
            $this->_fcpoSetinstallmentOptionIfIsset('MinimumInstallmentFee', $iInstallmentIndex, $aResponse);

            $this->_fcpoSetRatepayInstallmentMonthDetail($iInstallmentIndex, $sKey, $aResponse);

            // check search pattern to receive month of current installment detail
        }
        krsort($this->_aInstallmentCalculation);
    }

    /**
     * Fetches index from current key or false if not fetchable
     *
     * @param  $sKey
     * @return mixed
     */
    protected function _fcpoFetchCurrentRatepayInstallmentIndex($sKey)
    {
        if (!str_starts_with($sKey, 'add_paydata')) {
            return false;
        }

        preg_match('/add_paydata\[PaymentDetails_(\d*)_/', $sKey, $aResultInstallmentIndex);
        if (!isset($aResultInstallmentIndex[1]) || !is_numeric($aResultInstallmentIndex[1])) {
            return false;
        }

        return $aResultInstallmentIndex[1];
    }

    /**
     * Sets a single ratepay option if set in response
     *
     * @param  $sOption
     * @param  $iInstallmentIndex
     * @param  $aResponse
     * @return void
     */
    protected function _fcpoSetinstallmentOptionIfIsset($sOption, $iInstallmentIndex, $aResponse)
    {
        if (!isset($this->_aInstallmentCalculation[$iInstallmentIndex][$sOption])) {
            $this->_aInstallmentCalculation[$iInstallmentIndex][$sOption] = $aResponse['add_paydata[PaymentDetails_' . $iInstallmentIndex . '_' . $sOption . ']'];
        }
    }

    /**
     * check search pattern to receive month of current installment detail
     *
     * @param  $sKey
     * @param  $aResponse
     * @return void
     */
    protected function _fcpoSetRatepayInstallmentMonthDetail($iInstallmentIndex, $sKey, $aResponse)
    {
        // add_paydata[PaymentDetails_<n>_Installment_<m>_Amount]
        preg_match('/add_paydata\[PaymentDetails_(\d*)_Installment_(\d*)_Amount\]/', $sKey, $aMonthResult);
        if (isset($aMonthResult[2]) && is_numeric($aMonthResult[2])) {
            $this->_aInstallmentCalculation[$iInstallmentIndex]['Amount'] = $aResponse['add_paydata[PaymentDetails_' . $iInstallmentIndex . '_Installment_' . $aMonthResult[2] . '_Amount]'];
            $this->_aInstallmentCalculation[$iInstallmentIndex]['Months'][$aMonthResult[2]]['Amount'] = $aResponse['add_paydata[PaymentDetails_' . $iInstallmentIndex . '_Installment_' . $aMonthResult[2] . '_Amount]'];
            $this->_aInstallmentCalculation[$iInstallmentIndex]['Months'][$aMonthResult[2]]['Due'] = $aResponse['add_paydata[PaymentDetails_' . $iInstallmentIndex . '_Installment_' . $aMonthResult[2] . '_Due]'];
            $this->_aInstallmentCalculation[$iInstallmentIndex]['Months'][$aMonthResult[2]]['Currency'] = $aResponse['add_paydata[PaymentDetails_' . $iInstallmentIndex . '_Currency]'];
            $this->_aInstallmentCalculation[$iInstallmentIndex]['Months'][$aMonthResult[2]]['DraftUrl'] = $aResponse['add_paydata[PaymentDetails_' . $iInstallmentIndex . '_StandardCreditInformationUrl]'];
            ksort($this->_aInstallmentCalculation[$iInstallmentIndex]['Months']);
        }
    }

    /**
     * Returns duration by given installment index after performing calculation
     *
     * @param string $sSelectedIndex
     * @return mixed
     */
    protected function _fcpoPayolutionFetchDuration($sSelectedIndex)
    {
        $mReturn = false;
        if (isset($this->_aInstallmentCalculation[$sSelectedIndex]['Duration'])) {
            $mReturn = $this->_aInstallmentCalculation[$sSelectedIndex]['Duration'];
        }

        return $mReturn;
    }

    /**
     * Some validation error occured. Method checks which and returns its translation string
     *
     * @param  $blAgreedDataUsage
     * @param  $blValidMandatoryUserData
     * @return string
     */
    protected function _fcpoGetPayolutionErrorMessage($blAgreedDataUsage, $blValidMandatoryUserData)
    {
        $sTranslateErrorString = '';

        if (!$blAgreedDataUsage) {
            $sTranslateErrorString = 'FCPO_PAYOLUTION_NOT_AGREED';
        } elseif (!$blValidMandatoryUserData) {
            $sTranslateErrorString = 'FCPO_PAYOLUTION_NO_USTID';
        }

        return $sTranslateErrorString;
    }

    /**
     * Wether precheck has been called via ajax or not we probably need to echo an error message into frontend
     *
     * @param  $mReturn
     * @return mixed
     */
    protected function _fcpoGetPayolutionPreCheckReturnValue($mReturn)
    {
        if ($this->_blIsPayolutionInstallmentAjax && $mReturn === null) {
            $mReturn = $this->_sPayolutionCurrentErrorMessage;
        }

        return $mReturn;
    }

    /**
     * Checks if all mandatory data is available for using ratepay invoicing
     *
     * @param string $sPaymentId
     * @return mixed
     */
    protected function _fcpoCheckRatePayBillMandatoryUserData(mixed $mReturn, $sPaymentId)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $aRequestedValues = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');

        $this->_fcpoRatePaySaveRequestedValues($sPaymentId);

        $blB2b2Mode = $oConfig->getConfigParam('blFCPORatePayB2BMode');

        if ($blB2b2Mode) {
            $blShowUstid = $this->fcpoRatePayShowUstid();
            $mReturn = ($blShowUstid) ? false : $mReturn;
            if (!$mReturn) {
                $sMessage = $oLang->translateString('FCPO_RATEPAY_NO_USTID');
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
            }
        } else {
            $blShowFon = $this->fcpoRatePayShowFon();
            $blShowBirthdate = $this->fcpoRatePayShowBirthdate();
            $mReturn = (!$blShowBirthdate && !$blShowFon) ? $mReturn : false;
            if (!$mReturn) {
                $sMessage = $oLang->translateString('FCPO_RATEPAY_NO_SUFFICIENT_DATA');
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
            }
        }


        $blSepaAndDataUsageAgreed = (
            $sPaymentId != 'fcporp_debitnote' ||
            (
                $aRequestedValues['fcpo_ratepay_debitnote_agreed'] == 'agreed' &&
                $aRequestedValues['fcpo_ratepay_debitnote_sepa_agreed'] == 'agreed'
            )
        );

        if (!$blSepaAndDataUsageAgreed) {
            $mReturn = false;
            $sErrorTranslateString =
                ($aRequestedValues['fcpo_ratepay_debitnote_sepa_agreed'] != 'agreed') ?
                    'FCPO_RATEPAY_SEPA_NOT_AGREED' :
                    'FCPO_RATEPAY_NOT_AGREED';
            $sMessage = $oLang->translateString($sErrorTranslateString);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
        }

        return $mReturn;
    }

    /**
     * Check if values have been set via checkout payment process and save them
     *
     * @param string $sPaymentId
     * @return void
     */
    protected function _fcpoRatePaySaveRequestedValues($sPaymentId)
    {
        $blSaveUser = false;
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getBasketUser();

        $aRequestedValues = $this->_oFcpoHelper->fcpoGetRequestParameter('dynvalue');
        $sCurrentBirthdate = $oUser->oxuser__oxbirthdate->value;
        $sRequestBirthdate = $aRequestedValues[$sPaymentId . '_birthdate_year'] . "-" .
            $aRequestedValues[$sPaymentId . '_birthdate_month'] . "-" .
            $aRequestedValues[$sPaymentId . '_birthdate_day'];

        $blRefreshBirthdate = ($sCurrentBirthdate != $sRequestBirthdate && $sRequestBirthdate != '0000-00-00' && $sRequestBirthdate != '--');
        if ($blRefreshBirthdate) {
            $oUser->oxuser__oxbirthdate = new oxField($sRequestBirthdate, oxField::T_RAW);
            $blSaveUser = true;
        }

        $blRefreshFon = (isset($aRequestedValues[$sPaymentId . '_fon']) && strlen($aRequestedValues[$sPaymentId . '_fon']) > 0);

        if ($blRefreshFon) {
            $oUser->oxuser__oxfon = new oxField($aRequestedValues[$sPaymentId . '_fon'], oxField::T_RAW);
            $blSaveUser = true;
        }

        $blRefreshUstid = (isset($aRequestedValues[$sPaymentId . '_ustid']) && strlen($aRequestedValues[$sPaymentId . '_ustid']) > 0);

        if ($blRefreshUstid) {
            $oUser->oxuser__oxustid = new oxField($aRequestedValues[$sPaymentId . '_ustid'], oxField::T_RAW);
            $blSaveUser = true;
        }

        $this->_oFcpoHelper->fcpoSetSessionVariable('ratepayprofileid', $aRequestedValues[$sPaymentId . '_profileid']);

        if ($blSaveUser) {
            $oUser->save();
        }
    }

    /**
     * Template getter which checks if requesting ustid is needed
     *
     */
    public function fcpoRatePayShowUstid(): bool
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oUser = $this->getUser();
        $blB2b2Mode = ($oConfig->getConfigParam('blFCPORatePayB2BMode') && $oUser->oxuser__oxcompany->value != '');

        return $oUser->oxuser__oxustid->value == '' && $blB2b2Mode;
    }

    /**
     * Template getter which checks if requesting telephone number is needed
     *
     */
    public function fcpoRatePayShowFon(): bool
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oUser = $this->getUser();
        $blShowUstid = $this->fcpoRatePayShowUstid();
        $oConfig->getConfigParam('blFCPORatePayB2BMode');

        return $oUser->oxuser__oxfon->value == '' && !$blShowUstid;
    }

    /**
     * Template getter which checks if requesting birthdate is needed
     *
     */
    public function fcpoRatePayShowBirthdate(): bool
    {
        $this->_oFcpoHelper->fcpoGetConfig();
        $oUser = $this->getUser();
        $blShowUstid = $this->fcpoRatePayShowUstid();

        return $oUser->oxuser__oxbirthdate->value == '0000-00-00' && !$blShowUstid;
    }

    /**
     * Determines if adult check is needed and performing it in case
     *
     * @param $mReturn
     * @param $sPaymentId
     * @return mixed
     */
    protected function _fcpoAdultCheck($mReturn, $sPaymentId)
    {
        $blAgeCheckRequired = $this->_fcpoAdultCheckRequired($sPaymentId);
        if ($blAgeCheckRequired) {
            $blIsAdult = $this->_fcpoUserIsAdult();
            if (!$blIsAdult) {
                $oLang = $this->_oFcpoHelper->fcpoGetLang();
                $sMessage = $oLang->translateString('FCPO_NOT_ADULT');
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
                $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
                $mReturn = null;
            }
        }
        return $mReturn;
    }

    /**
     * Checks if adult control is needed
     *
     * @param $sPaymentId
     * @return bool
     */
    protected function _fcpoAdultCheckRequired($sPaymentId)
    {
        $aAffectedPaymentTypes = ['fcpo_secinvoice'];
        $blReturn = false;
        if (in_array($sPaymentId, $aAffectedPaymentTypes)) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Returns if user is an adult
     *
     *
     * @return bool
     */
    protected function _fcpoUserIsAdult()
    {
        $blIsAdult = true;
        $oUser = $this->getUser();
        $sBirthdateRaw = $oUser->oxuser__oxbirthdate->value;
        $iTimeBirthday = strtotime($sBirthdateRaw);
        $iTime18YearsAgo = strtotime('-18 years');

        if ($iTimeBirthday > $iTime18YearsAgo) {
            $blIsAdult = false;
        }

        return $blIsAdult;
    }

    /**
     * Template getter for previously calculated installments
     *
     *
     * @return array
     */
    public function fcpoGetInstallments()
    {
        return $this->_aInstallmentCalculation;
    }

    /**
     * Template getter for current userflag messages
     *
     *
     * @return mixed[]
     */
    public function fcpoGetUserFlagMessages(): array
    {
        $aMessages = [];
        $oUser = $this->getUser();
        $aUserFlags = $oUser->fcpoGetFlagsOfUser();
        foreach ($aUserFlags as $aUserFlag) {
            if (!$aUserFlag->fcpoGetIsActive()) {
                continue;
            }
            $sCustomerMessage = $this->getPaymentErrorText();
            $sMessage = $aUserFlag->fcpoGetTranslatedMessage($sCustomerMessage);
            if ($sMessage) {
                $aMessages[] = $sMessage;
            }
        }

        return $aMessages;
    }

    /**
     * Method for transport of params that came via payolution installment params
     */
    public function setPayolutionAjaxParams(array $aParams): void
    {
        $this->_aAjaxPayolutionParams = $aParams;
    }

    /**
     * Public method for payolution precheck which can be called via ajax wrapper
     *
     * @param string $sPaymentId
     * @return mixed
     */
    public function fcpoPayolutionPreCheck($sPaymentId)
    {
        $this->_blIsPayolutionInstallmentAjax = true;

        return $this->_fcpoPayolutionPreCheck(true, $sPaymentId);
    }

    public function fcpoGetRatePayDeviceFingerprint()
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $sFingerprint = $oSession->getVariable('fcpoRatepayDeviceFingerPrint');

        if (!$sFingerprint) {
            $sFingerprint = $this->fcpoGetUserValue('oxfname');
            $sFingerprint .= $this->fcpoGetUserValue('oxlname');
            $sFingerprint .= microtime();
            $sFingerprint = md5($sFingerprint);
            $oSession->setVariable('fcpoRatepayDeviceFingerPrint', $sFingerprint);
        }
        return $sFingerprint;
    }

    public function fcpoGetRatePayDeviceFingerprintSnippetId()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        return $oConfig->getConfigParam('sFCPORatePaySnippetID') ?: 'ratepay';
    }

    /**
     * Returns link for displaying legal terms for Ratepay
     *
     */
    public function fcpoGetRatepayAgreementLink(): string
    {
        return 'https://www.ratepay.com/legal-payment-terms';
    }

    /**
     * Returns link for displaying data privacy statement for Ratepay
     *
     */
    public function fcpoGetRatepayPrivacyLink(): string
    {
        return 'https://www.ratepay.com/legal-payment-dataprivacy';
    }

    /**
     * Ajax interface for triggering installment caclulation
     *
     */
    public function fcpoPerformInstallmentCalculation(): void
    {
        $this->_fcpoPerformInstallmentCalculation('fcpopo_installment');
    }

    /**
     * Loads shop version and formats it in a certain way
     *
     */
    public function getIntegratorid(): string
    {
        return $this->_oFcpoHelper->fcpoGetIntegratorId();
    }

    /**
     * Loads shop edition and shop version and formats it in a certain way
     *
     */
    public function getIntegratorver(): string
    {
        return $this->_oFcpoHelper->fcpoGetIntegratorVersion();
    }

    /**
     * get PAYONE module version
     *
     */
    public function getIntegratorextver(): string
    {
        return $this->_oFcpoHelper->fcpoGetModuleVersion();
    }

    /**
     * Returns the Klarna confirmation text for the current bill country
     *
     */
    public function fcpoGetConfirmationText(): string
    {
        $oPayment = $this->_oFcpoHelper->getFactoryObject('oxPayment');
        $sId = $oPayment->fcpoGetKlarnaStoreId();
        $sKlarnaLang = $this->_fcpoGetKlarnaLang();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sConfirmText = $oLang->translateString('FCPO_KLV_CONFIRM');

        return sprintf($sConfirmText, $sId, $sKlarnaLang, $sId, $sKlarnaLang);
    }

    /**
     * Returns the Klarna lang abbreviation
     *
     * @return string
     */
    protected function _fcpoGetKlarnaLang()
    {
        $sReturn = 'de_de';
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());
        if ($sBillCountryIso2 !== '' && $sBillCountryIso2 !== '0') {
            $aKlarnaLangMap = ['de' => 'de_de', 'at' => 'de_at', 'dk' => 'da_dk', 'fi' => 'fi_fi', 'nl' => 'nl_nl', 'no' => 'nb_no', 'se' => 'sv_se'];
            if (array_key_exists($sBillCountryIso2, $aKlarnaLangMap)) {
                $sReturn = $aKlarnaLangMap[$sBillCountryIso2];
            }
        }
        return $sReturn;
    }

    /**
     * Determine if info is needed
     *
     */
    public function fcpoKlarnaInfoNeeded(): bool
    {
        return $this->fcpoKlarnaIsTelephoneNumberNeeded() ||
        $this->fcpoKlarnaIsBirthdayNeeded() ||
        $this->fcpoKlarnaIsAddressAdditionNeeded() ||
        $this->fcpoKlarnaIsDelAddressAdditionNeeded() ||
        $this->fcpoKlarnaIsGenderNeeded() ||
        $this->fcpoKlarnaIsPersonalIdNeeded();
    }

    /**
     * Checks if telephone number
     *
     */
    public function fcpoKlarnaIsTelephoneNumberNeeded(): bool
    {
        $oUser = $this->getUser();
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());
        $blCountryNeedsPhone = (in_array($sBillCountryIso2, ['no', 'se', 'dk']));
        return $blCountryNeedsPhone && $oUser->oxuser__oxfon->value == '';
    }

    /**
     * Checks if birthday neeeded for klarna
     *
     */
    public function fcpoKlarnaIsBirthdayNeeded(): bool
    {
        $oUser = $this->getUser();
        $sBirthdate = $oUser->oxuser__oxbirthdate->value;
        $sUserCountryIso2 = strtoupper($this->fcGetBillCountry());
        $blNoBirthdaySet = (!$sBirthdate || $sBirthdate == '0000-00-00');
        $blInCountryList = in_array($sUserCountryIso2, $this->_aKlarnaBirthdayNeededCountries);

        return $blInCountryList && $blNoBirthdaySet;
    }

    /**
     * Determine if address addition is needed
     *
     */
    public function fcpoKlarnaIsAddressAdditionNeeded(): bool
    {
        $oUser = $this->getUser();
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());

        return $sBillCountryIso2 == 'nl' && !$oUser->oxuser__oxaddinfo->value;
    }

    /**
     * Determine if delivery address addition is needed
     *
     *
     * @return boolean
     */
    public function fcpoKlarnaIsDelAddressAdditionNeeded()
    {
        $blReturn = false;
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());

        if ($sBillCountryIso2 == 'nl') {
            $oUser = $this->getUser();
            $sDeliveryAddressId = $oUser->getSelectedAddressId();
            if ($sDeliveryAddressId) {
                $oAddress = $this->_oFcpoHelper->getFactoryObject('oxaddress');
                $oAddress->load($sDeliveryAddressId);
                if ($oAddress && !$oAddress->oxaddress__oxaddinfo->value) {
                    $blReturn = true;
                }
            }
        }

        return $blReturn;
    }

    /**
     * Determine if gender is needed
     *
     */
    public function fcpoKlarnaIsGenderNeeded(): bool
    {
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());
        $oUser = $this->getUser();
        $blValidCountry = (in_array($sBillCountryIso2, ['de', 'at', 'nl']));
        $blValidSalutation = !$oUser->oxuser__oxsal->value;

        return $blValidCountry && $blValidSalutation;
    }

    /**
     * Determine if personal id is needed
     *
     */
    public function fcpoKlarnaIsPersonalIdNeeded(): bool
    {
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());
        $oUser = $this->getUser();
        $blValidCountry = (in_array($sBillCountryIso2, ['dk', 'fi', 'no', 'se']));
        $blValidPersId = !$oUser->oxuser__fcpopersonalid->value;

        return $blValidCountry && $blValidPersId;
    }

    /**
     * Returns an array of configured debit countries
     *
     *
     * @return array<int|string, mixed>
     */
    public function fcpoGetDebitCountries(): array
    {
        $aCountries = [];
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $aFCPODebitCountries = $oConfig->getConfigParam('aFCPODebitCountries');

        if (is_array($aFCPODebitCountries) && count($aFCPODebitCountries)) {
            foreach ($aFCPODebitCountries as $aFCPODebitCountry) {
                $oPayment = $this->_oFcpoHelper->getFactoryObject('oxPayment');
                $aCountries[$oPayment->fcpoGetCountryIsoAlphaById($aFCPODebitCountry)] = $oPayment->fcpoGetCountryNameById($aFCPODebitCountry);
            }
        }

        return $aCountries;
    }

    /**
     * Template getter returns formatted payment costs by offering
     * current oxpayment object
     *
     * @param object $oPayment
     * @return string
     */
    public function fcpoGetFormattedPaymentCosts($oPayment)
    {
        $oPaymentPrice = $oPayment->getPrice();
        $oViewConf = $this->_oFcpoHelper->fcpoGetViewConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();

        $dPrice = $oPaymentPrice->getBruttoPrice();
        $blShowPrice = ($dPrice > 0.00);

        if (!$blShowPrice) {
            return '';
        }

        $blShowVATForPayCharge = $oViewConf->isFunctionalityEnabled('blShowVATForPayCharge');

        // create output
        $sFormattedCosts = "(";
        $sFormattedCosts .= $this->_fcpoFormatCurrency($dPrice);
        if ($blShowVATForPayCharge) {
            $dVat = $oPaymentPrice->getVatValue();
            $sFormattedCosts .= " " . $oLang->translateString('PLUS_VAT');
            $sFormattedCosts .= " " . $this->_fcpoFormatCurrency($dVat);
        }

        return $sFormattedCosts . ")";
    }

    /**
     * Formatting currency with currency sign
     *
     * @param double $dPrice
     * @return string
     */
    protected function _fcpoFormatCurrency($dPrice)
    {
        $oCur = $this->getActCurrency();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();

        $sPrice = $oLang->formatCurrency($dPrice, $oCur);
        $sSide = $oCur->side;

        return (isset($sSide) && $sSide == 'Front') ?
            $oCur->sign . $sPrice :
            $sPrice . ' ' . $oCur->sign;
    }

    /**
     * Generic method for determine if order is b2c
     *
     *
     * @return bool
     */
    public function fcpoIsB2C()
    {
        $blIsB2B = $this->fcpoIsB2B();

        return !$blIsB2B;
    }

    /**
     * Template getter which delivers certain parts of birthdate
     *
     * @param string $sPart (year,month,day)
     * @return string
     */
    public function fcpoGetBirthdayField($sPart)
    {
        $sBirthdate = $this->fcpoGetUserValue('oxbirthdate');
        $aBirthdateParts = explode('-', $sBirthdate);
        $aMap = ['year' => 0, 'month' => 1, 'day' => 2];

        $sReturn = '';
        if (isset($aBirthdateParts[$aMap[$sPart]])) {
            $sReturn = $aBirthdateParts[$aMap[$sPart]];
        }

        return $sReturn;
    }

    /**
     * Returns prepared link for displaying agreement as
     *
     *
     * @return string
     */
    public function fcpoGetPayolutionAgreementLink()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sLangAbbr = $oLang->getLanguageAbbr();
        $sTargetCountry = strtoupper($this->fcpoGetTargetCountry());

        $sCompanyName = $oConfig->getConfigParam('sFCPOPayolutionCompany');
        $sLink = $this->_sPayolutionAgreementBaseLink . '?mId=' . base64_encode($sCompanyName);
        $sLink .= '&lang=' . $sLangAbbr;

        return $sLink . ('&territory=' . $sTargetCountry);
    }

    /**
     * Template getter returns payolution sepa mandata
     *
     * @return string
     */
    public function fcpoGetPayolutionSepaAgreementLink()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sShopUrl = $oConfig->getShopUrl();

        return $sShopUrl .
        '/modules/fc/fcpayone/lib/fcpopopup_content.php?loadurl=' .
        $this->_sPayolutionSepaAgreement;
    }

    /**
     * Template getter for returning an array with last hundred years
     *
     */
    public function fcpoGetYearRange(): array
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sChooseString = $oLang->translateString('FCPO_PAYOLUTION_PLEASE SELECT');
        $iCurrentYear = (int)date('Y');
        $iHundredYearsAgo = $iCurrentYear - 100;

        $aRange = $this->_fcpoGetNumericRange($iHundredYearsAgo, $iCurrentYear, 4, false);
        $aReturn = [$sChooseString];
        $aReverse = array_reverse($aRange);
        foreach ($aReverse as $sYear) {
            $aReturn[] = $sYear;
        }

        return $aReturn;
    }

    /**
     * Returns an array with range of given numbers as pad formatted string
     *
     * @param int $iFrom
     * @param int $iTo
     * @param int $iPositions
     * @return array
     */
    protected function _fcpoGetNumericRange($iFrom, $iTo, $iPositions, $blChooseString = true)
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sChooseString = $oLang->translateString('FCPO_PAYOLUTION_PLEASE SELECT');
        $aRange = ($blChooseString) ? [$sChooseString] : [];

        for ($iCurrentNumber = $iFrom; $iCurrentNumber <= $iTo; $iCurrentNumber++) {
            $aRange[] = str_pad($iCurrentNumber, $iPositions, '0', STR_PAD_LEFT);
        }

        return $aRange;
    }

    /**
     * Returns an array of available months
     *
     *
     * @return array
     */
    public function fcpoGetMonthRange()
    {
        return $this->_fcpoGetNumericRange(1, 12, 2);
    }

    /**
     * Returns an array of available days
     *
     *
     * @return array
     */
    public function fcpoGetDayRange()
    {
        return $this->_fcpoGetNumericRange(1, 31, 2);
    }

    /**
     * Fetch the agreement string based on locale
     * then insert the payment method particle in the placeholder
     *
     * @param string $sPaymentId
     */
    public function fcpoGetPoAgreementInit($sPaymentId): string
    {
        $oTranslator = $this->_oFcpoHelper->fcpoGetLang();
        $sBaseString = $oTranslator->translateString('FCPO_PAYOLUTION_AGREEMENT_PART_1');
        $sPaymentMethodSuffix = $oTranslator->translateString('FCPO_PAYOLUTION_AGREEMENT_PART_1_' . strtoupper($sPaymentId));

        return sprintf($sBaseString, $sPaymentMethodSuffix);
    }

    /**
     * Updating given birthday data of user
     *
     * @param array $aBirthdayValidation
     * @return bool
     */
    protected function _fcpoUpdateBirthdayData($aBirthdayValidation)
    {
        $oUser = $this->_fcpoGetUserFromSession();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $blValidBirthdateData = $aBirthdayValidation['blValidBirthdateData'];
        $sRequestBirthdate = $aBirthdayValidation['sRequestBirthdate'];

        $blResult = false;

        if ($blValidBirthdateData) {
            $oUser->oxuser__oxbirthdate = new oxField($sRequestBirthdate, oxField::T_RAW);
            $oUser->save();
            $blResult = true;
        } else {
            $sMessage = $oLang->translateString('FCPO_BIRTHDATE_INVALID');
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
        }

        return $blResult;
    }

    /**
     * Checks complete company data
     *
     * @param $sPaymentId
     * @return bool
     */
    protected function _fcValidateCompanyData($sPaymentId)
    {
        $aPayments2Validate = ['fcpo_secinvoice'];

        $blDeeperValidationNeeded = in_array($sPaymentId, $aPayments2Validate);
        if (!$blDeeperValidationNeeded) {
            return true;
        }

        $blReturn = $this->fcpoIsB2B(true);

        if (!$blReturn) {
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $sMessage = $oLang->translateString('FCPO_COMPANYDATA_INVALID');
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
        }

        return $blReturn;
    }

    /**
     * Returns value for ustid depending on payment or false if this hasn't been set
     *
     * @param string $aRequestedValues
     * @param string $sPaymentId
     * @return mixed string/boolean
     */
    protected function _fcpoGetRequestedUstid($aRequestedValues, $sPaymentId)
    {
        $sFieldNameAddition = str_replace("fcpopo_", "", $sPaymentId);

        $mReturn = false;
        if (isset($aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_ustid'])) {
            $mReturn = $aRequestedValues['fcpo_payolution_' . $sFieldNameAddition . '_ustid'];
        }

        return $mReturn;
    }

    /**
     * Extends oxid standard method _setValues
     * Extends it with the approval checkbox in the longdesc property
     *
     * Calculate payment cost for each payment. Sould be removed later
     *
     * @param array    &$aPaymentList payments array
     * @param oxBasket  $oBasket      basket object
     *
     * @return null
     */
    protected function _setValues(&$aPaymentList, $oBasket = null)
    {
        foreach ($aPaymentList as $oPayment) {
            if ($this->fcIsPayOnePaymentType($oPayment->getId()) && $this->fcShowApprovalMessage() && $oPayment->fcBoniCheckNeeded()) {
                $sApprovalLongdesc = '<br><table><tr><td><input type="hidden" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="false"><input type="checkbox" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="true" style="margin-bottom:0px;margin-right:10px;"></td><td>' . $this->fcGetApprovalText() . '</td></tr></table>';
                $oPayment->oxpayments__oxlongdesc->rawValue .= $sApprovalLongdesc;
            }
        }
    }

    /**
     * Returns wether payment is of type payone
     *
     * @param string $sId
     */
    public function fcIsPayOnePaymentType($sId): bool
    {
        return FcPayOnePayment::fcIsPayOnePaymentType($sId);
    }

    /**
     * Check if approval message should be displayed
     *
     */
    public function fcShowApprovalMessage(): bool
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOBonicheckMoment') == 'after';
    }

    /**
     * Returns the cn
     *
     *
     * @return mixed
     */
    public function fcGetApprovalText()
    {
        $iLangId = $this->fcGetLangId();
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOApprovalText_' . $iLangId);
    }

    /**
     * Extends oxid standard method _setDeprecatedValues
     * Extends it with the approval checkbox in the longdesc property
     *
     * Calculate payment cost for each payment. Sould be removed later
     *
     * @param array    &$aPaymentList payments array
     * @param oxBasket  $oBasket      basket object
     *
     * @return null
     */
    protected function _setDeprecatedValues(&$aPaymentList, $oBasket = null)
    {
        if ($this->_fcGetCurrentVersion() <= 4700) {
            $this->_oFcpoHelper->fcpoGetLang();
            foreach ($aPaymentList as $oPayment) {
                if ($this->fcIsPayOnePaymentType($oPayment->getId()) && $this->fcShowApprovalMessage() && $oPayment->fcBoniCheckNeeded()) {
                    $sApprovalLongdesc = '<br><table><tr><td><input type="hidden" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="false"><input type="checkbox" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="true" style="margin-bottom:0px;margin-right:10px;"></td><td>' . $this->fcGetApprovalText() . '</td></tr></table>';
                    $oPayment->oxpayments__oxlongdesc->value .= $sApprovalLongdesc;
                }
            }
        }
    }

    /**
     * Get current version number as 4 digit integer e.g. Oxid 4.5.9 is 4590
     */
    protected function _fcGetCurrentVersion(): int
    {
        return $this->_oFcpoHelper->fcpoGetIntShopVersion();
    }
}
