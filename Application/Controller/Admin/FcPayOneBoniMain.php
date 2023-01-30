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

use OxidEsales\Eshop\Core\Registry;

class FcPayOneBoniMain extends FcPayOneAdminDetails
{

    public $_oFcpoHelper;
    public $_oFcpoConfigExport;
    /**
     * Current class template name
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_boni_main';

    /**
     * Definitions of multilang files
     *
     * @var array
     */
    private const A_MULTI_LANG_FIELDS = ['sFCPOApprovalText', 
    'sFCPODenialText'];

    /**
     * Boni default values
     *
     * @var array
     */
    private const A_DEFAULT_VALUES = ['sFCPOMalusPPB' => '0', 
    'sFCPOMalusPHB' => '150', 
    'sFCPOMalusPAB' => '300', 
    'sFCPOMalusPKI' => '250', 
    'sFCPOMalusPNZ' => '400', 
    'sFCPOMalusPPV' => '500', 
    'sFCPOMalusPPF' => '400'];

    /**
     * Assignment of validation messages
     */
    private array $_aValidateCode2Message = ['1' => 'FCPO_BONI_ERROR_SET_TO_BONIVERSUM_PERSON', 
    '2' => 'FCPO_BONI_ERROR_DEACTIVATED_REGULAR_ADDRESSCHECK', 
    '3' => 'FCPO_BONI_ERROR_NO_BONIADDRESSCHECK_SET', 
    '4' => 'FCPO_BONI_ERROR_DEACTIVATED_BONI_ADDRESSCHECK', 
    '5' => 'FCPO_BONI_ERROR_SET_TO_BASIC', 
    '6' => 'FCPO_BONI_ERROR_SET_TO_PERSON'];

    /**
     * Collection of validation codes processed via saving
     */
    private ?array $_aValidationCodes = null;


    /**
     * Loads payment protection configuration and passes them to Smarty engine, returns
     * name of template file "fcpayone_boni_main.html.twig".
     *
     * @return string
     */
    public function render()
    {
        $sReturn = parent::render();
        $this->_oFcpoHelper->fcpoGetLang();

        $iLang = $this->_oFcpoHelper->fcpoGetRequestParameter("subjlang");
        if (empty($iLang)) {
            $iLang = '0';
        }

        $this->_aViewData["subjlang"] = $iLang;

        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sShopId = $oConfig->getShopId();

        $aConfigs = $this->_oFcpoConfigExport->fcpoGetConfig($sShopId, $iLang);

        $this->_aViewData["confbools"] = $aConfigs['bools'];
        $this->_aViewData["confstrs"] = $aConfigs['strs'];
        $this->_aViewData['sHelpURL'] = $this->_oFcpoHelper->fcpoGetHelpUrl();

        $aConfStrs = $this->_aViewData["confstrs"];
        foreach (self::A_DEFAULT_VALUES as $sVarName => $sValue) {
            if (!array_key_exists($sVarName, $aConfStrs) || empty($aConfStrs[$sVarName])) {
                $aConfStrs[$sVarName] = $sValue;
            }
        }
        $this->_aViewData["confstrs"] = $aConfStrs;

        return $sReturn;
    }

    /**
     * Saves changed configuration parameters.
     *
     * @return mixed
     */
    public function save(): void
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        $iLang = $this->_oFcpoHelper->fcpoGetRequestParameter("subjlang");
        if (empty($iLang)) {
            $iLang = '0';
        }

        $aConfBools = $this->_oFcpoHelper->fcpoGetRequestParameter("confbools");
        $aConfStrs = $this->_oFcpoHelper->fcpoGetRequestParameter("confstrs");
        if (is_array($aConfBools)) {
            foreach ($aConfBools as $sVarName => $sVarVal) {
                $oConfig->saveShopConfVar("bool", $sVarName, $sVarVal);
            }
        }

        if (is_array($aConfStrs)) {
            foreach ($aConfStrs as $sVarName => $sVarVal) {
                if (in_array($sVarName, self::A_MULTI_LANG_FIELDS)) {
                    $sVarName = $sVarName . '_' . $iLang;
                }
                $oConfig->saveShopConfVar("str", $sVarName, $sVarVal);
            }
        }

