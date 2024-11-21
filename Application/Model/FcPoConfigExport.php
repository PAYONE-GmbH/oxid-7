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

use Exception;
use Fatchip\PayOne\FcCheckChecksum;
use Fatchip\PayOne\Lib\FcPoHelper;
use JsonException;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Application\Model\Shop;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\Eshop\Core\Str;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class FcPoConfigExport extends BaseModel
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Centralized Database instance
     *
     * @var DatabaseInterface
     */
    protected DatabaseInterface $_oFcPoDb;

    /**
     * Holds config values for all available shop ids
     *
     * @var array
     */
    protected array $_aShopConfigs = [];

    /**
     * List of boolean config values
     *
     * @var array
     */
    protected array $_aConfBools = [];

    /**
     * List of string config values
     *
     * @var array
     */
    protected array $_aConfStrs = [];

    /**
     * List of array config values
     *
     * @var array
     */
    protected array $_aConfArrs = [];

    /**
     * Newline
     *
     * @var string
     */
    protected string $_sN = "\n";

    /**
     * Tab
     *
     * @var string
     */
    protected string $_sT = "    ";

    /**
     * Definitions of multilang files
     *
     * @var array
     */
    protected array $_aMultiLangFields = [
        'sFCPOApprovalText',
        'sFCPODenialText',
    ];

    /**
     * config fields which needs skipping multilines
     *
     * @var array
     */
    protected array $_aSkipMultiline = ['aFCPODebitCountries', 'aFCPOAplCreditCards'];


    /**
     * Init needed data
     *
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
    }

    /**
     * Generates and delivers an XML export of configuration
     *
     * @throws JsonException
     * @throws DatabaseErrorException
     */
    public function fcpoExportConfig()
    {
        $sXml = $this->fcpoGetConfigXml();
        if ($sXml !== '' && $sXml !== '0') {
            $this->_oFcPoHelper->fcpoHeader("Content-Type: text/xml; charset=\"utf8\"");
            $this->_oFcPoHelper->fcpoHeader("Content-Disposition: attachment; filename=\"payone_config_export" . date('Y-m-d H-i-s') . "_" . md5($sXml) . ".xml\"");
            echo $this->_oFcPoHelper->fcpoProcessResultString($sXml);
            $this->_oFcPoHelper->fcpoExit();
        }
    }

    /**
     * Returns xml configuration of all shops
     *
     * @return string
     * @throws JsonException|DatabaseErrorException
     */
    public function fcpoGetConfigXml(): string
    {
        $aShopIds = $this->fcpoGetShopIds();
        $this->_fcpoSetShopConfigVars($aShopIds);


        $sXml = '<?xml version="1.0" encoding="UTF-8"?>' . $this->_sN;
        $sXml .= '<config>' . $this->_sN;

        foreach ($this->_aShopConfigs as $_aShopConfig) {
            $sXml .= $this->_sT . '<shop>' . $this->_sN;
            $sXml .= $this->_fcpoGetShopXmlGeneric($_aShopConfig);
            $sXml .= $this->_fcpoGetShopXmlSystem($_aShopConfig);
            $sXml .= $this->_fcpoGetShopXmlGlobal($_aShopConfig);
            $sXml .= $this->_fcpoGetShopXmlClearingTypes($_aShopConfig);
            $sXml .= $this->_fcpoGetShopXmlProtect();
            $sXml .= $this->_fcpoGetShopXmlMisc();
            $sXml .= $this->_fcpoGetShopXmlChecksums();
            $sXml .= $this->_sT . '</shop>' . $this->_sN;
        }

        return $sXml . '</config>';
    }

    /**
     * Returns a list of shop ids
     *
     * @return array
     * @throws DatabaseErrorException
     */
    public function fcpoGetShopIds(): array
    {
        return $this->_oFcPoDb->getCol("SELECT `oxid` FROM `oxshops`");
    }

    /**
     * Sets needed shop values for later fetching from attribute
     *
     * @param array $aShopIds
     * @return void
     */
    protected function _fcpoSetShopConfigVars(array $aShopIds): void
    {
        $oConf = $this->_oFcPoHelper->fcpoGetConfig();

        foreach ($aShopIds as $aShopId) {
            $oShop = oxNew(Shop::class);
            $blLoaded = $oShop->load($aShopId);
            if ($blLoaded) {
                $this->_aShopConfigs[$aShopId]['sFCPOMerchantID'] = $oConf->getShopConfVar('sFCPOMerchantID', $aShopId);
                $this->_aShopConfigs[$aShopId]['sFCPOSubAccountID'] = $oConf->getShopConfVar('sFCPOSubAccountID', $aShopId);
                $this->_aShopConfigs[$aShopId]['sFCPOPortalID'] = $oConf->getShopConfVar('sFCPOPortalID', $aShopId);
                $this->_aShopConfigs[$aShopId]['sFCPORefPrefix'] = $oConf->getShopConfVar('sFCPORefPrefix', $aShopId);
                $this->_aShopConfigs[$aShopId]['sFCPOSubAccountID'] = $oConf->getShopConfVar('sFCPOSubAccountID', $aShopId);
                $this->_aShopConfigs[$aShopId]['sShopName'] = $oShop->oxshops__oxname->value;
                $this->_aShopConfigs[$aShopId]['sShopVersion'] = $oShop->oxshops__oxversion->value;
                $this->_aShopConfigs[$aShopId]['sShopEdition'] = $oShop->oxshops__oxedition->value;
                $this->_aShopConfigs[$aShopId]['sShopId'] = $aShopId;
            }
        }
    }

    /**
     * Returns payone configuration
     *
     * @param string $sShopId
     * @param int $iLang
     * @return array{strs: array, bools: array, arrs: array}
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoGetConfig(string $sShopId, int $iLang = 0): array
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb(true);
        $sQuery = "select oxvarname, oxvartype, oxvarvalue from oxconfig where oxshopid = '$sShopId' AND (oxvartype = 'str' OR oxvartype = 'bool' OR oxvartype = 'arr')";
        $aResult = $oDb->getAll($sQuery);

        if (count($aResult) > 0) {
            $oStr = Str::getStr();
            foreach ($aResult as $aRow) {
                $sVarName = $aRow['oxvarname'];
                $sVarType = $aRow['oxvartype'];
                $sVarVal = $aRow['oxvarvalue'];

                if ($sVarType == "bool") {
                    $this->_aConfBools[$sVarName] = ($sVarVal == "true" || $sVarVal == "1");
                }
                if ($sVarType == "str") {
                    $sVarName = $this->fcpoGetMultilangConfStrVarName($sVarName, $iLang);

                    $this->_aConfStrs[$sVarName] = $sVarVal;
                    if ($this->_aConfStrs[$sVarName]) {
                        $this->_aConfStrs[$sVarName] = $oStr->htmlentities($this->_aConfStrs[$sVarName]);
                    }
                }

                if ($sVarType == "arr") {
                    if (in_array($sVarName, $this->_aSkipMultiline)) {
                        $this->_aConfArrs[$sVarName] = unserialize($sVarVal);
                    } elseif (unserialize($sVarVal)) {
                        $this->_aConfArrs[$sVarName] = $oStr->htmlentities($this->_arrayToMultiline(unserialize($sVarVal)));
                    }
                }
            }
        }

        $aConfigs = [];
        $aConfigs['strs'] = $this->_aConfStrs;
        $aConfigs['bools'] = $this->_aConfBools;
        $aConfigs['arrs'] = $this->_aConfArrs;
        return $aConfigs;
    }

    /**
     * Returns multilang varname if multilangfield
     *
     * @param string $sVarName
     * @param int $iLang
     * @return string
     */
    public function fcpoGetMultilangConfStrVarName(string $sVarName, int $iLang): string
    {
        if ($iLang === 0) {
            $iLang = 0;
        }
        $sLang = (string)$iLang;

        foreach ($this->_aMultiLangFields as $_aMultiLangField) {
            $sMultilangVarConcat = $_aMultiLangField . '_' . $sLang;
            if ($sVarName == $sMultilangVarConcat) {
                $sVarName = $_aMultiLangField;
            }
        }

        return $sVarName;
    }

    /**
     * Converts simple array to multiline text. Returns this text.
     *
     * @param array $aInput Array with text
     */
    protected function _arrayToMultiline(array $aInput): string
    {
        return implode("\n", $aInput);
    }

    /**
     * Returns the generic part of shop specific xml
     *
     * @param array $aShopConfVars
     * @return string
     */
    protected function _fcpoGetShopXmlGeneric(array $aShopConfVars): string
    {
        $sXml = $this->_sT . $this->_sT . "<code>{$aShopConfVars['sShopId']}</code>" . $this->_sN;

        return $sXml . ($this->_sT . $this->_sT . "<name><![CDATA[{$aShopConfVars['sShopName']}]]></name>" . $this->_sN);
    }

    /**
     * Returns system block of shop specific xml
     *
     * @param array $aShopConfVars
     * @return string
     */
    protected function _fcpoGetShopXmlSystem(array $aShopConfVars): string
    {
        $sXml = $this->_sT . $this->_sT . "<system>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<name>OXID</name>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<version>{$aShopConfVars['sShopVersion']}</version>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<edition>{$aShopConfVars['sShopEdition']}</edition>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<modules>" . $this->_sN;
        $aModules = $this->_getModuleInfo();
        if ($aModules && $aModules !== []) {
            foreach ($aModules as $sModule => $sInfo) {
                $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<$sModule>$sInfo</$sModule>" . $this->_sN;
            }
        }
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "</modules>" . $this->_sN;

        return $sXml . ($this->_sT . $this->_sT . "</system>" . $this->_sN);
    }

    /**
     * Returns a list of available modules and their versions
     *
     * @return array<int|string, mixed>
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function _getModuleInfo(): array
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $shopConfiguration = $container->get(ShopConfigurationDaoBridgeInterface::class)->get();

        $modules = [];
        foreach ($shopConfiguration->getModuleConfigurations() as $moduleConfiguration) {
            $module = oxNew(Module::class);
            $module->load($moduleConfiguration->getId());
            $modules[$module->getId()] = $module->getInfo('version');
        }

        return $modules;
    }

    /**
     * Returns shop specific global block
     *
     * @param array $aShopConfVars
     * @return string
     */
    protected function _fcpoGetShopXmlGlobal(array $aShopConfVars): string
    {
        $sXml = $this->_sT . $this->_sT . "<global>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<mid>" . $aShopConfVars['sFCPOMerchantID'] . "</mid>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<aid>" . $aShopConfVars['sFCPOSubAccountID'] . "</aid>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<portalid>" . $aShopConfVars['sFCPOPortalID'] . "</portalid>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<refnr_prefix>" . $aShopConfVars['sFCPORefPrefix'] . "</refnr_prefix>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<status_mapping>" . $this->_sN;
        $aPaymentMapping = $this->_getMappings();

        foreach ($aPaymentMapping as $sAbbr => $aMappings) {
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<$sAbbr>" . $this->_sN;
            foreach ($aMappings as $index => $subtype) {
                $subtype = $index;
                $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<$subtype>" . $this->_sN;
                foreach ($aMappings[$subtype] as $aMap) {
                    $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . $this->_sT . $this->_sT . '<map from="' . $aMap['from'] . '" to="' . $aMap['to'] . '" name="' . $aMap['name'] . '"/>' . $this->_sN;
                }
                $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . $this->_sT . "</$subtype>" . $this->_sN;
            }
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "</$sAbbr>" . $this->_sN;
        }
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "</status_mapping>" . $this->_sN;

        return $sXml . ($this->_sT . $this->_sT . "</global>" . $this->_sN);
    }

    /**
     * Returns the configured mappings
     *
     * @return array<string, array<string, array<int, array{from: mixed, to: mixed, name: mixed}>&array>&array>
     */
    protected function _getMappings(): array
    {
        $aMappings = [];

        $oMapping = oxNew(FcPoMapping::class);
        $aExistingMappings = $oMapping->fcpoGetExistingMappings();

        foreach ($aExistingMappings as $aExistingMapping) {
            $sAbbr = $this->_getPaymentAbbreviation($aExistingMapping->sPaymentType);
            $sSubType = $this->_getPaymentSubType($aExistingMapping->sPaymentType);
            $aSubTypes = explode(',', $sSubType);
            if (!array_key_exists($sAbbr, $aMappings)) {
                $aMappings[$sAbbr] = [];
            }
            foreach ($aSubTypes as $aSubType) {
                $aMappings[$sAbbr][$aSubType][] = ['from' => $aExistingMapping->sPayoneStatusId, 'to' => $aExistingMapping->sShopStatusId, 'name' => $aExistingMapping->sPaymentType];
            }
        }

        return $aMappings;
    }

    /**
     * Returns matching abbreviation for given payment id
     *
     * @param string $sPaymentId
     * @return string
     */
    protected function _getPaymentAbbreviation(string $sPaymentId): string
    {
        $sAbbr = '';

        $aAbbreviations = [
            'fcpocreditcard' => 'cc',
            'fcpocashondel' => 'cod',
            'fcpodebitnote' => 'elv',
            'fcpopayadvance' => 'vor',
            'fcpoinvoice' => 'rec',
            'fcpopaypal' => 'wlt',
            'fcpopaypal_express' => 'wlt',
            'fcpopaypalv2' => 'wlt',
            'fcpopaypalv2_express' => 'wlt',
            'fcpoklarna' => 'fnc',
            'fcpoklarna_invoice' => 'fnc',
            'fcpoklarna_directdebit' => 'fnc',
            'fcpoklarna_installments' => 'fnc',
            'fcpobarzahlen' => 'csh',
            'fcpopaydirekt' => 'wlt',
            'fcpopo_bill' => 'fnc',
            'fcpopo_debitnote' => 'fnc',
            'fcpopo_installment' => 'fnc',
            'fcporp_bill' => 'fnc',
            'fcpocreditcard_iframe' => 'cc',
            'fcpobillsafe' => 'fnc',
            'fcpo_secinvoice' => 'rec',
            'fcpopl_secinvoice' => 'fnc',
            'fcpopl_secinstallment' => 'fnc',
            'fcpopl_secdebitnote' => 'fnc',
            'fcpo_sofort' => 'sb',
            'fcpo_eps' => 'sb',
            'fcpo_pf_finance' => 'sb',
            'fcpo_pf_card' => 'sb',
            'fcpo_ideal' => 'sb',
            'fcpo_p24' => 'sb',
            'fcpo_bancontact' => 'sb',
            'fcporp_debitnote' => 'fnc',
            'fcpo_alipay' => 'wlt',
            'fcpo_trustly' => 'sb',
            'fcpo_wechatpay' => 'wlt',
            'fcpo_apple_pay' => 'wlt',
            'fcporp_installment' => 'fnc',
        ];

        if (isset($aAbbreviations[$sPaymentId])) {
            $sAbbr = $aAbbreviations[$sPaymentId];
        }

        return $sAbbr;
    }

    /**
     * Returns matching subtypes for given payment id
     *
     * @param string $sPaymentId
     * @return string
     */
    protected function _getPaymentSubtype(string $sPaymentId): string
    {
        $sAbbr = '';

        $aAbbreviations = [
            'fcpocreditcard' => 'V,M,A,D,J,O,U,B',
            'fcpocashondel' => 'CSH', // has no subtype use clearingtype instead
            'fcpodebitnote' => 'ELV', // has no subtype use clearingtype instead
            'fcpopayadvance' => 'VOR', // has no subtype use clearingtype instead
            'fcpoinvoice' => 'REC', // has no subtype use clearingtype instead
            'fcpopaypal' => 'PPE',
            'fcpopaypal_express' => 'PPE',
            'fcpopaypalv2' => 'PAL',
            'fcpopaypalv2_express' => 'PAL',
            'fcpoklarna_invoice' => 'KIV',
            'fcpoklarna' => 'KLV',
            'fcpoklarna_directdebit' => 'KDD',
            'fcpoklarna_installments' => 'KIS',
            'fcpobarzahlen' => 'BZN',
            'fcpopaydirekt' => 'PDT',
            'fcpopo_bill' => 'PYV',
            'fcpopo_debitnote' => 'PYD',
            'fcpopo_installment' => 'PYS',
            'fcporp_bill' => 'RPV',
            'fcporp_debitnote' => 'RPD',
            'fcporp_installment' => 'RPS',
            'fcpocreditcard_iframe' => 'V,M,A,D,J,O,U,B',
            'fcpo_secinvoice' => 'POV',
            'fcpopl_secinvoice' => 'PIV',
            'fcpopl_secinstallment' => 'PIN',
            'fcpopl_secdebitnote' => 'PDD',
            'fcpo_sofort' => 'PNT',
            'fcpo_eps' => 'EPS',
            'fcpo_pf_finance' => 'PFF',
            'fcpo_pf_card' => 'PFC',
            'fcpo_ideal' => 'IDL',
            'fcpo_p24' => 'P24',
            'fcpo_bancontact' => 'BCT',
            'fcpo_alipay' => 'ALP',
            'fcpo_trustly' => 'TRL',
            'fcpo_wechatpay' => 'WCP',
            'fcpo_apple_pay' => 'APL',
        ];

        if (isset($aAbbreviations[$sPaymentId])) {
            $sAbbr = strtolower($aAbbreviations[$sPaymentId]);
        }

        return $sAbbr;
    }

    /**
     * Returns shop specific clearingtypes
     *
     * @param array $aShopConfVars
     * @return string
     * @throws DatabaseErrorException
     */
    protected function _fcpoGetShopXmlClearingTypes(array $aShopConfVars): string
    {
        $sXml = $this->_sT . $this->_sT . "<clearingtypes>" . $this->_sN;
        $aPayments = $this->_getPaymentTypes();
        foreach ($aPayments as $aPayment) {
            $sXml .= $this->_sT . $this->_sT . $this->_sT . "<" . $this->_getPaymentAbbreviation($aPayment->getId()) . ">" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<title><![CDATA[{$aPayment->oxpayments__oxdesc->value}]]></title>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<id>{$aPayment->getId()}</id>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<mid>{$aShopConfVars['sFCPOMerchantID']}</mid>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<aid>{$aShopConfVars['sFCPOSubAccountID']}</aid>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<portalid>{$aShopConfVars['sFCPOPortalID']}</portalid>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<min_order_total>{$aPayment->oxpayments__oxfromamount->value}</min_order_total>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<max_order_total>{$aPayment->oxpayments__oxtoamount->value}</max_order_total>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<active>{$aPayment->oxpayments__oxactive->value}</active>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<countries>{$this->_getPaymentCountries($aPayment)}</countries>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<authorization>{$aPayment->oxpayments__fcpoauthmode->value}</authorization>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<mode>{$aPayment->fcpoGetOperationMode()}</mode>" . $this->_sN;
            $sXml .= $this->_sT . $this->_sT . $this->_sT . "</" . $this->_getPaymentAbbreviation($aPayment->getId()) . ">" . $this->_sN;
        }

        return $sXml . ($this->_sT . $this->_sT . "</clearingtypes>" . $this->_sN);
    }

    /**
     * Returns array of payments
     *
     * @return array
     * @throws DatabaseErrorException
     */
    protected function _getPaymentTypes(): array
    {
        $aPayments = [];

        $sQuery = "SELECT oxid FROM oxpayments WHERE fcpoispayone = 1";
        $this->_oFcPoDb->setFetchMode(DatabaseInterface::FETCH_MODE_NUM);
        $aRows = $this->_oFcPoDb->getAll($sQuery);
        foreach ($aRows as $aRow) {
            $oPayment = oxNew(Payment::class);
            $sOxid = $aRow[0];
            if ($oPayment->load($sOxid)) {
                $aPayments[] = $oPayment;
            }
        }
        return $aPayments;
    }

    /**
     * Returns payment countries
     *
     * @param object $oPayment
     * @return string
     */
    protected function _getPaymentCountries(object $oPayment): string
    {
        $aCountries = $oPayment->getCountries();
        $sCountries = '';
        foreach ($aCountries as $aCountry) {
            $oCountry = oxNew(Country::class);
            if ($oCountry->load($aCountry)) {
                $sCountries .= $oCountry->oxcountry__oxisoalpha2->value . ',';
            }
        }
        return rtrim($sCountries, ',');
    }

    /**
     * Returns shop specific protect block of xml
     *
     * @return string
     */
    protected function _fcpoGetShopXmlProtect(): string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sShopId = $oConfig->getShopId();
        $sXml = $this->_sT . $this->_sT . "<protect>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<consumerscore>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<active>" . ($oConfig->getShopConfVar('sFCPOBonicheck', $sShopId) == '-1' ? '0' : '1') . "</active>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<mode>{$oConfig->getShopConfVar('sFCPOBoniOpMode', $sShopId)}</mode>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<min_order_total>{$oConfig->getShopConfVar('sFCPOStartlimitBonicheck', $sShopId)}</min_order_total>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<max_order_total>1000000</max_order_total>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<addresscheck></addresscheck>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<red>{$this->_getRedPayments()}</red>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<yellow>{$this->_getYellowPayments()}</yellow>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<duetime>" . ((int)$oConfig->getShopConfVar('sFCPODurabilityBonicheck', $sShopId) * (60 * 60 * 24)) . "</duetime>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "</consumerscore>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<addresscheck>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<active>" . ($oConfig->getShopConfVar('sFCPOAddresscheck', $sShopId) == 'NO' ? '0' : '1') . "</active>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<mode>{$oConfig->getShopConfVar('sFCPOBoniOpMode', $sShopId)}</mode>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<min_order_total>{$oConfig->getShopConfVar('sFCPOStartlimitBonicheck', $sShopId)}</min_order_total>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<max_order_total>1000000</max_order_total>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<checkbilling>" . ($oConfig->getShopConfVar('sFCPOAddresscheck', $sShopId) == 'NO' ? 'NO' : 'YES') . "</checkbilling>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<checkshipping>" . ($oConfig->getShopConfVar('blFCPOCheckDelAddress', $sShopId) == 0 ? 'NO' : 'YES') . "</checkshipping>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "</addresscheck>" . $this->_sN;

        return $sXml . ($this->_sT . $this->_sT . "</protect>" . $this->_sN);
    }

    /**
     * Returning red payments
     *
     * @return string
     */
    protected function _getRedPayments(): string
    {
        $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);

        return $oPayment->fcpoGetRedPayments();
    }

    /**
     * Returning yellow payments
     *
     * @return string
     */
    protected function _getYellowPayments(): string
    {
        $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);

        return $oPayment->fcpoGetYellowPayments();
    }

    /**
     * Returns miscellaneous
     *
     * @return string
     */
    protected function _fcpoGetShopXmlMisc(): string
    {
        $sXml = $this->_sT . $this->_sT . "<misc>" . $this->_sN;
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "<transactionstatus_forwarding>" . $this->_sN;
        $aForwardings = $this->_getForwardings();
        foreach ($aForwardings as $aForwarding) {
            $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . '<config status="' . $aForwarding['status'] . '" url="' . htmlentities((string)$aForwarding['url']) . '" timeout="' . $aForwarding['timeout'] . '"/>' . $this->_sN;
        }
        $sXml .= $this->_sT . $this->_sT . $this->_sT . "</transactionstatus_forwarding>" . $this->_sN;

        return $sXml . ($this->_sT . $this->_sT . "</misc>" . $this->_sN);
    }

    /**
     * Returns the configured list of forwardings
     *
     * @return array<int, array{status: mixed, url: mixed, timeout: mixed}>
     */
    protected function _getForwardings(): array
    {
        $aForwardings = [];
        $oForwarding = $this->_oFcPoHelper->getFactoryObject(FcPoForwarding::class);
        $aForwardingsList = $oForwarding->fcpoGetExistingForwardings();

        foreach ($aForwardingsList as $aForwardingList) {
            $aForwardings[] = [
                'status' => $aForwardingList->sPayoneStatusId,
                'url' => $aForwardingList->sForwardingUrl,
                'timeout' => $aForwardingList->iForwardingTimeout,
            ];
        }

        return $aForwardings;
    }

    /**
     * Returns shop specific checksum part of xml
     *
     * @return string
     * @throws JsonException
     */
    protected function _fcpoGetShopXmlChecksums(): string
    {
        $sXml = $this->_sT . $this->_sT . "<checksums>" . $this->_sN;
        $mUrlOpen = $this->_oFcPoHelper->fcpoIniGet('allow_url_fopen');
        $blCurlAvailable = $this->_oFcPoHelper->fcpoFunctionExists('curl_init');

        if ($mUrlOpen == 0) {
            $sXml .= $this->_sT . $this->_sT . $this->_sT . "<status>Cant verify checksums, allow_url_fopen is not activated on customer-server</status>" . $this->_sN;
        } elseif (!$blCurlAvailable) {
            $sXml .= $this->_sT . $this->_sT . $this->_sT . "<status>Cant verify checksums, curl is not activated on customer-server</status>" . $this->_sN;
        } else {
            $aErrors = $this->_getChecksumErrors();
            if ($aErrors === false) {
                $sXml .= $this->_sT . $this->_sT . $this->_sT . "<status>Correct</status>" . $this->_sN;
            } elseif (is_array($aErrors) && $aErrors !== []) {
                $sXml .= $this->_sT . $this->_sT . $this->_sT . "<status>Error</status>" . $this->_sN;
                $sXml .= $this->_sT . $this->_sT . $this->_sT . "<errors>" . $this->_sN;
                foreach ($aErrors as $aError) {
                    $sXml .= $this->_sT . $this->_sT . $this->_sT . $this->_sT . "<error>" . base64_encode((string)$aError) . "</error>" . $this->_sN;
                }
                $sXml .= $this->_sT . $this->_sT . $this->_sT . "</errors>" . $this->_sN;
            }
        }

        return $sXml . ($this->_sT . $this->_sT . "</checksums>" . $this->_sN);
    }

    /**
     * Returns collected checksum errors if there are any
     *
     * @return array|bool|null
     * @throws JsonException
     */
    protected function _getChecksumErrors(): array|bool|null
    {
        $blCheckSumAvailable = $this->_oFcPoHelper->fcpoCheckClassExists(FcCheckChecksum::class);
        if ($blCheckSumAvailable) {
            $sResult = $this->_fcpoGetCheckSumResult();
            if ($sResult == 'correct') {
                return false;
            } else {
                $aErrors = json_decode(stripslashes($sResult));
                if (is_array($aErrors)) {
                    return $aErrors;
                }
            }
        }
        return null;
    }

    /**
     * Method returns the checksum result
     *
     * @return string
     * @throws JsonException
     * @throws Exception
     */
    protected function _fcpoGetCheckSumResult(): string
    {
        $sIncludePath = VENDOR_PATH . 'payone-gmbh/oxid-7/FcCheckChecksum.php';
        $oScript = $this->_oFcPoHelper->fcpoGetInstance(FcCheckChecksum::class, $sIncludePath);

        return $oScript->checkChecksumXml();
    }

}
