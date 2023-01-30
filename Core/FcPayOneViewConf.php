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

namespace Fatchip\PayOne\Core;

use Fatchip\PayOne\Application\Model\FcPayOnePayment;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Lib\FcPoHelper;

class FcPayOneViewConf extends FcPayOneViewConf_parent
{

    /**
     * Name of the module folder
     *
     * @var string
     */
    protected $_sModuleFolder = "../../modules/fc/fcpayone";

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper;

    /**
     * Hosted creditcard js url
     *
     * @var string
     */
    protected $_sFcPoHostedJsUrl = 'https://secure.pay1.de/client-api/js/v1/payone_hosted_min.js';

    /**
     * List of handled themes and their belonging pathes
     *
     * @var array
     */
    protected $_aSupportedThemes = [
        'flow' => 'flow',
        'azure' => 'azure',
        'wave' => 'wave',
        'twig' => 'twig'
    ];

    /**
     * List of themes and their
     *
     * @var array
     */
    protected $_aTheme2CssPayButtonSelector = [
        'flow' => 'nextStep',
        'azure' => 'nextStep',
        'wave' => 'nextStep',
        'twig' => 'nextStep'
    ];

    /**
     * Counts the amount of widgets have been included by call
     */
    protected int $_iAmzWidgetIncludeCounter = 0;

    /**
     * Determines the source of a button include
     *
     * @var string|null
     */
    protected $_sCurrentAmazonButtonId;
    /** @var string */
    private const S_PAYMENT_ERROR_PARAM = '&payerror=-20';
    /** @var array<string, int> */
    private const A_CONTROLLER2_AMOUNT = ['basket' => 3, 'user' => 2];
    /** @var string */
    private const S_PATH = "modules/fc/fcpayone/Application/Model/FcPayOneAjax.php";


    /**
     * Initializing needed things
     */
    public function __construct()
    {
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns url to module img folder (admin)
     *
     *
     * @return string
     */
    public function fcpoGetAdminModuleImgUrl()
    {
        $sModuleUrl = $this->fcpoGetModuleUrl();

        return $sModuleUrl . 'out/admin/img/';
    }

    /**
     * Returns the url to module
     *
     *
     * @return string
     */
    public function fcpoGetModuleUrl()
    {
        return $this->getModuleUrl($this->_sModuleFolder);
    }

    /**
     * Returns the path to javascripts of module
     *
     * @param string $sFile
     * @return string
     */
    public function fcpoGetAbsModuleJsPath($sFile = "")
    {
        $sModulePath = $this->fcpoGetModulePath();
        $sModuleJsPath = $sModulePath . 'out/src/js/';
        if ($sFile !== '' && $sFile !== '0') {
            $sModuleJsPath .= $sFile;
        }

        return $sModuleJsPath;
    }

    /**
     * Returns the path to module
     *
     *
     * @return string
     */
    public function fcpoGetModulePath()
    {
        return $this->getModulePath($this->_sModuleFolder);
    }

    /**
     * Returns the path to javascripts of module
     *
     * @param string $sFile
     * @return string
     */
    public function fcpoGetModuleJsPath($sFile = "")
    {
        $sModuleUrl = $this->fcpoGetModuleUrl();
        $sModuleJsUrl = $sModuleUrl . 'out/src/js/';
        if ($sFile !== '' && $sFile !== '0') {
            $sModuleJsUrl .= $sFile;
        }

        return $sModuleJsUrl;
    }

    /**
     * Returns integer of shop version
     *
     *
     * @return string
     */
    public function fcpoGetIntShopVersion()
    {
        return $this->_oFcpoHelper->fcpoGetIntShopVersion();
    }

    /**
     * Returns the path to javascripts of module
     *
     * @param string $sFile
     * @return string
     */
    public function fcpoGetModuleCssPath($sFile = "")
    {
        $sModuleUrl = $this->fcpoGetModuleUrl();
        $sModuleUrl .= 'out/src/css/';
        if ($sFile !== '' && $sFile !== '0') {
            $sModuleUrl .= $sFile;
        }

        return $sModuleUrl;
    }

    /**
     * Returns the path to javascripts of module
     *
     * @param string $sFile
     * @return string
     */
    public function fcpoGetAbsModuleTemplateFrontendPath($sFile = "")
    {
        $sModulePath = $this->fcpoGetModulePath();
        $sModulePath .= 'Application/views/frontend/tpl/';
        if ($sFile !== '' && $sFile !== '0') {
            $sModulePath .= $sFile;
        }

        return $sModulePath;
    }

    /**
     * Returns hosted js url
     *
     * @return string
     */
    public function fcpoGetHostedPayoneJs()
    {
        return $this->_sFcPoHostedJsUrl;
    }

    /**
     * Returns Iframe mappings
     *
     *
     * @return array
     */
    public function fcpoGetIframeMappings()
    {
        $oErrorMapping = $this->_oFcpoHelper->getFactoryObject(FcPoErrorMapping::class);

        return $oErrorMapping->fcpoGetExistingMappings('iframe');
    }

    /**
     * Returns abbroviation by given id
     *
     * @param string $sLangId
     * @return string
     */
    public function fcpoGetLangAbbrById($sLangId)
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        return $oLang->getLanguageAbbr($sLangId);
    }

