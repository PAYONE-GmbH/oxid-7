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

namespace Fatchip\PayOne\Application\Controller\Admin;

use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Application\Model\FcPoPayPal;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;

class FcPayOneMain extends FcPayOneAdminDetails
{

    public $_oFcpoConfigExport;
    public $_oFcpoKlarna;
    public $_oFcpoPayPal;
    public $_oFcpoRatePay;
    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    private $_oFcpoHelper;

    /**
     * Current class template name
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_main';

    /**
     * List of boolean config values
     *
     * @var array
     */
    private $_aConfBools = [];

    /**
     * List of string config values
     */
    private array $_aConfStrs = [];

    /**
     * List of array config values
     *
     * @var array
     */
    private $_aConfArrs = [];

    /**
     * List of countries
     *
     * @var array
     */
    private $_aCountryList = [];

    /**
     * List of config errors encountered
     */
    private ?array $_aConfErrors = null;

    /**
     * Set of default config strings
     *
     * @var array
     */
    private const A_FCPO_DEFAULT_STRING_CONF = ['sFCPOCCType' => 'ajax', 
    'sFCPOCCNumberType' => 'tel', 
    'sFCPOCCNumberCount' => '30', 
    'sFCPOCCNumberMax' => '16', 
    'sFCPOCCNumberIframe' => 'standard', 
    'sFCPOCCNumberWidth' => '202px', 
    'sFCPOCCNumberHeight' => '20px', 
    'sFCPOCCNumberStyle' => 'standard', 
    'sFCPOCCNumberCSS' => '', 
    'sFCPOCCCVCType' => 'tel', 
    'sFCPOCCCVCCount' => '30', 
    'sFCPOCCCVCMax' => '4', 
    'sFCPOCCCVCIframe' => 'standard', 
    'sFCPOCCCVCWidth' => '202px', 
    'sFCPOCCCVCHeight' => '20px', 
    'sFCPOCCCVCStyle' => 'standard', 
    'sFCPOCCCVCCSS' => '', 
    'sFCPOCCMonthType' => 'select', 
    'sFCPOCCMonthCount' => '3', 
    'sFCPOCCMonthMax' => '2', 
    'sFCPOCCMonthIframe' => 'custom', 
    'sFCPOCCMonthWidth' => '50px', 
    'sFCPOCCMonthHeight' => '20px', 
    'sFCPOCCMonthStyle' => 'standard', 
    'sFCPOCCMonthCSS' => '', 
    'sFCPOCCYearType' => 'select', 
    'sFCPOCCYearCount' => '5', 
    'sFCPOCCYearMax' => '4', 
    'sFCPOCCYearIframe' => 'custom', 
    'sFCPOCCYearWidth' => '80px', 
    'sFCPOCCYearHeight' => '20px', 
    'sFCPOCCYearStyle' => 'standard', 
    'sFCPOCCYearCSS' => '', 
    'sFCPOCCIframeWidth' => '202px', 
    'sFCPOCCIframeHeight' => '20px', 
    'sFCPOCCStandardInput' => 'border: 1px solid #8c8989; border-radius: 2px;', 
    'sFCPOCCStandardOutput' => ''];

