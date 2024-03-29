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
use stdClass;

class FcPayOneUser extends FcPayOneUser_parent
{

    public $oxuser__oxpassword;
    public $oxuser__oxboni;
    public $oxuser__oxcountryid;
    public $oxuser__fcpobonicheckdate;
    /**
     * @var Fatchip\PayOne\Application\Model\oxField
     */
    public $oxuser__oxfname;
    /**
     * @var Fatchip\PayOne\Application\Model\oxField
     */
    public $oxuser__oxlname;
    /**
     * @var Fatchip\PayOne\Application\Model\oxField
     */
    public $oxuser__oxstreet;
    /**
     * @var Fatchip\PayOne\Application\Model\oxField
     */
    public $oxuser__oxstreetnr;
    /**
     * @var Fatchip\PayOne\Application\Model\oxField
     */
    public $oxuser__oxzip;
    /**
     * @var Fatchip\PayOne\Application\Model\oxField
     */
    public $oxuser__oxcity;
    /**
     * @var null
     */
    public $_oGroups;
    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper;

    /**
     * List of userflag ids of user
     *
     * @var array
     */
    protected $_aUserFlags;

    /**
     * Blocked payments for user (unvalidated)
     *
     * @var array
     */
    protected $_aBlockedPaymentIds = [];

    /**
     * Forbidden payments for user (validated)
     *
     * @var array
     */
    protected $_aForbiddenPaymentIds = [];
    /** @var array<string, int> */
    private const A_MAP = ['G' => 500, 'Y' => 300, 'R' => 100];

    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns if given payment is allowed by flags
     *
     * @param $sPaymentId
     * @return bool
     */
    public function fcpoPaymentCurrentlyAllowedByFlags($sPaymentId)
    {
        $aForbiddenPayments = $this->fcpoGetForbiddenPaymentIds();

        return !in_array($sPaymentId, $aForbiddenPayments);
    }

    /**
     * Returns an array of forbidden paymentids
     *
     * 
     * @return array
     */
    public function fcpoGetForbiddenPaymentIds()
    {
        $this->_fcpoAddForbiddenByUserFlags();

        return $this->_aForbiddenPaymentIds;
    }

    /**
     * Adds assigned payone userflags to user
     *
     * @param $aForbiddenPayments
     */
    private function _fcpoAddForbiddenByUserFlags(): void
    {
        $aUserFlags = $this->fcpoGetFlagsOfUser();
        foreach ($aUserFlags as $aUserFlag) {
            $aPaymentsNotAllowedByFlag = $aUserFlag->fcpoGetBlockedPaymentIds();
            $this->_aForbiddenPaymentIds = array_merge($this->_aForbiddenPaymentIds, $aPaymentsNotAllowedByFlag);
        }
    }

    /**
     * Returns current userflags
     *
     * @return array
     */
    public function fcpoGetFlagsOfUser()
    {
        if ($this->_aUserFlags === null) {
            $this->_fcpoSetUserFlags();
        }
        return $this->_aUserFlags;
    }

    /**
     * Sets current flags of user
     *
     * 
     * @return array
     */
    private function _fcpoSetUserFlags(): void
    {
        $this->_aUserFlags = [];
        $aUserFlagInfos = $this->_fcpoGetUserFlagInfos();
        foreach ($aUserFlagInfos as $aUserFlagInfo) {
            $sOxid = $aUserFlagInfo->sOxid;
            $sUserFlagId = $aUserFlagInfo->sUserFlagId;
            $sTimeStamp = $aUserFlagInfo->sTimeStamp;


            $oUserFlag = oxNew(FcPouserflag::class);
            if ($oUserFlag->load($sUserFlagId)) {
                $oUserFlag->fcpoSetAssignId($sOxid);
                $oUserFlag->fcpoSetTimeStamp($sTimeStamp);
                $this->_aUserFlags[$sUserFlagId] = $oUserFlag;
            }
        }
    }