    /**
     * Returns if a complete set of salutations is available
     *
     */
    public function fcpoUserHasSalutation(): bool
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getBasketUser();
        $oAddress = $oUser->getSelectedAddress();
        $sSalutation = $oUser->oxuser__oxsal->value;
        $sSalutationDelAddress = is_null($oAddress) ? $sSalutation : $oAddress->oxaddress__oxsal->value;

        return $sSalutation &&
            $sSalutationDelAddress;
    }

    /**
     * Returns session variable
     *
     *
     * @return bool
     */
    public function fcpoGetClientToken()
    {
        return $this->_oFcpoHelper->fcpoGetSessionVariable('klarna_client_token');
    }

    /**
     * Returns session variable
     *
     *
     * @return bool
     */
    public function fcpoGetKlarnaAuthToken()
    {
        return $this->_oFcpoHelper->fcpoGetSessionVariable('klarna_authorization_token');
    }

    /**
     * Returns cancel url for klarna payments
     *
     *
     * @return bool
     */
    public function fcpoGetKlarnaCancelUrl()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sShopURL = $oConfig->getCurrentShopUrl();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sPaymentErrorTextParam = "&payerrortext=" . urlencode((string)$oLang->translateString('FCPO_PAY_ERROR_REDIRECT', null, false)); // see source/modules/fc/fcpayone/out/blocks/fcpo_payment_errors.html.twig
        $sErrorUrl = $sShopURL . 'index.php?type=error&cl=payment' . self::S_PAYMENT_ERROR_PARAM . $sPaymentErrorTextParam;
        return $sErrorUrl;
    }

    /**
     * Checks if selected payment method is pay now
     */
    public function fcpoIsKlarnaPaynow(): bool
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        /** @var oxBasket $oBasket */
        $oBasket = $oSession->getBasket();
        return ($oBasket->getPaymentId() === 'fcpoklarna_directdebit');
    }

    /**
     * Returns if amazonpay is active and though button can be displayed
     *
     *
     * @return bool
     */
    public function fcpoCanDisplayAmazonPayButton()
    {
        return $this->_fcpoPaymentIsActive('fcpoamazonpay');
    }

    /**
     * Checks is given payment is active
     *
     * @param $sPaymentId
     * @return bool
     */
    private function _fcpoPaymentIsActive(string $sPaymentId)
    {
        $oPayment = $this->_oFcpoHelper->getFactoryObject('oxpayment');
        $oPayment->load($sPaymentId);

        return (bool)$oPayment->oxpayments__oxactive->value;
    }

    /**
     * Returns if paydirekt express button can be shown
     *
     *
     * @return bool
     */
    public function fcpoCanDisplayPaydirektExpressButton()
    {
        return $this->_fcpoPaymentIsActive('fcpopaydirekt_express');
    }

    /**
     * Returns amazon widgets url depending if mode is live or test
     */
    public function fcpoGetAmazonWidgetsUrl()
    {
        $oPayment = $this->_oFcpoHelper->getFactoryObject('oxpayment');
        $oPayment->load('fcpoamazonpay');
        $blIsLive = $oPayment->oxpayments__fcpolivemode->value;

        $sAmazonWidgetsUrl = 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/sandbox/lpa/js/Widgets.js';
        if ($blIsLive) {
            $sAmazonWidgetsUrl = 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/lpa/js/Widgets.js';
        }

        return $sAmazonWidgetsUrl;
    }

    /**
     * Returns amazon client id
     *
     * @return string
     */
    public function fcpoGetAmazonPayClientId()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sClientId = $oConfig->getConfigParam('sFCPOAmazonPayClientId');

        return (string)$sClientId;
    }

    /**
     * Returns amazon seller id
     *
     *
     * @return string
     */
    public function fcpoGetAmazonPaySellerId()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sSellerId = $oConfig->getConfigParam('sFCPOAmazonPaySellerId');

        return (string)$sSellerId;
    }

    /**
     * Method returns css selector matching to used (parent-)theme
     *
     *
     * @return string
     */
    public function fcpoGetAmazonBuyNowButtonCssSelector()
    {
        $sThemeId = $this->fcpoGetActiveThemePath();

        $blHasSelector =
            isset($this->_aTheme2CssPayButtonSelector[$sThemeId]);

        if (!$blHasSelector) {
            return '';
        }

        return (string)$this->_aTheme2CssPayButtonSelector[$sThemeId];
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
        $sReturn = 'flow';
        $oTheme = $this->_oFcpoHelper->getFactoryObject('oxTheme');

        $sCurrentActiveId = $oTheme->getActiveThemeId();
        $oTheme->load($sCurrentActiveId);
        $aThemeIds = array_keys($this->_aSupportedThemes);
        $sCurrentParentId = $oTheme->getInfo('parentTheme');

        // we're more interested on the parent then on child theme
        if ($sCurrentParentId) {
            $sCurrentActiveId = $sCurrentParentId;
        }

        if (in_array($sCurrentActiveId, $aThemeIds)) {
            $sReturn = $this->_aSupportedThemes[$sCurrentActiveId];
        }

        return $sReturn;
    }

    /**
     * Method returns previously saved reference id
     *
     *
     * @return mixed
     */
    public function fcpoGetAmazonPayReferenceId()
    {
        return $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoAmazonReferenceId');
    }

    /**
     * Returns config value for button type
     *
     *
     * @return string
     */
    public function fcpoGetAmazonPayButtonType()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sValue = $oConfig->getConfigParam('sFCPOAmazonButtonType');

        return (string)$sValue;
    }

    /**
     * Returns config value for button color
     *
     *
     * @return string
     */
    public function fcpoGetAmazonPayButtonColor()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sValue = $oConfig->getConfigParam('sFCPOAmazonButtonColor');

        return (string)$sValue;
    }

    /**
     * Returns if address widget should be displayed readonly
     *
     *
     * @return bool
     */
    public function fcpoGetAmazonPayAddressWidgetIsReadOnly()
    {
        return (bool)$this->_oFcpoHelper->fcpoGetSessionVariable('fcpoAmazonPayAddressWidgetLocked');
    }

    /**
     * Returns url that will be send to amazon for redirect after login
     *
     *
     * @return string
     */
    public function fcpoGetAmazonRedirectUrl()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sShopUrl = $oConfig->getSslShopUrl();
        // force protocol to be 100% ssl
        if (str_contains((string)$sShopUrl, 'http://')) {
            $sShopUrl = str_replace('http://',
                'https://', (string)$sShopUrl);
        }

        return $sShopUrl . "index.php?cl=user&fnc=fcpoamazonloginreturn";
    }

    /**
     * Method returns if there is an active amazon session
     *
     *
     * @return bool
     */
    public function fcpoAmazonLoginSessionActive()
    {
        $sAmazonLoginAccessToken =
            $this->_oFcpoHelper->fcpoGetSessionVariable('sAmazonLoginAccessToken');

        return (bool)$sAmazonLoginAccessToken;
    }

    /**
     * Makes this Email unique to be able to handle amazon users different from standard users
     * Currently the email address simply gets a prefix
     *
     * @param $sEmail
     * @return string
     */
    public function fcpoAmazonEmailEncode($sEmail)
    {
        return "fcpoamz_" . $sEmail;
    }

    /**
     * Returns the origin email of an amazon encoded email
     *
     * @param $sEmail
     * @return string
     */
    public function fcpoAmazonEmailDecode($sEmail)
    {
        $sOriginEmail = $sEmail;
        if (str_contains((string)$sEmail, 'fcpoamz_')) {
            $sOriginEmail = str_replace('fcpoamz_',
                '', (string)$sEmail);
        }

        return $sOriginEmail;
    }

    /**
     * Returns if amazon runs in async mode
     *
     *
     * @return bool
     */
    public function fcpoIsAmazonAsyncMode()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOAmazonMode = $oConfig->getConfigParam('sFCPOAmazonMode');
        $blReturn = false;
        if ($sFCPOAmazonMode == 'alwaysasync') {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Checks if popup method should be used. Depends on setting and/or
     * ssl state
     *
     *
     * @return string
     */
    public function fcpoGetAmzPopup()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOAmazonLoginMode = (string)$oConfig->getConfigParam('sFCPOAmazonLoginMode');
        switch ($sFCPOAmazonLoginMode) {
            case 'popup':
                $sReturn = 'true';
                break;
            case 'redirect':
                $sReturn = 'false';
                break;
            default:
                $sReturn = 'false';
                if ($this->isSsl()) {
                    $sReturn = 'true';
                }
        }

        return $sReturn;
    }

    /**
     * Returns current widget count
     */
    public function fcpoGetCurrentAmzWidgetCount(): int
    {
        return $this->_iAmzWidgetIncludeCounter;
    }

    /**
     * References current button id set in template
     * for determine the last amazon button on current page
     *
     * @param string $sButtonId
     */
    public function fcpoSetCurrentAmazonButtonId($sButtonId): void
    {
        $this->_sCurrentAmazonButtonId = $sButtonId;
    }


    /**
     * Decides if the JS widgets url source should be included
     * Makes sure it will be included after the last amazon button
     *
     *
     * @return bool
     */
    public function fcpoGetAllowIncludeAmazonWidgetUrl()
    {
        $iCurrentInludeCount = (int)$this->_oFcpoHelper->fcpoGetSessionVariable('iAmzWidgetsIncludeCounter');
        ++$iCurrentInludeCount;
        $this->_oFcpoHelper->fcpoSetSessionVariable('iAmzWidgetsIncludeCounter', $iCurrentInludeCount);

        $iExpectedButtonAmount = $this->_fcpoGetExpectedButtonAmount();

        $blReturn = $iCurrentInludeCount >= $iExpectedButtonAmount;
        if ($blReturn) {
            // reset counter
            $this->_oFcpoHelper->fcpoSetSessionVariable('iAmzWidgetsIncludeCounter', 0);
        }

        return $blReturn;
    }

    /**
     * Returns the expected amount of amazon buttons on current page
     *
     *
     * @return void
     */
    private function _fcpoGetExpectedButtonAmount()
    {
        $blModalMiniBasket = ($this->_sCurrentAmazonButtonId == 'modalLoginWithAmazonMiniBasket');

        $sActController = $this->_oFcpoHelper->fcpoGetRequestParameter('cl');

        $iAmountExpectedButtons = self::A_CONTROLLER2_AMOUNT[$sActController] ?? 1;
        if ($blModalMiniBasket) {
            ++$iAmountExpectedButtons;
        }

        return $iAmountExpectedButtons;
    }

    /**
     * Template getter for returning ajax controller url
     *
     *
     * @return string
     */
    public function fcpoGetAjaxControllerUrl()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sShopUrl = $oConfig->getShopUrl();

        return $sShopUrl . self::S_PATH;
    }

    /**
     * Template getter for returning shopurl
     *
     *
     * @return string
     */
    public function fcpoGetShopUrl()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        return $oConfig->getShopUrl();
    }

    /**
     * Returns if if given paymentid is of type payone
     *
     * @param $sPaymentId
     */
    public function fcpoIsPayonePayment($sPaymentId): bool
    {
        return FcPayOnePayment::fcIsPayOnePaymentType($sPaymentId);
    }

    /**
     * Return amazon confirmation error url
     *
     * @return mixed
     */
    public function fcpoGetAmazonConfirmErrorUrl()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();

        $sShopUrl = $oConfig->getShopUrl();
        $sShopUrl .= "index.php?cl=basket";

        $sTranslation = $oLang->translateString('FCPO_PAY_ERROR_REDIRECT', null, false);
        $sPaymentErrorTextParam = "&fcpoerror=" . urlencode((string)$sTranslation);

        return $sShopUrl . $sPaymentErrorTextParam . "&fcpoamzaction=logoff";
    }

    /**
     * Returns current user md5 delivery address hash
     *
     * @return mixed
     */
    public function fcpoGetDeliveryMD5()
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getBasketUser();

        $sDeliveryMD5 = $oUser->getEncodedDeliveryAddress();

        $sDelAddrInfo = $this->fcpoGetDelAddrInfo();
        if ($sDelAddrInfo !== '' && $sDelAddrInfo !== '0') {
            $sDeliveryMD5 .= $sDelAddrInfo;
        }

        return $sDeliveryMD5;
    }

    /**
     * Returns MD5 hash of current selected deliveryaddress
     *
     *
     * @return string
     */
    public function fcpoGetDelAddrInfo()
    {
        $sAddressId = $this->_oFcpoHelper->fcpoGetRequestParameter('deladrid');
        if (!$sAddressId) {
            $oSession = $this->_oFcpoHelper->fcpoGetSession();
            $sAddressId = $oSession->getVariable('deladrid');
        }

        $oAddress = $this->_oFcpoHelper->getFactoryObject('oxAddress');
        $oAddress->load($sAddressId);
        $sEncodedDeliveryAddress = $oAddress->getEncodedDeliveryAddress();

        return (string)$sEncodedDeliveryAddress;
    }

    /**
     * Returns payment error wether from param or session
     *
     *
     * @return mixed
     */
    public function fcpoGetPaymentError()
    {
        $iPayError = $this->_oFcpoHelper->fcpoGetRequestParameter('payerror');

        if (!$iPayError) {
            $oSession = $this->_oFcpoHelper->fcpoGetSession();
            $iPayError = $oSession->getVariable('payerror');
        }

        return $iPayError;
    }

    /**
     * Returns payment error text wether from param or session
     *
     *
     * @return mixed
     */
    public function fcpoGetPaymentErrorText()
    {
        $sPayErrorText = $this->_oFcpoHelper->fcpoGetRequestParameter('payerrortext');

        if (!$sPayErrorText) {
            $oSession = $this->_oFcpoHelper->fcpoGetSession();
            $sPayErrorText = $oSession->getVariable('payerrortext');
        }

        return $sPayErrorText;
    }
}
