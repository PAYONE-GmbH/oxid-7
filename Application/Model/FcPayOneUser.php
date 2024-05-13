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
use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Core\ViewConfig;
use stdClass;

class FcPayOneUser extends FcPayOneUser_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * List of userflag ids of user
     *
     * @var array|null
     */
    protected ?array $_aUserFlags = null;

    /**
     * Blocked payments for user (unvalidated)
     *
     * @var array
     */
    protected array $_aBlockedPaymentIds = [];

    /**
     * Forbidden payments for user (validated)
     *
     * @var array
     */
    protected array $_aForbiddenPaymentIds = [];


    /**
     * init object construction
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns if given payment is allowed by flags
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function fcpoPaymentCurrentlyAllowedByFlags(string $sPaymentId): bool
    {
        $aForbiddenPayments = $this->fcpoGetForbiddenPaymentIds();
        return !in_array($sPaymentId, $aForbiddenPayments);
    }

    /**
     * Returns an array of forbidden paymentids
     *
     * @return array
     */
    public function fcpoGetForbiddenPaymentIds(): array
    {
        $this->_fcpoAddForbiddenByUserFlags();

        return $this->_aForbiddenPaymentIds;
    }

    /**
     * Adds assigned payone userflags to user
     *
     * @return void
     */
    protected function _fcpoAddForbiddenByUserFlags(): void
    {
        $aUserFlags = $this->fcpoGetFlagsOfUser();
        foreach ($aUserFlags as $oUserFlag) {
            $aPaymentsNotAllowedByFlag = $oUserFlag->fcpoGetBlockedPaymentIds();
            $this->_aForbiddenPaymentIds = array_merge($this->_aForbiddenPaymentIds, $aPaymentsNotAllowedByFlag);
        }
    }

    /**
     * Returns current userflags
     *
     * @return array|null
     */
    public function fcpoGetFlagsOfUser(): ?array
    {
        if ($this->_aUserFlags === null) {
            $this->_fcpoSetUserFlags();
        }
        return $this->_aUserFlags;
    }

    /**
     * Sets current flags of user
     *
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function _fcpoSetUserFlags(): void
    {
        $this->_aUserFlags = [];
        $aUserFlagInfos = $this->_fcpoGetUserFlagInfos();
        foreach ($aUserFlagInfos as $oUserFlagInfo) {
            $sOxid = $oUserFlagInfo->sOxid;
            $sUserFlagId = $oUserFlagInfo->sUserFlagId;
            $sTimeStamp = $oUserFlagInfo->sTimeStamp;


            $oUserFlag = oxNew(FcPoUserFlag::class);
            if ($oUserFlag->load($sUserFlagId)) {
                $oUserFlag->fcpoSetAssignId($sOxid);
                $oUserFlag->fcpoSetTimeStamp($sTimeStamp);
                $this->_aUserFlags[$sUserFlagId] = $oUserFlag;
            }
        }
    }

    /**
     * Returns an array of userflag infos mandatory for
     * determining effects
     *
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function _fcpoGetUserFlagInfos(): array
    {
        $aUserFlagInfos = [];
        $oDb = $this->_oFcPoHelper->fcpoGetDb(true);
        $sUserId = $this->getId();
        $sQuery = "
          SELECT
            OXID, 
            FCPOUSERFLAGID,
            FCPODISPLAYMESSAGE,
            OXTIMESTAMP
          FROM 
            fcpouser2flag 
          WHERE
            OXUSERID=" . $oDb->quote($sUserId) . "
        ";
        $aRows = $oDb->getAll($sQuery);

        foreach ($aRows as $aRow) {
            $oUserFlag = new stdClass();
            $oUserFlag->sOxid = $aRow['OXID'];
            $oUserFlag->sUserFlagId = $aRow['FCPOUSERFLAGID'];
            $oUserFlag->sTimeStamp = $aRow['OXTIMESTAMP'];
            $oUserFlag->sDisplayMessage = $aRow['FCPODISPLAYMESSAGE'];
            $aUserFlagInfos[] = $oUserFlag;
        }

        return $aUserFlagInfos;
    }

    /**
     * Overwriting load method for directly setting user flags onload
     *
     * @param $sOXID
     * @return mixed
     */
    public function load($sOXID): mixed
    {
        $mReturn = parent::load($sOXID);
        if ($mReturn !== false) {
            $this->_fcpoSetUserFlags();
        }

        return $mReturn;
    }

    /**
     * Adds (or refreshes) a payone user flag
     *
     * @param object $oUserFlag
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoAddPayoneUserFlag(object $oUserFlag): void
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $oUtilsObject = $this->_oFcPoHelper->getFactoryObject(UtilsObject::class);
        $sUserFlagId = $oUserFlag->fcpouserflags__oxid->value;
        $sUserId = $this->getId();
        $sNewOxid = $oUtilsObject->generateUId();

        $sQuery = "
          REPLACE INTO fcpouser2flag
          (
            OXID,
            OXUSERID,
            FCPOUSERFLAGID,
            OXTIMESTAMP
          )
          VALUES
          (
            " . $oDb->quote($sNewOxid) . ",
            " . $oDb->quote($sUserId) . ",
            " . $oDb->quote($sUserFlagId) . ",
            NOW()
          )
        ";

        $oDb->execute($sQuery);
    }

    /**
     * Method adds a delivery address to user and directly set the deladrid session variable
     *
     * @param array $aResponse
     * @param string $sUserOxid
     * @param bool|null $blFixUtf8
     * @return void
     */
    public function _fcpoAddDeliveryAddress(array $aResponse, string $sUserOxid, ?bool $blFixUtf8 = false): void
    {
        if ($blFixUtf8) {
            $aResponse = array_map('utf8_decode', $aResponse);
        }
        $aStreetParts = $this->_fcpoSplitStreetAndStreetNr($aResponse['add_paydata[shipping_street]']);
        $sCountryId = $this->_fcpoGetCountryIdByIso2($aResponse['add_paydata[shipping_country]']);
        $sFirstName = trim($aResponse['add_paydata[shipping_firstname]']);
        $sLastName = trim($aResponse['add_paydata[shipping_lastname]']);

        if (empty($sLastName)) {
            $aNameParts = $this->_fcpoSplitNameParts($sFirstName);
            $sFirstName = $aNameParts['firstname'];
            $sLastName = $aNameParts['lastname'];
        }

        $oAddress = $this->_oFcPoHelper->getFactoryObject(Address::class);
        $oAddress->oxaddress__oxuserid = new Field($sUserOxid);
        $oAddress->oxaddress__oxaddressuserid = new Field($sUserOxid);
        $oAddress->oxaddress__oxfname = new Field($sFirstName);
        $oAddress->oxaddress__oxlname = new Field($sLastName);
        $oAddress->oxaddress__oxstreet = new Field($aStreetParts['street']);
        $oAddress->oxaddress__oxstreetnr = new Field($aStreetParts['streetnr']);
        $oAddress->oxaddress__oxfon = new Field($aResponse['add_paydata[shipping_telephonenumber]']);
        $oAddress->oxaddress__oxcity = new Field($aResponse['add_paydata[shipping_city]']);
        $oAddress->oxaddress__oxcountry = new Field($aResponse['add_paydata[shipping_country]']);
        $oAddress->oxaddress__oxcountryid = new Field($sCountryId);
        $oAddress->oxaddress__oxzip = new Field($aResponse['add_paydata[shipping_zip]']);
        $oAddress->oxaddress__oxaddinfo = new Field($aResponse['add_paydata[shipping_addressaddition]']);
        $oAddress->oxaddress__oxcompany = new Field($aResponse['add_paydata[shipping_company]']);

        // check if address exists
        $sEncodedDeliveryAddress = $oAddress->getEncodedDeliveryAddress();
        $blExists = $this->_fcpoCheckAddressExists($sEncodedDeliveryAddress);
        if ($blExists) {
            $oAddress->load($sEncodedDeliveryAddress);
        } else {
            $oAddress->setId($sEncodedDeliveryAddress);
            $oAddress->save();
        }

        $this->_oFcPoHelper->fcpoSetSessionVariable('deladrid', $sEncodedDeliveryAddress);
    }

    /**
     * Method splits street and streetnr from string
     *
     * @param string $sStreetAndStreetNr
     * @return array
     */
    protected function _fcpoSplitStreetAndStreetNr(string $sStreetAndStreetNr): array
    {
        /**
         * @todo currently very basic by simply splitting of space
         */
        $aStreetParts = explode(' ', $sStreetAndStreetNr);
        $blReturnDefault = (
            !is_array($aStreetParts) ||
            count($aStreetParts) <= 1
        );

        if ($blReturnDefault) {
            $aReturn['street'] = $sStreetAndStreetNr;
            $aReturn['streetnr'] = '';
            return $aReturn;
        }

        $aReturn['streetnr'] = array_pop($aStreetParts);
        $aReturn['street'] = implode(' ', $aStreetParts);

        return $aReturn;
    }

    /**
     * Returns id of a countrycode
     *
     * @param string $sIso2Country
     * @return string
     */
    protected function _fcpoGetCountryIdByIso2(string $sIso2Country): string
    {
        $oCountry = $this->_oFcPoHelper->getFactoryObject(Country::class);
        return $oCountry->getIdByCode($sIso2Country);
    }

    /**
     * Takes a complete name string and seperates into first and lastname
     *
     * @param string $sSingleNameString
     * @return array
     */
    protected function _fcpoSplitNameParts(string $sSingleNameString): array
    {
        $aParts = explode(' ', $sSingleNameString);
        $sLastName = array_pop($aParts);
        $sFirstName = implode(' ', $aParts);

        $aReturn['firstname'] = $sFirstName;
        $aReturn['lastname'] = $sLastName;

        return array_map('trim', $aReturn);
    }

    /**
     * Checks if address is already existing
     *
     * @param string $sEncodedDeliveryAddress
     * @return bool
     */
    protected function _fcpoCheckAddressExists(string $sEncodedDeliveryAddress): bool
    {
        $oAddress = $this->_oFcPoHelper->getFactoryObject(Address::class);
        $blReturn = false;
        if ($oAddress->load($sEncodedDeliveryAddress)) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Sets the user scorevalue to red (=100) if user declines
     * boni check
     *
     * @param int|null $iValue
     * @return void
     */
    public function fcpoSetScoreOnNonApproval(?int $iValue = 100): void
    {
        $this->oxuser__oxboni->value = $iValue;
        $this->save();
    }

    /**
     * Returns country iso code of users country
     *
     * @param int|null $iVersion
     * @return string
     */
    public function fcpoGetUserCountryIso(?int $iVersion = 2): string
    {
        $oCountry = $this->_oFcPoHelper->getFactoryObject(Country::class);
        if (!$oCountry->load($this->oxuser__oxcountryid->value)) {
            return '';
        }
        $sField = "oxcountry__oxisoalpha" . $iVersion;

        return $oCountry->$sField->value;
    }

    /**
     * Check the credit-worthiness of the user with the consumerscore or addresscheck request to the PAYONE API
     *
     * @param bool $blCheckAddress
     * @param bool $blCheckBoni
     * @return bool
     */
    public function checkAddressAndScore(?bool $blCheckAddress = true, ?bool $blCheckBoni = true): bool
    {
        // in general, we assume that everything is fine with score and address
        $blBoniChecked = $blAddressValid = true;

        // let's see what should be checked
        if ($blCheckBoni) {
            $blBoniChecked = $this->_fcpoPerformBoniCheck();
        }
        if ($blCheckAddress) {
            $blAddressValid = $this->_fcpoPerformAddressCheck();
        }

        // merge results
        return ($blBoniChecked && $blAddressValid);
    }

    /**
     * Performing boni check on user
     *
     * @return bool|null
     */
    protected function _fcpoPerformBoniCheck(): ?bool
    {
        $sFCPOBonicheck = $this->_fcpoGetBoniSetting();
        $blBoniCheckNeeded = $this->isBonicheckNeeded();

        // early return as success if bonicheck is inactive or not needed
        if (!$sFCPOBonicheck || !$blBoniCheckNeeded) return true;

        return $this->_fcpoValidateBoni();
    }

    /**
     * Returns boni setting or false if inactive
     *
     * @return mixed bool/string
     */
    protected function _fcpoGetBoniSetting(): mixed
    {
        // get raw configured setting
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');

        // multiple inactivity checks due to php is a non type checking language
        $blBoniInactive = ($sFCPOBonicheck == -1 || $sFCPOBonicheck == '-1' || !$sFCPOBonicheck);

        // sum it up
        return ($blBoniInactive) ? false : $sFCPOBonicheck;
    }

    /**
     * Check if the credit-worthiness has to be checked
     *
     * @return bool
     */
    protected function isBonicheckNeeded(): bool
    {
        return (
            (
                $this->oxuser__oxboni->value == $this->getBoni() ||
                $this->isNewBonicheckNeeded()
            ) &&
            $this->isBonicheckNeededForBasket()
        );
    }

    /**
     * Overrides oxid standard method getBoni()
     * Sets it to value defined in the admin area of PAYONE if it was configured
     *
     * @return float|int|string
     * @extend getBoni()
     */
    public function getBoni(): float|int|string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $iDefaultBoni = $oConfig->getConfigParam('sFCPODefaultBoni');
        if (is_numeric($iDefaultBoni) === true) {
            return $iDefaultBoni;
        }
        return parent::getBoni();
    }

    /**
     * Check if the credit-worthiness of the user has to be checked again
     *
     * @return bool
     */
    protected function isNewBonicheckNeeded(): bool
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sTimeLastCheck = strtotime($this->oxuser__fcpobonicheckdate->value);
        $iEnduranceBoniCheck = (int)$oConfig->getConfigParam('sFCPODurabilityBonicheck');
        $sTimeout = (time() - (60 * 60 * 24 * $iEnduranceBoniCheck));

        return $sTimeout > $sTimeLastCheck;
    }

    /**
     * Check if the current basket sum exceeds the minimum sum for the credit-worthiness check
     *
     * @return bool
     */
    protected function isBonicheckNeededForBasket(): bool
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $iStartlimitBonicheck = $oConfig->getConfigParam('sFCPOStartlimitBonicheck');

        $blReturn = true;
        if ($iStartlimitBonicheck && is_numeric($iStartlimitBonicheck)) {
            $oSession = $this->_oFcPoHelper->fcpoGetSession();
            $oBasket = $oSession->getBasket();
            $oPrice = $oBasket->getPrice();

            if ($oPrice->getBruttoPrice() < $iStartlimitBonicheck) {
                $blReturn = false;
            }
        }

        return $blReturn;
    }

    /**
     * Requesting for boni of user if conditions are alright
     *
     * @return true
     */
    protected function _fcpoValidateBoni(): bool
    {
        // Consumerscore
        $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oPORequest->sendRequestConsumerscore($this);
        $this->fcpoSetBoni($aResponse);

        return true;
    }

    /**
     * Sets the credit-worthiness of the user
     *
     * @param array $aResponse response of a API request
     *
     * @return void
     */
    protected function fcpoSetBoni(array $aResponse): void
    {
        $boni = 100;
        if ($aResponse['scorevalue']) {
            $boni = $this->_fcpoCalculateBoniFromScoreValue($aResponse['scorevalue']);
        } else {
            $aResponse = $this->_fcpoCheckUseFallbackBoniversum($aResponse);
            $aMap = ['G' => 500, 'Y' => 300, 'R' => 100];
            if (isset($aMap[$aResponse['score']])) {
                $boni = $aMap[$aResponse['score']];
            }
        }

        $this->oxuser__oxboni->value = $boni;

        $blValidResponse = ($aResponse && is_array($aResponse) && array_key_exists('fcWrongCountry', $aResponse) === false);

        if ($blValidResponse) {
            $this->oxuser__fcpobonicheckdate = new Field(date('Y-m-d H:i:s'));
        }

        $this->save();
    }

    /**
     * Calculates scorevalue to make it usable in OXID
     *
     * @param string $sScoreValue
     * @return string
     * @see https://integrator.payone.de/jira/browse/OXID-136
     */
    protected function _fcpoCalculateBoniFromScoreValue(string $sScoreValue): string
    {
        $dScoreValue = (double)$sScoreValue;
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');

        if ($sFCPOBonicheck == 'CE') {
            $sScoreValue = (string)round(1000 - ($dScoreValue / 6));
        }

        return $sScoreValue;
    }

    /**
     * Parses response and set fallback if conditions match
     *
     * @param array $aResponse
     * @return array
     */
    protected function _fcpoCheckUseFallbackBoniversum(array $aResponse): array
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sScore = $aResponse['score'];
        $sAddresscheckType = $this->_fcpoGetAddressCheckType();

        $blUseFallBack = (
            $sScore == 'U' &&
            in_array($sAddresscheckType, ['BB', 'PB'])
        );

        if ($blUseFallBack) {
            $sFCPOBoniversumFallback = $oConfig->getConfigParam('sFCPOBoniversumFallback');
            $aResponse['score'] = $sFCPOBoniversumFallback;
        }

        return $aResponse;
    }

    /**
     * Check, correct and return addresschecktype
     *
     * @return string
     */
    protected function _fcpoGetAddressCheckType(): string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sBoniCheckType = $oConfig->getConfigParam('sFCPOBonicheck');
        $sAddressCheckType = $oConfig->getConfigParam('sFCPOAddresscheck');

        if ($sBoniCheckType == 'CE') {
            $sAddressCheckType = 'PB';
        }

        return $sAddressCheckType;
    }

    /**
     * Performing address check
     *
     * @return bool
     */
    protected function _fcpoPerformAddressCheck(): bool
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $this->_fcpoGetAddresscheckSetting();
        // early return a success if addresscheck is inactive
        if (!$sFCPOAddresscheck) return true;

        // get more addresscheck related settings
        $blFCPOCorrectAddress = (bool)$oConfig->getConfigParam('blFCPOCorrectAddress');
        $blFCPOCheckDelAddress = (bool)$oConfig->getConfigParam('blFCPOCheckDelAddress');

        // perform validations
        $blIsValidAddress = $this->_fcpoValidateAddress($blFCPOCorrectAddress);
        return $this->_fcpoValidateDelAddress($blIsValidAddress, $blFCPOCheckDelAddress);
    }

    /**
     * Returns addresscheck setting or false if inactive
     *
     * @return mixed bool/string
     */
    protected function _fcpoGetAddresscheckSetting(): mixed
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $oConfig->getConfigParam('sFCPOAddresscheck');
        return ($sFCPOAddresscheck == 'NO') ? false : $sFCPOAddresscheck;
    }

    /**
     * Validates address by requesting payone
     *
     * @param bool $blFCPOCorrectAddress
     * @return bool
     */
    protected function _fcpoValidateAddress(bool $blFCPOCorrectAddress): bool
    {
        //check billing address
        $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oPORequest->sendRequestAddresscheck($this);

        if ($aResponse === true) {
            // check has been performed recently
            $blIsValidAddress = true;
        } else {
            // address check has been triggered - validate the response
            $blIsValidAddress = $this->fcpoIsValidAddress($aResponse, $blFCPOCorrectAddress);
        }

        return $blIsValidAddress;
    }

    /**
     * Checks if the address given by the user matches the address returned by the PAYONE addresscheck API request
     *
     * @param array $aResponse
     * @param bool $blCorrectUserAddress
     * @return bool
     */
    protected function fcpoIsValidAddress(array $aResponse, bool $blCorrectUserAddress): bool
    {
        $blEarlyValidation = (
            $aResponse &&
            array_key_exists('fcWrongCountry', $aResponse) &&
            $aResponse['fcWrongCountry'] === true
        );

        // early return on quick check
        if ($blEarlyValidation) return true;

        // dig deeper, do corrections if configured
        return $this->_fcpoValidateResponse($aResponse, $blCorrectUserAddress);
    }

    /**
     * Validating response of address check
     *
     * @param array $aResponse
     * @param bool $blCorrectUserAddress
     * @return bool
     */
    protected function _fcpoValidateResponse(array $aResponse, bool $blCorrectUserAddress): bool
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $oUtilsView = $this->_oFcPoHelper->fcpoGetUtilsView();

        if ($aResponse['status'] == 'VALID') {
            return $this->_fcpoValidateUserDataByResponse($aResponse, $blCorrectUserAddress);
        } elseif ($aResponse['status'] == 'INVALID') {
            $sErrorMsg = $oLang->translateString('FCPO_ADDRESSCHECK_FAILED1') . $aResponse['customermessage'] . $oLang->translateString('FCPO_ADDRESSCHECK_FAILED2');
            $oUtilsView->addErrorToDisplay($sErrorMsg, false, true);
            return false;
        } elseif ($aResponse['status'] == 'ERROR') {
            $sErrorMsg = $oLang->translateString('FCPO_ADDRESSCHECK_FAILED1') . $aResponse['customermessage'] . $oLang->translateString('FCPO_ADDRESSCHECK_FAILED2');
            $oUtilsView->addErrorToDisplay($sErrorMsg, false, true);
            return false;
        }
        return false;
    }

    /**
     * Validate user data against request response and correct address if configured
     *
     * @param array $aResponse
     * @param bool $blCorrectUserAddress
     * @return bool
     */
    protected function _fcpoValidateUserDataByResponse(array $aResponse, bool $blCorrectUserAddress): bool
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $oUtilsView = $this->_oFcPoHelper->fcpoGetUtilsView();
        $mPersonstatus = $oConfig->getConfigParam('blFCPOAddCheck' . $aResponse['personstatus']);

        if ($mPersonstatus) {
            $sErrorMsg = $oLang->translateString('FCPO_ADDRESSCHECK_FAILED1') . $oLang->translateString('FCPO_ADDRESSCHECK_' . $aResponse['personstatus']) . $oLang->translateString('FCPO_ADDRESSCHECK_FAILED2');
            $oUtilsView->addErrorToDisplay($sErrorMsg, false, true);
            return false;
        } else {
            if ($blCorrectUserAddress) {
                if ($aResponse['firstname']) {
                    $this->oxuser__oxfname = new Field($aResponse['firstname']);
                }
                if ($aResponse['lastname']) {
                    $this->oxuser__oxlname = new Field($aResponse['lastname']);
                }
                if ($aResponse['streetname']) {
                    $this->oxuser__oxstreet = new Field($aResponse['streetname']);
                }
                if ($aResponse['streetnumber']) {
                    $this->oxuser__oxstreetnr = new Field($aResponse['streetnumber']);
                }
                if ($aResponse['zip']) {
                    $this->oxuser__oxzip = new Field($aResponse['zip']);
                }
                if ($aResponse['city']) {
                    $this->oxuser__oxcity = new Field($aResponse['city']);
                }
                $this->save();
            }
            // Country auch noch ?!? ( umwandlung iso nach id )
            // $this->oxuser__oxfname->value = $aResponse['country'];
            return true;
        }
    }

    /**
     * Validating delivery address
     *
     * @param bool $blIsValidAddress
     * @param bool $blFCPOCheckDelAddress
     * @return bool
     */
    protected function _fcpoValidateDelAddress(bool $blIsValidAddress, bool $blFCPOCheckDelAddress): bool
    {
        if ($blIsValidAddress && $blFCPOCheckDelAddress === true) {
            //check delivery address
            $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
            $aResponse = $oPORequest->sendRequestAddresscheck($this, true);

            if ($aResponse === false || $aResponse === true) {
                // false = No deliveryaddress given
                // true = Address-check has been skipped because the address has been checked before
                return true;
            }

            $blIsValidAddress = $this->fcpoIsValidAddress($aResponse, false);
        }

        return $blIsValidAddress;
    }

    /**
     * Unsetting groups
     *
     * @return void
     */
    public function fcpoUnsetGroups(): void
    {
        $this->_oGroups = null;
    }

    /**
     * Checks if user already exists
     *
     * @param string $sEmail
     * @return false|string
     * @throws DatabaseConnectionException
     */
    public function fcpoDoesUserAlreadyExist(string $sEmail): false|string
    {
        $sQuery = "SELECT oxid FROM oxuser WHERE oxusername = " . DatabaseProvider::getDb()->quote($sEmail) . " AND oxpassword != ''";
        $sUserId = DatabaseProvider::getDb()->getOne($sQuery);
        return $sUserId ?: false;
    }

    /**
     * Method checks if a user WITH password exists using the given email-address
     *
     * @param string $sEmailAddress
     * @param bool $blWithPasswd
     * @return bool
     */
    protected function _fcpoUserExists(string $sEmailAddress, bool $blWithPasswd = false): bool
    {
        $blReturn = false;
        $sUserOxid = $this->_fcpoGetUserOxidByEmail($sEmailAddress);
        if ($sUserOxid && !$blWithPasswd) {
            $blReturn = true;
        } elseif ($sUserOxid && $blWithPasswd) {
            $this->load($sUserOxid);
            $blReturn = (bool)$this->oxuser__oxpassword->value;
        }

        return $blReturn;
    }

    /**
     * Logs user into session
     *
     * @param string|null $sUserId
     * @return void
     */
    protected function _fcpoLogMeIn(?string $sUserId = null): void
    {
        if ($sUserId === null) {
            $sUserId = $this->getId();
        }
        $this->_oFcPoHelper->fcpoSetSessionVariable('usr', $sUserId);
    }

}
