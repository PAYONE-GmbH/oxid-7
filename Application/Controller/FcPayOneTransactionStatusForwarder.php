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

namespace Fatchip\PayOne\Application\Controller;

use Exception;
use OxidEsales\Eshop\Core\DatabaseProvider;

set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('log_errors', 1);
ini_set('error_log', '../../../../../source/log/fcpoErrors.log');

class FcPayOneTransactionStatusForwarder extends FcPayOneTransactionStatusBase
{

    const STATE_STARTING = 'starting';
    const STATE_FINISHED = 'finished';

    /**
     * Map for translating database fields to call params
     *
     * @var array
     */
    protected array $_aDbFields2Params = [
        'FCPO_KEY' => 'key',
        'FCPO_TXACTION' => 'txaction',
        'FCPO_PORTALID' => 'portalid',
        'FCPO_AID' => 'aid',
        'FCPO_CLEARINGTYPE' => 'clearingtype',
        'FCPO_TXTIME' => 'txtime',
        'FCPO_CURRENCY' => 'currency',
        'FCPO_USERID' => 'userid',
        'FCPO_ACCESSNAME' => 'accessname',
        'FCPO_ACCESSCODE' => 'accesscode',
        'FCPO_PARAM' => 'param',
        'FCPO_MODE' => 'mode',
        'FCPO_PRICE' => 'price',
        'FCPO_TXID' => 'txid',
        'FCPO_REFERENCE' => 'reference',
        'FCPO_SEQUENCENUMBER' => 'sequencenumber',
        'FCPO_COMPANY' => 'company',
        'FCPO_FIRSTNAME' => 'firstname',
        'FCPO_LASTNAME' => 'lastname',
        'FCPO_STREET' => 'street',
        'FCPO_ZIP' => 'zip',
        'FCPO_CITY' => 'city',
        'FCPO_EMAIL' => 'email',
        'FCPO_COUNTRY' => 'country',
        'FCPO_SHIPPING_COMPANY' => 'shipping_company',
        'FCPO_SHIPPING_FIRSTNAME' => 'shipping_firstname',
        'FCPO_SHIPPING_LASTNAME' => 'shipping_lastname',
        'FCPO_SHIPPING_STREET' => 'shipping_street',
        'FCPO_SHIPPING_ZIP' => 'shipping_zip',
        'FCPO_SHIPPING_CITY' => 'shipping_city',
        'FCPO_SHIPPING_COUNTRY' => 'shipping_country',
        'FCPO_BANKCOUNTRY' => 'bankcountry',
        'FCPO_BANKACCOUNT' => 'bankaccount',
        'FCPO_BANKCODE' => 'bankcode',
        'FCPO_BANKACCOUNTHOLDER' => 'bankaccountholder',
        'FCPO_CARDEXPIREDATE' => 'cardexpiredate',
        'FCPO_CARDTYPE' => 'cardtype',
        'FCPO_CARDPAN' => 'cardpan',
        'FCPO_CUSTOMERID' => 'customerid',
        'FCPO_BALANCE' => 'balance',
        'FCPO_RECEIVABLE' => 'receivable',
        'FCPO_CLEARING_BANKACCOUNTHOLDER' => 'clearing_bankaccountholder',
        'FCPO_CLEARING_BANKACCOUNT' => 'clearing_bankaccount',
        'FCPO_CLEARING_BANKCODE' => 'clearing_bankcode',
        'FCPO_CLEARING_BANKNAME' => 'clearing_bankname',
        'FCPO_CLEARING_BANKBIC' => 'clearing_bankbic',
        'FCPO_CLEARING_BANKIBAN' => 'clearing_bankiban',
        'FCPO_CLEARING_LEGALNOTE' => 'clearing_legalnote',
        'FCPO_CLEARING_DUEDATE' => 'clearing_duedate',
        'FCPO_CLEARING_REFERENCE' => 'clearing_reference',
        'FCPO_CLEARING_INSTRUCTIONNOTE' => 'clearing_instructionnote',
    ];


    /**
     * Central handling of forward request
     */
    public function __construct()
    {
        parent::__construct();
        try {
            $this->_isJobAlreadyRunning();
            $this->_isKeyValid();
            $this->_setJobState(self::STATE_STARTING);
            $this->_forwardRequests();
            $this->_setJobState(self::STATE_FINISHED);
        } catch (Exception $oEx) {
            echo "Error occurred! Please check logfile for details.\n";
            $this->_logException($oEx->getMessage());
            exit(1);
        }
    }

    /**
     * Checks if a forward job is currently running
     *
     * @return void
     * @throws Exception
     */
    protected function _isJobAlreadyRunning(): void
    {
        $blProcessFileExists = $this->_checkProcessFileExists();
        if (!$blProcessFileExists) {
            return;
        }

        try {
            $this->_checkProcessExists();
        } catch (Exception $oEx) {
            throw $oEx;
        }
    }

    /**
     * Checking if process file exists
     * @return bool
     */
    protected function _checkProcessFileExists(): bool
    {
        $sProcessFile = $this->_getProcessFilePath();
        return file_exists($sProcessFile);
    }

