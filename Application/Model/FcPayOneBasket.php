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

use Fatchip\PayOne\Application\Helper\Payment;
use Fatchip\PayOne\Application\Helper\PayPal;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\DeliveryList;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Registry;

class FcPayOneBasket extends FcPayOneBasket_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;


    /**
     * init object construction
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Iterates through basket items and calculates its delivery costs
     *
     * @return Price
     */
    public function fcpoCalcDeliveryCost(): Price
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oDeliveryPrice = oxNew(Price::class);
        if ($oConfig->getConfigParam('blDeliveryVatOnTop')) {
            $oDeliveryPrice->setNettoPriceMode();
        } else {
            $oDeliveryPrice->setBruttoPriceMode();
        }
        $oUser = oxNew(User::class);
        $oUser->oxuser__oxcountryid = new Field('a7c40f631fc920687.20179984');
        $sDelCountry = $this->findDelivCountry();
        if (!$sDelCountry) {
            $sDelCountry = $oUser->oxuser__oxcountryid->value;
        }
        $fDelVATPercent = $this->getAdditionalServicesVatPercent();
        $oDeliveryPrice->setVat($fDelVATPercent);
        $aDeliveryList = Registry::get(DeliveryList::class)->getDeliveryList(
            $this,
            $oUser,
            $sDelCountry,
            $this->getShippingId()
        );
        if (count($aDeliveryList) > 0) {
            foreach ($aDeliveryList as $oDelivery) {
                //debug trace
                if ($oConfig->getConfigParam('iDebug') == 5) {
                    echo("DelCost : " . $oDelivery->oxdelivery__oxtitle->value . "<br>");
                }
                $oDeliveryPrice->addPrice($oDelivery->getDeliveryPrice($fDelVATPercent));
            }
        }

        return $oDeliveryPrice;
    }

    public function getUser()
    {
        $oUser = parent::getUser();
        return $oUser ? $oUser : $this->_oUser;
    }

}
