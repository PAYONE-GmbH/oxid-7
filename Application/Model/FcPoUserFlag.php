<?php
/**
 * Created by PhpStorm.
 * User: andre
 * Date: 16.01.18
 * Time: 11:27
 */

namespace Fatchip\PayOne\Application\Model;

use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Model\BaseModel;

final class FcPoUserFlag extends BaseModel
{

    /**
     * Object core table name
     *
     * @var string
     */
    private const S_CORE_TBL = 'fcpouserflags';

    /**
     * Current class name
     *
     * @var string
     */
    protected $_sClassName = FcPoUserFlag::class;

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    private $_oFcpoHelper;

    /**
     * The timestamp that should be used to determine penalty times
     */
    private ?string $_sTimeStamp = null;

    /**
     * List of blocked paymentids
     */
    private array $_aBlockedPaymentIds = [];

    /**
     * ID of n:m table assigned to this flag
     *
     * @var null
     */
    private $_sAssignId;

    /**
     * Init needed data
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
        $this->init(self::S_CORE_TBL);
    }

    /**
     * Loads userflag by error code
     *
     * @param $sErrorCode
     * @return mixed
     */
    public function fcpoLoadByErrorCode($sErrorCode)
    {
        $sOxid = $this->_fcpoGetIdByErrorCode($sErrorCode);

        return $this->load($sOxid);
    }

    /**
     * Tryes to fetch userflag by error code
     *
     * @param $sErrorCode
     * @return string
     */
    private function _fcpoGetIdByErrorCode($sErrorCode)
    {
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        $sQuery = "SELECT OXID FROM "
            . self::S_CORE_TBL
            . " WHERE FCPOCODE=" . $oDb->quote($sErrorCode);

        return (string)$oDb->GetOne($sQuery);
    }

    /**
     * Overloaded method to automatically set effects
     *
     * @return mixed
     */
    public function load($sOXID)
    {
        $mReturn = parent::load($sOXID);
        if ($mReturn) {
            $this->_fcpoSetEffects();
        }

        return $mReturn;
    }

    /**
     * Sets effects by effect-code
     *
     * 
     */
    private function _fcpoSetEffects(): void
    {
        $this->_fcpoSetPaymentsBlocked();
    }

    /**
     * Set blocked payments
     *
     * 
     */
    private function _fcpoSetPaymentsBlocked(): void
    {
        $sEffectCode = $this->fcpouserflags__fcpoeffect->value;

        if ($sEffectCode === 'RPR') {
            // case ratpay payments are blocked
            $this->_fcpoAddBlockedPayment('fcporp_bill');
        }
    }

    /**
     * Adds a payment id to blocked payments
     *
     * @param $sPaymentId
     */
    private function _fcpoAddBlockedPayment(string $sPaymentId): void
    {
        $this->_aBlockedPaymentIds[] = $sPaymentId;
    }

    /**
     * Returns if userflag is in active use
     *
     */
    public function fcpoGetIsActive(): bool
    {
        return $this->_fcpoFlagIsActive();
    }

    /**
     * Checks if this userflag is active related to timestamp of flag assigment
     * and its set duration. Setting a duration of 0 means infinite active state
     *
     * 
     */
    private function _fcpoFlagIsActive(): bool
    {
        $iDurationHours = $this->fcpouserflags__fcpoflagduration->value;
        $iTimeStampActiveUntil = $this->_fcpoGetTimeStampActiveUntil();
        $iTimeStampNow = time();

        return $iTimeStampActiveUntil >= $iTimeStampNow || $iDurationHours === 0;
    }

    /**
     * Returns the time until flag is active
     *
     * 
     * @return int
     */
    private function _fcpoGetTimeStampActiveUntil()
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
     * @param $sCustomMessage
     * @return string
     */
    public function fcpoGetTranslatedMessage($sCustomMessage = '')
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
            $language = $this->getLanguage();
            $sTranslatedMessage = (string)$language->translateString($sFlagDesc);
        }

        return $sTranslatedMessage;
    }

    /**
     * Sets custom display message to assigned id
     *
     * @param $sMessage
     */
    public function fcpoSetDisplayMessage($sMessage): void
    {
        if ($this->_sAssignId) {
            // mandatory for persisting the message
            $oDb = $this->_oFcpoHelper->fcpoGetDb();
            $sQuery = "
                UPDATE fcpouser2flag 
                SET FCPODISPLAYMESSAGE=" . $oDb->quote($sMessage) . "
                WHERE OXID=" . $oDb->quote($this->_sAssignId) . "
                LIMIT 1
            ";
            $oDb->Execute($sQuery);
        }
    }

    /**
     * Returns saved message
     *
     * 
     * @return string
     */
    private function _fcpoGetMessageFromDb()
    {
        $oDb = null;
        $sQuery = null;
        if ($this->_sAssignId) {
            $oDb = $this->_oFcpoHelper->fcpoGetDb();
            $sQuery = "
          SELECT FCPODISPLAYMESSAGE 
          FROM fcpouser2flag 
          WHERE OXID=" . $oDb->quote($this->_sAssignId);
        }
        return (string)$oDb->getOne($sQuery);
    }

    /**
     * Setter for timestamp of when the user received the flag
     */
    public function fcpoSetTimeStamp(string $sTimeStamp): void
    {
        $this->_sTimeStamp = $sTimeStamp;
    }

    /**
     * Sets assign id for current flag
     *
     * @param $sOxid
     */
    public function fcpoSetAssignId($sOxid): void
    {
        $this->_sAssignId = $sOxid;
    }

    /**
     * Returns an array of paymentids which are currently
     *
     * 
     * @return array
     */
    public function fcpoGetBlockedPaymentIds()
    {
        $aReturn = [];
        $blFlagActive = $this->_fcpoFlagIsActive();

        if ($blFlagActive) {
            $aReturn = $this->_aBlockedPaymentIds;
        }

        return $aReturn;
    }
}
