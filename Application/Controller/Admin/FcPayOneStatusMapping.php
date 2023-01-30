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

namespace Fatchip\PayOne\Application\Controller\Admin;

use Fatchip\PayOne\Application\Model\FcPoMapping;
use Fatchip\PayOne\Lib\FcPoHelper;
use stdClass;

class FcPayOneStatusMapping extends FcPayOneAdminDetails
{

    public $_oFcpoMapping;
    public $_oFcpoHelper;
    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_status_mapping';

    /**
     * Returns list of former configured mappings
     *
     *
     * @return array
     */
    public function getMappings()
    {
        $aMappings = $this->_fcpoGetExistingMappings();

        return $this->_fcpoAddNewMapping($aMappings);
    }

    /**
     * Requests database for existing mappings and returns an array of mapping objects
     *
     *
     * @return array
     */
    private function _fcpoGetExistingMappings()
    {
        return $this->_oFcpoMapping->fcpoGetExistingMappings();
    }

    /**
     * Adds a new entry if flag has been set
     *
     * @return array
     */
    private function _fcpoAddNewMapping(array $aMappings)
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
     * Returns a list of payment types
     *
     *
     * @return array
     */
    public function getPaymentTypeList()
    {
        $oPayment = oxNew('oxPayment');

        return $oPayment->fcpoGetPayonePaymentTypes();
    }

    /**
     * Returns a list of payone status list
     *
     *
     * @return \stdClass[]
     */
    public function getPayoneStatusList(): array
    {
        $aPayoneStatusList = $this->_oFcpoHelper->fcpoGetPayoneStatusList();

        $aNewList = [];
        foreach ($aPayoneStatusList as $aPayoneRectorPrefix202301StatusList) {
            $oStatus = new stdClass();
            $oStatus->sId = $aPayoneRectorPrefix202301StatusList;
            $oStatus->sTitle = $this->_oFcpoHelper->fcpoGetLang()->translateString('fcpo_status_' . $aPayoneRectorPrefix202301StatusList, null, true);
            $aNewList[] = $oStatus;
        }

        return $aNewList;
    }

    /**
     * Returns a list of shop states
     *
     *
     * @return array
     */
    public function getShopStatusList()
    {
        $oFcpoHelper = oxNew(FcPoHelper::class);
        $oConfig = $oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('aOrderfolder');
    }

    /**
     * Updating settings into database
     *
     */
    public function save(): void
    {
        $oMapping = $this->fcpoGetInstance(FcPoMapping::class);
        $aMappings = $this->_oFcpoHelper->fcpoGetRequestParameter("editval");
        if (is_array($aMappings) && $aMappings !== []) {
            $oMapping->fcpoUpdateMappings($aMappings);
        }
    }
}
