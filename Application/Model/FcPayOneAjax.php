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

use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Model\BaseModel;

/*
 * load OXID Framework
 */
if (!function_exists('getShopBasePath')) {
    function getShopBasePath()
    {
        return __DIR__ . '/../../../../../';
    }
}

if (file_exists(getShopBasePath() . "bootstrap.php")) {
    include_once getShopBasePath() . "bootstrap.php";
} else {
    // global variables which are important for older OXID.
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_USER_AGENT'] = 'payone_ajax';
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
    $_SERVER['HTTP_REFERER'] = '';
    $_SERVER['QUERY_STRING'] = '';

    include getShopBasePath() . 'modules/functions.php';
    include_once getShopBasePath() . 'core/oxfunctions.php';
    include_once getShopBasePath() . 'views/oxubase.php';
}

// receive params
$sPaymentId = filter_input(INPUT_POST, 'paymentid');
$sAction = filter_input(INPUT_POST, 'action');
$sParamsJson = filter_input(INPUT_POST, 'params');

/**
 * Class for receiving ajax calls and delivering needed data
 *
 * @author andre
 */
class FcPayOneAjax extends BaseModel
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    private $_oFcpoHelper;

    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     *
     *
     * @param $sPaymentId
     * @param $sAction
     * @param $sParamsJson
     * @return string
     */
    public function fcpoTriggerKlarnaAction($sPaymentId, $sAction, $sParamsJson)
    {
        if ($sAction === 'start_session') {
            return $this->fcpoTriggerKlarnaSessionStart($sPaymentId, $sParamsJson);
        }
    }

    /**
     * Trigger klarna session start
     *
     * @param $sPaymentId
     * @param $sParamsJson
     * @return string
     */
    public function fcpoTriggerKlarnaSessionStart($sPaymentId, $sParamsJson)
    {
        $this->_fcpoUpdateUser($sParamsJson);
        $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oRequest->sendRequestKlarnaStartSession($sPaymentId);
        $blIsValid = (
            isset($aResponse['status'], $aResponse['add_paydata[client_token]']) &&
            $aResponse['status'] === 'OK'
        );

        if (!$blIsValid) {
            $this->_oFcpoHelper->fcpoSetSessionVariable('payerror', -20);
            $this->_oFcpoHelper->fcpoSetSessionVariable(
                'payerrortext',
                $aResponse['errormessage']
            );
            header("HTTP/1.0 503 Service not available");
            return '';
        }

        $this->_fcpoSetKlarnaSessionParams($aResponse);

        $oParamsParser = $this->_oFcpoHelper->getFactoryObject('fcpoparamsparser');

        return $oParamsParser->fcpoGetKlarnaWidgetJS(
            $aResponse['add_paydata[client_token]'],
            $sParamsJson
        );
    }

    /**
     *
     *
     * @param $sParamsJson
     * @return string
     */
    public function _fcpoUpdateUser($sParamsJson): void
    {
        $aParams = json_decode((string) $sParamsJson, true, 512, JSON_THROW_ON_ERROR);
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getUser();
        /** @var oxUser $oUser value */
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
     * @param $aResponse
     */
    private function _fcpoSetKlarnaSessionParams($aResponse): void
    {
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('klarna_authorization_token');
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('klarna_client_token');
        $this->_oFcpoHelper->fcpoSetSessionVariable(
            'klarna_client_token',
            $aResponse['add_paydata[client_token]']
        );
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoWorkorderId');
        $this->_oFcpoHelper->fcpoSetSessionVariable(
            'fcpoWorkorderId',
            $aResponse['workorderid']
        );
    }

    /**
     * Triggers a call on payoneapi for handling ajax calls for referencedetails
     *
     * @param $sParamsJson
     */
    public function fcpoGetAmazonReferenceId($sParamsJson): void
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $aParams = json_decode((string) $sParamsJson, true, 512, JSON_THROW_ON_ERROR);
        $sAmazonReferenceId = $aParams['fcpoAmazonReferenceId'];
        $oSession->deleteVariable('fcpoAmazonReferenceId');
        $oSession->setVariable('fcpoAmazonReferenceId', $sAmazonReferenceId);
        $sAmazonLoginAccessToken = $oSession->getVariable('sAmazonLoginAccessToken');

        // do the call cascade
        $this->_fcpoHandleGetOrderReferenceDetails($sAmazonReferenceId, $sAmazonLoginAccessToken);
        $this->_fcpoHandleSetOrderReferenceDetails($sAmazonReferenceId, $sAmazonLoginAccessToken);
    }

    /**
     * Triggers call getorderreferencedetails
     *
     * @param $sAmazonReferenceId
     * @param $sAmazonLoginAccessToken
     */
    private function _fcpoHandleGetOrderReferenceDetails($sAmazonReferenceId, $sAmazonLoginAccessToken): void
    {
        $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
        $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);

        $aResponse = $oRequest->sendRequestGetAmazonOrderReferenceDetails($sAmazonReferenceId, $sAmazonLoginAccessToken);

        if ($aResponse['status'] == 'OK') {
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoAmazonWorkorderId');
            $this->_oFcpoHelper->fcpoSetSessionVariable('fcpoAmazonWorkorderId', $aResponse['workorderid']);
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('paymentid');
            $this->_oFcpoHelper->fcpoSetSessionVariable('paymentid', 
    'fcpoamazonpay');
        } else {
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $sShopUrl = $oConfig->getShopUrl();
            $oUtils->redirect($sShopUrl . "index.php?cl=basket");
        }
    }

    /**
     * Triggers call setorderreferencedetails
     *
     * @param $sAmazonReferenceId
     * @param $sAmazonLoginAccessToken
     */
    private function _fcpoHandleSetOrderReferenceDetails($sAmazonReferenceId, $sAmazonLoginAccessToken): void
    {
        $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
        $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $sWorkorderId = $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoAmazonWorkorderId');

        $aResponse = $oRequest->sendRequestSetAmazonOrderReferenceDetails($sAmazonReferenceId, $sAmazonLoginAccessToken, $sWorkorderId);

        if ($aResponse['status'] == 'OK') {
            $oUser = $this->_oFcpoHelper->getFactoryObject('oxuser');
            $oUser->fcpoSetAmazonOrderReferenceDetailsResponse($aResponse);
        } else {
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $sShopUrl = $oConfig->getShopUrl();
            $oUtils->redirect($sShopUrl . "index.php?cl=basket");
        }
    }

    /**
     *
     *
     * @param $sParamsJson
     */
    public function fcpoConfirmAmazonPayOrder($sParamsJson): void
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $aParams = json_decode((string) $sParamsJson, true, 512, JSON_THROW_ON_ERROR);
        $sAmazonReferenceId = $aParams['fcpoAmazonReferenceId'];
        $sToken = $aParams['fcpoAmazonStoken'];
        $sDeliveryMD5 = $aParams['fcpoAmazonDeliveryMD5'];

        $oSession->deleteVariable('fcpoAmazonReferenceId');
        $oSession->setVariable('fcpoAmazonReferenceId', $sAmazonReferenceId);

        $this->_fcpoHandleConfirmAmazonPayOrder($sAmazonReferenceId, $sToken, $sDeliveryMD5);
    }

    /**
     * Calls confirmorderreference call. Sends a 404 on invalid state
     *
     * @param $sAmazonReferenceId
     * @param $sToken
     */
    private function _fcpoHandleConfirmAmazonPayOrder($sAmazonReferenceId, $sToken, $sDeliveryMD5)
    {
        $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);

        $aResponse =
            $oRequest->sendRequestGetConfirmAmazonPayOrder($sAmazonReferenceId, $sToken, $sDeliveryMD5);

        $blSend400 = (
            isset($aResponse['status']) &&
            $aResponse['status'] != 'OK'
        );

        if ($blSend400) {
            header("HTTP/1.0 404 Not Found");
            return ;
        }

        header("HTTP/1.0 200 Ok");
    }

    /**
     * Performs a precheck for payolution installment
     *
     * @param type $sPaymentId
     * @return bool
     */
    public function fcpoTriggerPrecheck($sPaymentId, $sParamsJson)
    {
        $oPaymentController = $this->_oFcpoHelper->getFactoryObject('payment');
        $oPaymentController->setPayolutionAjaxParams(json_decode((string) $sParamsJson, true, 512, JSON_THROW_ON_ERROR));
        $mPreCheckResult = $oPaymentController->fcpoPayolutionPreCheck($sPaymentId);

        return ($mPreCheckResult === true) ? 'SUCCESS' : $mPreCheckResult;
    }

    /**
     * Performs a precheck for payolution installment
     *
     * @param string $sPaymentId
     * @return mixed
     */
    public function fcpoTriggerInstallmentCalculation($sPaymentId)
    {
        $oPaymentController = $this->_oFcpoHelper->getFactoryObject('payment');

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
    public function fcpoParseCalculation2Html($aCalculation)
    {
        $sDownloadUrl = null;
        $oLang = $this->_oFcpoHelper->fcpoGetLang();

        $sTranslateInstallmentSelection = utf8_encode((string) $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_SELECTION'));
        $sTranslateSelectInstallment = utf8_encode((string) $oLang->translateString('FCPO_PAYOLUTION_SELECT_INSTALLMENT'));

        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
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
            $sHtml .= $this->_fcpoGetInsterestRadio($sKey);
            $sHtml .= $this->_fcpoGetInsterestLabel($sKey, $aCurrentInstallment);
            $sHtml .= '<br>';
        }
        $sHtml .= '</fieldset>';
        $sHtml .= '</div></div>';
        $sHtml .= '<div class="payolution_installment_details">';
        foreach ($aCalculation as $sKey => $aCurrentInstallment) {
            $sHtml .= '<div id="payolution_rates_details_' . $sKey . '" class="payolution_rates_invisible">';
            foreach ($aCurrentInstallment['Months'] as $sMonth => $aRatesDetails) {
                $sHtml .= $this->_fcpoGetInsterestMonthDetail($sMonth, $aRatesDetails) . '<br>';
            }
            $sDownloadUrl = $oConfig->getShopUrl() . '/modules/fc/fcpayone/lib/fcpopopup_content.php?login=1&loadurl=' . $aCurrentInstallment['StandardCreditInformationUrl'];
            $sHtml .= '</div>';
        }
        $sHtml .= '</div>';

        return $sHtml . ('<div class="payolution_draft_download"><a href="' . $sDownloadUrl . '"' . $this->_fcpoGetLightView() . '>' . $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_DOWNLOAD_DRAFT') . '</a></div>');
    }

    /**
     * Set hidden fields for beeing able to set needed values
     *
     * @param string $sKey
     * @param array  $aCurrentInstallment
     * @return string
     */
    private function _fcpoGetInsterestHiddenFields($sKey, $aCurrentInstallment)
    {
        $sHtml = '<input type="hidden" id="payolution_installment_value_' . $sKey . '" value="' . str_replace('.', 
    ',', (string) $aCurrentInstallment['Amount']) . '">';
        $sHtml .= '<input type="hidden" id="payolution_installment_duration_' . $sKey . '" value="' . $aCurrentInstallment['Duration'] . '">';
        $sHtml .= '<input type="hidden" id="payolution_installment_eff_interest_rate_' . $sKey . '" value="' . str_replace('.', 
    ',', (string) $aCurrentInstallment['EffectiveInterestRate']) . '">';
        $sHtml .= '<input type="hidden" id="payolution_installment_interest_rate_' . $sKey . '" value="' . str_replace('.', 
    ',', (string) $aCurrentInstallment['InterestRate']) . '">';

        return $sHtml . ('<input type="hidden" id="payolution_installment_total_amount_' . $sKey . '" value="' . str_replace('.', 
    ',', (string) $aCurrentInstallment['TotalAmount']) . '">');
    }

    /**
     * Returns a html radio button for current installment offer
     *
     * @param string $sKey
     * @return string
     */
    private function _fcpoGetInsterestRadio($sKey)
    {
        return '<input type="radio" id="payolution_installment_offer_' . $sKey . '" name="payolution_installment_selection" value="' . $sKey . '">';
    }

    /**
     * Returns a html label for current installment offer radiobutton
     *
     * @param string $sKey
     * @param array  $aCurrentInstallment
     * @return string
     */
    private function _fcpoGetInsterestLabel($sKey, $aCurrentInstallment)
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
    private function _fcpoGetInsterestCaption($aCurrentInstallment)
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sPerMonth = $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_PER_MONTH');
        $sRates = $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_RATES');
        $sMonthlyAmount = str_replace('.', 
    ',', (string) $aCurrentInstallment['Amount']);
        $sDuration = $aCurrentInstallment['Duration'];
        $sCurrency = $aCurrentInstallment['Currency'];

        // put all together to final caption
        $sCaption = $sMonthlyAmount . " " . $sCurrency . " " . $sPerMonth . " - " . $sDuration . " " . $sRates;

        return $sCaption;
    }

    /**
     * Returns a caption for a certain month
     *
     * @param string $sMonth
     * @param array  $aRatesDetails
     * @return string
     */
    private function _fcpoGetInsterestMonthDetail($sMonth, $aRatesDetails)
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sRateCaption = $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_RATE');
        $sDueCaption = utf8_encode((string) $oLang->translateString('FCPO_PAYOLUTION_INSTALLMENT_DUE_AT'));
        $sDue = date('d.m.Y', strtotime((string) $aRatesDetails['Due']));
        $sRate = str_replace('.', 
    ',', (string) $aRatesDetails['Amount']);

        return $sMonth . '. ' . $sRateCaption . ': ' . $sRate . ' ' . $aRatesDetails['Currency'] . ' (' . $sDueCaption . ' ' . $sDue . ')';
    }

    /**
     * Returns lightview part for download
     *
     * 
     * @return string
     */
    private function _fcpoGetLightView()
    {
        $sContent = 'class="lightview" data-lightview-type="iframe" data-lightview-options="';
        $sContent .= "width: 800, height: 600, viewport: 'scale',background: { color: '#fff', opacity: 1 },skin: 'light'";

        return $sContent . '"';
    }

    /**
     * Formats error message to be displayed in a error box
     *
     * @param string $sMessage
     * @return string
     */
    public function fcpoReturnErrorMessage($sMessage)
    {
        $sReturn = '<p class="payolution_message_error">';
        $sReturn .= $sMessage;

        return $sReturn . '</p>';
    }
}