    /**
     * Returns path to processfile
     *
     * @return string
     */
    protected function _getProcessFilePath(): string
    {
        $sTmpPath = dirname(__FILE__) . "/../../";
        $sFile = "forwardprocess.txt";

        return $sTmpPath . $sFile;
    }

    /**
     * Deeply checking if former process still exists. If not processfile
     * should be cleaned up and reported, so we don't run into eternal loops.
     * Killing processes is explicitly not done here due this should be
     * handled by OS
     *
     * @return void
     * @throws Exception
     */
    protected function _checkProcessExists(): void
    {
        $sProcessFile = $this->_getProcessFilePath();
        $iPid = (int)file_get_contents($sProcessFile);

        if ($iPid === 0) {
            unlink($sProcessFile);
            $sMessage =
                'Processfile did not contain a valid PID! Deleted processfile for next run.';
            throw new Exception($sMessage);
        }

        if (file_exists("/proc/$iPid")) {
            throw new Exception('Cronjob already running! Abort current attempt.');
        }

        unlink($sProcessFile);
        $sMessage =
            'Former started process ' . $iPid . ' no longer exists! ' .
            'Deleted processfile for next run.';
        throw new Exception($sMessage);
    }

    /**
     * Setting current state of job
     *
     * @param string $sState
     * @return void
     * @throws Exception
     */
    protected function _setJobState(string $sState): void
    {
        try {
            $sProcessFile = $this->_getProcessFilePath();
            $iPid = getmypid();
            $this->_logForwardMessage($sState . ' job with PID ' . $iPid);

            if ($sState == self::STATE_STARTING) {
                $oProcessFile = fopen($sProcessFile, 'w');
                fwrite($oProcessFile, $iPid);
                fclose($oProcessFile);
                return;
            }

            if ($sState == self::STATE_FINISHED) {
                unlink($sProcessFile);
                return;
            }
        } catch (Exception $oEx) {
            throw $oEx;
        }
    }

    /**
     * Get requests to forward to and trigger forwarding
     *
     * @return void
     * @throws Exception
     */
    protected function _forwardRequests(): void
    {
        try {
            $sLimitStatusmessageId =
                $this->fcGetPostParam('statusmessageid');

            $sQueryLimitStatusmessageId = '';
            if ($sLimitStatusmessageId !== '' && $sLimitStatusmessageId !== '0') {
                $this->_createMissingQueueEntries($sLimitStatusmessageId);
                $sQueryLimitStatusmessageId =
                    " AND  FCSTATUSMESSAGEID='$sLimitStatusmessageId' ";
            }

            $sQuery = "
                SELECT
                    OXID,
                    FCSTATUSMESSAGEID,
                    FCSTATUSFORWARDID
                FROM fcpostatusforwardqueue
                WHERE FCFULFILLED='0'
                $sQueryLimitStatusmessageId
            ";
            $oDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
            $aRows = $oDb->getAll($sQuery);
            $this->_logForwardMessage('Found requests to forward: ' . print_r($aRows, true));

            foreach ($aRows as $aRow) {
                $sQueueId = $aRow['OXID'];
                $sStatusmessageId = $aRow['FCSTATUSMESSAGEID'];
                $sForwardId = $aRow['FCSTATUSFORWARDID'];

                $this->_forwardRequest($sQueueId, $sForwardId, $sStatusmessageId);
            }
        } catch (Exception $oEx) {
            throw $oEx;
        }
    }

    /**
     * If new redirect targets have been added for given statusmessage, create
     * referring queue entries
     *
     * @param string $sStatusmessageId
     * @return void
     * @throws Exception
     */
    protected function _createMissingQueueEntries(string $sStatusmessageId): void
    {
        try {
            $aParams = $this->_fetchPostParams($sStatusmessageId);
            $aRequest = $aParams['array'];
            $sPayoneStatus = $aRequest['txaction'];
            $this->_addQueueEntries($sStatusmessageId, $sPayoneStatus);
        } catch (Exception $oEx) {
            throw $oEx;
        }
    }

