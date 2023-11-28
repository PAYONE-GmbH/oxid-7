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

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Country;

class FcPayOneAddress extends FcPayOneAddress_parent
{
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;


    /**
     * init object construction
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns country iso code of users country
     *
     * @param int $iVersion
     * @return string
     */
    public function fcpoGetUserCountryIso(int $iVersion = 2): string
    {
        $oCountry = $this->_oFcPoHelper->getFactoryObject(Country::class);
        if (!$oCountry->load($this->oxaddress__oxcountryid->value)) {
            return '';
        }
        $sField = "oxcountry__oxisoalpha" . $iVersion;

        return $oCountry->$sField->value;
    }

}
