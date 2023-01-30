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

use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcPayOneAdminView extends AdminController
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    private $_oFcpoHelper;


    /**
     * Init needed data
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }


    /**
     * Return admin template seperator sign by shop-version
     *
     * @return string
     */
    public function fcGetAdminSeperator()
    {
        $iVersion = $this->_oFcpoHelper->fcpoGetIntShopVersion();
        if ($iVersion < 4300) {
            return '?';
        } else {
            return '&';
        }
    }

    /**
     * Returns current view identifier
     */
    public function getViewId(): string
    {
        return 'dyn_fcpayone';
    }


    /**
     * Template getter for integrator ID
     *
     *
     * @return string
     */
    public function fcpoGetIntegratorId()
    {
        return $this->_oFcpoHelper->fcpoGetIntegratorId();
    }


    /**
     * Template getter returns payone connector version
     *
     *
     * @return string
     */
    public function fcpoGetVersion()
    {
        return $this->_oFcpoHelper->fcpoGetModuleVersion();
    }

    /**
     * Template getter for Merchant ID
     *
     *
     * @return string
     */
    public function fcpoGetMerchantId()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOMerchantID');
    }
}