if ($sPaymentId) {
    $oPayoneAjax = new FcPayOneAjax();
    if ($sAction == 'precheck') {
        $sResult = $oPayoneAjax->fcpoTriggerPrecheck($sPaymentId, $sParamsJson);
        if ($sResult == 'SUCCESS') {
            $sAction = 'calculation';
        } else {
            echo $oPayoneAjax->fcpoReturnErrorMessage($sResult);
        }
    }

    if ($sAction == 'calculation') {
        $mResult = $oPayoneAjax->fcpoTriggerInstallmentCalculation($sPaymentId);
        if (is_array($mResult) && $mResult !== []) {
            // we have got a calculation result. Parse it to needed html
            echo $oPayoneAjax->fcpoParseCalculation2Html($mResult);
        }
    }

    if ($sAction == 'get_amazon_reference_details' && $sPaymentId == 'fcpoamazonpay') {
        $oPayoneAjax->fcpoGetAmazonReferenceId($sParamsJson);
    }


    $blConfirmAmazonOrder = (
        $sAction == 'confirm_amazon_pay_order' &&
        $sPaymentId == 'fcpoamazonpay'
    );
    if ($blConfirmAmazonOrder) {
        $oPayoneAjax->fcpoConfirmAmazonPayOrder($sParamsJson);
    }

    $aKlarnaPayments = ['fcpoklarna_invoice', 
    'fcpoklarna_installments', 
    'fcpoklarna_directdebit'];
    if (in_array($sPaymentId, $aKlarnaPayments)) {
        echo $oPayoneAjax->fcpoTriggerKlarnaAction($sPaymentId, $sAction, $sParamsJson);
    }
}
