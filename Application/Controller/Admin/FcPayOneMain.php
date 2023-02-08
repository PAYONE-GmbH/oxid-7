<?php

namespace Fatchip\PayOne\Application\Controller\Admin;


use Exception;
use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Application\Model\FcPoPaypal;
use Fatchip\PayOne\FcCheckChecksum;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\CountryList;
use OxidEsales\Eshop\Application\Model\DeliverySetList;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;

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
class FcPayOneMain extends FcPayOneAdminDetails
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoHelper = null;

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
     * List of credit cards
     *
     * @var array
     */
    protected $_aAplCreditCardsList = [];

    /**
     * List of config errors encountered
     *
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
     * @throws \JsonException
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sOxid = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sOxid);
        $this->_fcpoLoadCountryList();
        $this->_fcpoLoadAplCreditCardsList();
    }

    /**
     * Loads configurations of payone and make them accessable
     *
     * @return void
     * @throws DatabaseConnectionException
     */
    protected function _fcpoLoadConfigs($sShopId): void
    {
        $aConfigs = $this->_oFcPoConfigExport->fcpoGetConfig($sShopId);

        $this->_aConfStrs = $aConfigs['strs'];
        $this->_aConfStrs = $this->_initConfigStrings();
        $this->_aConfBools = $aConfigs['bools'];
        $this->_aConfArrs = $aConfigs['arrs'];
    }

    /**
     * Initialize config strings
     *
     * @return array
     */
    protected function _initConfigStrings()
    {
        $aConfStrs = $this->_aConfStrs;
        foreach ($this->_aFcpoDefaultStringConf as $sKey => $sValue) {
            $aConfStrs[$sKey] = $this->_fcpoSetDefault($aConfStrs, $sKey, $sValue);
        }
        return $aConfStrs;
    }

    /**
     * Set default values
     *
     * @param array  $aArray
     * @param string $sKey
     * @return array
     */
    protected function _fcpoSetDefault($aArray, $sKey, mixed $mValue)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        if (!isset($aArray[$sKey])) {
            $oConfig->saveShopConfVar("str", $sKey, $mValue);
        }

        return $oConfig->getShopConfVar($sKey);
    }

    /**
     * Loads list of countries
     *
     * @return void
     */
    protected function _fcpoLoadCountryList()
    {
        // #251A passing country list
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $oCountryList = $this->_oFcPoHelper->getFactoryObject(CountryList::class);
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
     * Loads list of supported creditcards for Apple Pay
     *
     */
    protected function _fcpoLoadAplCreditCardsList()
    {
        $this->_aAplCreditCardsList = [
            'V' => json_decode(json_encode([
                'name' => 'Visa',
                'selected' => $this->_aConfArrs["aFCPOAplCreditCards"] && in_array('V', $this->_aConfArrs["aFCPOAplCreditCards"]) ? 1 : 0
            ], JSON_THROW_ON_ERROR), null, 512, JSON_THROW_ON_ERROR),
            'M' => json_decode(json_encode([
                'name' => 'Mastercard',
                'selected' => $this->_aConfArrs["aFCPOAplCreditCards"] && in_array('M', $this->_aConfArrs["aFCPOAplCreditCards"]) ? 1 : 0
            ], JSON_THROW_ON_ERROR), null, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Loads PAYONE configuration and passes it to Smarty engine, returns
     * name of template file "fcpayone_main.tpl".
     *
     * @return string
     */
    public function render()
    {
        $sReturn = parent::render();

        $this->_aViewData['sHelpURL'] = $this->_oFcPoHelper->fcpoGetHelpUrl();

        if ($this->_oFcPoHelper->fcpoGetRequestParameter("aoc")) {
            $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
            $this->_aViewData["oxid"] = $sOxid;
            $sType = $this->_oFcPoHelper->fcpoGetRequestParameter("type");
            $this->_aViewData["type"] = $sType;

            $oPayOneAjax = oxNew(FcPayOneMainAjax::class);
            $aColumns = $oPayOneAjax->getColumns();
            $this->_aViewData['oxajax'] = $aColumns;

            return '@fcpayone/admin/popups/fcpayone_popup_main';
        }
        return $sReturn;
    }

    /**
     * Template getter that returns an array of available ISO-Codes of currencies
     */
    public function fcpoGetCurrencyIso(): array
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $aCurrencyArray = $oConfig->getCurrencyArray();
        $aReturn = array();
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
        return $this->_oFcPoHelper->fcpoGetModuleVersion();
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
     * Template getter for apple pay credit card list
     *
     * @return array
     */
    public function fcpoGetAplCreditCards()
    {
        return $this->_aAplCreditCardsList;
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

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $aConfBools = $this->_oFcPoHelper->fcpoGetRequestParameter("confbools");
        $aConfStrs = $this->_oFcPoHelper->fcpoGetRequestParameter("confstrs");
        $aConfArrs = $this->_oFcPoHelper->fcpoGetRequestParameter("confarrs");

        print_r($aConfArrs);

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
        $this->_fcpoCheckAndAddLogos();

        // add ratepay profiles if set
        $this->_fcpoCheckAndAddRatePayProfile();
        $this->_fcpoInsertProfiles();

        // request and add amazonpay configuration if triggered
        $this->_fcpoCheckRequestAmazonPayConfiguration();

        $this->_handlePayPalExpressLogos();

        $this->handleApplePayCredentials(
            $aConfStrs['sFCPOAplCertificate'],
            $aConfStrs['sFCPOAplKey']
        );

        //reload config after saving
        $sOxid = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sOxid);
    }

    /**
     * Validation of entered configuration values
     *
     * @return bool
     */
    protected function _fcpoValidateData()
    {
        return $this->_fcpoValidateAccountData();
    }

    /**
     * Checks accountdata section on errors
     */
    protected function _fcpoValidateAccountData(): bool
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
    protected function _multilineToArray($sMultiline)
    {
        $aArr = explode("\n", $sMultiline);

        if (!is_array($aArr)) {
            return [$sMultiline];
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
     * Check if logo shall be added. Adds it and set flag true in case
     *
     * @return void
     */
    protected function _fcpoCheckAndAddLogos()
    {
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('addPayPalLogo')) {
            $this->_oFcPoPayPal->fcpoAddPaypalExpressLogo();
            $this->_aAdminMessages["blLogoAdded"] = true;
        }
    }

    /**
     * Check and add a new Ratepay Profile
     *
     * @return void
     */
    protected function _fcpoCheckAndAddRatePayProfile()
    {
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('addRatePayProfile')) {
            $this->_oFcPoRatePay->fcpoAddRatePayProfile();
            $this->_aAdminMessages["blRatePayProfileAdded"] = true;
        }
    }

    /**
     * Insert Ratepay profile
     *
     * @return void
     */
    protected function _fcpoInsertProfiles()
    {
        $aRatePayProfiles = $this->_oFcPoHelper->fcpoGetRequestParameter('aRatepayProfiles');
        if (is_array($aRatePayProfiles)) {
            foreach ($aRatePayProfiles as $sOxid => $aRatePayData) {
                $this->_oFcPoRatePay->fcpoInsertProfile($sOxid, $aRatePayData);
            }
        }
    }

    /**
     * Checks if button for fetching configuration settings for amazon from payone api has been triggered
     * Initiates requesting api if true
     *
     * @return void
     */
    protected function _fcpoCheckRequestAmazonPayConfiguration()
    {
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('getAmazonPayConfiguration')) {
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $blSuccess = $this->_fcpoRequestAndAddAmazonConfig();
            $sMessage = 'FCPO_AMAZONPAY_ERROR_GETTING_CONFIG';
            if ($blSuccess) {
                $this->_aAdminMessages["blAmazonPayConfigFetched"] = true;
                $sMessage = 'FCPO_AMAZONPAY_SUCCESS_GETTING_CONFIG';
            }
            $sTranslatedMessage = $oLang->translateString($sMessage);
            $oUtilsView = $this->_oFcPoHelper->fcpoGetUtilsView();
            $oUtilsView->addErrorToDisplay($sTranslatedMessage, false, true);
        }
    }

    /**
     * Triggers requesting payone api for amazon configuration and returns
     * if succeeded
     *
     * @return bool
     */
    protected function _fcpoRequestAndAddAmazonConfig()
    {
        $oFcpoRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oFcpoRequest->sendRequestGetAmazonPayConfiguration();

        return $this->_fcpoSaveAmazonConfigFromResponse($aResponse);
    }

    /**
     * Analyzes response tries to save config and returns if everything succeeded
     *
     * @param $aResponse
     * @return bool
     */
    protected function _fcpoSaveAmazonConfigFromResponse($aResponse)
    {
        $sStatus = $aResponse['status'];
        $blReturn = false;
        if ($sStatus == 'OK') {
            $sSellerId = $aResponse['add_paydata[seller_id]'];
            $sClientId = $aResponse['add_paydata[client_id]'];
            $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
            $oConfig->saveShopConfVar('str', 'sFCPOAmazonPaySellerId', $sSellerId);
            $oConfig->saveShopConfVar('str', 'sFCPOAmazonPayClientId', $sClientId);
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Handling of paypal express logos
     *
     * @return void
     */
    protected function _handlePayPalExpressLogos()
    {
        $aLogos = $this->_oFcPoHelper->fcpoGetRequestParameter('logos');

        if (is_array($aLogos) && $aLogos !== []) {
            $this->_oFcPoPayPal->fcpoUpdatePayPalLogos($aLogos);
            $aMessages = $this->_oFcPoPayPal->fcpoGetMessages();
            $this->_aAdminMessages = array_merge($this->_aAdminMessages, $aMessages);
        }
    }

    /**
     * Handles the save of credential files/text for Apple Pay configuration
     *
     * @param string $sCertFilename
     * @param string $sKeyFileName
     */
    public function handleApplePayCredentials($sCertFilename, $sKeyFileName): void
    {
        $aFiles = $this->_oFcPoHelper->fcpoGetFiles();
        $sCertDir = getShopBasePath() . 'modules/fc/fcpayone/cert/';

        foreach ($aFiles as $sInputName => $aFile) {
            if (!in_array($sInputName, ['fcpoAplCertificateFile', 'fcpoAplKeyFile'])) {
                continue;
            }

            if ($sInputName == 'fcpoAplCertificateFile') {
                $this->saveApplePayFile($aFile, $sCertFilename, $sCertDir);
            }

            if ($sInputName == 'fcpoAplKeyFile') {
                $this->saveApplePayFile($aFile, $sKeyFileName, $sCertDir);
            }
        }

        if (!isset($aFiles['fcpoAplKeyFile']) || empty($aFiles['fcpoAplKeyFile']['name'])) {
            $sKeyText = $this->_oFcPoHelper->fcpoGetRequestParameter('fcpoAplKeyText');
            if (!empty($sKeyText)) {
                $this->saveApplePayTextKey($sKeyText, $sKeyFileName, $sCertDir);
            }
        }
    }

    /**
     * Saves the file, adjusting the filename if necessary
     *
     * @param array  $aFileData
     * @param string $sPostedFilename
     * @param string $sCertDir
     */
    protected function saveApplePayFile($aFileData, $sPostedFilename, $sCertDir)
    {
        if (!empty($aFileData['name'] && $aFileData['size'] > 0)) {
            $sFilename = $aFileData['name'];
            if (!empty($sPostedFilename)) {
                $sFilename = $sPostedFilename;
            }

            $this->saveFile(
                $sFilename,
                $aFileData['tmp_name'],
                $sCertDir
            );
        }
    }

    /**
     * Moves a file to a destination from the temporary storage after upload
     *
     * @param string $filename
     * @param string $tempFilePath
     * @param string $destinationPath
     * @return bool
     * @throws Exception
     */
    private function saveFile($filename, $tempFilePath, $destinationPath)
    {
        try {
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0700);
            }

            move_uploaded_file($tempFilePath, $destinationPath . $filename);
            chmod($destinationPath . $filename, 0644);

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Saves the Apple Pay key text in a proper file, and store its name in configuration
     *
     * @param string $sKeyContent
     * @param string $sPostedFilename
     * @param string $sCertDir
     */
    protected function saveApplePayTextKey($sKeyContent, $sPostedFilename, $sCertDir)
    {
        if (!empty($sKeyContent)) {
            $filename = 'merchant_id.key';
            if (!empty($sPostedFilename)) {
                $filename = $sPostedFilename;
            }

            $blResult = $this->writeFile($filename, $sKeyContent, $sCertDir);

            if ($blResult) {
                $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
                $oConfig->saveShopConfVar("str", 'sFCPOAplKey', $filename);
            }
        }
    }

    /**
     * Writes a content to a named destination file
     *
     * @param string $filename
     * @param string $content
     * @param string $destinationPath
     * @return bool
     * @throws Exception
     */
    private function writeFile($filename, $content, $destinationPath)
    {
        try {
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0700);
            }

            if (!is_file($destinationPath . $filename)) {
                touch($destinationPath . $filename);
                chmod($destinationPath . $filename, 0644);
            }
            file_put_contents($destinationPath . $filename, $content);

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Returns collected errors
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
     */
    public function fcpoIsLogoAdded(): bool
    {
        return isset($this->_aAdminMessages["blLogoAdded"]) && $this->_aAdminMessages["blLogoAdded"] === true;
    }

    /**
     * Returns configured ratepay profiles
     *
     * @return array
     */
    public function fcpoGetRatePayProfiles()
    {
        return $this->_oFcPoRatePay->fcpoGetRatePayProfiles();
    }

    /**
     * Return admin template seperator sign by shop-version
     *
     * @return string
     */
    public function fcGetAdminSeperator()
    {
        $iVersion = $this->_oFcPoHelper->fcpoGetIntShopVersion();
        if ($iVersion < 4300) {
            return '?';
        } else {
            return '&';
        }
    }

    /**
     * Generates and delivers an xml export of configuration
     */
    public function export(): void
    {
        $oConfigExport = $this->_oFcPoHelper->getFactoryObject(FcPoConfigExport::class);
        $oConfigExport->fcpoExportConfig();
    }

    /**
     * Returns an array of languages of the shop
     *
     * @return array<int|string, mixed>
     */
    public function fcGetLanguages(): array
    {
        $aReturn = array();
        $oFcLang = $this->_oFcPoHelper->fcpoGetLang();

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
     * @return array<int|string, mixed>
     */
    public function fcGetCurrencies(): array
    {
        $aReturn = array();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        foreach ($oConfig->getCurrencyArray() as $iKey => $oCurr) {
            $aReturn[$oCurr->name] = $oCurr->name;
        }
        return $aReturn;
    }

    /**
     * Returns list of uploaded paypalexpresslogos
     *
     * @return array
     */
    public function fcpoGetPayPalLogos()
    {
        $oPaypal = $this->_oFcPoHelper->getFactoryObject(FcPoPaypal::class);

        return $oPaypal->fcpoGetPayPalLogos();
    }

    /**
     * Returns fields belonging to creditcard
     */
    public function getCCFields(): array
    {
        return array(
            'Number',
            'CVC',
            'Month',
            'Year',
        );
    }

    /**
     * Return array of cc types
     *
     * @param string $sField
     * @return array{select?: mixed, tel: mixed, password: mixed, text: mixed}
     */
    public function getCCTypes($sField): array
    {
        $aTypes = array();
        if ($sField == 'Month' || $sField == 'Year') {
            $aTypes['select'] = $this->_oFcPoHelper->fcpoGetLang()->translateString('FCPO_CC_SELECT');
        }
        $aTypes['tel'] = $this->_oFcPoHelper->fcpoGetLang()->translateString('FCPO_CC_TYPE_NUMERIC');
        $aTypes['password'] = $this->_oFcPoHelper->fcpoGetLang()->translateString('FCPO_CC_TYPE_PASSWORD');
        $aTypes['text'] = $this->_oFcPoHelper->fcpoGetLang()->translateString('FCPO_CC_TYPE_TEXT');

        return $aTypes;
    }

    /**
     * Get available cc styles
     *
     * @return array{standard: mixed, custom: mixed}
     */
    public function getCCStyles(): array
    {
        return array(
            'standard' => $this->_oFcPoHelper->fcpoGetLang()->translateString('FCPO_CC_IFRAME_STANDARD'),
            'custom' => $this->_oFcPoHelper->fcpoGetLang()->translateString('FCPO_CC_IFRAME_CUSTOM'),
        );
    }

    /**
     * Template getter returns the preview javascript code
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
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeHeader()
    {
        $sJsCode = "";
        $sJsCode .= "var request, config;" . "\n";
        $sJsCode .= "config = {" . "\n";

        return $sJsCode . ("\t" . "fields: {" . "\n");
    }

    /**
     * Returns the configured fields
     *
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeFields()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sJsCode = "";

        foreach ($this->_aFcJsCCPreviewFieldConfigs as $sFieldGroupIdent => $aCCFieldConfig) {
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
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        return $oConfig->getConfigParam($sParam);
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
    protected function _fcGetJsPreviewCodeValue($sVar, $sConfVal, $blCustomStyle, $blCustomIframe)
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
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
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeDefaultStyle()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sJsCode = "\t" . 'defaultStyle: {' . "\n";
        $sJsCode .= "\t\t" . 'input: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['input']) . '",' . "\n";
        $sJsCode .= "\t\t" . 'select: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['select']) . '",' . "\n";
        $sJsCode .= "\t\t" . 'iframe: {' . "\n";
        $sJsCode .= "\t\t\t" . 'width: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['width']) . '",' . "\n";
        $sJsCode .= "\t\t\t" . 'height: "' . $oConfig->getConfigParam($this->_aFcJsCCPreviewDefaultStyle['height']) . '",' . "\n";
        $sJsCode .= "\t\t" . '}' . "\n";

        return $sJsCode . ("\t" . '},' . "\n");
    }

    /**
     * Getter which delivers the error block part
     *
     * @return string
     */
    protected function _fcpoGetJsPreviewCodeErrorBlock()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
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
     * @return array
     */
    public function fcpoGetDeliverySets()
    {
        $oDeliveryAdminList =
            $this->_oFcPoHelper->getFactoryObject(DeliverySetList::class);
        $oList = $oDeliveryAdminList->getItemList();
        return $oList->getArray();
    }

    /**
     * Adding a detected configuration error
     *
     * @param $sTranslationString
     * @return void
     */
    protected function _fcpoAddConfigError($sTranslationString)
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sMessage = $oLang->translateString($sTranslationString);

        if (!is_array($this->_aConfErrors)) {
            $this->_aConfErrors = array();
        }
        $this->_aConfErrors[] = $sMessage;
    }

    /**
     * Method returns the checksum result
     *
     * @return string
     * @throws \JsonException
     */
    protected function _fcpoGetCheckSumResult()
    {
        $sIncludePath = getShopBasePath() . 'modules/fc/fcpayone/FcCheckChecksum.php';
        $oScript = $this->_oFcPoHelper->fcpoGetInstance(FcCheckChecksum::class, $sIncludePath);

        return $oScript->checkChecksumXml();
    }

}
