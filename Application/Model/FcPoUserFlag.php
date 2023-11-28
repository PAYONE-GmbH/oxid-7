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
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Model\BaseModel;

/**
 * Created by PhpStorm.
 * User: andre
 * Date: 16.01.18
 * Time: 11:27
 */
class FcPoUserFlag extends BaseModel
{

    public $fcpouserflags__fcpoeffect;
    public $fcpouserflags__fcpoflagduration;
    public $fcpouserflags__fcpodesc;

    /**
     * Object core table name
     *
     * @var string
     */
    protected string $_sCoreTbl = 'fcpouserflags';

    /**
     * Current class name
     *
     * @var string
     */
    protected string $_sClassName = 'fcpouserflag';

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
     * The timestamp that should be used to determine penalty times
     *
     * @var string
     */
    protected string $_sTimeStamp;

    /**
     * List of blocked paymentids
     *
     * @var array
     */
    protected array $_aBlockedPaymentIds = [];

    /**
     * ID of n:m table assigned to this flag
     *
     * @var string
     */
    protected string $_sAssignId;


    /**
     * Init needed data
     */
    public function __construct()
    {
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
        $this->init($this->_sCoreTbl);
    }

    /**
     * Loads userflag by error code
     *
     * @param string $sErrorCode
     * @return mixed
     */
    public function fcpoLoadByErrorCode(string $sErrorCode): mixed
    {
        $sOxid = $this->_fcpoGetIdByErrorCode($sErrorCode);

        return $this->load($sOxid);
    }

    /**
     * Tries to fetch userflag by error code
     *
     * @param string $sErrorCode
     * @return string
     */
    protected function _fcpoGetIdByErrorCode(string $sErrorCode): string
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $sQuery = "SELECT OXID FROM "
            . $this->_sCoreTbl
            . " WHERE FCPOCODE=" . $oDb->quote($sErrorCode);

        return (string)$oDb->getOne($sQuery);
    }

    /**
     * Overloaded method to automatically set effects
     *
     * @param string $sOXID
     * @return mixed
     */
    public function load(string $sOXID): mixed
    {
        $mReturn = null;
        if ($mReturn !== false) {
            $this->_fcpoSetEffects();
        }

        return $mReturn;
    }

    /**
     * Sets effects by effect-code
     *
     * @return void
     */
    protected function _fcpoSetEffects(): void
    {
        $this->_fcpoSetPaymentsBlocked();
    }

    /**
     * Set blocked payments
     *
     * @return void
     */
    protected function _fcpoSetPaymentsBlocked(): void
    {
        $sEffectCode = $this->fcpouserflags__fcpoeffect->value;

        if ($sEffectCode === 'RPR') {
            // case Ratepay payments are blocked
            $this->_fcpoAddBlockedPayment('fcporp_bill');
        }
    }

    /**
     * Adds a payment id to blocked payments
     *
     * @param string $sPaymentId
     * @return void
     */
    protected function _fcpoAddBlockedPayment(string $sPaymentId): void
    {
        $this->_aBlockedPaymentIds[] = $sPaymentId;
    }

    /**
     * Returns if userflag is in active use
     *
     * @return bool
     */
    public function fcpoGetIsActive(): bool
    {
        return $this->_fcpoFlagIsActive();
    }

    /**
     * Checks if this userflag is active related to timestamp of flag assigment
     * and its set duration. Setting a duration of 0 means infinite active state
     *
     * @return bool
     */
    protected function _fcpoFlagIsActive(): bool
    {
        $iDurationHours = $this->fcpouserflags__fcpoflagduration->value;
        $iTimeStampActiveUntil = $this->_fcpoGetTimeStampActiveUntil();
        $iTimeStampNow = time();

        return $iTimeStampActiveUntil >= $iTimeStampNow || $iDurationHours === 0;
    }

    /**
     * Returns the time until flag is active
     *
     * @return int
     */
    protected function _fcpoGetTimeStampActiveUntil(): int
    {
        $iDurationHours = $this->fcpouserflags__fcpoflagduration->value;
        $iTimeStampFlagAssigned = strtotime($this->_sTimeStamp);
        $sTimeStringDuration = '+ ' . $iDurationHours . ' hours';
        $iTimeStampActiveUntil = strtotime($sTimeStringDuration, $iTimeStampFlagAssigned);

        return (int)$iTimeStampActiveUntil;
    }

    /**
     * Returns translated message, optional a customer message as fallback
     *
     * @param string $sCustomMessage
     * @return string
     */
    public function fcpoGetTranslatedMessage(string $sCustomMessage = ''): string
    {
        $sFlagDesc = $this->fcpouserflags__fcpodesc->value;
        $blSaveMessageInDb = (
            $sCustomMessage != '' &&
            $sFlagDesc == 'CUSTOM'
        );
        $blFetchMessageFromDb = (
            (
                $sCustomMessage == '' &&
                $sFlagDesc == 'CUSTOM'
            ) || $blSaveMessageInDb
        );

        if ($blSaveMessageInDb) {
            $this->fcpoSetDisplayMessage($sCustomMessage);
        }

        if ($blFetchMessageFromDb) {
            $sTranslatedMessage = $this->_fcpoGetMessageFromDb();
            $sTranslatedMessage = html_entity_decode($sTranslatedMessage);
        } else {
            // user flag has a defined translation string
            $oLang = $this->getLanguage();
            $sTranslatedMessage = (string)$oLang->translateString($sFlagDesc);
        }

        return $sTranslatedMessage;
    }

    /**
     * Sets custom display message to assigned id
     *
     * @param string $sMessage
     * @return void
     */
    public function fcpoSetDisplayMessage(string $sMessage): void
    {
        if ($this->_sAssignId) {
            // mandatory for persisting the message
            $oDb = $this->_oFcPoHelper->fcpoGetDb();
            $sQuery = "
                UPDATE fcpouser2flag 
                SET FCPODISPLAYMESSAGE=" . $oDb->quote($sMessage) . "
                WHERE OXID=" . $oDb->quote($this->_sAssignId) . "
                LIMIT 1
            ";
            $oDb->execute($sQuery);
        }
    }

    /**
     * Returns saved message
     *
     * @return string
     */
    protected function _fcpoGetMessageFromDb(): string
    {
        if ($this->_sAssignId) {
            $oDb = $this->_oFcPoHelper->fcpoGetDb();
            $sQuery = "
            SELECT FCPODISPLAYMESSAGE 
            FROM fcpouser2flag 
            WHERE OXID=" . $oDb->quote($this->_sAssignId);

            return (string)$oDb->getOne($sQuery);
        }

        return '';
    }

    /**
     * Setter for timestamp of when the user received the flag
     *
     * @param string $sTimeStamp
     * @return void
     */
    public function fcpoSetTimeStamp(string $sTimeStamp): void
    {
        $this->_sTimeStamp = $sTimeStamp;
    }

    /**
     * Sets assign id for current flag
     *
     * @param string $sOxid
     * @return void
     */
    public function fcpoSetAssignId(string $sOxid): void
    {
        $this->_sAssignId = $sOxid;
    }

    /**
     * Returns an array of payment ids which are currently
     *
     * @return array
     */
    public function fcpoGetBlockedPaymentIds(): array
    {
        $aReturn = [];
        $blFlagActive = $this->_fcpoFlagIsActive();

        if ($blFlagActive) {
            $aReturn = $this->_aBlockedPaymentIds;
        }

        return $aReturn;
    }

}
