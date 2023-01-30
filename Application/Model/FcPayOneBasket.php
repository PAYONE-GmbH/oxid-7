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
use oxfield;
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
    protected FcPoHelper $_oFcpoHelper;
    /** @var string */
    private const S_QUERY = "SELECT oxactive FROM oxpayments WHERE oxid = 'fcpopaypal_express'";
    /** @var array<string, string> */
    private const A_ASSIGN_MAP = ['green' => 'paydirekt-express-gruen.png', 
    'green2' => 'paydirekt-express-gruen2.png', 
    'white' => 'paydirekt-express-weiss.png', 
    'white2' => 'paydirekt-express-weiss2.png'];


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }


    /**
     * Returns whether paypal express is active or not
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function fcpoIsPayPalExpressActive()
    {
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        return (bool)$oDb->GetOne(self::S_QUERY);
    }


    /**
     * Returns pic that is configured in database
     *
     * @return string
     * @throws DatabaseConnectionException
     */
    public function fcpoGetPayPalExpressPic(): string
    {
        $oDb = $this->_oFcpoHelper->fcpoGetDb();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $iLangId = $oLang->getBaseLanguage();
        $sQuery = "SELECT fcpo_logo FROM fcpopayoneexpresslogos WHERE fcpo_logo != '' AND fcpo_langid = '{$iLangId}' ORDER BY fcpo_default DESC";

        return $oDb->GetOne($sQuery);
    }

    /**
     * Returns matching paydirekt express picture by config
     *
     *
     * @return string
     */
    public function fcpoGetPaydirektExpressPic(): string
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sButtonType = $oConfig->getConfigParam('sPaydirektExpressButtonType');
        $blAvailable = array_key_exists($sButtonType, self::A_ASSIGN_MAP);

        return ($blAvailable) ? self::A_ASSIGN_MAP[$sButtonType] : self::A_ASSIGN_MAP['green'];
    }

    /**
     * Iterates through basket items and calculates its delivery costs
     *
     * @return Price
     */
    public function fcpoCalcDeliveryCost(): Price
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oDeliveryPrice = oxNew('oxprice');
        if ($oConfig->getConfigParam('blDeliveryVatOnTop')) {
            $oDeliveryPrice->setNettoPriceMode();
        } else {
            $oDeliveryPrice->setBruttoPriceMode();
        }
        $oUser = oxNew(User::class);
        $oUser->oxuser__oxcountryid = new Field('a7c40f631fc920687.20179984');
        $fDelVATPercent = $this->getAdditionalServicesVatPercent();
        $oDeliveryPrice->setVat($fDelVATPercent);
        $aDeliveryList = Registry::get("oxDeliveryList")->getDeliveryList(
            $this,
            $oUser,
            $this->_findDelivCountry(),
            $this->getShippingId()
        );
        foreach ($aDeliveryList as $oDelivery) {
            //debug trace
            if ($oConfig->getConfigParam('iDebug') == 5) {
                echo("DelCost : " . $oDelivery->oxdelivery__oxtitle->value . "<br>");
            }
            $oDeliveryPrice->addPrice($oDelivery->getDeliveryPrice($fDelVATPercent));
        }

        return $oDeliveryPrice;
    }
}
