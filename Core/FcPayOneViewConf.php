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

use Exception;
use Fatchip\PayOne\Application\Helper\PayPal;
use Fatchip\PayOne\Application\Helper\Redirect;
use Fatchip\PayOne\Application\Model\FcPayOnePayment;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Application\Helper\Payment as PaymentHelper;
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Theme;

use function Fatchip\PayOne\getShopBasePath;

class FcPayOneViewConf extends FcPayOneViewConf_parent
{
    /**
     * Name of the module folder
     *
     * @var string
     */
    protected string $_sModuleFolder = "payone-gmbh/oxid-7";

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Hosted credit card js url
     *
     * @var string
     */
    protected string $_sFcPoHostedJsUrl = 'https://secure.pay1.de/client-api/js/v1/payone_hosted_min.js';

    /**
     * List of handled themes and their belonging paths
     *
     * @var array
     */
    protected array $_aSupportedThemes = [
        'apex' => 'apex',
        'twig' => 'twig'
    ];


    /**
     * Initializing needed things
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns the url to module
     *
     * @return string
     */
    public function fcpoGetModuleUrl(): string
    {
        return $this->_oFcPoHelper->getVendorDir() . $this->_sModuleFolder;
    }

    /**
     * Returns the path to module
     *
     * @return string
     */
    public function fcpoGetModulePath(): string
    {
        return $this->_oFcPoHelper->getVendorDir() . $this->_sModuleFolder;
    }

    /**
     * Returns hosted js url
     *
     * @return string
     */
    public function fcpoGetHostedPayoneJs(): string
    {
        return $this->_sFcPoHostedJsUrl;
    }

    /**
     * Returns Iframe mappings
     *
     * @return array
     */
    public function fcpoGetIframeMappings(): array
    {
        $oErrorMapping = $this->_oFcPoHelper->getFactoryObject(FcPoErrorMapping::class);
        return $oErrorMapping->fcpoGetExistingMappings('iframe');
    }

