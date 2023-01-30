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

namespace Fatchip\PayOne;

use Exception;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\UtilsObject;
use oxUtilsObject;

class FcPayOneTransactionStatusBase extends BaseModel
{
    protected array $_aShopList;

    protected string $_sLogFile = 'log/fcpo_message_forwarding.log';

    protected string$_sExceptionLog = 'log/fcpo_statusmessage_exception.log';

    protected Order $_oFcOrder;

    protected UtilsObject $_oUtilsObject;
    /** @var string */
    private const S_QUERY = "SELECT oxid FROM oxshops";

    /**
     * Check if key is available and valid. Throw exception if not
     *
     *
     * @return void
     * @throws Exception
     */
    protected function _isKeyValid(): void
    {
        if (defined('STDIN')) {
            return;
        }

        $sKey = $this->fcGetPostParam('key');
        if ($sKey === '' || $sKey === '0') {
            throw new Exception('Key missing!');
        }

        $aKeys = [...array_values($this->_getConfigParams('sFCPOPortalKey')), ...array_values($this->_getConfigParams('sFCPOSecinvoicePortalKey'))];
        $blValid = false;
        foreach ($aKeys as $aKey) {
            if (md5((string) $aKey) !== $sKey) {
                continue;
            }
            $blValid = true;
            break;
        }

        if (!$blValid) {
            throw new Exception('Invalid key!');
        }
    }

    /**
     * Check and return post parameter
     *
     * @param string $sKey
     * @return string
     */
    public function fcGetPostParam(string $sKey): string
    {
        $sReturn = '';
        $mValue = filter_input(INPUT_GET, $sKey, FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$mValue) {
            $mValue = filter_input(INPUT_POST, $sKey, FILTER_SANITIZE_SPECIAL_CHARS);
        }
        if ($mValue) {
            $sReturn = utf8_encode((string) $mValue);
        }

        return $sReturn;
    }

    /**
     * @param string $sParam
     * @return array
     */
    protected function _getConfigParams(string $sParam): array
    {
        $aShops = $this->_getShopList();
        $aParams = [];
        foreach ($aShops as $aShop) {
            $mValue = $this-> getConfig()->getShopConfVar($sParam, $aShop);
            if ($mValue) {
                $aParams[$aShop] = $mValue;
            }
        }

        return $aParams;
    }

    /**
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function _getShopList(): array
    {
        if ($this->_aShopList === null) {
            $aShops = [];
            $aRows = DatabaseProvider::getDb()->getAll(self::S_QUERY);

            foreach ($aRows as $aRow) {
                $aShops[] = $aRow[0];
            }

            $this->_aShopList = $aShops;
        }
        return $this->_aShopList;
    }

    /**
     * Logs exception for later analysis
     *
     * @param $sMessage
     * @return void
     */
    protected function _logException(string $sMessage): void
    {
        $sBasePath = __DIR__ . "/../../../";
        $sLogFilePath = $sBasePath . $this->_sExceptionLog;
        $sPrefix = "[" . date('Y-m-d H:i:s') . "] ";
        $sFullMessage = $sPrefix . $sMessage . "\n";

        $oLogFile = fopen($sLogFilePath, 'a');
        fwrite($oLogFile, $sFullMessage);
        fclose($oLogFile);
    }

    /**
     * Adding param
     *
     * @param string $sKey
     * @param        $mValue
     * @return string
     */
    protected function _addParam(string $sKey, $mValue): string
    {
        $sParams = '';
        if (is_array($mValue)) {
            foreach ($mValue as $sKey2 => $mValue2) {
                $sParams .= $this->_addParam($sKey . '[' . $sKey2 . ']', $mValue2);
            }
        } else {
            $sParams .= "&" . $sKey . "=" . urlencode((string) $mValue);
        }
        return $sParams;
    }

