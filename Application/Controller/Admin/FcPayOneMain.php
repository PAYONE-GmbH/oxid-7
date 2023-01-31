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
 * @link      http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version   OXID eShop CE
 */

namespace Fatchip\PayOne\Application\Controller\Admin;

use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Application\Model\FcPoPayPal;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\Payment;

class FcPayOneMain extends FcPayOneAdminDetails
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper = null;

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
    protected $_aConfBools = [];

    /**
     * List of string config values
     *
     * @var array
     */
    protected $_aConfStrs = [];

    /**
     * List of array config values
     *
     * @var array
     */
    protected $_aConfArrs = [];

    /**
     * List of countries
     *
     * @var array
     */
    protected $_aCountryList = [];

    /**
     * List of config errors encountered
     * @var array
     */
    protected $_aConfErrors = null;

    /**
     * Set of default config strings
     *
     * @var array
     */
    protected $_aFcpoDefaultStringConf = [
        'sFCPOCCType' => 'ajax',
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
        'sFCPOCCStandardOutput' => '',
    ];

    /**
     * Configuration for JS CC Preview generation
     *
     * @var array
     */
    protected $_aFcJsCCPreviewFieldConfigs = [
        'cardpan' => [
            'selector' => 'cardpan',
            'type' => 'sFCPOCCNumberType',
            'size' => 'sFCPOCCNumberCount',
            'maxlength' => 'sFCPOCCNumberMax',
            'customstyle' => 'sFCPOCCNumberStyle',
            'style' => 'sFCPOCCNumberCSS',
            'customiframe' => 'sFCPOCCNumberIframe',
            'widht' => 'sFCPOCCNumberWidth',
            'height' => 'sFCPOCCNumberHeight',
        ],
        'cardcvc2' => [
            'selector' => 'cardcvc2',
            'type' => 'sFCPOCCCVCType',
            'size' => 'sFCPOCCCVCCount',
            'maxlength' => 'sFCPOCCCVCMax',
            'customstyle' => 'sFCPOCCCVCStyle',
            'style' => 'sFCPOCCCVCCSS',
            'customiframe' => 'sFCPOCCCVCIframe',
            'widht' => 'sFCPOCCCVCWidth',
            'height' => 'sFCPOCCCVCHeight',
        ],
        'cardexpiremonth' => [
            'selector' => 'cardexpiremonth',
            'type' => 'sFCPOCCMonthType',
            'size' => 'sFCPOCCMonthCount',
            'maxlength' => 'sFCPOCCMonthMax',
            'customstyle' => 'sFCPOCCMonthStyle',
            'style' => 'sFCPOCCMonthCSS',
            'customiframe' => 'sFCPOCCMonthIframe',
            'widht' => 'sFCPOCCMonthWidth',
            'height' => 'sFCPOCCMonthHeight',
        ],
        'cardexpireyear' => [
            'selector' => 'cardexpireyear',
            'type' => 'sFCPOCCYearType',
            'size' => 'sFCPOCCYearCount',
            'maxlength' => 'sFCPOCCYearMax',
            'customstyle' => 'sFCPOCCYearStyle',
            'style' => 'sFCPOCCYearCSS',
            'customiframe' => 'sFCPOCCYearIframe',
            'widht' => 'sFCPOCCYearWidth',
            'height' => 'sFCPOCCYearHeight',
        ],
    ];

    /**
     * Configuration for JS CC Preview generation
     *
     * @var array
     */
    protected $_aFcJsCCPreviewDefaultStyle = [
        'input' => 'sFCPOCCStandardInput',
        'select' => 'sFCPOCCStandardOutput',
        'width' => 'sFCPOCCIframeWidth',
        'height' => 'sFCPOCCIframeHeight',
    ];

    /**
     * Collects messages of different types
     *
     * @var array
     */
    protected $_aAdminMessages = [];

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
     * Loads PAYONE configuration and passes it to Smarty engine, returns
     * name of template file "fcpayone_main.tpl".
     *
     * @return string
     */
    public function render(): string
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
                include_once 'inc/' . strtolower(__CLASS__) . '.inc.php';
            }
            $this->_aViewData['oxajax'] = $aColumns;

            return "fcpayone_popup_main.tpl";
        }
        return $sReturn;
    }

    /**
     * Template getter that returns an array of available ISO-Codes of currencies
     *
     * @return array
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
     * @return string
     */
    public function fcpoGetModuleVersion()
    {
        return $this->_oFcpoHelper->fcpoGetModuleVersion();
    }

    /**
     * Template getter for boolean config values
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
     * @return array
     */
    public function fcpoGetConfStrs()
    {
        return $this->_aConfStrs;
    }

    /**
     * Template getter for array config values
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
    public function save()
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
     * Returns collected errors
     *
     * @return mixed array|false
     */
    public function fcpoGetConfigErrors(): mixed
    {
        if (!is_array($this->_aConfErrors)) {
            return false;
        }

        return $this->_aConfErrors;
    }

    /**
     * Validation of entered configuration values
     *
     * @return bool
     */
    protected function _fcpoValidateData(): bool
    {
        $blValidAccountData = $this->_fcpoValidateAccountData();

        return (
            $blValidAccountData
        );
    }

    /**
     * Checks accountdata section on errors
     *
     * @return bool
     */
    protected function _fcpoValidateAccountData(): bool
    {
        return true;
    }

    /**
     * Adding a detected configuration error
     *
     * @param $sTranslationString
     * @return void
     */
    protected function _fcpoAddConfigError($sTranslationString): void
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sMessage = $oLang->translateString($sTranslationString);

        if (!is_array($this->_aConfErrors)) {
            $this->_aConfErrors = [];
        }
        $this->_aConfErrors[] = $sMessage;
    }

    /**
     * Loads list of countries
     *
     * @return void
     */
    protected function _fcpoLoadCountryList(): void
    {
        // #251A passing country list
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $oCountryList = $this->_oFcpoHelper->getFactoryObject("oxCountryList");
        $oCountryList->loadActiveCountries($oLang->getTplLanguage());

        $blValidCountryData = (
            isset($this->_aConfArrs["aFCPODebitCountries"]) &&
                count($this->_aConfArrs["aFCPODebitCountries"]) &&
                count($oCountryList)
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
     * Loads configurations of payone and make them accessable
     *
     * @return void
     */
    protected function _fcpoLoadConfigs($sShopId): void
    {
        $aConfigs = $this->_oFcpoConfigExport->fcpoGetConfig($sShopId);
        $this->_aConfStrs = $aConfigs['strs'];
        $this->_aConfStrs = $this->_initConfigStrings();
        $this->_aConfBools = $aConfigs['bools'];
        $this->_aConfArrs = $aConfigs['arrs'];
    }

    /**
     * Inserts added campaigns
     *
     * @return void
     */
    protected function _fcpoInsertCampaigns(): void
    {
        $aCampaigns = $this->_oFcpoHelper->fcpoGetRequestParameter('aCampaigns');
        $this->_oFcpoKlarna->fcpoInsertCampaigns($aCampaigns);
    }

    /**
     * Inserts added storeids
     *
     * @return void
     */
    protected function _fcpoInsertStoreIds(): void
    {
        $aStoreIds = $this->_oFcpoHelper->fcpoGetRequestParameter('aStoreIds');
        $this->_oFcpoKlarna->fcpoInsertStoreIds($aStoreIds);
    }

    /**
     * Insert RatePay profile
     *
     *  @return void
     */
    protected function _fcpoInsertProfiles(): void
    {
        $aRatePayProfiles = $this->_oFcpoHelper->fcpoGetRequestParameter('aRatepayProfiles');
        if (is_array($aRatePayProfiles)) {
            foreach ($aRatePayProfiles as $sOxid=>$aRatePayData) {
                $this->_oFcpoRatePay->fcpoInsertProfile($sOxid, $aRatePayData);
            }
        }
    }

    /**
     * Check and add strore id and set message flag
     *
     * @return void
     */
    protected function _fcpoCheckAndAddStoreId(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addStoreId')) {
            $this->_oFcpoKlarna->fcpoAddKlarnaStoreId();
            $this->_aAdminMessages["blStoreIdAdded"] = true;
        }
    }

    /**
     * Check and add a new RatePay Profile
     *
     * @return void
     */
    protected function _fcpoCheckAndAddRatePayProfile(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addRatePayProfile')) {
            $this->_oFcpoRatePay->fcpoAddRatePayProfile();
            $this->_aAdminMessages["blRatePayProfileAdded"] = true;
        }
    }

    /**
     * Checks if button for fetching configuration settings for amazon from payone api has been triggered
     * Initiates requesting api if true
     *
     * @return void
     */
    protected function _fcpoCheckRequestAmazonPayConfiguration(): void
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
     * @return bool
     */
    protected function _fcpoRequestAndAddAmazonConfig(): bool
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
    protected function _fcpoSaveAmazonConfigFromResponse($aResponse): bool
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
     * Check if campaign shall be added. Set flag true in case
     *
     * @return void
     */
    protected function _fcpoCheckAndAddCampaign(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addCampaign')) {
            $this->_oFcpoKlarna->fcpoAddKlarnaCampaign();
            $this->_aAdminMessages["blCampaignAdded"] = true;
        }
    }

    /**
     * Check if logo shall be added. Adds it and set flag true in case
     *
     * @return void
     */
    protected function _fcpoCheckAndAddLogos(): void
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addPayPalLogo')) {
            $this->_oFcpoPayPal->fcpoAddPaypalExpressLogo();
            $this->_aAdminMessages["blLogoAdded"] = true;
        }
    }

    /**
     * Handling of paypal express logos
     *
     * @return void
     */
    protected function _handlePayPalExpressLogos(): void
    {
        $aLogos = $this->_oFcpoHelper->fcpoGetRequestParameter('logos');

        if (is_array($aLogos) && count($aLogos) > 0) {
            $this->_oFcpoPayPal->fcpoUpdatePayPalLogos($aLogos);
            $aMessages = $this->_oFcpoPayPal->fcpoGetMessages();
            $this->_aAdminMessages = array_merge($this->_aAdminMessages, $aMessages);
        }
    }

    /**
     * Template getter for requesting if logo has recently been added
     *
     * @return bool
     */
    public function fcpoIsLogoAdded(): bool
    {
        return isset($this->_aAdminMessages["blLogoAdded"]) && $this->_aAdminMessages["blLogoAdded"] === true;
    }

    /**
     * Template getter for requesting if campaign has recently been added
     *
     * @return bool
     */
    public function fcpoIsCampaignAdded(): bool
    {
        return  (
            isset($this->_aAdminMessages["blCampaignAdded"]) &&
            $this->_aAdminMessages["blCampaignAdded"] === true
        );
    }

    /**
     * Template getter for requesting if campaign has recently been added
     *
     * @return bool
     */
    public function fcpoIsStoreIdAdded(): bool
    {
        return isset($this->_aAdminMessages["blStoreIdAdded"]) && $this->_aAdminMessages["blStoreIdAdded"] === true;
    }

    /**
     * Returns configured storeids for klarna payment
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
     * @return array
     */
    public function fcpoGetRatePayProfiles()
    {
        return $this->_oFcpoRatePay->fcpoGetRatePayProfiles();
    }

    /**
     * Returns configured klarna campaigns
     *
     * @return array
     */
    public function fcpoKlarnaCampaigns()
    {
        $oPayment = oxNew(Payment::class);
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
     * Method returns the checksum result
     *
     * @return string
     */
    protected function _fcpoGetCheckSumResult(): string
    {
        $sIncludePath = getShopBasePath() . 'modules/fcPayOne/fcCheckChecksum.php';
        $oScript = $this->_oFcpoHelper->fcpoGetInstance('fcCheckChecksum', $sIncludePath);

        return $oScript->checkChecksumXml();
    }

    /**
     * Generates and delivers an xml export of configuration
     *
     * @return void
     */
    public function export(): void
    {
        $oConfigExport = $this->_oFcpoHelper->getFactoryObject(FcPoConfigExport::class);
        $oConfigExport->fcpoExportConfig();
    }

    /**
     * Returns an array of languages of the shop
     *
     * @return array
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
     * @return array
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
     * @return array
     */
    public function fcpoGetPayPalLogos(): array
    {
        $oPaypal = $this->_oFcpoHelper->getFactoryObject(FcPoPayPal::class);
        return $oPaypal->fcpoGetPayPalLogos();
    }

    /**
     * Returns fields belonging to creditcard
     *
     * @return array
     */
    public function getCCFields(): array
    {
        return [
            'Number',
            'CVC',
            'Month',
            'Year',
        ];
    }

    /**
     * Return array of cc types
     *
     * @param string $sField
     * @return array
     */
    public function getCCTypes(string $sField): array
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
     * @return array
     */
    public function getCCStyles(): array
    {
        return [
            'standard' => $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_IFRAME_STANDARD'),
            'custom' => $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_IFRAME_CUSTOM'),
        ];
    }

    /**
     * Method returns config value of a given config name or false if not existing
     *
     * @param string $sParam
     * @return mixed
     */
    public function getConfigParam(string $sParam)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam($sParam);
    }

    /**
     * Template getter returns the preview javascript code
     *
     * @return string
     */
    public function fcpoGetJsCardPreviewCode(): string
    {
        $sJsCode = $this->_fcpoGetJsPreviewCodeHeader();
        $sJsCode .= $this->_fcpoGetJsPreviewCodeFields();
        $sJsCode .= "\t" . "},";
        $sJsCode .= $this->_fcpoGetJsPreviewCodeDefaultStyle();
        $sJsCode .= $this->_fcpoGetJsPreviewCodeErrorBlock();
        $sJsCode .= '};';
        $sJsCode .= 'var iframes = new Payone.ClientApi.HostedIFrames(config, request);';

        return $sJsCode;
    }

    /**
     * Returns a list of deliverysets for template select
     *
     * @return array
     */
    public function fcpoGetDeliverySets(): array
    {
        $oDeliveryAdminList =
            $this->_oFcpoHelper->getFactoryObject('DeliverySet_List');
        $oList = $oDeliveryAdminList->getItemList();
        return $oList->getArray();
    }

    /**
     * Getter which delivers the error block part
     *
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeErrorBlock(): string
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
     * Returns default style javascript block
     *
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeDefaultStyle(): string
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sJsCode = "\t" . 'defaultStyle: {' . "\n";
        $sJsCode .= "\t\t" . 'input: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['input']) . '",' . "\n";
        $sJsCode .= "\t\t" . 'select: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['select']) . '",' . "\n";
        $sJsCode .= "\t\t" . 'iframe: {' . "\n";
        $sJsCode .= "\t\t\t" . 'width: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['width']) . '",' . "\n";
        $sJsCode .= "\t\t\t" . 'height: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['height']) . '",' . "\n";
        $sJsCode .= "\t\t" . '}' . "\n";
        $sJsCode .= "\t" . '},' . "\n";

        return $sJsCode;
    }

    /**
     * Returns the configured fields
     *
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeFields(): string
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sJsCode = "";

        foreach ($this->_aFcJsCCPreviewFieldConfigs as $sFieldGroupIdent => $aCCFieldConfig) {
            $blCustomStyle = $oConfig->getConfigParam($aCCFieldConfig['customstyle']);
            $blCustomIframe = $oConfig->getConfigParam($aCCFieldConfig['customiframe']);
            $sJsCode .= "\t\t" . $sFieldGroupIdent . ": {" . "\n";
            foreach ($aCCFieldConfig as $sVar => $sConfVal) {
                $sValue = $this->_fcGetJsPreviewCodeValue($sVar, $sConfVal, $blCustomStyle, $blCustomIframe);
                if ($sValue) {
                    $sJsCode .= "\t\t\t" . $sVar . ': "' . $sValue . '",' . "\n";
                }
            }
            $sJsCode .= "\t\t" . "}," . "\n";
        }

        return $sJsCode;
    }

    /**
     * Method returns the matching value no matter if its a config value or direct
     *
     * @param string $sVar
     * @param string $sConfVal
     * @param bool   $blCustomStyle
     * @param bool   $blCustomIframe
     * @return string
     */
    protected function _fcGetJsPreviewCodeValue(string $sVar, string $sConfVal, bool $blCustomStyle, bool $blCustomIframe): string
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
     * Returns the header part of injected javascript
     *
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeHeader(): string
    {
        $sJsCode = "var request, config;" . "\n";
        $sJsCode .= "config = {" . "\n";
        $sJsCode .= "\t" . "fields: {" . "\n";

        return $sJsCode;
    }

    /**
     * Set default values
     *
     * @param array  $aArray
     * @param string $sKey
     * @param mixed  $mValue
     * @return bool|object
     */
    protected function _fcpoSetDefault(array $aArray, string $sKey, mixed $mValue): object|bool
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        if (!isset($aArray[$sKey])) {
            $oConfig->saveShopConfVar("str", $sKey, $mValue);
        }

        return $oConfig->getShopConfVar($sKey);
    }

    /**
     * Initialize config strings
     *
     * @return array
     */
    protected function _initConfigStrings(): array
    {
        $aConfStrs = $this->_aConfStrs;
        foreach ($this->_aFcpoDefaultStringConf as $sKey => $sValue) {
            $aConfStrs[$sKey] = $this->_fcpoSetDefault($aConfStrs, $sKey, $sValue);
        }

        return $aConfStrs;
    }

    /**
     * Converts Multiline text to simple array. Returns this array.
     *
     * @param string $sMultiline Multiline text
     *
     * @return array|null
     */
    protected function _multilineToArray(string $sMultiline): array|null
    {
        $aArr = explode("\n", $sMultiline);

        if (!is_array($aArr)) {
            return '';
        }

        foreach ($aArr as $key => $val) {
            $aArr[$key] = trim($val);
            if ($aArr[$key] == "") {
                unset($aArr[$key]);
            }
        }

        return $aArr;
    }
}
