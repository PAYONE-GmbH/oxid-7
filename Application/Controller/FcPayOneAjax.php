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
use Fatchip\PayOne\Application\Model\FcPoRatePay;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoParamsParser;
use Fatchip\PayOne\Lib\FcPoRequest;
use JsonException;
use OxidEsales\Eshop\Application\Controller\PaymentController;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Controller\BaseController;
use OxidEsales\Eshop\Core\Curl;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ViewConfig;


/**
 * Class for receiving ajax calls and delivering needed data
 *
 * @author andre
 */
class FcPayOneAjax extends BaseController
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;


    /**
     * init object construction
     *
     * @return void
     * @throws JsonException
     */
    public function __construct()
    {
        parent::__construct();

        $this->_oFcPoHelper = oxNew(FcPoHelper::class);

        // receive params
        $sPaymentId = filter_input(INPUT_POST, 'paymentid');
        $sAction = filter_input(INPUT_POST, 'action');
        $sParamsJson = filter_input(INPUT_POST, 'params');

        if ($sPaymentId) {
            if ($sAction == 'precheck') {
                $sResult = $this->fcpoTriggerPrecheck($sPaymentId, $sParamsJson);
                if ($sResult == 'SUCCESS') {
                    $sAction = 'calculation';
                } else {
                    echo $this->fcpoReturnErrorMessage($sResult);
                }
            }

            if ($sAction == 'calculation') {
                $mResult = $this->fcpoTriggerInstallmentCalculation($sPaymentId);
                if (is_array($mResult) && $mResult !== []) {
                    // we have got a calculation result. Parse it to needed html
                    echo $this->fcpoParseCalculation2Html($mResult);
                }
            }

            if ($sAction == 'fcpoapl_register_device' && $sPaymentId == 'fcpo_apple_pay') {
                echo $this->fcpoAplRegisterDevice($sParamsJson);
            }
            if ($sAction == 'fcpoapl_create_session' && $sPaymentId == 'fcpo_apple_pay') {
                echo $this->fcpoAplCreateSession($sParamsJson);
            }
            if ($sAction == 'fcpoapl_payment' && $sPaymentId == 'fcpo_apple_pay') {
                echo $this->fcpoAplPayment($sParamsJson);
            }
            if ($sAction == 'fcpoapl_get_order_info' && $sPaymentId == 'fcpo_apple_pay') {
                echo $this->fcpoAplOrderInfo();
            }

            if ($sAction == 'fcporp_calculation' && $sPaymentId == 'fcporp_installment') {
                echo $this->fcpoRatepayCalculation($sParamsJson);
            }

            if ($sAction == 'start_paypal_express' && $sPaymentId == PayPal::PPE_V2_EXPRESS) {
                echo $this->fcpoStartPayPalExpress();
            }

            if ($sAction == 'fcpopl_load_installment_form' && $sPaymentId == 'fcpopl_secinstallment') {
                echo json_encode($this->fcpoGetBNPLInstallment());
            }

            $aKlarnaPayments = [
                'fcpoklarna_invoice',
                'fcpoklarna_installments',
                'fcpoklarna_directdebit',
            ];

            if (in_array($sPaymentId, $aKlarnaPayments)) {
                echo $this->fcpoTriggerKlarnaAction($sPaymentId, $sAction, $sParamsJson);
            }
            die();
        }
    }

    /**
     * Performs a pre-check for payolution installment
     *
     * @param string $sPaymentId
     * @param string $sParamsJson
     * @return bool|string
     * @throws JsonException
     */
    public function fcpoTriggerPrecheck(string $sPaymentId, string $sParamsJson): bool|string
    {
        $oPaymentController = $this->_oFcPoHelper->getFactoryObject(PaymentController::class);
        $oPaymentController->setPayolutionAjaxParams(json_decode($sParamsJson, true));
        $mPreCheckResult = $oPaymentController->fcpoPayolutionPreCheck($sPaymentId);

        return ($mPreCheckResult === true) ? 'SUCCESS' : $mPreCheckResult;
    }

    /**
     * Formats error message to be displayed in a error box
     *
     * @param string $sMessage
     * @return string
     */
    public function fcpoReturnErrorMessage(string $sMessage): string
    {
        $sMessage = utf8_encode($sMessage);

        $sReturn = '<p class="payolution_message_error">';
        $sReturn .= $sMessage;

        return $sReturn . '</p>';
    }

    /**
     * Performs a pre-check for payolution installment
     *
     * @param string $sPaymentId
     * @return array|bool
     */
    public function fcpoTriggerInstallmentCalculation(string $sPaymentId): array|bool
    {
        $oPaymentController = $this->_oFcPoHelper->getFactoryObject(PaymentController::class);

        $oPaymentController->fcpoPerformInstallmentCalculation($sPaymentId);
        $mResult = $oPaymentController->fcpoGetInstallments();

        return (is_array($mResult) && $mResult !== []) ? $mResult : false;
    }

    /**
     * Parse result of calculation to html for returning html code
     *
     * @param array $aCalculation
     * @return string
     */
    public function fcpoParseCalculation2Html(array $aCalculation): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();

        $sTranslateInstallmentSelection = utf8_encode((string)$oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_SELECTION'));
        $sTranslateSelectInstallment = utf8_encode((string)$oLang->translateString('FCPO_PAYOLUTION_SELECT_INSTALLMENT'));

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sHtml = '
            <div class="content">
                <p id="payolution_installment_calculation_headline" class="payolution_installment_box_headline">2. ' . $sTranslateInstallmentSelection . '</p>
                <p id="payolution_installment_calculation_headline" class="payolution_installment_box_subtitle">' . $sTranslateSelectInstallment . '</p>
        ';
        $sHtml .= '<div class="payolution_installment_offers">';
        $sHtml .= '<input id="payolution_no_installments" type="hidden" value="' . count($aCalculation) . '">';
        $sHtml .= '<fieldset>';
        foreach ($aCalculation as $sKey => $aCurrentInstallment) {
            $sHtml .= $this->_fcpoGetInsterestHiddenFields($sKey, $aCurrentInstallment);
            $sHtml .= $this->_fcpoGetInsterestRadio($sKey, $aCurrentInstallment);
            $sHtml .= $this->_fcpoGetInsterestLabel($sKey, $aCurrentInstallment);
            $sHtml .= '<br>';
        }
        $sHtml .= '</fieldset>';
        $sHtml .= '</div></div>';
        $sHtml .= '<div class="payolution_installment_details">';
        $sDownloadUrl = '';
        foreach ($aCalculation as $sKey => $aCurrentInstallment) {
            $sHtml .= '<div id="payolution_rates_details_' . $sKey . '" class="payolution_rates_invisible">';
            foreach ($aCurrentInstallment['Months'] as $sMonth => $aRatesDetails) {
                $sHtml .= $this->_fcpoGetInsterestMonthDetail($sMonth, $aRatesDetails) . '<br>';
            }
            $sDownloadUrl = $oConfig->getShopUrl() . '?login=1&cl=FcPoPopUpContent&loadurl=' . $aCurrentInstallment['StandardCreditInformationUrl'];
            $sHtml .= '</div>';

        }
        $sHtml .= '</div>';

        return $sHtml . ('<div class="payolution_draft_download"><a href="' . $sDownloadUrl . '"' . $this->_fcpoGetLightView() . '>' . $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_DOWNLOAD_DRAFT') . '</a></div>');
    }

    /**
     * Set hidden fields for beeing able to set needed values
     *
     * @param string $sKey
     * @param array $aCurrentInstallment
     * @return string
     */
    protected function _fcpoGetInsterestHiddenFields(string $sKey, array $aCurrentInstallment): string
    {
        $sHtml = '<input type="hidden" id="payolution_installment_value_' . $sKey . '" value="' . str_replace('.', ',', (string)$aCurrentInstallment['Amount']) . '">';
        $sHtml .= '<input type="hidden" id="payolution_installment_duration_' . $sKey . '" value="' . $aCurrentInstallment['Duration'] . '">';
        $sHtml .= '<input type="hidden" id="payolution_installment_eff_interest_rate_' . $sKey . '" value="' . str_replace('.', ',', (string)$aCurrentInstallment['EffectiveInterestRate']) . '">';
        $sHtml .= '<input type="hidden" id="payolution_installment_interest_rate_' . $sKey . '" value="' . str_replace('.', ',', (string)$aCurrentInstallment['InterestRate']) . '">';

        return $sHtml . ('<input type="hidden" id="payolution_installment_total_amount_' . $sKey . '" value="' . str_replace('.', ',', (string)$aCurrentInstallment['TotalAmount']) . '">');
    }

    /**
     * Returns a html radio button for current installment offer
     *
     * @param string $sKey
     * @param array $aCurrentInstallment
     * @return string
     */
    protected function _fcpoGetInsterestRadio(string $sKey, array $aCurrentInstallment): string
    {
        return '<input type="radio" id="payolution_installment_offer_' . $sKey . '" name="payolution_installment_selection" value="' . $sKey . '">';
    }

    /**
     * Returns a html label for current installment offer radiobutton
     *
     * @param string $sKey
     * @param array $aCurrentInstallment
     * @return string
     */
    protected function _fcpoGetInsterestLabel(string $sKey, array $aCurrentInstallment): string
    {
        $sInterestCaption = $this->_fcpoGetInsterestCaption($aCurrentInstallment);

        return '<label for="payolution_installment_offer_' . $sKey . '">' . $sInterestCaption . '</label>';
    }

    /**
     * Returns translated caption for current installment offer
     *
     * @param array $aCurrentInstallment
     * @return string
     */
    protected function _fcpoGetInsterestCaption(array $aCurrentInstallment): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sPerMonth = $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_PER_MONTH');
        $sRates = $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_RATES');
        $sMonthlyAmount = str_replace('.', ',', (string)$aCurrentInstallment['Amount']);
        $sDuration = $aCurrentInstallment['Duration'];
        $sCurrency = $aCurrentInstallment['Currency'];

        // return all together to final caption
        return $sMonthlyAmount . " " . $sCurrency . " " . $sPerMonth . " - " . $sDuration . " " . $sRates;
    }

    /**
     * Returns a caption for a certain month
     *
     * @param string $sMonth
     * @param array $aRatesDetails
     * @return string
     */
    protected function _fcpoGetInsterestMonthDetail(string $sMonth, array $aRatesDetails): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sRateCaption = $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_RATE');
        $sDueCaption = utf8_encode((string)$oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_DUE_AT'));
        $sDue = date('d.m.Y', strtotime((string)$aRatesDetails['Due']));
        $sRate = str_replace('.', ',', (string)$aRatesDetails['Amount']);

        return $sMonth . '. ' . $sRateCaption . ': ' . $sRate . ' ' . $aRatesDetails['Currency'] . ' (' . $sDueCaption . ' ' . $sDue . ')';
    }

    /**
     * Returns lightview part for download
     *
     * @return string
     */
    protected function _fcpoGetLightView(): string
    {
        $sContent = 'class="lightview" data-lightview-type="iframe" data-lightview-options="';
        $sContent .= "width: 800, height: 600, viewport: 'scale',background: { color: '#fff', opacity: 1 },skin: 'light'";

        return $sContent . '"';
    }

    /**
     * @param string $sParamsJson
     * @return bool|string
     * @throws JsonException
     */
    public function fcpoAplRegisterDevice(string $sParamsJson): bool|string
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $aParams = json_decode($sParamsJson, true, 512, JSON_THROW_ON_ERROR);

        $allowedDevice = $aParams['allowed'];
        $oSession->setVariable('applePayAllowedDevice', $allowedDevice);
        return json_encode(['status' => 'SUCCESS', 'message' => '']);
    }

    /**
     * @param string $sParamsJson
     * @return false|string
     * @throws JsonException
     */
    public function fcpoAplCreateSession(string $sParamsJson): bool|string
    {
        $oLogger = Registry::getLogger();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $aParams = json_decode($sParamsJson, true, 512, JSON_THROW_ON_ERROR);

        /** @var ViewConfig $config */
        $oViewConfig = $this->_oFcPoHelper->fcpoGetViewConfig();
        /** @var  Config $oConfig */
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        $sCertDir = $oViewConfig->fcpoGetCertDirPath();
        $sShopFQDN = $_SERVER['SERVER_NAME'];
        $sValidationUrl = $aParams['validationUrl'];

        try {
            $sMerchantId = $oConfig->getConfigParam('sFCPOAplMerchantId');
            $sCertificateFileName = $oConfig->getConfigParam('sFCPOAplCertificate');
            $sKeyFileName = $oConfig->getConfigParam('sFCPOAplKey');
            $sKeyPassword = $oConfig->getConfigParam('sFCPOAplPassword');

            $payload = [
                'merchantIdentifier' => $sMerchantId,
                'displayName' => 'PAYONE Apple Pay',
                'initiative' => 'web',
                'initiativeContext' => $sShopFQDN
            ];

            $curl = new Curl();
            $curl->setUrl($sValidationUrl);
            $curl->setMethod('POST');
            $curl->setOption('CURLOPT_SSLCERT', $sCertDir . $sCertificateFileName);
            $curl->setOption('CURLOPT_SSLKEY', $sCertDir . $sKeyFileName);
            $curl->setOption('CURLOPT_SSLKEYPASSWD', $sKeyPassword);
            $curl->setOption('CURLOPT_POSTFIELDS', json_encode($payload, JSON_THROW_ON_ERROR));
            $oHttpResponse = $curl->execute();
            $iStatusCode = $curl->getStatusCode();

            if ($iStatusCode !== 200) {
                $oLogger->error($oLang->translateString('FCPO_APPLE_PAY_CREATE_SESSION_ERROR') . ' : ' . var_export($oHttpResponse, true));

                $aResponse = [
                    'status' => 'ERROR',
                    'message' => $oLang->translateString('FCPO_APPLE_PAY_CREATE_SESSION_ERROR'),
                    'errorDetails' => $oHttpResponse
                ];

                return json_encode($aResponse, JSON_THROW_ON_ERROR);
            }

            $aMerchantSession = json_decode($oHttpResponse, true, 512, JSON_THROW_ON_ERROR);
            $aResponse = [
                'status' => 'SUCCESS',
                'message' => '',
                'merchantSession' => $aMerchantSession
            ];

            return json_encode($aResponse, JSON_THROW_ON_ERROR);

        } catch (Exception $oEx) {
            $oLogger->error($oEx->getTraceAsString());

            $aResponse = [
                'status' => 'ERROR',
                'message' => $oLang->translateString('FCPO_APPLE_PAY_CREATE_SESSION_ERROR'),
                'errorDetails' => $oEx->getMessage()
            ];

            return json_encode($aResponse, JSON_THROW_ON_ERROR);
        }
    }

    /**
     * @param string $sParamsJson
     * @return bool|string
     */
    public function fcpoAplPayment(string $sParamsJson): bool|string
    {
        $aCreditCardMapping = [
            'visa' => 'V',
            'mastercard' => 'M',
            'amex' => 'A',
            'girocard' => 'G'
        ];

        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $aParams = json_decode($sParamsJson, true);

        $aPaymentData = $aParams['token']['paymentData'];
        $aMethodData = $aParams['token']['paymentMethod'];
        $sCreditCardType = '';
        if (isset($aCreditCardMapping[strtolower((string)$aMethodData['network'])])) {
            $sCreditCardType = $aCreditCardMapping[strtolower((string)$aMethodData['network'])];
        }

        $sTokenData = [
            'paydata' => [
                'paymentdata_token_data' => $aPaymentData['data'] ?? '',
                'paymentdata_token_ephemeral_publickey' => $aPaymentData['header']['ephemeralPublicKey'] ?? '',
                'paymentdata_token_publickey_hash' => $aPaymentData['header']['publicKeyHash'] ?? '',
                'paymentdata_token_transaction_id' => $aPaymentData['header']['transactionId'] ?? '',
                'paymentdata_token_signature' => $aPaymentData['signature'] ?? '',
                'paymentdata_token_version' => $aPaymentData['version'] ?? ''
            ],
            'creditCardType' => $sCreditCardType
        ];

        $oSession->setVariable('applePayTokenData', $sTokenData);

        $aResponse = [
            'status' => 'SUCCESS',
            'message' => ''
        ];

        return json_encode($aResponse);
    }

    /**
     * @return false|string
     */
    public function fcpoAplOrderInfo(): bool|string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $sPaymentId = $oBasket->getPaymentId();
        $dPrice = $oBasket->getPrice()->getPrice();

        $oActShopCurrencyObject = $oConfig->getActShopCurrencyObject();
        $sCurrency = $oActShopCurrencyObject->name;

        /** @var Country $oCountry */
        $oCountry = $this->_oFcPoHelper->getFactoryObject(Country::class);
        $oCountry->load($oBasket->getBasketUser()->getActiveCountry());
        $sCountry = $oCountry->oxcountry__oxisoalpha2->value;

        $aCreditCardMapping = [
            'V' => "visa",
            'M' => "masterCard",
            'A' => "amex",
            'G' => "girocard",
        ];

        $aSupportedNetwork = [];
        $aConfigAplCreditCard = $oConfig->getConfigParam('aFCPOAplCreditCards');
        if (!empty($aConfigAplCreditCard)) {
            foreach ($oConfig->getConfigParam('aFCPOAplCreditCards') as $sCardCode) {
                if (isset($aCreditCardMapping[$sCardCode])) {
                    $aSupportedNetwork[] = $aCreditCardMapping[$sCardCode];
                }
            }
        }

        $aResponse = [
            'status' => 'SUCCESS',
            'message' => '',
            'info' => [
                'isApl' => $sPaymentId == 'fcpo_apple_pay',
                'amount' => $dPrice,
                'currency' => $sCurrency,
                'country' => $sCountry,
                'supportedNetworks' => $aSupportedNetwork,
                'errorMessage' => ''
            ]
        ];

        if (count($aSupportedNetwork) < 1) {
            $aResponse['info']['errorMessage'] = $oLang->translateString('FCPO_APPLE_PAY_CREATE_SESSION_ERROR_CARDS');
        }

        return json_encode($aResponse);
    }

    /**
     * @param string $sParamsJson
     * @return string
     */
    public function fcpoRatepayCalculation(string $sParamsJson): string
    {
        $aParams = json_decode($sParamsJson, true);
        $sOxid = $aParams['sPaymentMethodOxid'];

        $oRatePay = oxNew(FcPoRatePay::class);
        $aRatepayData = $oRatePay->fcpoGetProfileData($sOxid);

        if ($aParams['sMode'] == 'runtime') {
            $sCalculationMode = 'calculation-by-time';
            $aRatepayData['duration'] = $aParams['iMonth'];
        } else {
            $sCalculationMode = 'calculation-by-rate';
            $aRatepayData['installment'] = $aParams['iInstallment'];
        }

        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oRequest->sendRequestRatepayCalculation($sCalculationMode, $aRatepayData);

        if (is_array($aResponse) && array_key_exists('workorderid', $aResponse)) {
            $this->_oFcPoHelper->fcpoSetSessionVariable('ratepay_workorderid', $aResponse['workorderid']);
        }

        $aInstallmentDetails = [
            'annualPercentageRate' => $aResponse['add_paydata[annual-percentage-rate]'],
            'interestAmount' => $aResponse['add_paydata[interest-amount]'],
            'amount' => $aResponse['add_paydata[amount]'],
            'numberOfRate' => $aResponse['add_paydata[number-of-rates]'],
            'numberOfRatesFull' => $aResponse['add_paydata[number-of-rates]'],
            'rate' => $aResponse['add_paydata[rate]'],
            'paymentFirstday' => $aResponse['add_paydata[payment-firstday]'],
            'interestRate' => $aResponse['add_paydata[interest-rate]'],
            'monthlyDebitInterest' => $aResponse['add_paydata[monthly-debit-interest]'],
            'lastRate' => $aResponse['add_paydata[last-rate]'],
            'serviceCharge' => $aResponse['add_paydata[service-charge]'],
            'totalAmount' => $aResponse['add_paydata[total-amount]'],
        ];

        if ($aInstallmentDetails['lastRate'] < $aInstallmentDetails['rate']) {
            $aInstallmentDetails['numberOfRate'] -= 1;
        }

        $iCode = $this->_generateTranslatedResultCode($aRatepayData, $aInstallmentDetails);

        return $this->_parseRatepayRateDetails($aRatepayData['OXPAYMENTID'], $aInstallmentDetails, $iCode);
    }

    public function fcpoStartPayPalExpress()
    {
        $aJsonResponse = [
            'success' => false,
        ];

        /** @var FcPoRequest $oRequest */
        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oRequest->sendRequestGenericPayment(PayPal::PPE_V2_EXPRESS);

        if (isset($aResponse['status'], $aResponse['workorderid'], $aResponse['add_paydata[orderId]']) && $aResponse['status'] == 'REDIRECT') {
            $aJsonResponse['success'] = true;
            $aJsonResponse['order_id'] = $aResponse['add_paydata[orderId]'];

            if (!empty($aResponse['workorderid'])) {
                $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoWorkorderId', $aResponse['workorderid']);
            }
        }

        if (isset($aResponse['status'], $aResponse['customermessage']) && $aResponse['status'] == 'ERROR') {
            $aJsonResponse['errormessage'] = $aResponse['customermessage'];
        }

        return json_encode($aJsonResponse);
    }

    /**
     * @return array
     */
    public function fcpoGetBNPLInstallment(): array
    {
        /** @var FcPoRequest $oRequest */
        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);

        if (!$this->_oFcPoHelper->fcpoIsBNPLConfigured()) {
            return ['status' => 'ERROR'];
        }

        $aResponse = $oRequest->sendRequestBNPLInstallmentOptions();

        $aFormattedData = [];
        $aFormattedData['status'] = $aResponse['status'];
        $aFormattedData['workorderid'] = $aResponse['workorderid'];
        $aFormattedData['amountValue'] = $this->_oFcPoHelper->fcpoPriceFromCentToDec($aResponse['add_paydata[amount_value]']);
        $aFormattedData['amountCurrency'] = $aResponse['add_paydata[amount_currency]'];
        $aFormattedData['plans'] = [];

        $this->_oFcPoHelper->fcpoSetSessionVariable('fcpopl_secinstallment_workorderid', $aResponse['workorderid']);

        $iCurrPlan = 0;
        while (true) {
            if (!isset ($aResponse['add_paydata[total_amount_currency_' . $iCurrPlan . ']'])) {
                break;
            }

            $aFormattedData['plans'][$iCurrPlan] = [
                'effectiveInterestRate' => $this->_oFcPoHelper->fcpoPriceFromCentToDec($aResponse['add_paydata[effective_interest_rate_' . $iCurrPlan . ']']),
                'firstRateDate' => $aResponse['add_paydata[first_rate_date_' . $iCurrPlan . ']'],
                'installmentOptionId' => $aResponse['add_paydata[installment_option_id_' . $iCurrPlan . ']'],
                'lastRateAmountCurrency' => $aResponse['add_paydata[last_rate_amount_currency_' . $iCurrPlan . ']'],
                'lastRateAmountValue' => $this->_oFcPoHelper->fcpoPriceFromCentToDec($aResponse['add_paydata[last_rate_amount_value_' . $iCurrPlan . ']']),
                'linkCreditInformationHref' => $aResponse['add_paydata[link_credit_information_href_' . $iCurrPlan . ']'],
                'linkCreditInformationType' => $aResponse['add_paydata[link_credit_information_type_' . $iCurrPlan . ']'],
                'monthlyAmountCurrency' => $aResponse['add_paydata[monthly_amount_currency_' . $iCurrPlan . ']'],
                'monthlyAmountValue' => $this->_oFcPoHelper->fcpoPriceFromCentToDec($aResponse['add_paydata[monthly_amount_value_' . $iCurrPlan . ']']),
                'nominalInterestRate' => $this->_oFcPoHelper->fcpoPriceFromCentToDec($aResponse['add_paydata[nominal_interest_rate_' . $iCurrPlan . ']']),
                'numberOfPayments' => $aResponse['add_paydata[number_of_payments_' . $iCurrPlan . ']'],
                'totalAmountCurrency' => $aResponse['add_paydata[total_amount_currency_' . $iCurrPlan . ']'],
                'totalAmountValue' => $this->_oFcPoHelper->fcpoPriceFromCentToDec($aResponse['add_paydata[total_amount_value_' . $iCurrPlan . ']']),
            ];

            $iCurrPlan++;
        }

        $aFormattedData['html'] = $this->_fcpoBNPLPrepareInstallementHTML($aFormattedData);

        return $aFormattedData;
    }

    /**
     * @param array $aInstallamentOptions
     * @return string
     */
    protected function _fcpoBNPLPrepareInstallementHTML(array $aInstallamentOptions) : string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();

        $sHtmlList = '';
        $sHtmlDetails = '';
        foreach ($aInstallamentOptions['plans'] as $iIndex => $aPlan) {
            $sHtmlList .= '    <div>';
            $sHtmlList .= '        <input id="bnplPlan_' . $iIndex . '" type="radio" name="dynvalue[fcpopl_secinstallment_plan]"'
                                    . 'value="' . $aPlan['installmentOptionId'] . '" onclick="fcpoSelectBNPLInstallmentPlan(' . $iIndex . ')"/>';
            $sHtmlList .= '        <a href="#" onclick="fcpoSelectBNPLInstallmentPlan(' . $iIndex . ')">'
                                    . $aPlan['monthlyAmountValue'] . ' ' . $aPlan['monthlyAmountCurrency'] . ' ' . $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_PER_MONTH')
                                    . ' - '
                                    . $aPlan['numberOfPayments'] . ' ' . $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_RATES')
                                    . '</a>';
            $sHtmlList .= '    </div>';

            $sHtmlDetails .= '    <div id="bnpl_installment_overview_' . $iIndex . '" class="bnpl_installment_overview" style="display: none">';
            $sHtmlDetails .= '        <strong>' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_TITLE') . '</strong>';
            $sHtmlDetails .= '        <br/>';
            $sHtmlDetails .= '        <div class="container-fluid">';
            $sHtmlDetails .= '            <div class="row">';
            $sHtmlDetails .= '                <div class="col-lg-8">' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_NBRATES') . ':</div>';
            $sHtmlDetails .= '                <div class="col-lg-4 fcpopl-secinstallment-table-value">' . $aPlan['numberOfPayments'] . '</div>';
            $sHtmlDetails .= '            </div>';
            $sHtmlDetails .= '            <div class="row">';
            $sHtmlDetails .= '                <div class="col-lg-8">' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_TOTALFINANCING') . ':</div>';
            $sHtmlDetails .= '                <div class="col-lg-4 fcpopl-secinstallment-table-value">' . $aInstallamentOptions['amountValue'] . ' ' . $aInstallamentOptions['amountCurrency'] . '</div>';
            $sHtmlDetails .= '            </div>';
            $sHtmlDetails .= '            <div class="row">';
            $sHtmlDetails .= '                <div class="col-lg-8">' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_TOTALAMOUNT') . ':</div>';
            $sHtmlDetails .= '                <div class="col-lg-4 fcpopl-secinstallment-table-value">' . $aPlan['totalAmountValue'] . ' ' . $aPlan['totalAmountCurrency'] . '</div>';
            $sHtmlDetails .= '            </div>';
            $sHtmlDetails .= '            <div class="row">';
            $sHtmlDetails .= '                <div class="col-lg-8">' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_INTEREST') . ':</div>';
            $sHtmlDetails .= '                <div class="col-lg-4 fcpopl-secinstallment-table-value">' . $aPlan['nominalInterestRate'] . '%</div>';
            $sHtmlDetails .= '            </div>';
            $sHtmlDetails .= '            <div class="row">';
            $sHtmlDetails .= '                <div class="col-lg-8">' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_EFFECTIVEINTEREST') . ':</div>';
            $sHtmlDetails .= '                <div class="col-lg-4 fcpopl-secinstallment-table-value">' . $aPlan['effectiveInterestRate'] . '%</div>';
            $sHtmlDetails .= '            </div>';
            $sHtmlDetails .= '            <div class="row">';
            $sHtmlDetails .= '                <div class="col-lg-8">' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_MONTHLYRATE') . ':</div>';
            $sHtmlDetails .= '                <div class="col-lg-4 fcpopl-secinstallment-table-value">' . $aPlan['monthlyAmountValue'] . ' ' . $aPlan['monthlyAmountCurrency'] . '</div>';
            $sHtmlDetails .= '            </div>';
            $sHtmlDetails .= '            <div class="row">';
            $sHtmlDetails .= '                <div class="col-lg-12">';
            $sHtmlDetails .= '                    <br/>';
            $sHtmlDetails .= '                    <a target="_blank" href="' . $aPlan['linkCreditInformationHref'] . '">' . $oLang->translateString('FCPO_BNPL_SECINSTALLMENT_OVW_DL_CREDINFO') . '</a>';
            $sHtmlDetails .= '                </div>';
            $sHtmlDetails .= '            </div>';
            $sHtmlDetails .= '        </div>';
            $sHtmlDetails .= '    </div>';
        }
        $sHtml = '<div class="form-floating mb-3">';
        $sHtml .= '    <div></div>';
        $sHtml .= $sHtmlList;
        $sHtml .= '</div>';
        $sHtml .= '<div class="form-floating mb-3">';
        $sHtml .= $sHtmlDetails;
        $sHtml .= ' </div> ';

        return $sHtml;
    }

    /**
     * @param array $aRatepayData
     * @param array $aInstallmentDetails
     * @return string|int
     */
    protected function _generateTranslatedResultCode(array $aRatepayData, array $aInstallmentDetails): string|int
    {
        if (isset($aRatepayData['installment']) && $aRatepayData['installment'] < $aInstallmentDetails['rate']) {
            return 'RATE_INCREASED';
        }
        if (isset($aRatepayData['installment']) && $aRatepayData['installment'] > $aInstallmentDetails['rate']) {
            return 'RATE_REDUCED';
        }

        return 603;
    }

    /**
     * @param string $sPaymentMethod
     * @param array $aInstallmentDetails
     * @param int $iCode
     * @return string
     */
    protected function _parseRatepayRateDetails(string $sPaymentMethod, array $aInstallmentDetails, int $iCode): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();

        $sHtml = '<div class="rp-table-striped">';
        $sHtml .= '    <div>';
        $sHtml .= '        <div class="text-center text-uppercase" colspan="2">' . $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_TITLE') . '</div>';
        $sHtml .= '    </div>';

        $sHtml .= '    <div>';
        $sHtml .= '        <div class="warning small text-center" colspan="2">' . $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_CODE_TRANSLATION_' . $iCode) . '<br/>' . $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_EXAMPLE') . '</div>';
        $sHtml .= '    </div>';

        $sHtml .= '    <div class="rp-menue">';
        $sHtml .= '        <div colspan="2" class="small text-right">';
        $sHtml .= '             <a class="rp-link" id="' . $sPaymentMethod . '_rp-show-installment-plan-details" onclick="fcpoRpChangeDetails(\'' . $sPaymentMethod . '\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_SHOW');
        $sHtml .= '                <img src="out/modules/fcpayone/img/icon-enlarge.png" class="rp-details-icon" />';
        $sHtml .= '            </a>';
        $sHtml .= '             <a class="rp-link" id="' . $sPaymentMethod . '_rp-hide-installment-plan-details" onclick="fcpoRpChangeDetails(\'' . $sPaymentMethod . '\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_HIDE');
        $sHtml .= '                <img src="out/modules/fcpayone/img/icon-shrink.png" class="rp-details-icon" />';
        $sHtml .= '            </a>';
        $sHtml .= '        </div>';
        $sHtml .= '    </div>';

        $sHtml .= '    <div id="' . $sPaymentMethod . '_rp-installment-plan-details">';
        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '             <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_amount\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_amount\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_PRICE_LABEL') . '&nbsp;';
        $sHtml .= '                 <p id="' . $sPaymentMethod . '_amount" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_PRICE_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['amount'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';

        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '            <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_serviceCharge\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_serviceCharge\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_SERVICE_CHARGE_LABEL') . '&nbsp;';
        $sHtml .= '                <p id="' . $sPaymentMethod . '_serviceCharge" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DETAILS_SERVICE_CHARGE_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['serviceCharge'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';

        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '            <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_annualPercentageRate\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_annualPercentageRate\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_EFFECTIVE_RATE_LABEL') . '&nbsp;';
        $sHtml .= '                <p id="' . $sPaymentMethod . '_annualPercentageRate" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_EFFECTIVE_RATE_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['annualPercentageRate'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';

        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '            <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_interestRate\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_interestRate\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DEBIT_RATE_LABEL') . '&nbsp;';
        $sHtml .= '                <p id="' . $sPaymentMethod . '_interestRate" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DEBIT_RATE_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['interestRate'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';

        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '            <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_interestAmount\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_interestAmount\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_INTEREST_AMOUNT_LABEL') . '&nbsp;';
        $sHtml .= '                <p id="' . $sPaymentMethod . '_interestAmount" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_INTEREST_AMOUNT_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['interestAmount'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';

        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '            <div colspan="2"></div>';
        $sHtml .= '        </div>';

        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '            <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_rate\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_rate\')">';
        $sHtml .= $aInstallmentDetails['numberOfRate'] . ' ' . $oLang->translateString('FCPO_RATEPAY_CALCULATION_DURATION_MONTH_LABEL') . '&nbsp;';
        $sHtml .= '                <p id="' . $sPaymentMethod . '_rate" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DURATION_MONTH_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['rate'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';

        $sHtml .= '        <div class="rp-installment-plan-details">';
        $sHtml .= '            <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_lastRate\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_lastRate\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_LAST_RATE_LABEL') . '&nbsp;';
        $sHtml .= '                <p id="' . $sPaymentMethod . '_lastRate" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_LAST_RATE_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['lastRate'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';
        $sHtml .= '    </div>';


        $sHtml .= '    <div id="' . $sPaymentMethod . '_rp-installment-plan-no-details">';
        $sHtml .= '        <div class="rp-installment-plan-no-details">';
        $sHtml .= '            <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_rate2\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_rate2\')">';
        $sHtml .= $aInstallmentDetails['numberOfRatesFull'] . ' ' . $oLang->translateString('FCPO_RATEPAY_CALCULATION_DURATION_MONTH_LABEL') . '&nbsp;';
        $sHtml .= '                <p id="' . $sPaymentMethod . '_rate2" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_DURATION_MONTH_DESC');
        $sHtml .= '                </p>';
        $sHtml .= '            </div>';
        $sHtml .= '            <div class="text-right">';
        $sHtml .= $aInstallmentDetails['rate'];
        $sHtml .= '            </div>';
        $sHtml .= '        </div>';
        $sHtml .= '    </div>';
        $sHtml .= '    <div class="rp-installment-plan-details">';
        $sHtml .= '        <div class="rp-installment-plan-title" onmouseover="fcpoMouseOver(\'' . $sPaymentMethod . '_totalAmount\')" onmouseout="fcpoMouseOut(\'' . $sPaymentMethod . '_totalAmount\')">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_TOTAL_AMOUNT_LABEL') . '&nbsp;';
        $sHtml .= '            <p id="' . $sPaymentMethod . '_totalAmount" class="rp-installment-plan-description small">';
        $sHtml .= $oLang->translateString('FCPO_RATEPAY_CALCULATION_TOTAL_AMOUNT_DESC');
        $sHtml .= '            </p>';
        $sHtml .= '        </div>';
        $sHtml .= '        <div class="text-right">';
        $sHtml .= $aInstallmentDetails['totalAmount'];
        $sHtml .= '        </div>';
        $sHtml .= '    </div>';
        $sHtml .= '</div>';
        $sHtml .= '<div>';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_amount]" value="' . $aInstallmentDetails['rate'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_number]" value="' . $aInstallmentDetails['numberOfRatesFull'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_last_amount]" value="' . $aInstallmentDetails['lastRate'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_interest_rate]" value="' . $aInstallmentDetails['interestRate'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_total_amount]" value="' . $aInstallmentDetails['totalAmount'] . '">';

        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_service_charge]" value="' . $aInstallmentDetails['serviceCharge'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_annual_percentage_rate]" value="' . $aInstallmentDetails['annualPercentageRate'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_interest_amount]" value="' . $aInstallmentDetails['interestAmount'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_basket_amount]" value="' . $aInstallmentDetails['amount'] . '">';
        $sHtml .= '<input type="hidden" name="dynvalue[fcporp_installment_number_of_rate]" value="' . $aInstallmentDetails['numberOfRate'] . '">';

        return $sHtml . '</div>';
    }

    /**
     * @param string $sPaymentId
     * @param string $sAction
     * @param string $sParamsJson
     * @return string
     */
    public function fcpoTriggerKlarnaAction(string $sPaymentId, string $sAction, string $sParamsJson): string
    {
        if ($sAction === 'start_session') {
            try {
                return $this->fcpoTriggerKlarnaSessionStart($sPaymentId, $sParamsJson);
            } catch (JsonException $oEx) {
                $oLogger = Registry::getLogger();
                $oLogger->error($oEx->getTraceAsString());
                return '';
            }
        }

        return '';
    }

    /**
     * Trigger klarna session start
     *
     * @param string $sPaymentId
     * @param string $sParamsJson
     * @return string
     * @throws JsonException
     */
    public function fcpoTriggerKlarnaSessionStart(string $sPaymentId, string $sParamsJson): string
    {
        $this->_fcpoUpdateUser($sParamsJson);
        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oRequest->sendRequestKlarnaStartSession($sPaymentId);
        $blIsValid = (
            isset($aResponse['status'], $aResponse['add_paydata[client_token]']) &&
            $aResponse['status'] === 'OK'
        );

        if (!$blIsValid) {
            $this->_oFcPoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcPoHelper->fcpoSetSessionVariable(
                'payerrortext',
                $aResponse['errormessage']
            );
            header("HTTP/1.0 503 Service not available");
        }

        $this->_fcpoSetKlarnaSessionParams($aResponse);

        $oParamsParser = $this->_oFcPoHelper->getFactoryObject(FcPoParamsParser::class);

        return $oParamsParser->fcpoGetKlarnaWidgetJS(
            $aResponse['add_paydata[client_token]'],
            $sParamsJson
        );
    }

    /**
     * @param string $sParamsJson
     * @return void
     * @throws JsonException
     */
    public function _fcpoUpdateUser(string $sParamsJson): void
    {
        $aParams = json_decode($sParamsJson, true, 512, JSON_THROW_ON_ERROR);
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        /** @var User $oUser value */
        if ($aParams['birthday'] !== 'undefined') {
            $oUser->oxuser__oxbirthdate = new Field($aParams['birthday']);
        }
        if ($aParams['telephone'] !== 'undefined') {
            $oUser->oxuser__oxfon = new Field($aParams['telephone']);
        }
        if ($aParams['personalid'] !== 'undefined') {
            $oUser->oxuser__fcpopersonalid = new Field($aParams['personalid']);
        }
        $oUser->save();
    }

    /**
     * Set needed session params for later handling of Klarna payment
     *
     * @param array $aResponse
     * @return void
     */
    protected function _fcpoSetKlarnaSessionParams(array $aResponse): void
    {
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('klarna_authorization_token');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('klarna_client_token');
        $this->_oFcPoHelper->fcpoSetSessionVariable(
            'klarna_client_token',
            $aResponse['add_paydata[client_token]']
        );
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoWorkorderId');
        $this->_oFcPoHelper->fcpoSetSessionVariable(
            'fcpoWorkorderId',
            $aResponse['workorderid']
        );
    }

}