    /**
     * Collects request data from database and prepare result
     *
     * @param string $sStatusmessageId
     * @return array{string: string, array: array}
     * @throws
     */
    protected function _fetchPostParams(string $sStatusmessageId): array
    {
        try {
            $oDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
            $sQuery = "
                SELECT * 
                FROM fcpotransactionstatus 
                WHERE OXID=" . $oDb->quote($sStatusmessageId);

            $aRow = $oDb->getRow($sQuery);
            if (empty($aRow)) {
                $sExceptionMessage =
                    'Could not find transaction status message for ID ' . $sStatusmessageId . '!';
                throw new Exception($sExceptionMessage);
            }

            $aRequestParams = $this->_cleanParams($aRow);
            $sParams = '';
            foreach ($aRequestParams as $sKey => $mValue) {
                $sParams .= $this->_addParam($sKey, $mValue);
            }

            return [
                'string' => $sParams,
                'array' => $aRequestParams,
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Removes all empty params and translate db fields to corresponding
     * call
     *
     * @param array $aParams
     * @return array<int|string, mixed>
     */
    protected function _cleanParams(array $aParams): array
    {
        $aCleanedParams = [];
        foreach ($aParams as $sKey => $sValue) {
            $blValid = (
                isset($this->_aDbFields2Params[$sKey]) &&
                $sValue != ''
            );
            if (!$blValid) {
                continue;
            }
            if ($sKey === 'FCPO_TXTIME') {
                $sValue = strtotime((string)$sValue);
            }
            $sCallKey = $this->_aDbFields2Params[$sKey];
            $aCleanedParams[$sCallKey] = $sValue;
        }

        return $aCleanedParams;
    }

    /**
     * Forward request from queue
     *
     * @param string $sQueueId
     * @param string $sForwardId
     * @param string $sStatusmessageId
     * @return void
     * @throws
     */
    protected function _forwardRequest(string $sQueueId, string $sForwardId, string $sStatusmessageId): void
    {
        try {
            $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
            $sConfTimeout = $oConfig->getConfigParam('sTransactionRedirectTimeout');
            $iTimeout = ($sConfTimeout) ? (int)$sConfTimeout : 10;
            $aParams = $this->_fetchPostParams($sStatusmessageId);
            $sParams = $aParams['string'];
            $aRequest = $aParams['array'];
            $aForwardData = $this->_getForwardData($sForwardId);

            $sUrl = $aForwardData['url'];
            $this->_logForwardMessage('Trying to forward to url: ' . $sUrl . '...');
            $this->_logForwardMessage($sParams);
            $sParams = substr((string)$sParams, 1);

            $oCurl = curl_init($sUrl);
            curl_setopt($oCurl, CURLOPT_POST, 1);
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, $sParams);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $iTimeout);

            $mResult = curl_exec($oCurl);
            $mCurlInfo = curl_getinfo($oCurl);
            $blValidResult = (is_string($mResult) && trim($mResult) == 'TSOK');
            $this->_setForwardingResult($sQueueId, $blValidResult, $aRequest, $mResult, $mCurlInfo);
        } catch (Exception $oEx) {
            throw $oEx;
        }

        curl_close($oCurl);
    }

    /**
     * Returns elementary forward data
     *
     * @param string $sForwardId
     * @return array
     * @throws Exception
     */
    protected function _getForwardData(string $sForwardId): array
    {
        $oDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        $sQuery = "
                SELECT 
                    FCPO_URL,
                    FCPO_TIMEOUT
                FROM fcpostatusforwarding 
                WHERE OXID=" . $oDb->quote($sForwardId);

        $aRow = $oDb->getRow($sQuery);
        if (empty($aRow)) {
            throw new Exception('Could not find forward data for ID ' . $sForwardId . '!');
        }

        return [
            'url' => $aRow['FCPO_URL'],
            'timeout' => $aRow['FCPO_TIMEOUT'],
        ];
    }

    /**
     * Updates processed queue entry with current data
     *
     * @param string $sQueueId
     * @param bool $blValidResult
     * @param array $aRequest
     * @param mixed $mResult
     * @param mixed $mCurlInfo
     * @return void
     * @throws Exception
     */
    protected function _setForwardingResult(string $sQueueId, bool $blValidResult, array $aRequest, mixed $mResult, mixed $mCurlInfo): void
    {
        try {
            $oDb = DatabaseProvider::getDb();
            $sFulfilled = ($blValidResult) ? '1' : '0';
            $sFulfilled = $oDb->quote($sFulfilled);
            $sRequest = $oDb->quote(print_r($aRequest, true));
            $sResponse = $oDb->quote((string)$mResult);
            $sResponseInfo = $oDb->quote((string)print_r($mCurlInfo, true));

            $sQuery = "
            UPDATE fcpostatusforwardqueue
            SET 
                FCTRIES=FCTRIES+1,
                FCLASTTRY=NOW(),
                FCLASTREQUEST=" . $sRequest . ",
                FCLASTRESPONSE=" . $sResponse . ",
                FCRESPONSEINFO=" . $sResponseInfo . ",
                FCFULFILLED=" . $sFulfilled . "
            WHERE
                OXID=" . $oDb->quote($sQueueId);
            $this->_logForwardMessage("Updating Request with query:\n" . $sQuery . "\n");

            $oDb->execute($sQuery);

            // update entry in transactionlog table for filtering tries and status
            $sForwardState = ($blValidResult) ? 'OK' : 'ERROR';

            $sQueryUpdateTransactionlog = "
            UPDATE fcpotransactionstatus
            SET 
                FCPO_FORWARD_TRIES=FCPO_FORWARD_TRIES+1,
                FCPO_FORWARD_STATE='" . $sForwardState . "'
            WHERE
                FCPO_TXID='" . $aRequest['txid'] . "' AND FCPO_TXACTION = '" . $aRequest['txaction'] . "'";

            $this->_logForwardMessage("Updating transaction log with query:\n" . $sQueryUpdateTransactionlog . "\n");
            $oDb->execute($sQueryUpdateTransactionlog);

        } catch (Exception $oEx) {
            throw $oEx;
        }
    }

}