    /**
     * Method collects redirect targets and add them to statusforward queue
     *
     * @param string      $sStatusmessageId
     * @param string|null $sPayoneStatus
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function _addQueueEntries(string $sStatusmessageId, string $sPayoneStatus = null): void
    {
        $sQuery = null;
        $aRows = null;
        if ($sPayoneStatus === null) {
            $sPayoneStatus = $this->fcGetPostParam('txaction');
        }
        $sQuery = "
            SELECT 
                OXID
            FROM 
                fcpostatusforwarding 
            WHERE 
                fcpo_payonestatus = '{$sPayoneStatus}'";
        $aRows = DatabaseProvider::getDb()->getAll($sQuery);
        $this->_logForwardMessage('Add fowardings to queue: ' . print_r($aRows, true));
        foreach ($aRows as $aRow) {
            $sForwardId = (string)$aRow[0];
            $this->_addToQueue($sStatusmessageId, $sForwardId);
        }
    }

    /**
     * Logs given message if logging is activated
     *
     * @param string $sMessage
     * @return void
     */
    protected function _logForwardMessage(string $sMessage): void
    {
        $blLoggingAllowed = $this->_fcCheckLoggingAllowed();
        if (!$blLoggingAllowed) {
            return;
        }

        $sBasePath = __DIR__ . "/../../../";
        $sLogFilePath = $sBasePath . $this->_sLogFile;
        $sPrefix = "[" . date('Y-m-d H:i:s') . "] ";
        $sFullMessage = $sPrefix . $sMessage . "\n";

        $oLogFile = fopen($sLogFilePath, 'a');
        fwrite($oLogFile, $sFullMessage);
        fclose($oLogFile);
    }

    /**
     * Check if logging is activated by configuration
     *
     *
     * @return bool
     */
    protected function _fcCheckLoggingAllowed(): bool
    {
        $oConfig = $this->getConfig();
        $sLogMethod =
            $oConfig->getConfigParam('sTransactionRedirectLogging');

        return $sLogMethod == 'all';
    }

    /**
     * Add certain combination of transaction and forward configuration
     * to queue
     *
     * @param string $sStatusmessageId
     * @param string $sForwardId
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     * @throws Exception
     */
    protected function _addToQueue(string $sStatusmessageId, string $sForwardId)
    {
        $oUtilsObject = null;
        $sOxid = null;
        $sQuery = null;
        if ($this->_queueEntryExists($sStatusmessageId, $sForwardId)) {
            $this->_logForwardMessage(
                'Entry already exitsts. Skipping. StatusmessageId: ' .
                $sStatusmessageId .
                ', ForwardId: ' .
                $sForwardId
            );
            return;
        }
        $oUtilsObject = $this->_getUtilsObject();
        $sOxid = $oUtilsObject->generateUId();
        $sQuery = "
                INSERT INTO fcpostatusforwardqueue
                (
                    OXID,
                    FCSTATUSMESSAGEID,
                    FCSTATUSFORWARDID,
                    FCTRIES,
                    FCLASTTRY,
                    FCLASTREQUEST,
                    FCLASTRESPONSE
                )
                VALUES
                (
                    '{$sOxid}',
                    '{$sStatusmessageId}',
                    '{$sForwardId}',
                    '0',
                    '0000-00-00 00:00:00',
                    '',
                    ''
                )
            ";
        DatabaseProvider::getDb()->Execute($sQuery);
    }

    /**
     * Checks if a certain combination of statusmessageid already
     * exists
     *
     * @param string $sStatusmessageId
     * @param string $sForwardId
     * @return bool
     * @throws DatabaseConnectionException
     */
    protected function _queueEntryExists(string $sStatusmessageId, string $sForwardId): bool
    {
        $sQuery = "
                SELECT COUNT(*) 
                FROM fcpostatusforwardqueue
                WHERE
                    FCSTATUSMESSAGEID='{$sStatusmessageId}' AND
                    FCSTATUSFORWARDID='{$sForwardId}'
        ";

        $iRows = (int)DatabaseProvider::getDb()->getOne($sQuery);

        return $iRows > 0;
    }

    /**
     * Returns instance of oxUtilsObject
     *
     *
     * @return UtilsObject
     */
    protected function _getUtilsObject(): UtilsObject
    {
        if ($this->_oUtilsObject === null) {
            $this->_oUtilsObject = oxNew(UtilsObject::class);
        }

        return $this->_oUtilsObject;
    }
}
