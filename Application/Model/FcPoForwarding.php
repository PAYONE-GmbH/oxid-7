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
use stdClass;

final class FcPoForwarding extends BaseModel
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    private $_oFcpoHelper;
    /** @var string */
    private const S_QUERY = "SELECT oxid, fcpo_payonestatus, fcpo_url, fcpo_timeout FROM fcpostatusforwarding ORDER BY oxid ASC";

    /**
     * Init needed data
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns an array of currently existing forwardings as an array with standard objects
     *
     * 
     * @return \stdClass[]
     */
    public function fcpoGetExistingForwardings(): array
    {
        $aForwardings = [];
        $oDb = $this->_oFcpoHelper->fcpoGetDb(true);
        $aRows = $oDb->getAll(self::S_QUERY);
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
     *
     *
     * @param array $aForwardings
     */
    public function fcpoUpdateForwardings($aForwardings): void
    {
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        // iterate through forwardings
        foreach ($aForwardings as $sForwardingId => $aData) {
            $sQuery = $this->_fcpoGetQuery($sForwardingId, $aData);
            $oDb->Execute($sQuery);
        }
    }

    /**
     * Returns the matching query for updating/adding data
     *
     * @param string $sForwardingId
     * @param array  $aData
     * @return string
     */
    private function _fcpoGetQuery($sForwardingId, $aData)
    {
        $database = DatabaseProvider::getDb();
        // quote values from outer space
        $sOxid = $database->quote($sForwardingId);
        $sPayoneStatus = $database->quote($aData['sPayoneStatus']);
        $sUrl = $database->quote($aData['sForwardingUrl']);
        $iTimeout = $database->quote($aData['iForwardingTimeout']);


        if (array_key_exists('delete', $aData)) {
            $sQuery = "DELETE FROM fcpostatusforwarding WHERE oxid = {$sOxid}";
        } else {
            $sQuery = $this->_fcpoGetUpdateQuery($sForwardingId, $sPayoneStatus, $sUrl, $iTimeout);
        }

        return $sQuery;
    }

    /**
     * Returns wether an insert or update query, depending on data
     *
     * @param string $sForwardingId
     * @param string $sPayoneStatus
     * @param string $sUrl
     * @param string $iTimeout
     * @return string
     */
    private function _fcpoGetUpdateQuery($sForwardingId, string|bool $sPayoneStatus, string|bool $sUrl, string|bool $iTimeout)
    {
        $blValidNewEntry = $this->_fcpoIsValidNewEntry($sForwardingId, $sPayoneStatus, $sUrl);

        if ($blValidNewEntry) {
            $oUtilsObject = $this->_oFcpoHelper->fcpoGetUtilsObject();
            $sOxid = $oUtilsObject->generateUID();
            $sQuery = " INSERT INTO fcpostatusforwarding (
                                oxid, fcpo_payonestatus,  fcpo_url,   fcpo_timeout
                            ) VALUES (
                                '{$sOxid}', {$sPayoneStatus}, {$sUrl},  {$iTimeout}
                            )";
        } else {
            $database = DatabaseProvider::getDb();
            $sForwardingId = $database->quote($sForwardingId);
            $sQuery = " UPDATE fcpostatusforwarding
                            SET
                                fcpo_payonestatus = {$sPayoneStatus},
                                fcpo_url = {$sUrl},
                                fcpo_timeout = {$iTimeout}
                            WHERE
                                oxid = {$sForwardingId}";
        }

        return $sQuery;
    }

    /**
     * Checks if current entry is new and complete
     *
     * @param string $sForwardingId
     * @param string $sPayoneStatus
     * @param string $sUrl
     */
    private function _fcpoIsValidNewEntry($sForwardingId, string|bool $sPayoneStatus, string|bool $sUrl): bool
    {
        $blComplete = (!empty($sPayoneStatus) || !empty($sUrl));

        return $sForwardingId == 'new' && $blComplete;
    }
}
