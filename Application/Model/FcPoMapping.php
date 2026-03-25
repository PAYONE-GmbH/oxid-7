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

class FcPoMapping extends BaseModel
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
     */
    public function __construct()
    {
        parent::__construct();

        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = $this->_oFcPoHelper->fcpoGetPdoDb();
    }

    /**
     * Requests database for existing mappings and returns an array of mapping objects
     *
     * @return stdClass[]
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoGetExistingMappings(): array
    {
        $aMappings = [];

        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        $oQuery
            ->select('oxid', 'fcpo_paymentid', 'fcpo_payonestatus', 'fcpo_folder')
            ->from('fcpostatusmapping')
            ->orderBy('oxid', 'ASC');

        $aRows = $oQuery->execute()->fetchAllAssociative();
        foreach ($aRows as $aRow) {
            // collect data
            $sOxid = $aRow['oxid'];
            $sPaymentId = $aRow['fcpo_paymentid'];
            $sPayoneStatus = $aRow['fcpo_payonestatus'];
            $sFolder = $aRow['fcpo_folder'];

            // create object
            $oMapping = new stdClass();
            $oMapping->sOxid = $sOxid;
            $oMapping->sPaymentType = $sPaymentId;
            $oMapping->sPayoneStatusId = $sPayoneStatus;
            $oMapping->sShopStatusId = $sFolder;
            $aMappings[] = $oMapping;
        }

        return $aMappings;
    }

    /**
     * Updates current set of mappings into database
     *
     * @param array $aMappings
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoUpdateMappings(array $aMappings): void
    {
        // iterate through mappings
        foreach ($aMappings as $sMappingId => $aData) {
            $oQuery = $this->_fcpoGetQuery($sMappingId, $aData);
            $oQuery->execute();
        }
    }

    /**
     * Returns the matching query for updating/adding data
     *
     * @param string $sMappingId
     * @param array $aData
     * @return QueryBuilder
     * @throws DatabaseConnectionException
     */
    protected function _fcpoGetQuery(string $sMappingId, array $aData): QueryBuilder
    {
        if (array_key_exists('delete', $aData)) {
            $oQuery = $this->_oFcPoDb->createQueryBuilder();
            $oQuery
                ->delete('fcpostatusmapping')
                ->where('oxid = :sOxid')
                ->setParameter('sOxid', $sMappingId);
        } else {
            $oQuery = $this->_fcpoGetUpdateQuery($sMappingId, $aData);
        }

        return $oQuery;
    }

    /**
     * Returns whether an insert or update query, depending on data
     *
     * @param string $sMappingId
     * @param array $aData
     * @return QueryBuilder
     */
    protected function _fcpoGetUpdateQuery(string $sMappingId, array $aData): QueryBuilder
    {
        $blValidNewEntry = $this->_fcpoIsValidNewEntry($sMappingId, $aData['sPaymentType'], $aData['sPayoneStatus'], $aData['sShopStatus']);

        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        if ($blValidNewEntry) {
            $oQuery
                ->insert('fcpostatusmapping')
                ->values(
                    [
                        'fcpo_paymentid' => ':sPaymentId',
                        'fcpo_payonestatus' => ':sPayoneStatus',
                        'fcpo_folder' => ':sFolder',
                    ]
                )
                ->setParameters(
                    [
                        'sPaymentId' => $aData['sPaymentType'],
                        'sPayoneStatus' => $aData['sPayoneStatus'],
                        'sFolder' => $aData['sShopStatus'],
                    ]
                );
        } else {
            $oQuery
                ->update('fcpostatusmapping')
                ->set('fcpo_paymentid', ':sPaymentId')
                ->set('fcpo_payonestatus', ':sPayoneStatus')
                ->set('fcpo_folder', ':sFolder')
                ->where('oxid = :sOxid')
                ->setParameters(
                    [
                        'sPaymentId' => $aData['sPaymentType'],
                        'sPayoneStatus' => $aData['sPayoneStatus'],
                        'sFolder' => $aData['sShopStatus'],
                        'sOxid' => $sMappingId
                    ]
                );
        }

        return $oQuery;
    }

    /**
     * Checks if current entry is new and complete
     *
     * @param string $sMappingId
     * @param string $sPaymentId
     * @param string $sPayoneStatus
     * @param string $sFolder
     * @return bool
     */
    protected function _fcpoIsValidNewEntry(string $sMappingId, string $sPaymentId, string $sPayoneStatus, string $sFolder): bool
    {
        $blComplete = (!empty($sPayoneStatus) || !empty($sPaymentId) || !empty($sFolder));

        return $sMappingId == 'new' && $blComplete;
    }

}
