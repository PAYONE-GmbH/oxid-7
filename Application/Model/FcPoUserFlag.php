<?php

namespace Fatchip\PayOne\Application\Model;


use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Model\BaseModel;
use sting;

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
    protected $_sCoreTbl = 'fcpouserflags';

    /**
     * Current class name
     *
     * @var string
     */
    protected $_sClassName = 'fcpouserflag';

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoHelper = null;

    /**
     * Centralized Database instance
     *
     * @var object
     */
    protected $_oFcPoDb = null;

    /**
     * The timestamp that should be used to determine penalty times
     *
     * @var string
     */
    protected $_sTimeStamp = null;

    /**
     * List of blocked paymentids
     *
     * @var array
     */
    protected $_aBlockedPaymentIds = array();

    /**
     * ID of n:m table assigned to this flag
     *
     * @var null
     */
    protected $_sAssignId = null;

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
    protected function _fcpoGetIdByErrorCode($sErrorCode)
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
     * @return mixed
     */
    public function load($sOXID)
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
    protected function _fcpoSetEffects()
    {
        $this->_fcpoSetPaymentsBlocked();
    }

    /**
     * Set blocked payments
     *
     * @return void
     */
    protected function _fcpoSetPaymentsBlocked()
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
     * @return void
     */
    protected function _fcpoAddBlockedPayment($sPaymentId)
    {
        $this->_aBlockedPaymentIds[] = $sPaymentId;
    }

    /**
     * Returns if userflag is in active use
     *
     * @return bool
     */
    public function fcpoGetIsActive()
    {
        return $this->_fcpoFlagIsActive();
    }

    /**
     * Checks if this userflag is active related to timestamp of flag assigment
     * and its set duration. Setting a duration of 0 means infinite active state
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
    protected function _fcpoGetTimeStampActiveUntil()
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
     * @return sting
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
            $oLang = $this->getLanguage();
            $sTranslatedMessage = (string)$oLang->translateString($sFlagDesc);
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
    protected function _fcpoGetMessageFromDb()
    {
        if ($this->_sAssignId) {
            $oDb = $this->_oFcPoHelper->fcpoGetDb();
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
     * @return array
     */
    public function fcpoGetBlockedPaymentIds()
    {
        $aReturn = array();
        $blFlagActive = $this->_fcpoFlagIsActive();

        if ($blFlagActive) {
            $aReturn = $this->_aBlockedPaymentIds;
        }

        return $aReturn;
    }
}
