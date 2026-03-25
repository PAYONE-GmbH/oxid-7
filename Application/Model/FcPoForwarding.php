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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Model\BaseModel;
use stdClass;

class FcPoForwarding extends BaseModel
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
     * @var Connection
     */
    protected Connection $_oFcPoDb;


    /**
     * Init needed data
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = $this->_oFcPoHelper->fcpoGetPdoDb();
    }

    /**
     * Returns an array of currently existing forwardings as an array with standard objects
     *
     * @return stdClass[]
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoGetExistingForwardings(): array
    {
        $aForwardings = [];
        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        $oQuery
            ->select('oxid', 'fcpo_payonestatus', 'fcpo_url', 'fcpo_timeout')
            ->from('fcpostatusforwarding')
            ->orderBy('oxid', 'ASC');

        $aRows = $oQuery->execute()->fetchAllAssociative();
        foreach ($aRows as $aRow) {
            // collect values
            $sOxid = $aRow['oxid'];
            $sStatus = $aRow['fcpo_payonestatus'];
            $sUrl = $aRow['fcpo_url'];
            $sTimeout = $aRow['fcpo_timeout'];

            // build object
            $oForwarding = new stdClass();
            $oForwarding->sOxid = $sOxid;
            $oForwarding->sPayoneStatusId = $sStatus;
            $oForwarding->sForwardingUrl = $sUrl;
            $oForwarding->iForwardingTimeout = $sTimeout;
            $aForwardings[] = $oForwarding;
        }

        return $aForwardings;
    }

    /**
     * @param array $aForwardings
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoUpdateForwardings(array $aForwardings): void
    {
        // iterate through forwardings
        foreach ($aForwardings as $sForwardingId => $aData) {
            $oQuery = $this->_fcpoGetQuery($sForwardingId, $aData);
            $oQuery->execute();
        }
    }

    /**
     * Returns the matching query for updating/adding data
     *
     * @param string $sForwardingId
     * @param array $aData
     * @return QueryBuilder
     * @throws DatabaseConnectionException
     */
    protected function _fcpoGetQuery(string $sForwardingId, array $aData): QueryBuilder
    {
        $sPayoneStatus = $aData['sPayoneStatus'];
        $sUrl = $aData['sForwardingUrl'];
        $iTimeout = (int) ($aData['iForwardingTimeout']);

        if (array_key_exists('delete', $aData)) {
            $oQuery = $this->_oFcPoDb->createQueryBuilder();
            $oQuery
                ->delete('fcpostatusforwarding')
                ->where('oxid = :sOxid')
                ->setParameter('sOxid', $sForwardingId);
        } else {
            $oQuery = $this->_fcpoGetUpdateQuery($sForwardingId, $sPayoneStatus, $sUrl, $iTimeout);
        }

        return $oQuery;
    }

    /**
     * Returns whether an insert or update query, depending on data
     *
     * @param string $sForwardingId
     * @param string $sPayoneStatus
     * @param string $sUrl
     * @param int $iTimeout
     * @return QueryBuilder
     * @throws DatabaseConnectionException
     */
    protected function _fcpoGetUpdateQuery(string $sForwardingId, string $sPayoneStatus, string $sUrl, int $iTimeout): QueryBuilder
    {
        $blValidNewEntry = $this->_fcpoIsValidNewEntry($sForwardingId, $sPayoneStatus, $sUrl);

        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        if ($blValidNewEntry) {
            $oUtilsObject = $this->_oFcPoHelper->fcpoGetUtilsObject();
            $sOxid = $oUtilsObject->generateUID();
            $oQuery
                ->insert('fcpostatusforwarding')
                ->values(
                    [
                        'oxid' => ':sOxid',
                        'fcpo_payonestatus' => ':sPayoneStatus',
                        'fcpo_url' => ':sUrl',
                        'fcpo_timeout' => ':iTimeout'
                    ]
                )
                ->setParameters(
                    [
                        'sOxid' => $sOxid,
                        'sPayoneStatus' => $sPayoneStatus,
                        'sUrl' => $sUrl,
                        'iTimeout' => $iTimeout
                    ]
                );
        } else {
            $oQuery
                ->update('fcpostatusforwarding')
                ->set('fcpo_payonestatus', ':sPayoneStatus')
                ->set('fcpo_url', ':sUrl')
                ->set('fcpo_timeout', ':iTimeout')
                ->where('oxid = :sOxid')
                ->setParameters(
                    [
                        'sPayoneStatus' => $sPayoneStatus,
                        'sUrl' => $sUrl,
                        'iTimeout' => $iTimeout,
                        'sOxid' => $sForwardingId
                    ]
                );
        }

        return $oQuery;
    }

    /**
     * Checks if current entry is new and complete
     *
     * @param string $sForwardingId
     * @param string $sPayoneStatus
     * @param string $sUrl
     * @return bool
     */
    protected function _fcpoIsValidNewEntry(string $sForwardingId, string $sPayoneStatus, string $sUrl): bool
    {
        $blComplete = (!empty($sPayoneStatus) || !empty($sUrl));

        return $sForwardingId == 'new' && $blComplete;
    }

}
