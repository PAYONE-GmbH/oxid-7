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

namespace Fatchip\PayOne\Application\Helper;

use Fatchip\PayOne\Lib\FcPoHelper;

abstract class Base
{
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected $_oHelper = null;

    /**
     * Returns fcpohelper object
     *
     * @return FcPoHelper
     */
    public function getMainHelper() {
        if ($this->_oHelper === null) {
            $this->_oHelper = oxNew(FcPoHelper::class);
        }
        return $this->_oHelper;
    }
}