        $iValidateCode = $this->_fcpoValidateAddresscheckType();
        $this->_fcpoDisplayMessage($iValidateCode);
    }

    /**
     * Validating addresstype. Fix setting if needed and respond with message
     * of changes
     *
     *
     */
    private function _fcpoValidateAddresscheckType(): void
    {
        $this->_aValidationCodes = [];
        $this->_fcpoCheckIssetBoniAddresscheck();
        $this->_fcpoValidateDuplicateAddresscheck();
        $this->_fcpoValidateAddresscheckBasic();
        $this->_fcpoValidateAddresscheckPerson();
        $this->_fcpoValidateAddresscheckBoniversum();
        $this->_fcpoDisplayValidationMessages();
    }

    /**
     * Checks if mandatory boniaddresscheck is set on active bonicheck
     * (only both or nothing is allowed)
     *
     *
     */
    private function _fcpoCheckIssetBoniAddresscheck(): void
    {
        $blBoniCheckActive = $this->_fcpoCheckBonicheckIsActive();
        $blBoniAddresscheckActive = $this->_fcpoBoniAddresscheckActive();

        $blSetBoniAddresscheckActive = (
            $blBoniCheckActive &&
            !$blBoniAddresscheckActive
        );

        if ($blSetBoniAddresscheckActive) {
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $oConfig->saveShopConfVar("str", 'sFCPOConsumerAddresscheck', 
    'BA');
            $this->_aValidationCodes[] = 3;
        }
    }

    /**
     * Method returns if boni check is in active use
     *
     *
     */
    private function _fcpoCheckBonicheckIsActive(): bool
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');

        return $sFCPOBonicheck !== null &&
        $sFCPOBonicheck !== '-1';
    }

    /**
     * Checks if there is a value set for boni addresscheck
     *
     *
     * @return void
     */
    private function _fcpoBoniAddresscheckActive()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOConsumerAddresscheck =
            $oConfig->getConfigParam('sFCPOConsumerAddresscheck');

        return (bool)$sFCPOConsumerAddresscheck;
    }

    /**
     * Check if bonicheck and regular adresscheck is set to active
     * simultanously and fix setting if needed
     *
     *
     */
    private function _fcpoValidateDuplicateAddresscheck(): void
    {
        $blBoniCheckActive = $this->_fcpoCheckBonicheckIsActive();
        $blBoniAddressCheckActive = $this->_fcpoBoniAddresscheckActive();

        $blDeactivateBoniAddressCheck = (
            !$blBoniCheckActive &&
            $blBoniAddressCheckActive
        );

        if ($blDeactivateBoniAddressCheck) {
            $this->_fcpoDeactivateBoniAdresscheck();
            $this->_aValidationCodes[] = 4;
        }

        $blRegularAddressCheckActive =
            $this->_fcpoCheckRegularAddressCheckActive();
        $blDuplicateAddressCheck = (
            $blBoniCheckActive &&
            $blRegularAddressCheckActive
        );

        if ($blDuplicateAddressCheck) {
            $this->_fcpoDeactivateRegularAddressCheck();
            $this->_aValidationCodes[] = 2;
        }
    }

    /**
     * Deactivates bonicheck addresscheck type
     *
     *
     */
    private function _fcpoDeactivateBoniAdresscheck(): void
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oConfig->saveShopConfVar("str", 'sFCPOConsumerAddresscheck', null);
    }

    /**
     * Returns if regular addresscheck is set active
     */
    private function _fcpoCheckRegularAddressCheckActive(): bool
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $oConfig->getConfigParam('sFCPOAddresscheck');

        return $sFCPOAddresscheck !== null &&
        $sFCPOAddresscheck !== 'NO';
    }

    /**
     * Deactivates regular address check setting to 'no addresscheck'
     *
     *
     */
    private function _fcpoDeactivateRegularAddressCheck(): void
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oConfig->saveShopConfVar("str", 'sFCPOAddresscheck', 
    'NO');
    }

    /**
     * Validate settings and check if this must be switched to basic addresscheck depending on
     * selected bonicheck
     *
     *
     * @return int
     */
    private function _fcpoValidateAddresscheckBasic(): void
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $aConfStrs = $this->_oFcpoHelper->fcpoGetRequestParameter("confstrs");
        $aMatchingBoniChecks = ['IH', 
    'IA', 
    'IB'];
        $aMatchingAddressChecks = ['BB'];
        $blSwitchToBasic = (
            isset($aConfStrs['sFCPOBonicheck']) &&
            isset($aConfStrs['sFCPOConsumerAddresscheck']) &&
            in_array($aConfStrs['sFCPOBonicheck'], $aMatchingBoniChecks) &&
            in_array($aConfStrs['sFCPOConsumerAddresscheck'], $aMatchingAddressChecks)
        );
        if ($blSwitchToBasic) {
            $this->_aValidationCodes[] = 5;
            $oConfig->saveShopConfVar("str", 'sFCPOConsumerAddresscheck', 
    'BA');
        }
    }

    /**
     * Validate settings and check if this must be switched to person addresscheck depending on
     * selected bonicheck
     *
     *
     * @return int
     */
    private function _fcpoValidateAddresscheckPerson(): void
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $aConfStrs = $this->_oFcpoHelper->fcpoGetRequestParameter("confstrs");
        $aMatchingBoniChecks = ['IH', 
    'IA', 
    'IB'];
        $aMatchingAddressChecks = ['PB'];

        $blSwitchToPerson = (
            isset($aConfStrs['sFCPOBonicheck']) &&
            isset($aConfStrs['sFCPOConsumerAddresscheck']) &&
            in_array($aConfStrs['sFCPOBonicheck'], $aMatchingBoniChecks) &&
            in_array($aConfStrs['sFCPOConsumerAddresscheck'], $aMatchingAddressChecks)
        );

        if ($blSwitchToPerson) {
            $this->_aValidationCodes[] = 6;
            $oConfig->saveShopConfVar("str", 'sFCPOConsumerAddresscheck', 
    'PE');
        }
    }

    /**
     * Validates addresscheck related to boniversum. Correct settings and return error
     * code for notifying user
     *
     *
     */
    private function _fcpoValidateAddresscheckBoniversum(): void
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');
        $sFCPOConsumerAddresscheck = $oConfig->getConfigParam('sFCPOConsumerAddresscheck');
        $blCorrectSetting = (
            is_string($sFCPOBonicheck) &&
            is_string($sFCPOConsumerAddresscheck) &&
            (
                $sFCPOBonicheck == 'CE' &&
                $sFCPOConsumerAddresscheck != 'PB'
            )
        );

        if ($blCorrectSetting) {
            // addresschecktype ALWAYS has to be PB if bonichecktype is CE
            // => Set error code ...
            $this->_aValidationCodes[] = 1;
            // ... and fix setting
            $oConfig->saveShopConfVar("str", 'sFCPOConsumerAddresscheck', 
    'PB');
        }
    }

    /**
     * If there have been validation adjustments, cumulate and
     * present them
     *
     *
     */
    private function _fcpoDisplayValidationMessages(): void
    {
        // collect messages
        $sTranslatedMessage = "";
        foreach ($this->_aValidationCodes as $_aValidationCode) {
            $blSkipCode = !(
                $_aValidationCode > 0 &&
                isset($this->_aValidateCode2Message[$_aValidationCode])
            );
            if ($blSkipCode) {
                continue;
            }
            $sTranslateString = $this->_aValidateCode2Message[$_aValidationCode];
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $sTranslatedMessage .= $oLang->translateString($sTranslateString) . "<br>";
        }

        if ($sTranslatedMessage !== '' && $sTranslatedMessage !== '0') {
            $oUtilsView = Registry::getUtilsView();
            $oUtilsView->addErrorToDisplay($sTranslatedMessage);
        }
    }

    /**
     * Displays a message in admin frontend if there is an error code present
     *
     * @param $iValidateCode
     */
    public function _fcpoDisplayMessage($iValidateCode): void
    {
        if ($iValidateCode > 0 && isset($this->_aValidateCode2Message[$iValidateCode])) {
            $oxUtilsView = Registry::get('oxUtilsView');
            $sTranslateString = $this->_aValidateCode2Message[$iValidateCode];
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $sTranslatedMessage = $oLang->translateString($sTranslateString);
            $oxUtilsView->addErrorToDisplay($sTranslatedMessage);
        }
    }

    /**
     * Method decides if regular addresscheck can be used. Depends on bonicheck
     * is inactive/not in use
     *
     *
     * @return bool
     */
    public function fcpoShowRegularAddresscheck()
    {
        $blBoniCheckActive = $this->_fcpoCheckBonicheckIsActive();
        if ($blBoniCheckActive) {
            $this->_fcpoDeactivateRegularAddressCheck();
        }

        return !$blBoniCheckActive;
    }
}
