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

namespace Fatchip\PayOne\Application\Controller\Admin;

use Fatchip\PayOne\Application\Model\FcPoMapping;
use OxidEsales\Eshop\Application\Model\Payment;
use stdClass;

class FcPayOneStatusMapping extends FcPayOneAdminDetails
{

    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_status_mapping';

    /**
     * Returns list of former configured mappings
     *
     * @return array
     */
    public function getMappings()
    {
        $aMappings = $this->_fcpoGetExistingMappings();
        $aMappings = $this->_fcpoAddNewMapping($aMappings);

        return $aMappings;
    }

    /**
     * Adds a new entry if flag has been set
     *
     * @param  array $aMappings
     * @return array
     */
    protected function _fcpoAddNewMapping($aMappings)
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('add')) {
            $oMapping = new stdClass();
            $oMapping->sOxid = 'new';
            $oMapping->sPaymentType = '';
            $oMapping->sPayoneStatusId = '';
            $oMapping->sShopStatusId = '';
            $aMappings[] = $oMapping;
        }

        return $aMappings;
    }

    /**
     * Requests database for existing mappings and returns an array of mapping objects
     *
     * @return array
     */
    protected function _fcpoGetExistingMappings()
    {
        $aExistingStatusMappings = $this->_oFcpoMapping->fcpoGetExistingMappings();

        return $aExistingStatusMappings;
    }

    /**
     * Returns a list of payment types
     *
     * @return array
     */
    public function getPaymentTypeList()
    {
        $oPayment = oxNew(Payment::class);
        $aPaymentTypes = $oPayment->fcpoGetPayonePaymentTypes();

        return $aPaymentTypes;
    }

    /**
     * Returns a list of payone status list
     *
     * @return array
     */
    public function getPayoneStatusList()
    {
        $aPayoneStatusList = $this->_oFcpoHelper->fcpoGetPayoneStatusList();

        $aNewList = [];
        foreach ($aPayoneStatusList as $sStatusId) {
            $oStatus = new stdClass();
            $oStatus->sId = $sStatusId;
            $oStatus->sTitle = $this->_oFcpoHelper->fcpoGetLang()->translateString('fcpo_status_' . $sStatusId, null, true);
            $aNewList[] = $oStatus;
        }

        return $aNewList;
    }

    /**
     * Returns a list of shop states
     *
     * @return array
     */
    public function getShopStatusList()
    {
        $aFolders = $this->_oFcpoHelper->fcpoGetConfig()->getConfigParam('aOrderfolder');
        return $aFolders;
    }

    /**
     * Updating settings into database
     *
     * @return void
     */
    public function save()
    {
        $oMapping = $this->fcpoGetInstance(FcPoMapping::class);
        $aMappings = $this->_oFcpoHelper->fcpoGetRequestParameter("editval");
        if (is_array($aMappings) && count($aMappings) > 0) {
            $oMapping->fcpoUpdateMappings($aMappings);
        }
    }
}
