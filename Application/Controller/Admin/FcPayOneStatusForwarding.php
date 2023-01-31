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

use Fatchip\PayOne\Application\Model\FcPoForwarding;
use stdClass;

class FcPayOneStatusForwarding extends FcPayOneAdminDetails
{

    /**
     * Current class template name.
  *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_status_forwarding';


    /**
     * Returns list fo configured forwardings
     *
     * @return array
     */
    public function getForwardings()
    {
        $aForwardings = $this->fcpoGetExistingForwardings();
        $aForwardings = $this->_fcpoGetNewForwarding($aForwardings);

        return $aForwardings;
    }


    /**
     * Returns an array of currently existing forwardings as an array with standard objects
     *
     * @return array
     */
    protected function fcpoGetExistingForwardings()
    {
        $oForwarding = oxNew(FcPoForwarding::class);
        $aForwardings = $oForwarding->fcpoGetExistingForwardings();

        return $aForwardings;
    }


    /**
     * Parses existing forwardings and add a new one if param has been set to
     *
     * @param  array $aForwardings
     * @return array
     */
    protected function _fcpoGetNewForwarding($aForwardings)
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('add')) {
            $oForwarding = new stdClass();
            $oForwarding->sOxid = 'new';
            $oForwarding->sPayoneStatusId = '';
            $oForwarding->sForwardingUrl = '';
            $oForwarding->iForwardingTimeout = '';
            $aForwardings[] = $oForwarding;
        }

        return $aForwardings;
    }


    /**
     * Returns payone status list
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
            $oStatus->sTitle = $this->_oFcpoHelper->fcpoGetLang()->translateString('fcpo_status_'.$sStatusId, null, true);
            $aNewList[] = $oStatus;
        }

        return $aNewList;
    }


    /**
     * Save current configured forwardings
     *
     * @return void
     */
    public function save()
    {
        $oForwarding = oxNew(FcPoForwarding::class);
        $aForwardings = $this->_oFcpoHelper->fcpoGetRequestParameter("editval");
        if (is_array($aForwardings) && count($aForwardings) > 0) {
            $oForwarding->fcpoUpdateForwardings($aForwardings);
        }
    }
}