    /**
     * Configuration for JS CC Preview generation
     *
     * @var array
     */
    private const A_FC_JS_CC_PREVIEW_FIELD_CONFIGS = ['cardpan' => ['selector' => 'cardpan', 
    'type' => 'sFCPOCCNumberType', 
    'size' => 'sFCPOCCNumberCount', 
    'maxlength' => 'sFCPOCCNumberMax', 
    'customstyle' => 'sFCPOCCNumberStyle', 'style' => 'sFCPOCCNumberCSS', 'customiframe' => 'sFCPOCCNumberIframe', 'widht' => 'sFCPOCCNumberWidth', 'height' => 'sFCPOCCNumberHeight'], 'cardcvc2' => ['selector' => 'cardcvc2', 'type' => 'sFCPOCCCVCType', 'size' => 'sFCPOCCCVCCount', 'maxlength' => 'sFCPOCCCVCMax', 'customstyle' => 'sFCPOCCCVCStyle', 'style' => 'sFCPOCCCVCCSS', 'customiframe' => 'sFCPOCCCVCIframe', 'widht' => 'sFCPOCCCVCWidth', 'height' => 'sFCPOCCCVCHeight'], 'cardexpiremonth' => ['selector' => 'cardexpiremonth', 'type' => 'sFCPOCCMonthType', 'size' => 'sFCPOCCMonthCount', 'maxlength' => 'sFCPOCCMonthMax', 'customstyle' => 'sFCPOCCMonthStyle', 'style' => 'sFCPOCCMonthCSS', 'customiframe' => 'sFCPOCCMonthIframe', 'widht' => 'sFCPOCCMonthWidth', 'height' => 'sFCPOCCMonthHeight'], 'cardexpireyear' => ['selector' => 'cardexpireyear', 'type' => 'sFCPOCCYearType', 'size' => 'sFCPOCCYearCount', 'maxlength' => 'sFCPOCCYearMax', 'customstyle' => 'sFCPOCCYearStyle', 'style' => 'sFCPOCCYearCSS', 'customiframe' => 'sFCPOCCYearIframe', 'widht' => 'sFCPOCCYearWidth', 'height' => 'sFCPOCCYearHeight']];

    /**
     * Configuration for JS CC Preview generation
     *
     * @var array
     */
    private const A_FC_JS_CC_PREVIEW_DEFAULT_STYLE = ['input' => 'sFCPOCCStandardInput', 'select' => 'sFCPOCCStandardOutput', 'width' => 'sFCPOCCIframeWidth', 'height' => 'sFCPOCCIframeHeight'];

