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

use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Model\BaseModel;

final class FcPoKlarna extends BaseModel
{

    /**
     * Centralized Database instance
     *
     * @var object
     */
    private readonly DatabaseInterface $_oFcpoDb;

    /**
     * Init needed data
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
    }

    /**
     * Returns stored store ids
     *
     * 
     * @return array<int|string, mixed>
     */
    public function fcpoGetStoreIds(): array
    {
        $aStoreIds = [];
        $sQuery = "SELECT oxid, fcpo_storeid FROM fcpoklarnastoreids ORDER BY oxid ASC";
        $aRows = $this->_oFcpoDb->getAll($sQuery);
        foreach ($aRows as $aRow) {
            $aStoreIds[$aRow['oxid']] = $aRow['fcpo_storeid'];
        }

        return $aStoreIds;
    }

    /**
     * Add/Update klarna campaigns into database
     *
     * @param array $aCampaigns
     */
    public function fcpoInsertCampaigns($aCampaigns): void
    {
        if (is_array($aCampaigns) && $aCampaigns !== []) {
            foreach ($aCampaigns as $iId => $aCampaignData) {
                if (array_key_exists('delete', $aCampaignData)) {
                    $sQuery = "DELETE FROM fcpoklarnacampaigns WHERE oxid = " . DatabaseProvider::getDb()->quote($iId);
                } else {
                    $sQuery = " UPDATE
                                    fcpoklarnacampaigns
                                SET
                                    fcpo_campaign_code = " . DatabaseProvider::getDb()->quote($aCampaignData['code']) . ",
                                    fcpo_campaign_title = " . DatabaseProvider::getDb()->quote($aCampaignData['title']) . ",
                                    fcpo_campaign_language = " . DatabaseProvider::getDb()->quote(serialize($aCampaignData['language'])) . ",
                                    fcpo_campaign_currency = " . DatabaseProvider::getDb()->quote(serialize($aCampaignData['currency'])) . "
                                WHERE
                                    oxid = " . DatabaseProvider::getDb()->quote($iId);
                }
                $this->_oFcpoDb->Execute($sQuery);
            }
        }
    }

    /**
     * Add/Update klarna storeid into database
     *
     * @param array $aStoreIds
     */
    public function fcpoInsertStoreIds($aStoreIds): void
    {
        if (is_array($aStoreIds) && $aStoreIds !== []) {
            foreach ($aStoreIds as $iId => $aStoreIdData) {
                if (array_key_exists('delete', $aStoreIdData)) {
                    $sQuery = "DELETE FROM fcpopayment2country WHERE fcpo_paymentid = 'KLV' AND fcpo_type = " . DatabaseProvider::getDb()->quote($iId);
                    $this->_oFcpoDb->Execute($sQuery);
                    $sQuery = "DELETE FROM fcpoklarnastoreids WHERE oxid = " . DatabaseProvider::getDb()->quote($iId);
                } else {
                    $sQuery = "UPDATE fcpoklarnastoreids SET fcpo_storeid = " . DatabaseProvider::getDb()->quote($aStoreIdData['id']) . " WHERE oxid = " . DatabaseProvider::getDb()->quote($iId);
                }
                $this->_oFcpoDb->Execute($sQuery);
            }
        }
    }

    /**
     * Add Klarna store id
     *
     * 
     */
    public function fcpoAddKlarnaStoreId(): void
    {
        $sQuery = "INSERT INTO fcpoklarnastoreids (fcpo_storeid) VALUES ('')";
        $this->_oFcpoDb->Execute($sQuery);
    }

    /**
     * Add Klarna campaign id
     *
     * 
     */
    public function fcpoAddKlarnaCampaign(): void
    {
        $sQuery = "INSERT INTO fcpoklarnacampaigns (fcpo_campaign_code, fcpo_campaign_title) VALUES ('', 
    '')";
        $this->_oFcpoDb->Execute($sQuery);
    }
}
