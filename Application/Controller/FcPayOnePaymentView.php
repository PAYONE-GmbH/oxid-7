<?php

namespace Fatchip\PayOne\Application\Controller;

use Exception;
use Fatchip\PayOne\Application\Model\FcPayOnePayment;
use Fatchip\PayOne\Application\Model\FcPoRatePay;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Application\Model\UserPayment;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\ViewConfig;
use stdClass;

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
class FcPayOnePaymentView extends FcPayOnePaymentView_parent
{

    /**
     * Contains dynvalue list of requested params of payment page (all)
     *
     * @var array
     */
    public $_aFcRequestedValues = null;
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
    protected $_oFcPoHelper = null;
    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoDb = null;
    /**
     * bill country id of the user object
     *
     * @var string
     */
    protected $_sUserBillCountryId = null;
    /**
     * delivery country id if existant
     *
     * @var string
     */
    protected $_sUserDelCountryId = null;
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
    protected $_aKlarnaBirthdayNeededCountries = ['DE', 'NL', 'AT', 'CH'];
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
     *
     * @var bool
     */
    protected $_blIsPayolutionInstallmentAjax = null;
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
    protected $_aRatePayProfileIds = [
        'fcporp_bill' => null,
        'fcporp_debitnote' => null,
        'fcporp_installment' => null
    ];
    /**
     * Contains a cached version of profile data for successive short term requests
     *
     * @var string
     */
    protected $_aCachedRatepayProfileData = [
        'fcporp_bill' => null,
        'fcporp_debitnote' => null,
        'fcporp_installment' => null
    ];
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
    protected $_sPayolutionCurrentErrorMessage = null;

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
        $this->_blIsPayolutionInstallmentAjax = false;
    }

    /**
     * Wrapper for checking if payment is allowed to be in usual payment
     * selection
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function fcpoShowAsRegularPaymentSelection(string $sPaymentId): bool
    {
        $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
        $oPayment->load($sPaymentId);
        $blShowAsRegularPaymentSelection =
            $oPayment->fcpoShowAsRegularPaymentSelection();

        if ($blShowAsRegularPaymentSelection && in_array($sPaymentId, ['fcpopl_secinvoice', 'fcpopl_secinstallment'])) {
            $blShowAsRegularPaymentSelection = $this->fcpoShowBNPLPaymentSelection();
        }

        return $blShowAsRegularPaymentSelection;
    }

    /**
     * Check if BNPL methods can be shown (AT/DE country and EUR currency only)
     *
     * @return bool
     */
    protected function fcpoShowBNPLPaymentSelection()
    {
        if (!in_array($this->getUserBillCountryId(), ['a7c40f631fc920687.20179984', 'a7c40f6320aeb2ec2.72885259'])) {
            return false;
        }
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oCurr = $oConfig->getActShopCurrencyObject();
        if ($oCurr->name != 'EUR') {
            return false;
        }

        return true;
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
     * Extends oxid standard method init()
     * Executes parent method parent::init().
     *
     * @return null
     */
    public function init()
    {
        if ($this->_hasFilterDynDataMethod() === false) {
            $this->_filterDynData();
        }
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        $sOrderId = $this->_oFcPoHelper->fcpoGetSessionVariable('sess_challenge');
        $sType = $this->_oFcPoHelper->fcpoGetRequestParameter('type');
        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        $blReduceStockBefore = !(bool)$oConfig->getConfigParam('blFCPOReduceStock');
        if ($sOrderId && $blPresaveOrder && $blReduceStockBefore && ($sType == 'error' || $sType == 'cancel')) {
            $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
            $oOrder->load($sOrderId);
            if ($oOrder) {
                $oOrder->cancelOrder();
            }
            unset($oOrder);
        }
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('sess_challenge');

        parent::init();
    }

    /**
     * Checks whether the oxid version has the _filterDynData method
     * Oxid 4.2 and below dont have the _filterDynData method
     *
     * @return bool
     */
    protected function _hasFilterDynDataMethod()
    {
        return true;
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
        if ($this->_hasFilterDynDataMethod() === true) {
            parent::_filterDynData();
        }

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        //in case we actually ARE allowed to store the data
        if ($oConfig->getConfigParam("blStoreCreditCardInfo")) {
            //then do nothing
            return;
        }

        $aDynData = $this->_oFcPoHelper->fcpoGetSessionVariable("dynvalue");

        if ($aDynData && is_array($aDynData)) {
            $aDynData["fcpo_kktype"] = null;
            $aDynData["fcpo_kknumber"] = null;
            $aDynData["fcpo_kkname"] = null;
            $aDynData["fcpo_kkmonth"] = null;
            $aDynData["fcpo_kkyear"] = null;
            $aDynData["fcpo_kkpruef"] = null;
            $aDynData["fcpo_kkcsn"] = null;
            $this->_oFcPoHelper->fcpoSetSessionVariable("dynvalue", $aDynData);
        }

        $aParameters = [
            'fcpo_kktype',
            'fcpo_kknumber',
            'fcpo_kkname',
            'fcpo_kkmonth',
            'fcpo_kkyear',
            'fcpo_kkpruef',
            'fcpo_kkcsn'
        ];

        foreach ($aParameters as $sParameter) {
            unset($_REQUEST['dynvalue'][$sParameter]);
            unset($_POST['dynvalue'][$sParameter]);
            unset($_GET['dynvalue'][$sParameter]);
        }
    }

    /**
     * Returns array of years for credit cards
     *
     * @return array
     */
    public function getCreditYears(): array
    {
        return range(date('Y'), date('Y', strtotime('+10 year')));
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
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam($sParam);
    }

    public function fcpoGetBNPLInstallment()
    {
        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oRequest->sendRequestBNPLInstallmentOptions();

        $aFormattedData = [];
        $aFormattedData['status'] = $aResponse['status'];
        $aFormattedData['workorderid'] = $aResponse['workorderid'];
        $aFormattedData['amountValue'] = $this->fcpoPriceFromCentToDec($aResponse['add_paydata[amount_value]']);
        $aFormattedData['amountCurrency'] = $aResponse['add_paydata[amount_currency]'];
        $aFormattedData['plans'] = [];

        $this->_oFcPoHelper->fcpoSetSessionVariable('fcpopl_secinstallment_workorderid', $aResponse['workorderid']);

        $iCurrPlan = 0;
        while (true) {
            if (!isset ($aResponse['add_paydata[total_amount_currency_' . $iCurrPlan . ']'])) {
                break;
            }

            $aFormattedData['plans'][$iCurrPlan] = [
                'effectiveInterestRate' => $this->fcpoPriceFromCentToDec($aResponse['add_paydata[effective_interest_rate_' . $iCurrPlan . ']']),
                'firstRateDate' => $aResponse['add_paydata[first_rate_date_' . $iCurrPlan . ']'],
                'installmentOptionId' => $aResponse['add_paydata[installment_option_id_' . $iCurrPlan . ']'],
                'lastRateAmountCurrency' => $aResponse['add_paydata[last_rate_amount_currency_' . $iCurrPlan . ']'],
                'lastRateAmountValue' => $this->fcpoPriceFromCentToDec($aResponse['add_paydata[last_rate_amount_value_' . $iCurrPlan . ']']),
                'linkCreditInformationHref' => $aResponse['add_paydata[link_credit_information_href_' . $iCurrPlan . ']'],
                'linkCreditInformationType' => $aResponse['add_paydata[link_credit_information_type_' . $iCurrPlan . ']'],
                'monthlyAmountCurrency' => $aResponse['add_paydata[monthly_amount_currency_' . $iCurrPlan . ']'],
                'monthlyAmountValue' => $this->fcpoPriceFromCentToDec($aResponse['add_paydata[monthly_amount_value_' . $iCurrPlan . ']']),
                'nominalInterestRate' => $this->fcpoPriceFromCentToDec($aResponse['add_paydata[nominal_interest_rate_' . $iCurrPlan . ']']),
                'numberOfPayments' => $aResponse['add_paydata[number_of_payments_' . $iCurrPlan . ']'],
                'totalAmountCurrency' => $aResponse['add_paydata[total_amount_currency_' . $iCurrPlan . ']'],
                'totalAmountValue' => $this->fcpoPriceFromCentToDec($aResponse['add_paydata[total_amount_value_' . $iCurrPlan . ']']),
            ];

            $iCurrPlan++;
        }

        return $aFormattedData;
    }

    protected function fcpoPriceFromCentToDec($iAmount)
    {
        return number_format($iAmount / 100, 2, ',', '.');
    }

    /**
     * Prepares some parameter for the installment calculation
     *
     * @param string $sPaymentId
     * @return array
     */
    public function fcpoGetRatepayCalculatorParams($sPaymentId)
    {
        $aRatepayCalculatorParams = [];
        $aRatepayData = $this->fcpoGetRatepayProfileData($sPaymentId);

        $aMonthAllowed = explode(',', $aRatepayData['month_allowed']);
        $iValidMaxDuration = $this->_fcpoGetRatepayValidMaxAllowedMonth($aRatepayData, max($aMonthAllowed));
        $aMonthAllowed = array_filter($aMonthAllowed, function ($month) use ($iValidMaxDuration) {
            return $month <= $iValidMaxDuration;
        });

        $aRatepayCalculatorParams['monthAllowed'] = $aMonthAllowed;

        return $aRatepayCalculatorParams;
    }

    /**
     * Returns matched profile details
     *
     * @param string $sPaymentId
     * @return array
     */
    public function fcpoGetRatepayProfileData($sPaymentId)
    {
        if (is_null($this->_aCachedRatepayProfileData[$sPaymentId])) {
            $sOxid = $this->fcpoGetRatePayMatchedProfile($sPaymentId);
            $oRatePay = oxNew(FcPoRatePay::class);
            $aProfileData = $oRatePay->fcpoGetProfileData($sOxid);
            $this->_aCachedRatepayProfileData[$sPaymentId] = $aProfileData;
        }

        return $this->_aCachedRatepayProfileData[$sPaymentId];
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
     * Execute a pre-check to determine the maximal duration and limit the offered options
     *
     * @param array $aRatepayData
     * @param int   $iMaxMonthAllowed
     * @return int
     */
    protected function _fcpoGetRatepayValidMaxAllowedMonth($aRatepayData, $iMaxMonthAllowed)
    {
        $aRatepayData['duration'] = $iMaxMonthAllowed;
        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oRequest->sendRequestRatepayCalculation('calculation-by-time', $aRatepayData);

        return $aResponse['add_paydata[number-of-rates]'];
    }

    /**
     * Determines which settlement types are available in the connected Ratepay profile
     *
     * @return string
     */
    public function fcpoGetRatepaySettlementType($sPaymentId)
    {
        $aRatepayData = $this->fcpoGetRatepayProfileData($sPaymentId);
        $sFirstDay = $aRatepayData['payment_firstday'];
        $sBillCountry = $aRatepayData['country_code_billing'];

        if (!in_array($sBillCountry, ['DE', 'AT'])) {
            return false;
        }

        if ($sFirstDay == '2,28') {
            return 'both';
        } elseif ($sFirstDay == '28') {
            return 'banktransfer';
        }
        return 'debit';
    }

    /**
     * Returns matching notiication string if sofo is configured to show iban
     *
     * @return bool
     */
    public function fcpoGetTrustlyShowIban()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blFCPOSofoShowIban = $oConfig->getConfigParam('blFCPOTrustlyShowIban');

        return (bool)$blFCPOSofoShowIban;
    }

    /**
     * Method checks if deprecated bankdata should be requested instead of
     * IBAN/BIC
     *
     * @return bool
     */
    public function fcpoForceDeprecatedBankData()
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
     * @return string
     */
    public function fcGetBillCountry()
    {
        $sBillCountryId = $this->getUserBillCountryId();
        $oCountry = $this->_oFcPoHelper->getFactoryObject(Country::class);

        return ($oCountry->load($sBillCountryId)) ? $oCountry->oxcountry__oxisoalpha2->value : '';
    }

    /**
     * Returns matching notiication string if sofo is configured to show iban
     *
     * @return bool
     */
    public function fcpoGetSofoShowIban()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blFCPOSofoShowIban = $oConfig->getConfigParam('blFCPOSofoShowIban');

        return (bool)$blFCPOSofoShowIban;
    }

    /**
     * Returns if given paymentid represents an active payment
     *
     * @param $sPaymentId
     * @return bool
     */
    public function fcpoPaymentActive($sPaymentId)
    {
        $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
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
     * @return mixed object/bool
     */
    protected function _fcpoGetUserFromSession()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();

        return $oBasket->getBasketUser();
    }

    /**
     * Method decides if certain paymentid is of newer klarna type,
     * the currency and country is supported and
     * the combined widget already has been displayed.
     *
     * @param $sPaymentId
     * @return bool
     */
    public function fcpoShowKlarnaCombined($sPaymentId)
    {
        $blIsKlarnaCombined = $this->fcpoIsKlarnaCombined($sPaymentId);
        $blIsCountryCurrencyAllowedByKlarna = $this->_fcpoIsCountryCurrencyAllowedByKlarna();
        if (
            $blIsKlarnaCombined &&
            $blIsCountryCurrencyAllowedByKlarna &&
            $this->_blKlarnaCombinedIsPresent === false
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
     * @return bool
     */
    public function fcpoIsKlarnaCombined($sPaymentId)
    {
        return (
        in_array($sPaymentId, [
            'fcpoklarna_invoice',
            'fcpoklarna_directdebit',
            'fcpoklarna_installments',
        ])
        );
    }

    /**
     * Checks if klarna support the user's billing country with the active shop currency for payments.
     *
     * @return bool
     */
    protected function _fcpoIsCountryCurrencyAllowedByKlarna()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        $sCountryIso = $oUser->fcpoGetUserCountryIso();

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oActCurrency = $oConfig->getActShopCurrencyObject();
        $sCurrencyName = $oActCurrency->name;

        $aAllowedCountryCurrencies = [
            'AT' => 'EUR',
            'DK' => 'DKK',
            'FI' => 'EUR',
            'DE' => 'EUR',
            'NL' => 'EUR',
            'NO' => 'NOK',
            'SE' => 'SEK',
            'CH' => 'CHF',
        ];

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
        if (count($aMatchingRatePayProfile) > 0) {
            $blReturn = true;
        }

        if ($blReturn === true) {
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

        foreach ($aRatePayProfiles as $aCurrentRatePayProfile) {
            $sPaymentStringAddition = $this->_fcpoGetRatePayStringAdditionByPaymentId($sPaymentId);
            if ($sPaymentStringAddition) {
                $sProfileBasketMaxIndex = 'tx_limit_' . $sPaymentStringAddition . '_max';
                $sProfileBasketMinIndex = 'tx_limit_' . $sPaymentStringAddition . '_min';
                $sProfileActivationStatusIndex = 'activation_status_' . $sPaymentStringAddition;
                $dProfileBasketValueMax = (double)$aCurrentRatePayProfile[$sProfileBasketMaxIndex];
                $dProfileBasketValueMin = (double)$aCurrentRatePayProfile[$sProfileBasketMinIndex];
                $sProfileActivationStatus = $aCurrentRatePayProfile[$sProfileActivationStatusIndex];
                $sProfileCountryBilling = $aCurrentRatePayProfile['country_code_billing'];
                $sProfileCurrency = $aCurrentRatePayProfile['currency'];

                $aRatepayMatchData = [
                    'basketvalue_max' => $dProfileBasketValueMax,
                    'basketvalue_min' => $dProfileBasketValueMin,
                    'activation_status' => $sProfileActivationStatus,
                    'country_code_billing' => $sProfileCountryBilling,
                    'currency' => $sProfileCurrency,
                ];

                $blProfileMatches = $this->_fcpoCheckRatePayProfileMatch($aRatepayMatchData);
                if ($blProfileMatches) {
                    $aReturn = $aCurrentRatePayProfile;
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
        $oRatePay = oxNew(FcPoRatePay::class);

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
        $aMap = [
            'fcporp_bill' => 'invoice',
            'fcporp_debitnote' => 'elv',
            'fcporp_installment' => 'installment',
        ];

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
     * @return boolean
     */
    protected function _fcpoCheckRatePayProfileMatch($aRatepayMatchData)
    {
        if ($aRatepayMatchData['activation_status'] != '2') {
            return false;
        }

        $dBasketValue = $this->fcpoGetDBasketSum();
        $blBasketValueMatches = (
            $dBasketValue <= $aRatepayMatchData['basketvalue_max'] &&
            $dBasketValue >= $aRatepayMatchData['basketvalue_min']
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
        $blCurrencyMatches = (
            $oCur->name == $aRatepayMatchData['currency']
        );
        if (!$blCurrencyMatches) {
            return false;
        }

        return true;
    }

    /**
     * Returns the sum of basket
     *
     * @return float
     */
    public function fcpoGetDBasketSum()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        return $oBasket->getBruttoSum();
    }

    /**
     * Check if there are available sub payment types for the user
     *
     * @param string $sType payment type PAYONE
     *
     * @return bool
     */
    public function hasPaymentMethodAvailableSubTypes($sType)
    {
        $aSubtypes = [
            'cc' => [
                $this->getVisa(),
                $this->getMastercard(),
                $this->getAmex(),
                $this->getDiners(),
                $this->getJCB(),
                $this->getMaestroInternational(),
                $this->getMaestroUK(),
                $this->getCarteBleue(),
            ],
            'sb' => [
                $this->getSofortUeberweisung(),
                $this->getPostFinanceEFinance(),
                $this->getPostFinanceCard(),
                $this->getIdeal(),
                $this->getP24(),
                $this->getBancontact(),
            ],
        ];

        return in_array(true, $aSubtypes[$sType]);
    }

    /**
     * Check if sub payment method Visa is available to the user
     *
     * @return bool
     */
    public function getVisa()
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
        if (array_key_exists($sSubPaymentId . '_' . $sType, $this->_aCheckedSubPayments) === false) {
            $sUserBillCountryId = $this->getUserBillCountryId();
            $sUserDelCountryId = $this->getUserDelCountryId();
            $oPayment = oxNew(Payment::class);
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
            $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
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
     *
     * @return bool
     */
    public function getMastercard()
    {
        return ($this->getConfigParam('blFCPOMastercardActivated') && $this->isPaymentMethodAvailableToUser('M', 'cc'));
    }

    /**
     * Check if sub payment method Amex is available to the user
     *
     * @return bool
     */
    public function getAmex()
    {
        return ($this->getConfigParam('blFCPOAmexActivated') && $this->isPaymentMethodAvailableToUser('A', 'cc'));
    }

    /**
     * Check if sub payment method Diners is available to the user
     *
     * @return bool
     */
    public function getDiners()
    {
        return ($this->getConfigParam('blFCPODinersActivated') && $this->isPaymentMethodAvailableToUser('D', 'cc'));
    }

    /**
     * Check if sub payment method JCB is available to the user
     *
     * @return bool
     */
    public function getJCB()
    {
        return ($this->getConfigParam('blFCPOJCBActivated') && $this->isPaymentMethodAvailableToUser('J', 'cc'));
    }

    /**
     * Check if sub payment method MaestroInternational is available to the user
     *
     * @return bool
     */
    public function getMaestroInternational()
    {
        return ($this->getConfigParam('blFCPOMaestroIntActivated') && $this->isPaymentMethodAvailableToUser('O', 'cc'));
    }

    /**
     * Check if sub payment method MaestroUK is available to the user
     *
     * @return bool
     */
    public function getMaestroUK()
    {
        return ($this->getConfigParam('blFCPOMaestroUKActivated') && $this->isPaymentMethodAvailableToUser('U', 'cc'));
    }

    /**
     * Check if sub payment method CarteBleue is available to the user
     *
     * @return bool
     */
    public function getCarteBleue()
    {
        return ($this->getConfigParam('blFCPOCarteBleueActivated') && $this->isPaymentMethodAvailableToUser('B', 'cc'));
    }

    /**
     * Check if sub payment method SofortUeberweisung is available to the user
     *
     * @return bool
     */
    public function getSofortUeberweisung()
    {
        return ($this->getConfigParam('blFCPOSofoActivated') && $this->isPaymentMethodAvailableToUser('PNT', 'sb'));
    }

    /**
     * Check if sub payment method PostFinanceEFinance is available to the user
     *
     * @return bool
     */
    public function getPostFinanceEFinance()
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
     *
     * @return bool
     */
    public function getPostFinanceCard()
    {
        return ($this->getConfigParam('blFCPOPoFiCaActivated') && $this->isPaymentMethodAvailableToUser('PFC', 'sb'));
    }

    /**
     * Check if sub payment method Ideal is available to the user
     *
     * @return bool
     */
    public function getIdeal()
    {
        return ($this->getConfigParam('blFCPOiDealActivated') && $this->isPaymentMethodAvailableToUser('IDL', 'sb'));
    }

    /**
     * Check if sub payment method Przelewy24 is available to the user
     *
     * @return bool
     */
    public function getP24()
    {
        return ($this->getConfigParam('blFCPOP24Activated') && $this->isPaymentMethodAvailableToUser('P24', 'sb'));
    }

    /**
     * Check if sub payment method Bancontact is available to the user
     *
     * @return bool
     */
    public function getBancontact()
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
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oPrice = $oBasket->getPrice();
        $dPrice = $oPrice->getBruttoPrice();

        return number_format($dPrice, 2, '.', '') * 100;
    }

    /**
     * Get the language the user is using in the shop
     *
     * @return string
     */
    public function getTplLang()
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        return $oLang->getLanguageAbbr();
    }

    /**
     * Template getter for delivering all meta information to build input fields in foreach loop
     *
     * @return array
     */
    public function fcpoGetCCPaymentMetaData()
    {
        $this->_aPaymentCCMetaData = [];
        $sPaymentId = 'fcpocreditcard';

        $oPayment = oxNew(Payment::class);
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
     * @param Payment $oPayment
     * @param string  $sBrandShortcut
     * @param string  $sBrandName
     */
    protected function _fcpoSetCCMetaData($oPayment, $sBrandShortcut, $sBrandName)
    {
        $aActiveCCBrands = [
            'V' => $this->getVisa(),
            'M' => $this->getMastercard(),
            'A' => $this->getAmex(),
            'D' => $this->getDiners(),
            'J' => $this->getJCB(),
            'O' => $this->getMaestroInternational(),
            'U' => $this->getMaestroUK(),
            'B' => $this->getCarteBleue(),
        ];

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
    protected function _fcpoGetCCPaymentMetaData($oPayment, $sPaymentTag, $sPaymentName)
    {
        $sPaymentId = $oPayment->getId();
        $sHashNamePrefix = "fcpo_hashcc_";
        $OperationModeNamePrefix = "fcpo_mode_";
        $aDynValue = $this->getDynValue();
        $blSelected = ($aDynValue['fcpo_kktype'] == $sPaymentTag) ? true : false;

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
     * @return array
     */
    public function getDynValue()
    {
        $aReturn = parent::getDynValue();
        if ((bool)$this->getConfigParam('sFCPOSaveBankdata') === true) {
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
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoordernotchecked');
        if ($this->_oPaymentList === null) {
            $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
            $oUser = $this->getUser();
            $sBoniCheckMoment = $oConfig->getConfigParam('sFCPOBonicheckMoment');

            if ($oUser) {
                $blContinue = $sBoniCheckMoment != 'after' ? $oUser->checkAddressAndScore() : $oUser->checkAddressAndScore(true, false);
            } else {
                $blContinue = true;
            }

            if ($blContinue === true) {
                parent::getPaymentList();
                $this->_fcpoCheckPaypalExpressRemoval();
                $this->_fcpoRemoveForbiddenPaymentsByUser();
                $this->_fcpoCheckSecInvoiceRemoval();
                $this->_fcpoCheckBNPLRemoval();
            } else {
                $oUtils = $this->_oFcPoHelper->fcpoGetUtils();
                $oUtils->redirect($this->_oFcPoHelper->fcpoGetConfig()->getShopHomeURL() . 'cl=user', false);
            }
        }
        return $this->_oPaymentList;
    }

    /**
     * Checking if paypal express should be removed from payment list
     *
     * @return void
     */
    protected function _fcpoCheckPaypalExpressRemoval()
    {
        $this->_fcpoRemovePaymentFromFrontend('fcpopaypal_express');
        //&& !$this->_oFcPoHelper->fcpoGetSessionVariable('fcpoWorkorderId')
    }

    /**
     * Removes payment from frontend
     *
     * @param $sPaymentId
     * @return void
     */
    protected function _fcpoRemovePaymentFromFrontend($sPaymentId)
    {
        if (array_key_exists($sPaymentId, $this->_oPaymentList) !== false) {
            unset($this->_oPaymentList[$sPaymentId]);

        }
    }

    /**
     * Removes payments that are forbidden by user
     *
     * @return void
     */
    protected function _fcpoRemoveForbiddenPaymentsByUser()
    {
        $oUser = $this->getUser();
        $aForbiddenPaymentIds = $oUser->fcpoGetForbiddenPaymentIds();
        foreach ($aForbiddenPaymentIds as $sForbiddenPaymentId) {
            $this->_fcpoRemovePaymentFromFrontend($sForbiddenPaymentId);
        }
    }

    /**
     * Checking if secure invoice should be removed from payment list
     *
     * @return void
     */
    protected function _fcpoCheckSecInvoiceRemoval()
    {
        $blshowshipaddress = $this->_oFcPoHelper->fcpoGetSessionVariable('blshowshipaddress');
        if ($blshowshipaddress == 1) {
            $this->_fcpoRemovePaymentFromFrontend('fcpo_secinvoice');
        }
    }

    /**
     * Checking if BNPL methods should be removed from payment list
     *
     * @return void
     */
    protected function _fcpoCheckBNPLRemoval()
    {
        $oUser = $this->getUser();
        $blIsB2B = $oUser->oxuser__oxcompany->value != '';

        $blshowshipaddress = $this->_oFcPoHelper->fcpoGetSessionVariable('blshowshipaddress');
        $blDiffShippingAllowed = $this->_oFcPoHelper->fcpoGetConfig()->getConfigParam('blFCPOPLAllowDiffAddress');

        if ((!$blDiffShippingAllowed && $blshowshipaddress == 1) || $blIsB2B) {
            $this->_fcpoRemovePaymentFromFrontend('fcpopl_secinvoice');
            $this->_fcpoRemovePaymentFromFrontend('fcpopl_secinstallment');
        }
    }

    /**
     * Assign debit note payment values to view data. Loads user debit note payment
     * if available and assigns payment data to $this->_aDynValue
     *
     * @return null
     */
    protected function _assignDebitNoteParams()
    {
        parent::_assignDebitNoteParams();
        if ((bool)$this->getConfigParam('sFCPOSaveBankdata') === true) {
            //such info available ?
            if ($oUserPayment = $this->_fcGetPaymentByPaymentType($this->getUser(), 'fcpodebitnote')) {
                $oUtils = $this->_oFcPoHelper->fcpoGetUtils();
                $aAddPaymentData = $oUtils->assignValuesFromText($oUserPayment->oxuserpayments__oxvalue->value);
                //checking if some of values is allready set in session - leave it
                foreach ($aAddPaymentData as $oData) {
                    if (!isset($this->_aDynValue[$oData->name]) || (isset($this->_aDynValue[$oData->name]) && !$this->_aDynValue[$oData->name])) {
                        $this->_aDynValue[$oData->name] = $oData->value;
                    }
                }
            }
        }
    }

    /**
     * Get user payment by payment id with oxid bugfix for getting last payment
     *
     * @param User   $oUser        user object
     * @param string $sPaymentType payment type
     *
     * @return bool
     */
    protected function _fcGetPaymentByPaymentType($oUser = null, $sPaymentType = null)
    {
        $mReturn = false;
        if ($oUser && $sPaymentType != null) {
            $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
            $sOxid = $oPayment->fcpoGetUserPaymentId($oUser->getId(), $sPaymentType);

            if ($sOxid) {
                $oUserPayment = $this->_oFcPoHelper->getFactoryObject(UserPayment::class);
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
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
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
     *
     * @return string
     */
    public function getEncoding()
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
        $oPayment = oxNew(Payment::class);
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
     * @return array
     */
    public function fcpoGetOnlinePaymentMetaData()
    {
        $aPaymentMetaData = [];

        if ($this->getSofortUeberweisung()) {
            $aPaymentMetaData[] = $this->_fcpoGetOnlinePaymentData('PNT');
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
    protected function _fcpoGetOnlinePaymentData($sIdent)
    {
        $aDynValue = $this->getDynValue();
        $blSelected = ($aDynValue['fcpo_sotype'] == $sIdent) ? true : false;

        $aCaptions = [
            'PNT' => 'SOFORT &Uuml;berweisung',
            'EPS' => 'eps - Online-&Uuml;berweisung',
            'PFF' => 'PostFinance E-Finance',
            'PFC' => 'PostFinance Card',
            'IDL' => 'iDeal',
            'P24' => 'P24',
            'BCT' => 'Bancontact',
        ];

        $sCaption = ($aCaptions[$sIdent]) ? $aCaptions[$sIdent] : '';

        $oPaymentMetaData = new stdClass();
        $oPaymentMetaData->sShortcut = $sIdent;
        $oPaymentMetaData->sCaption = $sCaption;
        $oPaymentMetaData->blSelected = $blSelected;

        return $oPaymentMetaData;
    }

    /**
     * Check if sub payment method EPS is available to the user
     *
     * @return bool
     */
    public function getEPS()
    {
        return ($this->getConfigParam('blFCPOepsActivated') && $this->isPaymentMethodAvailableToUser('EPS', 'sb'));
    }

    /**
     * Method returns active theme path by checking current theme and its parent
     * If theme is not assignable, 'azure' will be the fallback
     *
     * @return string
     */
    public function fcpoGetActiveThemePath()
    {
        $oViewConfig = $this->_oFcPoHelper->getFactoryObject(ViewConfig::class);

        return $oViewConfig->fcpoGetActiveThemePath();
    }

    /**
     * Get verification safety hash for debitnote payment method with checktype parameter
     *
     * @return string
     */
    public function getHashELVWithChecktype()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sFCPOHashMethod = $oConfig->getConfigParam('sFCPOHashMethod');
        $sKey = $this->getPortalKey();

        $sData =
            $this->getSubAccountId() .
            $this->getChecktype() .
            $this->getEncoding() .
            $this->getMerchantId() .
            $this->_getOperationModeELV() .
            $this->getPortalId() .
            'bankaccountcheck' .
            'JSON';

        $sHashMD5 = md5($sData . $sKey);
        $sHashSha2 = hash_hmac('sha384', $sData, $sKey);

        return ($sFCPOHashMethod == 'sha2-384')
            ? $sHashSha2 : $sHashMD5;
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
        $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
        $oPayment->load('fcpodebitnote');
        return $oPayment->fcpoGetOperationMode();
    }

    /**
     * Get verification safety hash for debitnote payment method without checktype parameter
     *
     * @return string
     */
    public function getHashELVWithoutChecktype()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sFCPOHashMethod = $oConfig->getConfigParam('sFCPOHashMethod');
        $sKey = $this->getPortalKey();

        $sData =
            $this->getSubAccountId() .
            $this->getEncoding() .
            $this->getMerchantId() .
            $this->_getOperationModeELV() .
            $this->getPortalId() .
            'bankaccountcheck' .
            'JSON';

        $sHashMD5 = md5($sData . $sKey);
        $sHashSha2 = hash_hmac('sha384', $sData, $sKey);

        return ($sFCPOHashMethod == 'sha2-384')
            ? $sHashSha2 : $sHashMD5;
    }

    /**
     * Returns if option for BIC is set mandatory as string to handle it with javascript checks
     *
     * @return string
     */
    public function fcpoGetBICMandatory()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blFCPODebitBICMandatory = $oConfig->getConfigParam('blFCPODebitBICMandatory');

        return ($blFCPODebitBICMandatory) ? 'true' : 'false';
    }

    /**
     * Returns creditcard type
     *
     * @return mixed
     */
    public function fcpoGetCreditcardType()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOCCType');
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

        $mReturn = parent::validatePayment();

        $mReturn = $this->_processParentReturnValue($mReturn);

        return $this->_fcpoProcessValidation($mReturn, $sPaymentId);
    }

    /**
     * Returns paymentid wether from request parameter or session
     *
     * @return mixed
     */
    protected function _fcpoGetPaymentId()
    {
        $sPaymentId = $this->_oFcPoHelper->fcpoGetRequestParameter('paymentid');
        if (!$sPaymentId) {
            $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');
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

        if (array_key_exists('fcpo_' . $sType . '_del_addinfo', $aDynValue) !== false) {
            $sDeliveryAddressId = $oUser->getSelectedAddressId();
            if ($sDeliveryAddressId) {
                $oAddress = $this->_oFcPoHelper->getFactoryObject(Address::class);
                if ($oAddress->load($sDeliveryAddressId)) {
                    $oAddress->oxaddress__oxaddinfo = new Field($aDynValue['fcpo_' . $sType . '_del_addinfo'], Field::T_RAW);
                    $oAddress->save();
                }
            }
        }

        if ($blUserChanged === true) {
            $oUser->save();
        }
    }

    /**
     * Set payment type and process special case of klarna
     *
     * @return string
     */
    protected function _fcpoGetType($sPaymentId)
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
     * @param User    $oUser
     * @return boolean
     */
    protected function _fcpoCheckUpdateField($blUserChanged, $sType, $aDynValue, $sDbField, $sDynValueField, $oUser)
    {
        $blAlreadyChanged = $blUserChanged;
        $sCompleteDynValueName = 'fcpo_' . $sType . '_' . $sDynValueField;

        if (array_key_exists($sCompleteDynValueName, $aDynValue) !== false) {
            $sObjAttribute = 'oxuser__' . $sDbField;

            $oUser->$sObjAttribute = new Field($aDynValue[$sCompleteDynValueName], Field::T_RAW);
            $blUserChanged = true;
        }

        if ($blAlreadyChanged === true) {
            $blReturn = $blAlreadyChanged;
        } else {
            $blReturn = $blUserChanged;
        }

        return $blReturn;
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
     * @param mixed  $mReturn
     * @param string $sPaymentId
     * @return mixed
     */
    protected function _fcpoProcessValidation($mReturn, $sPaymentId)
    {
        if ($mReturn == 'order') { // success
            $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
            $oPayment->load($sPaymentId);
            $mReturn = $this->_fcpoSecInvoiceSaveRequestedValues($mReturn, $sPaymentId);
            $mReturn = $this->_fcpoBNPLSaveRequestedValues($mReturn, $sPaymentId);
            $blContinue = $this->_fcpoCheckBoniMoment($oPayment);

            if ($blContinue !== true) {
                $this->_fcpoSetBoniErrorValues($sPaymentId);
                $mReturn = 'basket';
            } else {
                $this->_fcpoSetMandateParams($oPayment);
            }

            $this->_fcCleanupSessionFragments($oPayment);

            $mReturn = $this->_fcpoKlarnaCombinedValidate($mReturn, $sPaymentId);

            $mReturn = $this->_fcpoPayolutionPreCheck($mReturn, $sPaymentId);
            if (in_array($sPaymentId, ['fcporp_bill', 'fcporp_debitnote', 'fcporp_installment'])) {
                $mReturn = $this->_fcpoCheckRatePayBillMandatoryUserData($mReturn, $sPaymentId);
            }
            $mReturn = $this->_fcpoAdultCheck($mReturn, $sPaymentId);
        }

        return $mReturn;
    }

    /**
     * Save requested values of secure invoice
     *
     * @param mixed  $mReturn
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoSecInvoiceSaveRequestedValues($mReturn, $sPaymentId)
    {
        $blIsSecInvoice = ($sPaymentId == 'fcpo_secinvoice');
        if (!$blIsSecInvoice) return $mReturn;

        $aRequestedValues = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
        $aBirthdayValidation = $this->_fcpoValidateBirthdayData($sPaymentId, (array)$aRequestedValues);
        $blBirthdayRequired = $aBirthdayValidation['blBirthdayRequired'];

        $blBirthdayCheckPassed = true;
        if ($blBirthdayRequired) {
            $blBirthdayCheckPassed =
                $this->_fcpoSaveBirthdayData((array)$aRequestedValues, $sPaymentId);
        }

        $this->_fcpoSaveUserData($sPaymentId, 'oxustid');

        return ($blBirthdayCheckPassed) ? $mReturn : false;
    }

    /**
     * Checks request data for valid birthday data
     *
     * @param string $sPaymentId
     * @param array  $aRequestedValues
     * @return array
     */
    protected function _fcpoValidateBirthdayData(string $sPaymentId, array $aRequestedValues): array
    {
        $blBirthdayRequired = false;

        // validation
        switch ($sPaymentId) {
            case 'fcpopo_bill':
            case 'fcpopo_debitnote':
            case 'fcpopo_installment':
                $blB2CMode = $this->fcpoShowPayolutionB2C();
                $blBirthdayRequired = $blB2CMode;
                $blValidBirthdateData = $this->_fcpoValidatePayolutionBirthdayData($sPaymentId, $aRequestedValues);
                break;
            case 'fcpo_secinvoice':
            case 'fcpopl_secinvoice':
            case 'fcpopl_secinstallment':
                $blB2CMode = !$this->fcpoIsB2BPov();
                $blFieldPresence = isset($aRequestedValues['fcpopl_secinvoice_birthdate_day'])
                    && isset($aRequestedValues['fcpopl_secinvoice_birthdate_month'])
                    && isset($aRequestedValues['fcpopl_secinvoice_birthdate_year']);
                $blBirthdayRequired = $blB2CMode && $blFieldPresence;
                $blValidBirthdateData = $this->_fcpoValidateSecInvoiceBirthdayData($sPaymentId, $aRequestedValues);
                break;
        }

        return [
            'blValidBirthdateData' => $blValidBirthdateData,
            'blBirthdayRequired' => $blBirthdayRequired
        ];
    }

    /**
     * Template getter for checking which kind of field should be shown
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
     * @return bool
     */
    public function fcpoShowPayolutionB2B()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blB2BModeActive = $oConfig->getConfigParam('blFCPOPayolutionB2BMode');

        if ($blB2BModeActive) {
            $blReturn = $this->fcpoIsB2B();
        } else {
            $blReturn = false;
        }

        return $blReturn;
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
    protected function _fcpoValidatePayolutionBirthdayData(string $sPaymentId, array $aRequestedValues): bool
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
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
     * @return boolean
     */
    protected function _fcpoValidateSecInvoiceBirthdayData($sPaymentId, $aRequestedValues)
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sChooseString = $oLang->translateString('FCPO_PAYOLUTION_PLEASE SELECT');
        $sBirthdateYear = $aRequestedValues[$sPaymentId . '_birthdate_year'];
        $sBirthdateMonth = $aRequestedValues[$sPaymentId . '_birthdate_month'];
        $sBirthdateDay = $aRequestedValues[$sPaymentId . '_birthdate_day'];
        $blValidRequestYear = ((!empty($sBirthdateYear) && $sBirthdateYear != $sChooseString));
        $blValidRequestMonth = ((!empty($sBirthdateMonth) && $sBirthdateMonth != $sChooseString));
        $blValidRequestDay = ((!empty($sBirthdateDay) && $sBirthdateDay != $sChooseString));

        return $blValidRequestYear && $blValidRequestMonth && $blValidRequestDay;
    }

    /**
     * Method saves birthday data if needed and returns if it has saved data or not
     *
     * @param array  $aRequestedValues
     * @param string $sPaymentId
     * @return bool
     */
    protected function _fcpoSaveBirthdayData(array $aRequestedValues, string $sPaymentId): bool
    {
        $oUser = $this->_fcpoGetUserFromSession();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $blSavedData = false;

        $aBirthdayValidation = $this->_fcpoValidateBirthdayData($sPaymentId, (array)$aRequestedValues);
        $blValidBirthdateData = $aBirthdayValidation['blValidBirthdateData'];
        $blBirthdayRequired = $aBirthdayValidation['blBirthdayRequired'];

        if (!$blBirthdayRequired) {
            return true;
        }

        if ($blValidBirthdateData) {
            $sRequestBirthdate = $this->_fcpoExtractBirthdateFromRequest($aRequestedValues, $sPaymentId);
            $blRefreshBirthdate = ($sRequestBirthdate != '0000-00-00' && $sRequestBirthdate != '--');
            if ($blRefreshBirthdate) {
                $oUser->oxuser__oxbirthdate = new Field($sRequestBirthdate, Field::T_RAW);
                $blSavedData = (bool)$oUser->save();
            }
        } elseif ($blBirthdayRequired) {
            $sMessage = $oLang->translateString('FCPO_PAYOLUTION_BIRTHDATE_INVALID');
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
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
            case 'fcpopl_secinvoice':
            case 'fcpopl_secinstallment':
                $sRequestBirthdate = $aRequestedValues[$sPaymentId . '_birthdate_year'] .
                    "-" . $aRequestedValues[$sPaymentId . '_birthdate_month'] .
                    "-" . $aRequestedValues[$sPaymentId . '_birthdate_day'];
                break;
        }

        return $sRequestBirthdate;
    }

    /**
     * Method checks if given field should be saved and returns if it has saved this data or not
     *
     * @param $sPaymentId
     * @param $sDbFieldName
     * @return bool
     */
    protected function _fcpoSaveUserData($sPaymentId, $sDbFieldName): bool
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
     * @param string $sDbFieldName
     * @return mixed string/boolean
     */
    protected function _fcpoGetRequestedValue(string $sPaymentId, string $sDbFieldName): mixed
    {
        $aRequestedValues = (array)$this->_fcpoGetRequestedValues();
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
     * @return array
     */
    protected function _fcpoGetRequestedValues()
    {
        if ($this->_aFcRequestedValues === null) {
            $aRequestedValues = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
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
        } catch (Exception $ex) {
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
            $oUser->$sUserField = new Field($sValue, Field::T_RAW);
            $oUser->save();
        }
    }

    /**
     * Save requested values of BNPL methods
     *
     * @param mixed  $mReturn
     * @param string $sPaymentId
     * @return mixed
     */
    public function _fcpoBNPLSaveRequestedValues(mixed $mReturn, string $sPaymentId): mixed
    {
        $blIsBNPL = ($sPaymentId == 'fcpopl_secinvoice' || $sPaymentId == 'fcpopl_secinstallment');
        if (!$blIsBNPL) return $mReturn;

        $aRequestedValues = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
        $aBirthdayValidation = $this->_fcpoValidateBirthdayData($sPaymentId, $aRequestedValues);
        $blBirthdayRequired = $aBirthdayValidation['blBirthdayRequired'];

        $blBirthdayCheckPassed = true;
        if ($blBirthdayRequired) {
            $blBirthdayCheckPassed = $this->_fcpoSaveBirthdayData((array)$aRequestedValues, $sPaymentId);
        }

        $blInstallmentPlanCheckPassed = true;
        if ($sPaymentId == 'fcpopl_secinstallment') {
            $blInstallmentPlanCheckPassed = $this->_fcpoBNPLValidateInstallmentPlan($aRequestedValues);
        }

        if (isset($aRequestedValues[$sPaymentId . '_fon'])) {
            $sCurrentValue = $this->fcpoGetUserValue('oxfon');
            $blRefreshValue = ($sCurrentValue != $aRequestedValues[$sPaymentId . '_fon']);
            if ($blRefreshValue) {
                $this->_fcpoSetUserValue('oxfon', $aRequestedValues[$sPaymentId . '_fon']);
            }
        }

        return ($blBirthdayCheckPassed && $blInstallmentPlanCheckPassed) ? $mReturn : false;
    }

    protected function _fcpoBNPLValidateInstallmentPlan($aRequestedValues)
    {
        if (empty($aRequestedValues['fcpopl_secinstallment_plan'])) {
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $sMessage = $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_PLAN_INVALID');
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);

            return false;
        }

        return true;

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
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blContinue = true;

        if ($oConfig->getConfigParam('sFCPOBonicheckMoment') == 'after') {
            $blContinue = $this->_fcpoCheckAddressAndScore($oPayment);
        }

        return $blContinue;
    }

    /**
     * Checks the address and boni values
     *
     * @param Payment $oPayment
     * @return boolean
     */
    protected function _fcpoCheckAddressAndScore($oPayment)
    {
        $oUser = $this->getUser();
        $sPaymentId = $oPayment->getId();
        $aApproval = $this->_oFcPoHelper->fcpoGetRequestParameter('fcpo_bonicheckapproved');
        $blApproval = $this->_fcpoValidateApproval($sPaymentId, $aApproval);
        $blBoniCheckNeeded = $oPayment->fcBoniCheckNeeded();

        if ($blBoniCheckNeeded === true && $blApproval === true) {
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
     * @param boolean $blContinue
     * @param Payment $oPayment
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
        if ($blBoniCheckNeeded === true && $blApproval === false) {
            $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoordernotchecked', 1);
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
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $iLangId = $this->fcGetLangId();

        // $this->_oFcPoHelper->fcpoSetSessionVariable( 'payerror', $oPayment->getPaymentErrorNumber() );
        $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
        $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $oConfig->getConfigParam('sFCPODenialText_' . $iLangId));

        //#1308C - delete paymentid from session, and save selected it just for view
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('paymentid');
        if (!($sPaymentId = $this->_oFcPoHelper->fcpoGetRequestParameter('paymentid'))) {
            $sPaymentId = $this->_oFcPoHelper->fcpoGetSessionVariable('paymentid');
        }
        $this->_oFcPoHelper->fcpoSetSessionVariable('_selected_paymentid', $sPaymentId);
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('stsprotection');

        $oSession = $this->_oFcPoHelper->fcpoGetSession();
    }

    public function fcGetLangId()
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        if (!$oLang) {
            return 0;
        }
        return (int)$oLang->getTplLanguage();
    }

    /**
     * Takes care of debit specific actions arround mandates
     *
     * @param Payment $oPayment
     * @return void
     */
    protected function _fcpoSetMandateParams($oPayment)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        if ($oPayment->getId() == 'fcpodebitnote' && $oConfig->getConfigParam('blFCPOMandateIssuance')) {
            $oUser = $this->getUser();
            $aDynValue = $this->_fcpoGetDynValues();

            $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
            $aResponse = $oPORequest->sendRequestManagemandate($oPayment->fcpoGetOperationMode(), $aDynValue, $oUser);

            $this->_fcpoHandleMandateResponse($aResponse);
        }
    }

    /**
     * Returns dynvalues wether from request or session
     *
     * @return mixed
     */
    protected function _fcpoGetDynValues()
    {
        $aDynvalue = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
        if (!$aDynvalue) {
            $aDynvalue = $this->_oFcPoHelper->fcpoGetSessionVariable('dynvalue');
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
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $oLang->translateString('FCPO_MANAGEMANDATE_ERROR'));
            return;
        } else if (is_array($aResponse) && array_key_exists('mandate_status', $aResponse) !== false) {
            $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoMandate', $aResponse);
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

        $aPayments2SessionVariables = [
            'fcpodebitnote' => ['fcpoMandate'],
            'fcpobarzahlen' => ['sFcpoBarzahlenHtml'],
            'fcpoklarna' => ['fcpo_klarna_campaign'],
        ];

        // remove own payment from list
        unset($aPayments2SessionVariables[$sPaymentId]);

        // iterate through the rest and delete session variables
        foreach ($aPayments2SessionVariables as $aSessionVariables) {
            foreach ($aSessionVariables as $sSessionVariable) {
                $this->_oFcPoHelper->fcpoDeleteSessionVariable($sSessionVariable);
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
                $this->_oFcPoHelper->fcpoSetSessionVariable('klarna_authorization_token', $aDynValues['klarna_authorization_token']);
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
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $sTranslatedString = $oLang->translateString($sLangString);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sTranslatedString);
        }
    }

    /**
     * Perform payolution precheck
     *
     * @param mixed  $mReturn
     * @param string $sPaymentId
     * @return mixed
     */
    protected function _fcpoPayolutionPreCheck($mReturn, $sPaymentId)
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
     * @return bool
     */
    protected function _fcpoIsPayolution($sPaymentId)
    {
        $aPayolutionPayments = [
            'fcpopo_bill',
            'fcpopo_debitnote',
            'fcpopo_installment',
        ];

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
     * @return bool
     */
    protected function _fcpoPayolutionSaveRequestedValues(string $sPaymentId): bool
    {
        $aRequestedValues = $this->_fcpoGetRequestedValues();
        $blSavedBirthday = $this->_fcpoSaveBirthdayData((array)$aRequestedValues, $sPaymentId);
        $blSavedUstid = $this->_fcpoSaveUserData($sPaymentId, 'oxustid');
        $blSavedTelephone = $this->_fcpoSaveUserData($sPaymentId, 'oxfon');

        return $blSavedBirthday || $blSavedUstid || $blSavedTelephone;
    }

    /**
     * Checks if user confirmed agreement of data usage
     *
     * @return bool
     */
    protected function _fcpoCheckAgreedDataUsage($sPaymentId = 'fcpopo_bill')
    {
        $aParams = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
        if ($this->_blIsPayolutionInstallmentAjax) {
            $aParams = (array)$this->_aAjaxPayolutionParams;
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
        $blValidPayolutionBill = $this->_fcpoValidateMandatoryUserDataForPayolutionBill($sPaymentId);

        // sum check results up
        $blReturn = ($blValidPayolutionBill);

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
        $blValidPayment = in_array($sPaymentId, ['fcpopo_bill']);
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
     * @return bool
     */
    protected function _fcpoValidatePayolutionBillHasTelephone()
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $blTelephoneRequired = $this->fcpoPayolutionBillTelephoneRequired();
        $blReturn = true;

        if ($blTelephoneRequired) {
            $sCurrentTelephoneNumber = $this->fcpoGetUserValue('oxfon');
            $blReturn = (bool)$sCurrentTelephoneNumber;
        }

        if (!$blReturn) {
            $sMessage = $oLang->translateString('FCPO_PAYOLUTION_PHONE_MISSING');
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
        }

        return $blReturn;
    }

    /**
     * Template getter for checking if a telephone number is required and need to be requested
     * from user
     *
     * @return bool
     */
    public function fcpoPayolutionBillTelephoneRequired()
    {
        $sTargetCountry = $this->fcpoGetTargetCountry();
        $sCurrentTelephone = $this->fcpoGetUserValue('oxfon');

        return in_array($sTargetCountry, $this->_aPayolutionBillMandatoryTelephoneCountries) &&
            empty($sCurrentTelephone);
    }

    /**
     * Returns shipping country if set or billing country if not as ISO 2 string
     *
     * @return void
     */
    public function fcpoGetTargetCountry()
    {
        $sBillCountry = $this->fcGetBillCountry();
        $sShippingCountry = $this->fcGetShippingCountry();

        return ($sShippingCountry) ? $sShippingCountry : $sBillCountry;
    }

    /**
     * Return ISO2 code of shipping country
     *
     * @return string
     */
    public function fcGetShippingCountry()
    {
        $sShippingCountryId = $this->getUserDelCountryId();
        $oCountry = $this->_oFcPoHelper->getFactoryObject(Country::class);

        return ($oCountry->load($sShippingCountryId)) ? $oCountry->oxcountry__oxisoalpha2->value : '';
    }

    /**
     * Method checks if user has valid ustid and if its mandatory anyway
     * Will return true if check is not mandatory due to circumstances
     *
     * @return bool
     */
    protected function _fcpoValidatePayolutionBillHasUstid()
    {
        $blReturn = true;
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $blB2BModeActive = $oConfig->getConfigParam('blFCPOPayolutionB2BMode');
        $blIsCompany = (bool)$this->fcpoGetUserValue('oxcompany');

        if ($blIsCompany && $blB2BModeActive) {
            $sUstid = $this->fcpoGetUserValue('oxustid');
            $blReturn = (bool)$sUstid;
        }

        if (!$blReturn) {
            $sMessage = $oLang->translateString('FCPO_PAYOLUTION_NO_USTID');
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
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
            $blBankDataValid = (!$this->_blIsPayolutionInstallmentAjax) ? $this->_fcpoValidateBankData($aBankData, $sPaymentId) : false;
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
     * @return boolean
     */
    protected function _fcpoCheckIsBankDataRelatedPayolutionPayment($sPaymentId)
    {
        $aBankDataRelatedPaymentIds = [
            'fcpopo_debitnote',
            'fcpopo_installment',
        ];

        return in_array($sPaymentId, $aBankDataRelatedPaymentIds);
    }

    /**
     * Reutrns possible given Bankdata
     *
     * @return mixed
     */
    protected function _fcpoGetPayolutionBankData($sPaymentId)
    {
        $aParams = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
        $aBankData = [];

        if (is_array($aParams) && count($aParams) > 0) {
            foreach ($aParams as $sKey => $sParam) {
                $aInstallmentAdditions = [
                    'fcpopo_bill' => '',
                    'fcpopo_installment' => '_installment',
                    'fcpopo_debitnote' => '_debitnote',
                ];

                $sInstallmentAddition = $aInstallmentAdditions[$sPaymentId];

                $aMap = [
                    'fcpo_payolution' . $sInstallmentAddition . '_iban',
                    'fcpo_payolution' . $sInstallmentAddition . '_bic',
                    'fcpo_payolution' . $sInstallmentAddition . '_accountholder',
                ];

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
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $this->_sPayolutionCurrentErrorMessage = $oLang->translateString($sLangString);
            // only put this into session if this is not from ajax
            if (!$this->_blIsPayolutionInstallmentAjax) {
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $this->_sPayolutionCurrentErrorMessage);
            }
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
        $aParams = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
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
        $aParams = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');

        return (isset($aParams['fcpo_payolution_installment_index'])) ? $aParams['fcpo_payolution_installment_index'] : false;
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
        $blPreCheckValid = ($mReturn !== null) ? $this->_fcpoPerformPayolutionPreCheck($sPaymentId) : false;
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
        $blPreCheckNeeded = $this->_fcpoCheckIfPrecheckNeeded($sPaymentId);
        if ($blPreCheckNeeded) {
            $oUser = $this->getUser();
            if (!$oUser) {
                // try to fetch user from session
                $oSession = $this->_oFcPoHelper->fcpoGetSession();
                $oBasket = $oSession->getBasket();
                $oUser = $oBasket->getBasketUser();
            }
            $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
            $aBankData = $this->_fcpoGetPayolutionBankData($sPaymentId);
            $sSelectedIndex = $this->_fcpoGetPayolutionSelectedInstallmentIndex();
            $aResponse = $oPORequest->sendRequestPayolutionPreCheck($sPaymentId, $oUser, $aBankData, $sWorkOrderId);
            if ($aResponse['status'] == 'ERROR') {
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
                $blReturn = false;
            } else if (is_array($aResponse) && array_key_exists('workorderid', $aResponse) !== false) {
                $this->_oFcPoHelper->fcpoSetSessionVariable('payolution_workorderid', $aResponse['workorderid']);
                $this->_oFcPoHelper->fcpoSetSessionVariable('payolution_bankdata', $aBankData);
                $blReturn = true;
            }
        } else {
            $sWorkOrderId = $this->_oFcPoHelper->fcpoGetSessionVariable('payolution_workorderid');
            $blValidCalculation = $this->_fcpoPerformInstallmentCalculation($sPaymentId, $sWorkOrderId);
            if (!$blValidCalculation) {
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
                $blReturn = false;
            } else {
                $sSelectedIndex = $this->_fcpoGetPayolutionSelectedInstallmentIndex();
                $sDuration = $this->_fcpoPayolutionFetchDuration($sSelectedIndex);
                $this->_oFcPoHelper->fcpoSetSessionVariable('payolution_installment_duration', $sDuration);

                // OXID-225 add bank details as they are added in form after precheck is performed
                $aBankData = $this->_fcpoGetPayolutionBankData($sPaymentId);
                $this->_oFcPoHelper->fcpoSetSessionVariable('payolution_bankdata', $aBankData);

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
     * @param null   $sWorkOrderId
     * @return bool
     */
    protected function _fcpoPerformInstallmentCalculation(string $sPaymentId, $sWorkOrderId = null)
    {
        $oUser = $this->getUser();
        $aBankData = $this->_fcpoGetPayolutionBankData($sPaymentId);
        $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oPORequest->sendRequestPayolutionInstallment($sPaymentId, $oUser, $aBankData, 'calculation', $sWorkOrderId);
        if ($aResponse['status'] == 'ERROR') {
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $blReturn = false;
        } else if (is_array($aResponse) && array_key_exists('workorderid', $aResponse) !== false) {
            $this->_oFcPoHelper->fcpoSetSessionVariable('payolution_workorderid', $aResponse['workorderid']);
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
        foreach ($aResponse as $sKey => $sValue) {
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
        if (substr($sKey, 0, 11) != 'add_paydata') {
            return false;
        }

        preg_match('/add_paydata\[PaymentDetails_([0-9]*)_/', $sKey, $aResultInstallmentIndex);
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
        preg_match('/add_paydata\[PaymentDetails_([0-9]*)_Installment_([0-9]*)_Amount\]/', $sKey, $aMonthResult);
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
        } else if (!$blValidMandatoryUserData) {
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
     * @param mixed  $mReturn
     * @param string $sPaymentId
     * @return mixed
     */
    protected function _fcpoCheckRatePayBillMandatoryUserData($mReturn, $sPaymentId)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $aRequestedValues = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');

        $this->_fcpoRatePaySaveRequestedValues($sPaymentId);

        $blB2b2Mode = $oConfig->getConfigParam('blFCPORatePayB2BMode');

        if ($blB2b2Mode) {
            $blShowUstid = $this->fcpoRatePayShowUstid();
            $mReturn = (!$blShowUstid) ? $mReturn : false;
            if (!$mReturn) {
                $sMessage = $oLang->translateString('FCPO_RATEPAY_NO_USTID');
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
            }
        } else {
            $blShowFon = $this->fcpoRatePayShowFon();
            $blShowBirthdate = $this->fcpoRatePayShowBirthdate();
            $mReturn = (!$blShowBirthdate && !$blShowFon) ? $mReturn : false;
            if (!$mReturn) {
                $sMessage = $oLang->translateString('FCPO_RATEPAY_NO_SUFFICIENT_DATA');
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
            }
        }

        $blSepaAndDataUsageAgreed = false;
        if ($sPaymentId != 'fcporp_debitnote' && $sPaymentId != 'fcporp_installment') {
            $blSepaAndDataUsageAgreed = true;
        } else {
            $sFieldPrefix = ($sPaymentId == 'fcporp_debitnote') ? 'fcpo_ratepay_debitnote' : 'fcpo_ratepay_installment';

            if ($aRequestedValues[$sFieldPrefix . '_agreed'] == 'agreed') {
                if ($sPaymentId == 'fcporp_installment' && $aRequestedValues['fcporp_installment_settlement_type'] == 'banktransfer') {
                    $blSepaAndDataUsageAgreed = true;
                } elseif ($aRequestedValues[$sFieldPrefix . '_sepa_agreed'] == 'agreed') {
                    $blSepaAndDataUsageAgreed = true;
                }
            }
        }

        if (!$blSepaAndDataUsageAgreed) {
            $mReturn = false;
            $sErrorTranslateString =
                ($aRequestedValues['fcpo_ratepay_debitnote_sepa_agreed'] != 'agreed') ?
                    'FCPO_RATEPAY_SEPA_NOT_AGREED' :
                    'FCPO_RATEPAY_NOT_AGREED';
            $sMessage = $oLang->translateString($sErrorTranslateString);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
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
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getBasketUser();

        $aRequestedValues = (array)$this->_oFcPoHelper->fcpoGetRequestParameter('dynvalue');
        $sCurrentBirthdate = $oUser->oxuser__oxbirthdate->value;
        $sRequestBirthdate = $aRequestedValues[$sPaymentId . '_birthdate_year'] . "-" .
            $aRequestedValues[$sPaymentId . '_birthdate_month'] . "-" .
            $aRequestedValues[$sPaymentId . '_birthdate_day'];

        $blRefreshBirthdate = ($sCurrentBirthdate != $sRequestBirthdate && $sRequestBirthdate != '0000-00-00' && $sRequestBirthdate != '--');
        if ($blRefreshBirthdate) {
            $oUser->oxuser__oxbirthdate = new Field($sRequestBirthdate, Field::T_RAW);
            $blSaveUser = true;
        }

        $blRefreshFon = (isset($aRequestedValues[$sPaymentId . '_fon']) && strlen($aRequestedValues[$sPaymentId . '_fon']) > 0);

        if ($blRefreshFon) {
            $oUser->oxuser__oxfon = new Field($aRequestedValues[$sPaymentId . '_fon'], Field::T_RAW);
            $blSaveUser = true;
        }

        $blRefreshUstid = (isset($aRequestedValues[$sPaymentId . '_ustid']) && strlen($aRequestedValues[$sPaymentId . '_ustid']) > 0);

        if ($blRefreshUstid) {
            $oUser->oxuser__oxustid = new Field($aRequestedValues[$sPaymentId . '_ustid'], Field::T_RAW);
            $blSaveUser = true;
        }

        $this->_oFcPoHelper->fcpoSetSessionVariable('ratepayprofileid', $aRequestedValues[$sPaymentId . '_profileid']);

        if ($blSaveUser) {
            $oUser->save();
        }
    }

    /**
     * Template getter which checks if requesting ustid is needed
     *
     * @return bool
     */
    public function fcpoRatePayShowUstid()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oUser = $this->getUser();
        $blB2b2Mode = ($oConfig->getConfigParam('blFCPORatePayB2BMode') && $oUser->oxuser__oxcompany->value != '');

        return ($oUser->oxuser__oxustid->value == '' && $blB2b2Mode) ? true : false;
    }

    /**
     * Template getter which checks if requesting telephone number is needed
     *
     * @return bool
     */
    public function fcpoRatePayShowFon()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oUser = $this->getUser();
        $blShowUstid = $this->fcpoRatePayShowUstid();
        $blB2BMode = $oConfig->getConfigParam('blFCPORatePayB2BMode');

        return ($oUser->oxuser__oxfon->value == '' && !$blShowUstid) ? true : false;
    }

    /**
     * Template getter which checks if requesting birthdate is needed
     *
     * @return bool
     */
    public function fcpoRatePayShowBirthdate()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oUser = $this->getUser();
        $blShowUstid = $this->fcpoRatePayShowUstid();

        return ($oUser->oxuser__oxbirthdate->value == '0000-00-00' && !$blShowUstid) ? true : false;
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
                $oLang = $this->_oFcPoHelper->fcpoGetLang();
                $sMessage = $oLang->translateString('FCPO_NOT_ADULT');
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
                $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
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
     * @return array
     */
    public function fcpoGetInstallments()
    {
        return $this->_aInstallmentCalculation;
    }

    /**
     * Template getter for current userflag messages
     *
     * @return array
     */
    public function fcpoGetUserFlagMessages()
    {
        $aMessages = [];
        $oUser = $this->getUser();
        $aUserFlags = $oUser->fcpoGetFlagsOfUser();
        foreach ($aUserFlags as $oUserFlag) {
            if (!$oUserFlag->fcpoGetIsActive()) continue;
            $sCustomerMessage = $this->getPaymentErrorText();
            $sMessage = $oUserFlag->fcpoGetTranslatedMessage($sCustomerMessage);
            if ($sMessage) {
                $aMessages[] = $sMessage;
            }
        }

        return $aMessages;
    }

    /**
     * Method for transport of params that came via payolution installment params
     *
     * @param array $aParams
     * @return void
     */
    public function setPayolutionAjaxParams($aParams)
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

    /**
     * Returns the sum of basket
     *
     * @return string
     */
    public function fcpoGetBasketSum()
    {
        return number_format($this->fcpoGetDBasketSum(), 2, ',', '.');
    }

    public function fcpoGetRatePayDeviceFingerprint()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
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
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        return $oConfig->getConfigParam('sFCPORatePaySnippetID') ? $oConfig->getConfigParam('sFCPORatePaySnippetID') : 'ratepay';
    }

    /**
     * Returns link for displaying legal terms for Ratepay
     *
     * @return string
     */
    public function fcpoGetRatepayAgreementLink()
    {

        return 'https://www.ratepay.com/legal-payment-terms';
    }

    /**
     * Returns link for displaying data privacy statement for Ratepay
     *
     * @return string
     */
    public function fcpoGetRatepayPrivacyLink()
    {
        return 'https://www.ratepay.com/legal-payment-dataprivacy';
    }

    /**
     * Ajax interface for triggering installment caclulation
     *
     * @return void
     */
    public function fcpoPerformInstallmentCalculation()
    {
        $this->_fcpoPerformInstallmentCalculation('fcpopo_installment');
    }

    /**
     * Loads shop version and formats it in a certain way
     *
     * @return string
     */
    public function getIntegratorid()
    {
        return $this->_oFcPoHelper->fcpoGetIntegratorId();
    }

    /**
     * Loads shop edition and shop version and formats it in a certain way
     *
     * @return string
     */
    public function getIntegratorver()
    {
        return $this->_oFcPoHelper->fcpoGetIntegratorVersion();
    }

    /**
     * get PAYONE module version
     *
     * @return string
     */
    public function getIntegratorextver()
    {
        return $this->_oFcPoHelper->fcpoGetModuleVersion();
    }

    /**
     * Returns the Klarna confirmation text for the current bill country
     *
     * @return string
     */
    public function fcpoGetConfirmationText()
    {
        $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
        $sId = '';
        $sKlarnaLang = $this->_fcpoGetKlarnaLang();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
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
        if ($sBillCountryIso2) {
            $aKlarnaLangMap = [
                'de' => 'de_de',
                'at' => 'de_at',
                'dk' => 'da_dk',
                'fi' => 'fi_fi',
                'nl' => 'nl_nl',
                'no' => 'nb_no',
                'se' => 'sv_se',
            ];
            if (array_key_exists($sBillCountryIso2, $aKlarnaLangMap) !== false) {
                $sReturn = $aKlarnaLangMap[$sBillCountryIso2];
            }
        }
        return $sReturn;
    }

    /**
     * Determine if info is needed
     *
     * @return bool
     */
    public function fcpoKlarnaInfoNeeded()
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
     * @return bool
     */
    public function fcpoKlarnaIsTelephoneNumberNeeded()
    {
        $oUser = $this->getUser();
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());
        $blCountryNeedsPhone = (array_search($sBillCountryIso2, ['no', 'se', 'dk']) !== false);
        return $blCountryNeedsPhone && $oUser->oxuser__oxfon->value == '';
    }

    /**
     * Checks if birthday neeeded for klarna
     *
     * @return bool
     */
    public function fcpoKlarnaIsBirthdayNeeded()
    {
        $oUser = $this->getUser();
        $sBirthdate = $oUser->oxuser__oxbirthdate->value;
        $sUserCountryIso2 = strtoupper($this->fcGetBillCountry());
        $blNoBirthdaySet = (!$sBirthdate || $sBirthdate == '0000-00-00');
        $blInCountryList = in_array($sUserCountryIso2, $this->_aKlarnaBirthdayNeededCountries);

        return (bool)($blInCountryList && $blNoBirthdaySet);
    }

    /**
     * Determine if address addition is needed
     *
     * @return bool
     */
    public function fcpoKlarnaIsAddressAdditionNeeded()
    {
        $oUser = $this->getUser();
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());

        return $sBillCountryIso2 == 'nl' && !$oUser->oxuser__oxaddinfo->value;
    }

    /**
     * Determine if delivery address addition is needed
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
                $oAddress = $this->_oFcPoHelper->getFactoryObject(Address::class);
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
     * @return bool
     */
    public function fcpoKlarnaIsGenderNeeded()
    {
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());
        $oUser = $this->getUser();
        $blValidCountry = (array_search($sBillCountryIso2, ['de', 'at', 'nl']) !== false);
        $blValidSalutation = !$oUser->oxuser__oxsal->value;
        $blIsValid = $blValidCountry && $blValidSalutation;

        return ($blIsValid) ? true : false;
    }

    /**
     * Determine if personal id is needed
     *
     * @return bool
     */
    public function fcpoKlarnaIsPersonalIdNeeded()
    {
        $sBillCountryIso2 = strtolower($this->fcGetBillCountry());
        $oUser = $this->getUser();
        $blValidCountry = (array_search($sBillCountryIso2, ['dk', 'fi', 'no', 'se']) !== false);
        $blValidPersId = !$oUser->oxuser__fcpopersonalid->value;
        $blIsValid = $blValidCountry && $blValidPersId;

        return ($blIsValid) ? true : false;
    }

    /**
     * Returns an array of configured debit countries
     *
     * @return array
     */
    public function fcpoGetDebitCountries()
    {
        $aCountries = [];
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $aFCPODebitCountries = $oConfig->getConfigParam('aFCPODebitCountries');

        if (is_array($aFCPODebitCountries) && count($aFCPODebitCountries)) {
            foreach ($aFCPODebitCountries as $sCountryId) {
                $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
                $aCountries[$oPayment->fcpoGetCountryIsoAlphaById($sCountryId)] = $oPayment->fcpoGetCountryNameById($sCountryId);
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
        $oViewConf = $this->_oFcPoHelper->fcpoGetViewConfig();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();

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
        $oLang = $this->_oFcPoHelper->fcpoGetLang();

        $sPrice = $oLang->formatCurrency($dPrice, $oCur);
        $sSide = $oCur->side;

        return (isset($sSide) && $sSide == 'Front') ?
            $oCur->sign . $sPrice :
            $sPrice . ' ' . $oCur->sign;
    }

    /**
     * Generic method for determine if order is b2c
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
        $aMap = [
            'year' => 0,
            'month' => 1,
            'day' => 2,
        ];

        $sReturn = '';
        if (isset($aBirthdateParts[$aMap[$sPart]])) {
            $sReturn = $aBirthdateParts[$aMap[$sPart]];
        }

        return $sReturn;
    }

    public function fcpoGetAccountHolder()
    {
        return $this->fcpoGetUserValue('oxfname') . ' FcPayOnePaymentView.php' . $this->fcpoGetUserValue('oxlname');
    }

    /**
     * Returns prepared link for displaying agreement as
     *
     * @return string
     */
    public function fcpoGetPayolutionAgreementLink()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
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
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sShopUrl = $oConfig->getShopUrl();

        return $sShopUrl .
            '/modules/fc/fcpayone/lib/fcpopopup_content.php?loadurl=' .
            $this->_sPayolutionSepaAgreement;
    }

    /**
     * Template getter for returning an array with last hundred years
     *
     * @return array
     */
    public function fcpoGetYearRange()
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
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
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
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
     * @return array
     */
    public function fcpoGetMonthRange()
    {
        return $this->_fcpoGetNumericRange(1, 12, 2);
    }

    /**
     * Returns an array of available days
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
     * @return string
     */
    public function fcpoGetPoAgreementInit($sPaymentId)
    {
        $oTranslator = $this->_oFcPoHelper->fcpoGetLang();
        $sBaseString = $oTranslator->translateString('FCPO_PAYOLUTION_AGREEMENT_PART_1');
        $sPaymentMethodSuffix = $oTranslator->translateString('FCPO_PAYOLUTION_AGREEMENT_PART_1_' . strtoupper($sPaymentId));

        return sprintf($sBaseString, $sPaymentMethodSuffix);
    }

    /**
     * Retrieve the session stored value of the device check for Apple Pay compatibility
     *
     * @return int
     */
    public function fcpoAplGetDeviceCheck()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        return $oSession->getVariable('applePayAllowedDevice');
    }

    /**
     * Checks if configured certificate file exists, for Apple Pay availability
     *
     * @return mixed
     */
    public function fcpoAplCertificateCheck()
    {
        $oViewConf = $this->_oFcPoHelper->fcpoGetViewConfig();
        return $oViewConf->fcpoCertificateExists();
    }

    /**
     * Template getter which checks if requesting telephone number is needed
     *
     * @return bool
     */
    public function fcpoBNPLShowFon()
    {
        $oUser = $this->getUser();
        $blIsB2B = $oUser->oxuser__oxcompany->value != '';

        return (!$blIsB2B && $oUser->oxuser__oxfon->value == '');
    }

    /**
     * Template getter which checks if requesting birthdate is needed
     *
     * @return bool
     */
    public function fcpoBNPLShowBirthdate()
    {
        $oUser = $this->getUser();
        $blIsB2B = $oUser->oxuser__oxcompany->value != '';

        return (!$blIsB2B && (is_null($oUser->oxuser__oxbirthdate->value) || $oUser->oxuser__oxbirthdate->value == '0000-00-00'));
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
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $blValidBirthdateData = $aBirthdayValidation['blValidBirthdateData'];
        $sRequestBirthdate = $aBirthdayValidation['sRequestBirthdate'];

        $blResult = false;

        if ($blValidBirthdateData) {
            $oUser->oxuser__oxbirthdate = new Field($sRequestBirthdate, Field::T_RAW);
            $oUser->save();
            $blResult = true;
        } else {
            $sMessage = $oLang->translateString('FCPO_BIRTHDATE_INVALID');
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
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
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $sMessage = $oLang->translateString('FCPO_COMPANYDATA_INVALID');
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerrortext', $sMessage);
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
     * Calculate payment cost for each payment. Should be removed later
     *
     * @param array    &$aPaymentList payments array
     * @param Basket    $oBasket      basket object
     *
     * @return null
     */
    protected function _setValues(&$aPaymentList, $oBasket = null)
    {
        parent::_setValues($aPaymentList, $oBasket);

        if (is_array($aPaymentList)) {
            foreach ($aPaymentList as $index => $oPayment) {
                if ($this->fcIsPayOnePaymentType($oPayment->getId()) && $this->fcShowApprovalMessage() && $oPayment->fcBoniCheckNeeded()) {
                    $test = $oPayment->oxpayments__oxlongdesc->value;
                    $sApprovalLongdesc = '<br><table><tr><td><input type="hidden" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="false"><input type="checkbox" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="true" style="margin-bottom:0px;margin-right:10px;"></td><td>' . $this->fcGetApprovalText() . '</td></tr></table>';
                    $oPayment->oxpayments__oxlongdesc->rawValue .= $sApprovalLongdesc;
                }
            }
        }
    }

    /**
     * Returns wether payment is of type payone
     *
     * @param string $sId
     * @return bool
     */
    public function fcIsPayOnePaymentType($sId)
    {
        return (bool)FcPayOnePayment::fcIsPayOnePaymentType($sId);
    }

    /**
     * Check if approval message should be displayed
     *
     * @return bool
     */
    public function fcShowApprovalMessage()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        return ($oConfig->getConfigParam('sFCPOBonicheckMoment') == 'after') ? true : false;
    }

    /**
     * Returns the cn
     *
     * @return mixed
     */
    public function fcGetApprovalText()
    {
        $iLangId = $this->fcGetLangId();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOApprovalText_' . $iLangId);
    }

    /**
     * Extends oxid standard method _setDeprecatedValues
     * Extends it with the approval checkbox in the longdesc property
     *
     * Calculate payment cost for each payment. Sould be removed later
     *
     * @param array    &$aPaymentList payments array
     * @param Basket    $oBasket      basket object
     *
     * @return null
     */
    protected function _setDeprecatedValues(&$aPaymentList, $oBasket = null)
    {
        if ($this->_fcGetCurrentVersion() <= 4700) {
            parent::_setDeprecatedValues($aPaymentList, $oBasket);
            if (is_array($aPaymentList)) {
                $oLang = $this->_oFcPoHelper->fcpoGetLang();
                foreach ($aPaymentList as $oPayment) {
                    if ($this->fcIsPayOnePaymentType($oPayment->getId()) && $this->fcShowApprovalMessage() && $oPayment->fcBoniCheckNeeded()) {
                        $sApprovalLongdesc = '<br><table><tr><td><input type="hidden" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="false"><input type="checkbox" name="fcpo_bonicheckapproved[' . $oPayment->getId() . ']" value="true" style="margin-bottom:0px;margin-right:10px;"></td><td>' . $this->fcGetApprovalText() . '</td></tr></table>';
                        $oPayment->oxpayments__oxlongdesc->value .= $sApprovalLongdesc;
                    }
                }
            }
        }
    }

    /**
     * Get current version number as 4 digit integer e.g. Oxid 7.0.0 is 7000
     *
     * @return integer
     */
    protected function _fcGetCurrentVersion()
    {
        return $this->_oFcPoHelper->fcpoGetIntShopVersion();
    }
}
