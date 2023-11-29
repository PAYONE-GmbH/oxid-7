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
    public function getForwardings(): array
    {
        $aForwardings = $this->fcpoGetExistingForwardings();

        return $this->_fcpoGetNewForwarding($aForwardings);
    }

    /**
     * Returns an array of currently existing forwardings as an array with standard objects
     *
     * @return array
     */
    protected function fcpoGetExistingForwardings(): array
    {
        $oForwarding = oxNew(FcPoForwarding::class);

        return $oForwarding->fcpoGetExistingForwardings();
    }

    /**
     * Parses existing forwardings and add a new one if param has been set to
     *
     * @param array $aForwardings
     * @return array
     */
    protected function _fcpoGetNewForwarding(array $aForwardings): array
    {
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('add')) {
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
     * Save current configured forwardings
     *
     * @return void
     */
    public function save(): void
    {
        $oForwarding = oxNew(FcPoForwarding::class);
        $aForwardings = $this->_oFcPoHelper->fcpoGetRequestParameter("editval");
        if (is_array($aForwardings) && $aForwardings !== []) {
            $oForwarding->fcpoUpdateForwardings($aForwardings);
        }
    }

}