    /**
     * Returns abbreviation by given id
     *
     * @param string $sLangId
     * @return string
     * @throws LanguageNotFoundException
     */
    public function fcpoGetLangAbbrById(string $sLangId): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        return $oLang->getLanguageAbbr($sLangId);
    }

    /**
     * Returns if a complete set of salutations is available
     *
     * @return bool
     */
    public function fcpoUserHasSalutation(): bool
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $oUser = $oBasket->getBasketUser();
        $oAddress = $oUser->getSelectedAddress();
        $sSalutation = $oUser->oxuser__oxsal->value;
        $sSalutationDelAddress = is_null($oAddress) ? $sSalutation : $oAddress->oxaddress__oxsal->value;

        return (
            $sSalutation &&
            $sSalutationDelAddress
        );
    }

    /**
     * Returns session variable
     *
     * @return string
     */
    public function fcpoGetClientToken(): string
    {
        return $this->_oFcPoHelper->fcpoGetSessionVariable('klarna_client_token');
    }

    /**
     * Returns session variable
     *
     * @return string
     */
    public function fcpoGetKlarnaAuthToken(): string
    {
        return $this->_oFcPoHelper->fcpoGetSessionVariable('klarna_authorization_token');
    }

    /**
     * Returns cancel url for klarna payments
     *
     * @return string
     */
    public function fcpoGetKlarnaCancelUrl(): string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sShopURL = $oConfig->getCurrentShopUrl();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sPaymentErrorTextParam = "&payerrortext=" . urlencode($oLang->translateString('FCPO_PAY_ERROR_REDIRECT', null, false));
        $sPaymentErrorParam = '&payerror=-20';
        return $sShopURL . 'index.php?type=error&cl=payment' . $sPaymentErrorParam . $sPaymentErrorTextParam;
    }

    /**
     * Checks if selected payment method is pay now
     *
     * @return bool
     */
    public function fcpoIsKlarnaPaynow(): bool
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        /** @var Basket $oBasket */
        $oBasket = $oSession->getBasket();
        return ($oBasket->getPaymentId() === 'fcpoklarna_directdebit');
    }

    /**
     * Returns if paypal is active and therefor button can be displayed
     *
     * @return bool
     */
    public function fcpoCanDisplayPayPalExpressV2Button()
    {
        return PaymentHelper::getInstance()->isPaymentMethodActive(PayPal::PPE_V2_EXPRESS);
    }

    /**
     * Returns PayPal V2 Express Button ID
     *
     * @return string
     */
    public function fcpoGetPayPalExpressV2GetButtonId($sPosition)
    {
        $sButtonId = "fcpoPayPalExpressV2";
        #if (PayPal::getInstance()->showBNPLButton() === true) {
        #    $sButtonId .= "PayLater";
        #}
        return $sButtonId.$sPosition;
    }

    /**
     * Returns PayPal V2 Express Button Javascript URL
     *
     * @return string
     */
    public function fcpoGetPayPalExpressV2JavascriptUrl()
    {
        return PayPal::getInstance()->getJavascriptUrl();
    }

    /**
     * Returns PayPal V2 Express Button color
     *
     * @return string
     */
    public function fcpoGetPayPalExpressButtonColor()
    {
        return PayPal::getInstance()->getButtonColor();
    }

    /**
     * Returns PayPal V2 Express Button shape
     *
     * @return string
     */
    public function fcpoGetPayPalExpressButtonShape()
    {
        return PayPal::getInstance()->getButtonShape();
    }

    /**
     * @return string
     */
    public function fcpoGetPayPalExpressSuccessUrl()
    {
        return Redirect::getInstance()->getSuccessUrl(false, 'fcpoHandlePayPalExpressV2');
    }

    /**
     * Method returns active theme path by checking current theme and its parent
     * If theme is not assignable, 'apex' will be the fallback
     *
     * @return string
     */
    public function fcpoGetActiveThemePath(): string
    {
        $sReturn = 'apex';
        $oTheme = $this->_oFcPoHelper->getFactoryObject(Theme::class);

        $sCurrentActiveId = $oTheme->getActiveThemeId();
        $oTheme->load($sCurrentActiveId);
        $aThemeIds = array_keys($this->_aSupportedThemes);
        $sCurrentParentId = $oTheme->getInfo('parentTheme');

        // we're more interested on the parent than on child theme
        if ($sCurrentParentId) {
            $sCurrentActiveId = $sCurrentParentId;
        }

        if (in_array($sCurrentActiveId, $aThemeIds)) {
            $sReturn = $this->_aSupportedThemes[$sCurrentActiveId];
        }

        return $sReturn;
    }

    /**
     * Template getter for returning ajax controller url
     *
     * @return string
     */
    public function fcpoGetAjaxControllerUrl(): string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sShopUrl = $oConfig->getShopUrl();
        $sPath = "index.php?cl=FcPayOneAjax";
        return $sShopUrl . $sPath;
    }

    /**
     * Returns if is given paymentid is of type payone
     *
     * @param $sPaymentId
     * @return bool
     */
    public function fcpoIsPayonePayment($sPaymentId): bool
    {
        return FcPayOnePayment::fcIsPayOnePaymentType($sPaymentId);
    }

    /**
     * Returns MD5 hash of current selected deliveryaddress
     *
     * @return string
     */
    public function fcpoGetDelAddrInfo(): string
    {
        $sAddressId = Registry::getRequest()->getRequestParameter('deladrid');
        if (!$sAddressId) {
            $oSession = $this->_oFcPoHelper->fcpoGetSession();
            $sAddressId = $oSession->getVariable('deladrid');
        }

        $oAddress = $this->_oFcPoHelper->getFactoryObject(Address::class);
        $oAddress->load($sAddressId);
        $sEncodedDeliveryAddress = $oAddress->getEncodedDeliveryAddress();

        return (string)$sEncodedDeliveryAddress;
    }

    /**
     * Returns payment error whether from param or session
     *
     * @return mixed
     */
    public function fcpoGetPaymentError(): mixed
    {
        $iPayError = Registry::getRequest()->getRequestParameter('payerror');

        if (!$iPayError) {
            $oSession = $this->_oFcPoHelper->fcpoGetSession();
            $iPayError = $oSession->getVariable('payerror');
        }

        return $iPayError;
    }

    /**
     * Returns payment error text whether from param or session
     *
     * @return mixed
     */
    public function fcpoGetPaymentErrorText(): mixed
    {
        $sPayErrorText = Registry::getRequest()->getRequestParameter('payerrortext');

        if (!$sPayErrorText) {
            $oSession = $this->_oFcPoHelper->fcpoGetSession();
            $sPayErrorText = $oSession->getVariable('payerrortext');
        }

        return $sPayErrorText;
    }

    /**
     * Returns the url of Apple Pay payment library
     *
     * @return string
     */
    public function fcpoGetApplePayLibraryUrl(): string
    {
        return 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js';
    }

    /**
     * Checks if the saved certificate file exists
     *
     * @return bool
     */
    public function fcpoCertificateExists(): bool
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $certificateFilename = $oConfig->getConfigParam('sFCPOAplCertificate');
        return is_file($this->fcpoGetCertDirPath() . $certificateFilename);
    }

    /**
     * Returns the path to credential directory
     *
     * @return string
     */
    public function fcpoGetCertDirPath(): string
    {
        return $this->getModulePath('fcpayone') . 'cert/';
    }

    /**
     * @param string $sPaylaPartnerId
     * @param string $sPartnerMerchantId
     * @return string
     * @throws Exception
     */
    public function fcpoGetBNPLDeviceToken(string $sPaylaPartnerId, string $sPartnerMerchantId): string
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $sUUIDv4 = $oSession->getId();
        if (empty($sUUIDv4)) {
            $sUUIDv4 = $this->_oFcPoHelper->fcpoGenerateUUIDv4();
            $oSession->setId($sUUIDv4);
        }

        return $sPaylaPartnerId . "_" . $sPartnerMerchantId . "_" . $sUUIDv4;
    }

    /**
     * @param string $sPaymentId
     * @return string
     */
    public function fcpoGetPayoneSecureEnvironment(string $sPaymentId): string
    {
        $sEnvironment = 't'; // test
        if (PaymentHelper::getInstance()->isLiveMode($sPaymentId) === true) {
            $sEnvironment = 'p'; // production/live
        }
        return $sEnvironment;
    }

    /**
     * @return string
     */
    public function fcpoGetMerchantId(): string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sClientId = $oConfig->getConfigParam('sFCPOMerchantID');

        return (string)$sClientId;
    }

}
