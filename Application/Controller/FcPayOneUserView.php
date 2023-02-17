<?php

namespace Fatchip\PayOne\Application\Controller;

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\UtilsServer;

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
class FcPayOneUserView extends FcPayOneUserView_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoHelper = null;

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoDb = null;


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
    }

    /**
     * Returns user error message if there is some. false if none
     *
     * @return mixed string|bool
     */
    public function fcpoGetUserErrorMessage()
    {
        $mReturn = false;
        $sMessage = $this->_oFcPoHelper->fcpoGetRequestParameter('fcpoerror');
        if ($sMessage) {
            $sMessage = urldecode($sMessage);
            $mReturn = $sMessage;
        }

        return $mReturn;
    }

}
