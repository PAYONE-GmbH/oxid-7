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
 * @link      http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version   OXID eShop CE
 */

namespace Fatchip\PayOne\Application\Model;

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcPoKlarna extends \OxidEsales\Eshop\Core\Model\BaseModel
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var fcpohelper
     */
    protected $_oFcpoHelper = null;

    /**
     * Centralized Database instance
     *
     * @var object
     */
    protected $_oFcpoDb = null;

    /**
     * Init needed data
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
        $this->_oFcpoDb = DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
    }

    /**
     * Returns stored store ids
     *
     * @return array
     */
    public function fcpoGetStoreIds()
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
     * @param  array $aCampaigns
     * @return void
     */
    public function fcpoInsertCampaigns($aCampaigns)
    {
        if (is_array($aCampaigns) && count($aCampaigns) > 0) {
            foreach ($aCampaigns as $iId => $aCampaignData) {
                if (array_key_exists('delete', $aCampaignData) != false) {
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
                $this->_oFcpoDb->execute($sQuery);
            }
        }
    }

    /**
     * Add/Update klarna storeid into database
     *
     * @param  array $aStoreIds
     * @return void
     */
    public function fcpoInsertStoreIds($aStoreIds)
    {
        if (is_array($aStoreIds) && count($aStoreIds) > 0) {
            foreach ($aStoreIds as $iId => $aStoreIdData) {
                if (array_key_exists('delete', $aStoreIdData) != false) {
                    $sQuery = "DELETE FROM fcpopayment2country WHERE fcpo_paymentid = 'KLV' AND fcpo_type = " . DatabaseProvider::getDb()->quote($iId);
                    $this->_oFcpoDb->execute($sQuery);
                    $sQuery = "DELETE FROM fcpoklarnastoreids WHERE oxid = " . DatabaseProvider::getDb()->quote($iId);
                } else {
                    $sQuery = "UPDATE fcpoklarnastoreids SET fcpo_storeid = " . DatabaseProvider::getDb()->quote($aStoreIdData['id']) . " WHERE oxid = " . DatabaseProvider::getDb()->quote($iId);
                }
                $this->_oFcpoDb->execute($sQuery);
            }
        }
    }

    /**
     * Add Klarna store id
     *
     * @return void
     */
    public function fcpoAddKlarnaStoreId()
    {
        $sQuery = "INSERT INTO fcpoklarnastoreids (fcpo_storeid) VALUES ('')";
        $this->_oFcpoDb->execute($sQuery);
    }

    /**
     * Add Klarna campaign id
     *
     * @return void
     */
    public function fcpoAddKlarnaCampaign()
    {
        $sQuery = "INSERT INTO fcpoklarnacampaigns (fcpo_campaign_code, fcpo_campaign_title) VALUES ('', '')";
        $this->_oFcpoDb->execute($sQuery);
    }
}
