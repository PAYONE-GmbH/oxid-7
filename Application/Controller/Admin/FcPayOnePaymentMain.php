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

use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;

class FcPayOnePaymentMain extends FcPayOnePaymentMain_parent
{
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * List of boolean config values
     *
     * @var array
     */
    protected array $_aConfBools = [];

    /**
     * fcpoconfigexport instance
     *
     * @var FcPoConfigExport
     */
    protected FcPoConfigExport $_oFcPoConfigExport;


    /**
     * init object construction
     *
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoConfigExport = oxNew(FcPoConfigExport::class);
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sShopId = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sShopId);
    }

    /**
     * Loads configurations of payone and make them accessible
     *
     * @param string $sShopId
     * @return void
     * @throws DatabaseConnectionException
     */
    protected function _fcpoLoadConfigs(string $sShopId): void
    {
        $aConfigs = $this->_oFcPoConfigExport->fcpoGetConfig($sShopId);
        $this->_aConfBools = $aConfigs['bools'];
    }

    /**
     * Template getter for boolean config values
     *
     * @return array
     */
    public function fcpoGetConfBools(): array
    {
        return $this->_aConfBools;
    }

    /**
     * Save Method overwriting
     *
     * @return void
     * @throws DatabaseConnectionException
     */
    public function save(): void
    {
        parent::save();

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $aConfBools = $this->_oFcPoHelper->fcpoGetRequestParameter("confbools");

        if (is_array($aConfBools)) {
            foreach ($aConfBools as $sVarName => $sVarVal) {
                $oConfig->saveShopConfVar("bool", $sVarName, (bool)$sVarVal);
            }
        }

        $sShopId = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sShopId);
    }

}
