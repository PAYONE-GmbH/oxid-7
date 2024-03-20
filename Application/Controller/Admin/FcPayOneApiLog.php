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

use Fatchip\PayOne\Application\Model\FcPoRequestLog;

class FcPayOneApiLog extends FcPayOneAdminDetails
{

    /**
     * Current class template name
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_apilog';

    /**
     * Array with existing status of order
     *
     * @var array|null
     */
    protected ?array $_aStatus = null;


    /**
     * Loads transaction log entry with given oxid, passes
     * its data to Twig engine and returns path to a template
     * "fcpayone_apilog".
     *
     * @return string
     */
    public function render(): string
    {
        parent::render();

        $oLogEntry = $this->_oFcPoHelper->getFactoryObject(FcPoRequestLog::class);

        $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            // load object
            $oLogEntry->load($sOxid);
            $this->_aViewData["edit"] = $oLogEntry;
        }

        $this->_aViewData['sHelpURL'] = $this->_oFcPoHelper->fcpoGetHelpUrl();

        return $this->_sThisTemplate;
    }

}