    /**
     * Collects messages of different types
     */
    private array $_aAdminMessages = [];

    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);

        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sOxid = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sOxid);
        $this->_fcpoLoadCountryList();
    }

    /**
     * Loads configurations of payone and make them accessable
     *
     *
     */
    private function _fcpoLoadConfigs($sShopId): void
    {
        $aConfigs = $this->_oFcpoConfigExport->fcpoGetConfig($sShopId);
        $this->_aConfStrs = $this->_initConfigStrings();
        $this->_aConfBools = $aConfigs['bools'];
        $this->_aConfArrs = $aConfigs['arrs'];
    }

    /**
     * Initialize config strings
     *
     *
     * @return array
     */
    private function _initConfigStrings()
    {
        $aConfStrs = $this->_aConfStrs;
        foreach (self::A_FCPO_DEFAULT_STRING_CONF as $sKey => $sValue) {
            $aConfStrs[$sKey] = $this->_fcpoSetDefault($aConfStrs, $sKey, $sValue);
        }

        return $aConfStrs;
    }

    /**
     * Set default values
     *
     * @return array
     */
    private function _fcpoSetDefault(array $aArray, string $sKey, mixed $mValue)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        if (!isset($aArray[$sKey])) {
            $oConfig->saveShopConfVar("str", $sKey, $mValue);
        }

        return $oConfig->getShopConfVar($sKey);
    }

    /**
     * Loads list of countries
     *
     *
     */
    private function _fcpoLoadCountryList(): void
    {
        // #251A passing country list
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $oCountryList = $this->_oFcpoHelper->getFactoryObject("oxCountryList");
        $oCountryList->loadActiveCountries($oLang->getTplLanguage());

        $blValidCountryData = (
            isset($this->_aConfArrs["aFCPODebitCountries"]) &&
            (is_countable($this->_aConfArrs["aFCPODebitCountries"]) ? count($this->_aConfArrs["aFCPODebitCountries"]) : 0) &&
            (is_countable($oCountryList) ? count($oCountryList) : 0)
        );

        if ($blValidCountryData) {
            foreach ($oCountryList as $sCountryId => $oCountry) {
                if (in_array($oCountry->oxcountry__oxid->value, $this->_aConfArrs["aFCPODebitCountries"])) {
                    $oCountryList[$sCountryId]->selected = "1";
                }
            }
        }

        $this->_aCountryList = $oCountryList;
    }

    /**
     * Loads PAYONE configuration and passes it to Smarty engine, returns
     * name of template file "fcpayone_main.html.twig".
     *
     * @return string
     */
    public function render()
    {
        $sReturn = parent::render();
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        $this->_aViewData['sHelpURL'] = $this->_oFcpoHelper->fcpoGetHelpUrl();

        if ($this->_oFcpoHelper->fcpoGetRequestParameter("aoc")) {
            $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");
            $this->_aViewData["oxid"] = $sOxid;
            $sType = $this->_oFcpoHelper->fcpoGetRequestParameter("type");
            $this->_aViewData["type"] = $sType;

            if (version_compare($oConfig->getVersion(), '4.6.0', '>=')) {
                $oPayOneAjax = oxNew(FcPayOneMainAjax::class);
                $aColumns = $oPayOneAjax->getColumns();
            } else {
                $aColumns = [];
                include_once 'inc/' . strtolower(self::class) . '.inc.php';
            }
            $this->_aViewData['oxajax'] = $aColumns;

            return "fcpayone_popup_main.html.twig";
        }
        return $sReturn;
    }

    /**
     * Template getter that returns an array of available ISO-Codes of currencies
     *
     *
     * @return mixed[]
     */
    public function fcpoGetCurrencyIso(): array
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $aCurrencyArray = $oConfig->getCurrencyArray();
        $aReturn = [];
        foreach ($aCurrencyArray as $oCur) {
            $aReturn[] = $oCur->name;
        }

        return $aReturn;
    }

    /**
     * Template getter for returning template version
     *
     *
     * @return string
     */
    public function fcpoGetModuleVersion()
    {
        return $this->_oFcpoHelper->fcpoGetModuleVersion();
    }

    /**
     * Template getter for boolean config values
     *
     *
     * @return array
     */
    public function fcpoGetConfBools()
    {
        return $this->_aConfBools;
    }

    /**
     * Template getter for string config values
     *
     */
    public function fcpoGetConfStrs(): array
    {
        return $this->_aConfStrs;
    }

    /**
     * Template getter for array config values
     *
     *
     * @return array
     */
    public function fcpoGetConfArrs()
    {
        return $this->_aConfArrs;
    }

    /**
     * Template getter for countrylist
     *
     *
     * @return array
     */
    public function fcpoGetCountryList()
    {
        return $this->_aCountryList;
    }

    /**
     * Saves changed configuration parameters.
     *
     * @return mixed
     */
    public function save(): void
    {
        $blValid = $this->_fcpoValidateData();

        if (!$blValid) {
            return;
        }

        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $aConfBools = $this->_oFcpoHelper->fcpoGetRequestParameter("confbools");
        $aConfStrs = $this->_oFcpoHelper->fcpoGetRequestParameter("confstrs");
        $aConfArrs = $this->_oFcpoHelper->fcpoGetRequestParameter("confarrs");

        if (is_array($aConfBools)) {
            foreach ($aConfBools as $sVarName => $sVarVal) {
                $oConfig->saveShopConfVar("bool", $sVarName, $sVarVal);
            }
        }

        if (is_array($aConfStrs)) {
            foreach ($aConfStrs as $sVarName => $sVarVal) {
                $oConfig->saveShopConfVar("str", $sVarName, $sVarVal);
            }
        }

        if (is_array($aConfArrs)) {
            foreach ($aConfArrs as $sVarName => $aVarVal) {
                // home country multiple selectlist feature
                if (!is_array($aVarVal)) {
                    $aVarVal = $this->_multilineToArray($aVarVal);
                }
                $oConfig->saveShopConfVar("arr", $sVarName, $aVarVal);
            }
        }

        // add storeids, campaigns and logos if set
        $this->_fcpoCheckAndAddStoreId();
        $this->_fcpoCheckAndAddCampaign();
        $this->_fcpoCheckAndAddLogos();

        // fill storeids and campaigns  if set
        $this->_fcpoInsertStoreIds();
        $this->_fcpoInsertCampaigns();

        // add ratepay profiles if set
        $this->_fcpoCheckAndAddRatePayProfile();
        $this->_fcpoInsertProfiles();

        // request and add amazonpay configuration if triggered
        $this->_fcpoCheckRequestAmazonPayConfiguration();

        $this->_handlePayPalExpressLogos();

        //reload config after saving
        $sOxid = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sOxid);
    }

    /**
     * Validation of entered configuration values
     *
     */
    private function _fcpoValidateData(): bool
    {
        return $this->_fcpoValidateAccountData();
    }

    /**
     * Checks accountdata section on errors
     *
     *
     */
    private function _fcpoValidateAccountData(): bool
    {
        return true;
    }

    /**
     * Converts Multiline text to simple array. Returns this array.
     *
     * @param string $sMultiline Multiline text
     *
     * @return array
     */
    private function _multilineToArray($sMultiline)
    {
        $aArr = explode("\n", $sMultiline);

        if (!is_array($aArr)) {
            return;
        }

        foreach ($aArr as $key => $val) {
            $aArr[$key] = trim($val);
            if ($aArr[$key] == "") {
                unset($aArr[$key]);
            }
        }

        return $aArr;
    }

    /**
     * Check and add strore id and set message flag
     *
     *
     */
    private function _fcpoCheckAndAddStoreId(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addStoreId')) {
            $this->_oFcpoKlarna->fcpoAddKlarnaStoreId();
            $this->_aAdminMessages["blStoreIdAdded"] = true;
        }
    }

    /**
     * Check if campaign shall be added. Set flag true in case
     *
     *
     */
    private function _fcpoCheckAndAddCampaign(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addCampaign')) {
            $this->_oFcpoKlarna->fcpoAddKlarnaCampaign();
            $this->_aAdminMessages["blCampaignAdded"] = true;
        }
    }

    /**
     * Check if logo shall be added. Adds it and set flag true in case
     *
     *
     */
    private function _fcpoCheckAndAddLogos(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addPayPalLogo')) {
            $this->_oFcpoPayPal->fcpoAddPaypalExpressLogo();
            $this->_aAdminMessages["blLogoAdded"] = true;
        }
    }

    /**
     * Inserts added storeids
     *
     *
     */
    private function _fcpoInsertStoreIds(): void
    {
        $aStoreIds = $this->_oFcpoHelper->fcpoGetRequestParameter('aStoreIds');
        $this->_oFcpoKlarna->fcpoInsertStoreIds($aStoreIds);
    }

    /**
     * Inserts added campaigns
     *
     *
     */
    private function _fcpoInsertCampaigns(): void
    {
        $aCampaigns = $this->_oFcpoHelper->fcpoGetRequestParameter('aCampaigns');
        $this->_oFcpoKlarna->fcpoInsertCampaigns($aCampaigns);
    }

    /**
     * Check and add a new RatePay Profile
     *
     *
     */
    private function _fcpoCheckAndAddRatePayProfile(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addRatePayProfile')) {
            $this->_oFcpoRatePay->fcpoAddRatePayProfile();
            $this->_aAdminMessages["blRatePayProfileAdded"] = true;
        }
    }

    /**
     * Insert RatePay profile
     *
     *
     */
    private function _fcpoInsertProfiles(): void
    {
        $aRatePayProfiles = $this->_oFcpoHelper->fcpoGetRequestParameter('aRatepayProfiles');
        if (is_array($aRatePayProfiles)) {
            foreach ($aRatePayProfiles as $sOxid => $aRatePayData) {
                $this->_oFcpoRatePay->fcpoInsertProfile($sOxid, $aRatePayData);
            }
        }
    }

    /**
     * Checks if button for fetching configuration settings for amazon from payone api has been triggered
     * Initiates requesting api if true
     *
     *
     */
    private function _fcpoCheckRequestAmazonPayConfiguration(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('getAmazonPayConfiguration')) {
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $blSuccess = $this->_fcpoRequestAndAddAmazonConfig();
            $sMessage = 'FCPO_AMAZONPAY_ERROR_GETTING_CONFIG';
            if ($blSuccess) {
                $this->_aAdminMessages["blAmazonPayConfigFetched"] = true;
                $sMessage = 'FCPO_AMAZONPAY_SUCCESS_GETTING_CONFIG';
            }
            $sTranslatedMessage = $oLang->translateString($sMessage);
            $oUtilsView = $this->_oFcpoHelper->fcpoGetUtilsView();
            $oUtilsView->addErrorToDisplay($sTranslatedMessage, false, true);
        }
    }

    /**
     * Triggers requesting payone api for amazon configuration and returns
     * if succeeded
     *
     *
     * @return bool
     */
    private function _fcpoRequestAndAddAmazonConfig()
    {
        $oFcpoRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oFcpoRequest->sendRequestGetAmazonPayConfiguration();

        return $this->_fcpoSaveAmazonConfigFromResponse($aResponse);
    }

    /**
     * Analyzes response tries to save config and returns if everything succeeded
     *
     * @param $aResponse
     * @return bool
     */
    private function _fcpoSaveAmazonConfigFromResponse($aResponse)
    {
        $sStatus = $aResponse['status'];
        $blReturn = false;
        if ($sStatus == 'OK') {
            $sSellerId = $aResponse['add_paydata[seller_id]'];
            $sClientId = $aResponse['add_paydata[client_id]'];
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $oConfig->saveShopConfVar('str', 'sFCPOAmazonPaySellerId', $sSellerId);
            $oConfig->saveShopConfVar('str', 'sFCPOAmazonPayClientId', $sClientId);
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Handling of paypal express logos
     *
     *
     */
    private function _handlePayPalExpressLogos(): void
    {
        $aLogos = $this->_oFcpoHelper->fcpoGetRequestParameter('logos');

        if (is_array($aLogos) && $aLogos !== []) {
            $this->_oFcpoPayPal->fcpoUpdatePayPalLogos($aLogos);
            $aMessages = $this->_oFcpoPayPal->fcpoGetMessages();
            $this->_aAdminMessages = array_merge($this->_aAdminMessages, $aMessages);
        }
    }

    /**
     * Returns collected errors
     *
     *
     * @return mixed array|false
     */
    public function fcpoGetConfigErrors()
    {
        if (!is_array($this->_aConfErrors)) {
            return false;
        }

        return $this->_aConfErrors;
    }

    /**
     * Template getter for requesting if logo has recently been added
     *
     *
     */
    public function fcpoIsLogoAdded(): bool
    {
        return isset($this->_aAdminMessages["blLogoAdded"]) && $this->_aAdminMessages["blLogoAdded"] === true;
    }

    /**
     * Template getter for requesting if campaign has recently been added
     *
     *
     */
    public function fcpoIsCampaignAdded(): bool
    {
        return (
            isset($this->_aAdminMessages["blCampaignAdded"]) &&
            $this->_aAdminMessages["blCampaignAdded"] === true
        );
    }

    /**
     * Template getter for requesting if campaign has recently been added
     *
     *
     */
    public function fcpoIsStoreIdAdded(): bool
    {
        return isset($this->_aAdminMessages["blStoreIdAdded"]) && $this->_aAdminMessages["blStoreIdAdded"] === true;
    }

    /**
     * Returns configured storeids for klarna payment
     *
     *
     * @return array
     */
    public function fcpoGetStoreIds()
    {
        return $this->_oFcpoKlarna->fcpoGetStoreIds();
    }

    /**
     * Returns configured ratepay profiles
     *
     *
     * @return array
     */
    public function fcpoGetRatePayProfiles()
    {
        return $this->_oFcpoRatePay->fcpoGetRatePayProfiles();
    }

    /**
     * Returns configured klarna campaigns
     *
     *
     * @return array
     */
    public function fcpoKlarnaCampaigns()
    {
        $oPayment = oxNew('oxpayment');
        return $oPayment->fcpoGetKlarnaCampaigns(true);
    }

    /**
     * Return admin template seperator sign by shop-version
     *
     * @return string
     */
    public function fcGetAdminSeperator()
    {
        $iVersion = $this->_oFcpoHelper->fcpoGetIntShopVersion();
        if ($iVersion < 4300) {
            return '?';
        } else {
            return '&';
        }
    }

    /**
     * Generates and delivers an xml export of configuration
     *
     *
     */
    public function export(): void
    {
        $oConfigExport = $this->_oFcpoHelper->getFactoryObject(FcPoConfigExport::class);
        $oConfigExport->fcpoExportConfig();
    }

    /**
     * Returns an array of languages of the shop
     *
     *
     * @return array<int|string, mixed>
     */
    public function fcGetLanguages(): array
    {
        $aReturn = [];
        $oFcLang = $this->_oFcpoHelper->fcpoGetLang();

        foreach ($oFcLang->getLanguageArray() as $oLang) {
            if ($oLang->active == 1) {
                $aReturn[$oLang->oxid] = $oLang->name;
            }
        }
        return $aReturn;
    }

    /**
     * Returns an array of currencies of the shop
     *
     *
     * @return array<int|string, mixed>
     */
    public function fcGetCurrencies(): array
    {
        $aReturn = [];
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        foreach ($oConfig->getCurrencyArray() as $oCurr) {
            $aReturn[$oCurr->name] = $oCurr->name;
        }
        return $aReturn;
    }

    /**
     * Returns list of uploaded paypalexpresslogos
     *
     *
     * @return array
     */
    public function fcpoGetPayPalLogos()
    {
        $oPaypal = $this->_oFcpoHelper->getFactoryObject(FcPoPayPal::class);

        return $oPaypal->fcpoGetPayPalLogos();
    }

    /**
     * Returns fields belonging to creditcard
     *
     *
     */
    public function getCCFields(): array
    {
        return ['Number', 'CVC', 'Month', 'Year'];
    }

    /**
     * Return array of cc types
     *
     * @param string $sField
     * @return array{select?: mixed, tel: mixed, password: mixed, text: mixed}
     */
    public function getCCTypes($sField): array
    {
        $aTypes = [];
        if ($sField == 'Month' || $sField == 'Year') {
            $aTypes['select'] = $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_SELECT');
        }
        $aTypes['tel'] = $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_TYPE_NUMERIC');
        $aTypes['password'] = $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_TYPE_PASSWORD');
        $aTypes['text'] = $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_TYPE_TEXT');

        return $aTypes;
    }

    /**
     * Get available cc styles
     *
     *
     * @return array{standard: mixed, custom: mixed}
     */
    public function getCCStyles(): array
    {
        return ['standard' => $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_IFRAME_STANDARD'), 'custom' => $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_IFRAME_CUSTOM')];
    }

    /**
     * Template getter returns the preview javascript code
     *
     *
     * @return string
     */
    public function fcpoGetJsCardPreviewCode()
    {
        $sJsCode = "";
        $sJsCode .= $this->_fcpoGetJsPreviewCodeHeader();
        $sJsCode .= $this->_fcpoGetJsPreviewCodeFields();
        $sJsCode .= '	},';
        $sJsCode .= $this->_fcpoGetJsPreviewCodeDefaultStyle();
        $sJsCode .= $this->_fcpoGetJsPreviewCodeErrorBlock();
        $sJsCode .= '};';

        return $sJsCode . 'var iframes = new Payone.ClientApi.HostedIFrames(config, request);';
    }

    /**
     * Returns the header part of injected javascript
     *
     *
     * @return string
     */
    private function _fcpoGetJsPreviewCodeHeader()
    {
        $sJsCode = "";
        $sJsCode .= "var request, config;" . "\n";
        $sJsCode .= "config = {" . "\n";

        return $sJsCode . ("\t" . "fields: {" . "\n");
    }

    /**
     * Returns the configured fields
     *
     *
     * @return string
     */
    private function _fcpoGetJsPreviewCodeFields()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sJsCode = "";

        foreach (self::A_FC_JS_CC_PREVIEW_FIELD_CONFIGS as $sFieldGroupIdent => $aCCFieldConfig) {
            $blCustomStyle = $oConfig->getConfigParam($aCCFieldConfig['customstyle']);
            $blCustomIframe = $oConfig->getConfigParam($aCCFieldConfig['customiframe']);
            $sJsCode .= "\t\t" . $sFieldGroupIdent . ": {" . "\n";
            foreach ($aCCFieldConfig as $sVar => $sConfVal) {
                $sValue = $this->_fcGetJsPreviewCodeValue($sVar, $sConfVal, $blCustomStyle, $blCustomIframe);
                if ($sValue !== '' && $sValue !== '0') {
                    $sJsCode .= "\t\t\t" . $sVar . ': "' . $sValue . '",' . "\n";
                }
            }
            $sJsCode .= "\t\t" . "}," . "\n";
        }

        return $sJsCode;
    }

    /**
     * Method returns config value of a given config name or false if not existing
     *
     * @param string $sParam
     * @return mixed
     */
    public function getConfigParam($sParam)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        return $oConfig->getConfigParam($sParam);
    }

    /**
     * Method returns the matching value no matter if its a config value or direct
     *
     * @param bool   $blCustomStyle
     * @param bool   $blCustomIframe
     * @return string
     */
    private function _fcGetJsPreviewCodeValue(string $sVar, string $sConfVal, $blCustomStyle, $blCustomIframe)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sReturn = "";

        $blCustomStyleVar = ($sVar == 'style');
        $blCustomIframeVar = ($sVar == 'width' || $sVar == 'height');
        $blNoCustomVar = (!$blCustomStyleVar && !$blCustomIframeVar);

        if ($sVar == 'selector') {
            $sReturn = $sConfVal;
        } elseif ($blCustomStyleVar && $blCustomStyle) {
            $sReturn = $oConfig->getConfigParam($sConfVal);
        } elseif ($blCustomIframeVar && $blCustomIframe) {
            $sReturn = $oConfig->getConfigParam($sConfVal);
        } elseif ($blNoCustomVar) {
            $sReturn = $oConfig->getConfigParam($sConfVal);
        }

        return $sReturn;
    }

    /**
     * Returns default style javascript block
     *
     *
     * @return string
     */
    private function _fcpoGetJsPreviewCodeDefaultStyle()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sJsCode = "\t" . 'defaultStyle: {' . "\n";
        $sJsCode .= "\t\t" . 'input: "' . $oConfig->getConfigParam(self::A_FC_JS_CC_PREVIEW_DEFAULT_STYLE['input']) . '",' . "\n";
        $sJsCode .= "\t\t" . 'select: "' . $oConfig->getConfigParam(self::A_FC_JS_CC_PREVIEW_DEFAULT_STYLE['select']) . '",' . "\n";
        $sJsCode .= "\t\t" . 'iframe: {' . "\n";
        $sJsCode .= "\t\t\t" . 'width: "' . $oConfig->getConfigParam(self::A_FC_JS_CC_PREVIEW_DEFAULT_STYLE['width']) . '",' . "\n";
        $sJsCode .= "\t\t\t" . 'height: "' . $oConfig->getConfigParam(self::A_FC_JS_CC_PREVIEW_DEFAULT_STYLE['height']) . '",' . "\n";
        $sJsCode .= "\t\t" . '}' . "\n";

        return $sJsCode . ("\t" . '},' . "\n");
    }

    /**
     * Getter which delivers the error block part
     *
     *
     * @return string
     */
    private function _fcpoGetJsPreviewCodeErrorBlock()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sJsCode = "";
        $blFCPOCCErrorsActive = $oConfig->getConfigParam('blFCPOCCErrorsActive');
        $sFCPOCCErrorsLang = $oConfig->getConfigParam('sFCPOCCErrorsLang');
        $sLangConcat = ($sFCPOCCErrorsLang == 'de') ? 'de' : 'en';

        if ($blFCPOCCErrorsActive) {
            $sJsCode .= "\t\t" . 'error: "errorOutput",' . "\n";
            $sJsCode .= "\t\t\t" . 'language: language: Payone.ClientApi.Language.' . $sLangConcat . "\n";
        }

        return $sJsCode;
    }

    /**
     * Returns a list of deliverysets for template select
     *
     *
     * @return array
     */
    public function fcpoGetDeliverySets()
    {
        $oDeliveryAdminList =
            $this->_oFcpoHelper->getFactoryObject('DeliverySet_List');
        $oList = $oDeliveryAdminList->getItemList();
        return $oList->getArray();
    }

    /**
     * Adding a detected configuration error
     *
     * @param $sTranslationString
     */
    private function _fcpoAddConfigError($sTranslationString): void
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sMessage = $oLang->translateString($sTranslationString);

        if (!is_array($this->_aConfErrors)) {
            $this->_aConfErrors = [];
        }
        $this->_aConfErrors[] = $sMessage;
    }

    /**
     * Method returns the checksum result
     *
     *
     * @return string
     */
    private function _fcpoGetCheckSumResult()
    {
        $sIncludePath = getShopBasePath() . 'modules/fcPayOne/fcCheckChecksum.php';
        $oScript = $this->_oFcpoHelper->fcpoGetInstance('fcCheckChecksum', $sIncludePath);

        return $oScript->checkChecksumXml();
    }
}