    /**
     * Returns an array of userflag infos mandatory for
     * determing effects
     *
     *
     * @return \stdClass[]
     */
    private function _fcpoGetUserFlagInfos(): array
    {
        $aUserFlagInfos = [];
        $oDb = $this->_oFcpoHelper->fcpoGetDb(true);
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
    public function load($sOXID)
    {
        $this->_fcpoSetUserFlags();
        return null;
    }

    /**
     * Adds (or refreshes) a payone user flag
     *
     * @param $oUserFlag
     */
    public function fcpoAddPayoneUserFlag($oUserFlag): void
    {
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        $oUtilsObject = $this->_oFcpoHelper->getFactoryObject('oxUtilsObject');
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

        $oDb->Execute($sQuery);
    }

    /**
     * Method manages adding/merging userdata
     *
     * @param array $aResponse
     */
    public function fcpoSetAmazonOrderReferenceDetailsResponse($aResponse): void
    {
        $sAmazonEmailAddress = $this->_fcpoAmazonEmailEncode($aResponse['add_paydata[email]']);
        $aResponse['add_paydata[email]'] = $sAmazonEmailAddress;
        $this->_fcpoAddOrUpdateAmazonUser($aResponse);
    }

    /**
     * Makes this Email unique to be able to handle amazon users different from standard users
     * Currently the email address simply gets a prefix
     *
     * @param $sEmail
     * @return string
     */
    private function _fcpoAmazonEmailEncode($sEmail)
    {
        $oViewConf = $this->_oFcpoHelper->getFactoryObject('oxViewConfig');

        return $oViewConf->fcpoAmazonEmailEncode($sEmail);
    }

    /**
     * Checks if a user should be added or updated, redirects to matching method
     * and logs user in
     *
     * @param $aResponse
     */
    private function _fcpoAddOrUpdateAmazonUser($aResponse): void
    {
        $sAmazonEmailAddress = $aResponse['add_paydata[email]'];
        $blUserExists = $this->_fcpoUserExists($sAmazonEmailAddress);
        $sUserId = $blUserExists ? $this->_fcpoUpdateAmazonUser($aResponse) : $this->_fcpoAddAmazonUser($aResponse);
        // logoff and on again
        $this->_fcpoLogMeIn($sUserId);
    }

    /**
     * Method checks if a user WITH password exists using the given email-address
     *
     * @param string $sEmailAddress
     * @param bool   $blWithPasswd
     * @return bool
     */
    private function _fcpoUserExists($sEmailAddress, $blWithPasswd = false)
    {
        $blReturn = false;
        $sUserOxid = $this->_fcpoGetUserOxidByEmail($sEmailAddress);
        if ($sUserOxid && !$blWithPasswd) {
            $blReturn = true;
        } elseif ($sUserOxid && $blWithPasswd) {
            $this->load($sUserOxid);
            $blReturn = (bool) $this->oxuser__oxpassword->value;
        }

        return $blReturn;
    }

    /**
     * Method delivers OXID of a user by offering an email address or false if email does not exist
     *
     * @param string $sAmazonEmailAddress
     * @return mixed
     */
    private function _fcpoGetUserOxidByEmail($sAmazonEmailAddress)
    {
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        $sQuery = "SELECT OXID FROM oxuser WHERE OXUSERNAME=" . $oDb->quote($sAmazonEmailAddress);

        return $oDb->GetOne($sQuery);
    }

    /**
     * Updating user. Checking current address, if different add new address as additional address to user
     * iff current address is not known until now
     *
     * @param $aResponse
     * @return string
     */
    private function _fcpoUpdateAmazonUser($aResponse)
    {
        $sAmazonEmailAddress = $aResponse['add_paydata[email]'];
        $sUserOxid = $this->_fcpoGetUserOxidByEmail($sAmazonEmailAddress);

        $oUser = $this->_oFcpoHelper->getFactoryObject('oxUser');
        $oUser->load($sUserOxid);

        $aStreetParts = $this->_fcpoSplitStreetAndStreetNr($aResponse['add_paydata[billing_street]']);
        $sCountryId = $this->_fcpoGetCountryIdByIso2($aResponse['add_paydata[billing_country]']);

        $oUser->oxuser__oxusername = new oxField($aResponse['add_paydata[email]']);
        $oUser->oxuser__oxstreet = new oxField($aStreetParts['street']);
        $oUser->oxuser__oxstreetnr = new oxField($aStreetParts['streetnr']);
        $oUser->oxuser__oxzip = new oxField($aResponse['add_paydata[billing_zip]']);
        $oUser->oxuser__oxfon = new oxField($aResponse['add_paydata[billing_telephonenumber]']);
        $oUser->oxuser__oxfname = new oxField(trim((string) $aResponse['add_paydata[billing_firstname]']));
        $oUser->oxuser__oxlname = new oxField(trim((string) $aResponse['add_paydata[billing_lastname]']));
        $oUser->oxuser__oxcity = new oxField($aResponse['add_paydata[billing_city]']);
        $oUser->oxuser__oxcountryid = new oxField($sCountryId);
        $oUser->addToGroup('oxidnotyetordered');

        $oUser->save();

        // add and set deliveryaddress
        $this->_fcpoAddDeliveryAddress($aResponse, $sUserOxid);

        return $sUserOxid;
    }

    /**
     * Method splits street and streetnr from string
     *
     * @param string $sStreetAndStreetNr
     * @return array
     */
    private function _fcpoSplitStreetAndStreetNr($sStreetAndStreetNr)
    {
        $aReturn = [];
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
     * @param $sIso2Country
     * @return string
     */
    private function _fcpoGetCountryIdByIso2($sIso2Country)
    {
        $oCountry = $this->_oFcpoHelper->getFactoryObject('oxCountry');

        return $oCountry->getIdByCode($sIso2Country);
    }

    /**
     * Method adds a delivery address to user and directly set the deladrid session variable
     *
     * @param array  $aResponse
     * @param string $sUserOxid
     */
    public function _fcpoAddDeliveryAddress($aResponse, $sUserOxid, $blFixUtf8 = false): void
    {
        if ($blFixUtf8) {
            $aResponse = array_map('utf8_decode', $aResponse);
        }
        $aStreetParts = $this->_fcpoSplitStreetAndStreetNr($aResponse['add_paydata[shipping_street]']);
        $sCountryId = $this->_fcpoGetCountryIdByIso2($aResponse['add_paydata[shipping_country]']);
        $sFirstName = trim((string) $aResponse['add_paydata[shipping_firstname]']);
        $sLastName = trim((string) $aResponse['add_paydata[shipping_lastname]']);

        if (empty($sLastName)) {
            $aNameParts = $this->_fcpoSplitNameParts($sFirstName);
            $sFirstName = $aNameParts['firstname'];
            $sLastName = $aNameParts['lastname'];
        }

        $oAddress = $this->_oFcpoHelper->getFactoryObject('oxaddress');
        $oAddress->oxaddress__oxuserid = new oxField($sUserOxid);
        $oAddress->oxaddress__oxaddressuserid = new oxField($sUserOxid);
        $oAddress->oxaddress__oxfname = new oxField($sFirstName);
        $oAddress->oxaddress__oxlname = new oxField($sLastName);
        $oAddress->oxaddress__oxstreet = new oxField($aStreetParts['street']);
        $oAddress->oxaddress__oxstreetnr = new oxField($aStreetParts['streetnr']);
        $oAddress->oxaddress__oxfon = new oxField($aResponse['add_paydata[shipping_telephonenumber]']);
        $oAddress->oxaddress__oxcity = new oxField($aResponse['add_paydata[shipping_city]']);
        $oAddress->oxaddress__oxcountry = new oxField($aResponse['add_paydata[shipping_country]']);
        $oAddress->oxaddress__oxcountryid = new oxField($sCountryId);
        $oAddress->oxaddress__oxzip = new oxField($aResponse['add_paydata[shipping_zip]']);
        $oAddress->oxaddress__oxaddinfo = new oxField($aResponse['add_paydata[shipping_addressaddition]']);

        // check if address exists
        $sEncodedDeliveryAddress = $oAddress->getEncodedDeliveryAddress();
        $blExists = $this->_fcpoCheckAddressExists($sEncodedDeliveryAddress);
        if ($blExists) {
            $oAddress->load($sEncodedDeliveryAddress);
        } else {
            $oAddress->setId($sEncodedDeliveryAddress);
            $oAddress->save();
        }

        $this->_oFcpoHelper->fcpoSetSessionVariable('deladrid', $sEncodedDeliveryAddress);
    }

    /**
     * Takes a complete name string and seperates into first and lastname
     *
     * @param $sSingleNameString
     * @return array{firstname: string, lastname: string}
     */
    private function _fcpoSplitNameParts(string $sSingleNameString): array
    {
        $aReturn = [];
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
     * @param $sEncodedDeliveryAddress
     * @return bool
     */
    private function _fcpoCheckAddressExists($sEncodedDeliveryAddress)
    {
        $oAddress = $this->_oFcpoHelper->getFactoryObject('oxaddress');
        $blReturn = false;
        if ($oAddress->load($sEncodedDeliveryAddress)) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Method adds a new amazon user into OXIDs user system. User won't get a password
     *
     * @param $aResponse
     * @return string
     */
    private function _fcpoAddAmazonUser($aResponse)
    {
        $aStreetParts = $this->_fcpoSplitStreetAndStreetNr($aResponse['add_paydata[billing_street]']);
        $sCountryId = $this->_fcpoGetCountryIdByIso2($aResponse['add_paydata[billing_country]']);

        $oUser = $this->_oFcpoHelper->getFactoryObject('oxUser');
        $sUserOxid = $oUser->getId();
        $oUser->oxuser__oxusername = new oxField($aResponse['add_paydata[email]']);
        $oUser->oxuser__oxstreet = new oxField($aStreetParts['street']);
        $oUser->oxuser__oxstreetnr = new oxField($aStreetParts['streetnr']);
        $oUser->oxuser__oxzip = new oxField($aResponse['add_paydata[billing_zip]']);
        $oUser->oxuser__oxfon = new oxField($aResponse['add_paydata[billing_telephonenumber]']);
        $oUser->oxuser__oxfname = new oxField($aResponse['add_paydata[billing_firstname]']);
        $oUser->oxuser__oxlname = new oxField($aResponse['add_paydata[billing_lastname]']);
        $oUser->oxuser__oxcity = new oxField($aResponse['add_paydata[billing_city]']);
        $oUser->oxuser__oxcountryid = new oxField($sCountryId);
        $oUser->addToGroup('oxidnotyetordered');

        $oUser->save();

        // add and set deliveryaddress
        $this->_fcpoAddDeliveryAddress($aResponse, $sUserOxid);

        return $sUserOxid;
    }

    /**
     * Logs user into session
     *
     */
    private function _fcpoLogMeIn($sUserId = null): void
    {
        if ($sUserId === null) {
            $sUserId = $this->getId();
        }
        $this->_oFcpoHelper->fcpoSetSessionVariable('usr', $sUserId);
    }

    /**
     * Sets the user scorevalue to red (=100) if user declines
     * boni check
     *
     * @param int $iValue
     */
    public function fcpoSetScoreOnNonApproval($iValue = 100): void
    {
        $this->oxuser__oxboni->value = $iValue;
        $this->save();
    }

    /**
     * Returns country iso code of users country
     *
     * @param int $iVersion
     * @return string
     */
    public function fcpoGetUserCountryIso($iVersion = 2)
    {
        $oCountry = $this->_oFcpoHelper->getFactoryObject('oxCountry');
        if (!$oCountry->load($this->oxuser__oxcountryid->value)) {
            return '';
        }
        $sField = "oxcountry__oxisoalpha" . $iVersion;

        return $oCountry->$sField->value;
    }

    /**
     * Check the credit-worthiness of the user with the consumerscore or addresscheck request to the PAYONE API
     *
     * @return bool
     */
    public function checkAddressAndScore($blCheckAddress = true, $blCheckBoni = true)
    {
        // in general we assume that everything is fine with score and address
        $blBoniChecked = $blAddressValid = true;

        // let's see what should be checked
        if ($blCheckBoni) {
            $blBoniChecked = $this->_fcpoPerformBoniCheck();
        }
        if ($blCheckAddress) {
            $blAddressValid = $this->_fcpoPerformAddressCheck();
        }

        // merge results
        $blChecksValid = ($blBoniChecked && $blAddressValid);

        return $blChecksValid;
    }

    /**
     * Performing boni check on user
     *
     * 
     * @return void
     */
    private function _fcpoPerformBoniCheck()
    {
        $sFCPOBonicheck = $this->_fcpoGetBoniSetting();
        $blBoniCheckNeeded = $this->isBonicheckNeeded();

        // early return as success if bonicheck is inactive or not needed
        if (!$sFCPOBonicheck || !$blBoniCheckNeeded) {
            return true;
        }

        return $this->_fcpoValidateBoni();
    }

    /**
     * Returns boni setting or false if inactive
     *
     * 
     * @return mixed bool/string
     */
    private function _fcpoGetBoniSetting()
    {
        // get raw configured setting
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');

        // multiple inactivity checks due to php is a non type checking language
        $blBoniInactive = ($sFCPOBonicheck == -1 || $sFCPOBonicheck == '-1' || !$sFCPOBonicheck);

        // sum it up
        $mFCPOBonicheck = ($blBoniInactive) ? false : $sFCPOBonicheck;

        return $mFCPOBonicheck;
    }

    /**
     * Check if the credit-worthiness has to be checked
     */
    private function isBonicheckNeeded(): bool
    {
        return (
            $this->oxuser__oxboni->value == $this->getBoni() ||
            $this->isNewBonicheckNeeded()
        ) &&
        $this->isBonicheckNeededForBasket();
    }

    /**
     * Overrides oxid standard method getBoni()
     * Sets it to value defined in the admin area of PAYONE if it was configured
     *
     * @return int
     * @extend getBoni()
     */
    public function getBoni()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $iDefaultBoni = $oConfig->getConfigParam('sFCPODefaultBoni');
        if ($iDefaultBoni !== null && is_numeric($iDefaultBoni)) {
            return $iDefaultBoni;
        }
        return null;
    }

    /**
     * Check if the credit-worthiness of the user has to be checked again
     *
     * @return bool
     */
    private function isNewBonicheckNeeded()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sTimeLastCheck = strtotime((string) $this->oxuser__fcpobonicheckdate->value);
        $iEnduranceBoniCheck = (int)$oConfig->getConfigParam('sFCPODurabilityBonicheck');
        $sTimeout = (time() - (60 * 60 * 24 * $iEnduranceBoniCheck));

        return $sTimeout > $sTimeLastCheck;
    }

    /**
     * Check if the current basket sum exceeds the minimum sum for the credit-worthiness check
     *
     * @return bool
     */
    private function isBonicheckNeededForBasket()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $iStartlimitBonicheck = $oConfig->getConfigParam('sFCPOStartlimitBonicheck');

        $blReturn = true;
        if ($iStartlimitBonicheck && is_numeric($iStartlimitBonicheck)) {
            $oSession = $this->_oFcpoHelper->fcpoGetSession();
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
     * 
     * @return void
     */
    private function _fcpoValidateBoni(): bool
    {
        // Consumerscore
        $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oPORequest->sendRequestConsumerscore($this);
        $this->fcpoSetBoni($aResponse);

        return true;
    }

    /**
     * Sets the credit-worthiness of the user
     *
     * @param array $aResponse response of a API request
     *
     * @return null
     */
    private function fcpoSetBoni($aResponse): void
    {
        $boni = 100;
        if ($aResponse['scorevalue']) {
            $boni = $this->_fcpoCalculateBoniFromScoreValue($aResponse['scorevalue']);
        } else {
            $aResponse = $this->_fcpoCheckUseFallbackBoniversum($aResponse);
            if (isset(self::A_MAP[$aResponse['score']])) {
                $boni = self::A_MAP[$aResponse['score']];
            }
        }

        $this->oxuser__oxboni->value = $boni;

        $blValidResponse = ($aResponse && is_array($aResponse) && !array_key_exists('fcWrongCountry', $aResponse));

        if ($blValidResponse) {
            $this->oxuser__fcpobonicheckdate = new oxField(date('Y-m-d H:i:s'));
        }

        $this->save();
    }

    /**
     * Calculates scorevalue to make it usable in OXID
     *
     * @param $sScoreValue
     * @return string
     * @see https://integrator.payone.de/jira/browse/OXID-136
     */
    private function _fcpoCalculateBoniFromScoreValue($sScoreValue)
    {
        $dScoreValue = (double)$sScoreValue;
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOBonicheck = $oConfig->getConfigParam('sFCPOBonicheck');

        if ($sFCPOBonicheck == 'CE') {
            $sScoreValue = (string)round(1000 - ($dScoreValue / 6), 0);
        }

        return $sScoreValue;
    }

    /**
     * Parses response and set fallback if conditions match
     *
     * @param $aResponse
     * @return array
     */
    private function _fcpoCheckUseFallbackBoniversum($aResponse)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sScore = $aResponse['score'];
        $sAddresscheckType = $this->_fcpoGetAddressCheckType();

        $blUseFallBack = (
            $sScore == 'U' &&
            in_array($sAddresscheckType, ['BB', 
    'PB'])
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
     * 
     * @return string
     */
    private function _fcpoGetAddressCheckType()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
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
     * 
     * @return bool
     */
    private function _fcpoPerformAddressCheck()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $this->_fcpoGetAddresscheckSetting();
        // early return a success if addresscheck is inactive
        if (!$sFCPOAddresscheck) {
            return true;
        }

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
     * 
     * @return mixed bool/string
     */
    private function _fcpoGetAddresscheckSetting()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sFCPOAddresscheck = $oConfig->getConfigParam('sFCPOAddresscheck');

        return ($sFCPOAddresscheck == 'NO') ? false : $sFCPOAddresscheck;
    }

    /**
     * Validates address by requesting payone
     *
     * @param string $sFCPOBonicheck
     * @param bool   $blCheckedBoni
     * @return bool
     */
    private function _fcpoValidateAddress(bool $blFCPOCorrectAddress)
    {
        //check billing address
        $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oPORequest->sendRequestAddresscheck($this);

        return $aResponse === true ? true : $this->fcpoIsValidAddress($aResponse, $blFCPOCorrectAddress);
    }

    /**
     * Checks if the address given by the user matches the address returned by the PAYONE addresscheck API request
     *
     * @return bool
     */
    private function fcpoIsValidAddress($aResponse, bool $blCorrectUserAddress)
    {
        $blEarlyValidation = (
            $aResponse &&
            is_array($aResponse) &&
            array_key_exists('fcWrongCountry', $aResponse) &&
            $aResponse['fcWrongCountry'] === true
        );

        // early return on quick check
        if ($blEarlyValidation) {
            return true;
        }

        // dig deeper, do corrections if configured
        $blReturn = $this->_fcpoValidateResponse($aResponse, $blCorrectUserAddress);

        return $blReturn;
    }

    /**
     * Validating response of address check
     *
     * @param array $aResponse
     * @return boolean
     */
    private function _fcpoValidateResponse($aResponse, bool $blCorrectUserAddress)
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $oUtilsView = $this->_oFcpoHelper->fcpoGetUtilsView();

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
    }

    /**
     * Validate user data against request response and correct address if configured
     *
     * @param array $aResponse
     * @return boolean
     */
    private function _fcpoValidateUserDataByResponse($aResponse, bool $blCorrectUserAddress)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $oUtilsView = $this->_oFcpoHelper->fcpoGetUtilsView();
        $mPersonstatus = $oConfig->getConfigParam('blFCPOAddCheck' . $aResponse['personstatus']);

        if ($mPersonstatus) {
            $sErrorMsg = $oLang->translateString('FCPO_ADDRESSCHECK_FAILED1') . $oLang->translateString('FCPO_ADDRESSCHECK_' . $aResponse['personstatus']) . $oLang->translateString('FCPO_ADDRESSCHECK_FAILED2');
            $oUtilsView->addErrorToDisplay($sErrorMsg, false, true);
            return false;
        } else {
            if ($blCorrectUserAddress) {
                if ($aResponse['firstname']) {
                    $this->oxuser__oxfname = new oxField($aResponse['firstname']);
                }
                if ($aResponse['lastname']) {
                    $this->oxuser__oxlname = new oxField($aResponse['lastname']);
                }
                if ($aResponse['streetname']) {
                    $this->oxuser__oxstreet = new oxField($aResponse['streetname']);
                }
                if ($aResponse['streetnumber']) {
                    $this->oxuser__oxstreetnr = new oxField($aResponse['streetnumber']);
                }
                if ($aResponse['zip']) {
                    $this->oxuser__oxzip = new oxField($aResponse['zip']);
                }
                if ($aResponse['city']) {
                    $this->oxuser__oxcity = new oxField($aResponse['city']);
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
     * @return boolean
     */
    private function _fcpoValidateDelAddress(bool $blIsValidAddress, bool $blFCPOCheckDelAddress)
    {
        if ($blIsValidAddress && $blFCPOCheckDelAddress) {
            //check delivery address
            $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
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
     */
    public function fcpoUnsetGroups(): void
    {
        $this->_oGroups = null;
    }
}
