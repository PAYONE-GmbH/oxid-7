<?php

namespace Fatchip\PayOne\Application\Controller;

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Basket;

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
class FcPayOneBasketView extends FcPayOneBasketView_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Path where paypal logos can be found
     *
     * @var string
     */
    protected $_sPayPalExpressLogoPath = 'modules/fc/fcpayone/out/img/';

    /**
     * Paypal Express picture
     *
     * @var string
     */
    protected $_sPayPalExpressPic = null;

    /**
     * init object construction
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns basket error message if there is some. false if none
     *
     * @return mixed string|bool
     */
    public function fcpoGetBasketErrorMessage()
    {
        $mReturn = false;
        $sMessage = $this->_oFcPoHelper->fcpoGetRequestParameter('fcpoerror');
        if ($sMessage) {
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            $sMessage = urldecode($sMessage);
            $sMessage = $oLang->translateString($sMessage);
            $mReturn = $sMessage;
            $this->_oFcPoHelper->fcpoDeleteSessionVariable('payerrortext');
            $this->_oFcPoHelper->fcpoDeleteSessionVariable('payerror');
        }

        return $mReturn;
    }

    /**
     * Public getter for paypal express picture
     *
     * @return string
     */
    public function fcpoGetPayPalExpressPic()
    {
        if ($this->_sPayPalExpressPic === null) {
            $this->_sPayPalExpressPic = false;
            if ($this->_fcpoIsPayPalExpressActive()) {
                $this->_sPayPalExpressPic = $this->_fcpoGetPayPalExpressPic();
            }
        }

        return $this->_sPayPalExpressPic;
    }

    /**
     * Returns wether paypal express is active or not
     *
     * @return boolean
     */
    protected function _fcpoIsPayPalExpressActive()
    {
        $oBasket = $this->_oFcPoHelper->getFactoryObject(Basket::class);
        return $oBasket->fcpoIsPayPalExpressActive();
    }

    /**
     * Finally fetches needed values and set attribute value
     *
     * @return mixed
     */
    protected function _fcpoGetPayPalExpressPic()
    {
        $sPayPalExpressPic = false;
        $oBasket = $this->_oFcPoHelper->getFactoryObject(Basket::class);
        $sPic = $oBasket->fcpoGetPayPalExpressPic();

        $sPaypalExpressLogoPath = getShopBasePath() . $this->_sPayPalExpressLogoPath . $sPic;
        $blLogoPathExists = $this->_oFcPoHelper->fcpoFileExists($sPaypalExpressLogoPath);

        if ($blLogoPathExists) {
            $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
            $sShopURL = $oConfig->getCurrentShopUrl(false);
            $sPayPalExpressPic = $sShopURL . $this->_sPayPalExpressLogoPath . $sPic;
        }

        return $sPayPalExpressPic;
    }

    /**
     * Method will return false or redirect to paypal express if used
     *
     * @return boolean
     */
    public function fcpoUsePayPalExpress()
    {
        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aOutput = $oRequest->sendRequestGenericPayment();

        if ($aOutput['status'] == 'ERROR') {
            $this->_iLastErrorNo = $aOutput['errorcode'];
            $this->_sLastError = $aOutput['customermessage'];
            return false;
        } elseif ($aOutput['status'] == 'REDIRECT') {
            $this->_oFcPoHelper->fcpoSetSessionVariable('fcpoWorkorderId', $aOutput['workorderid']);
            $oUtils = $this->_oFcPoHelper->fcpoGetUtils();
            $oUtils->redirect($aOutput['redirecturl'], false);
        }
    }

    /**
     * Logout user
     *
     * @return void
     */
    public function fcpoLogoutUser()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oSession->deleteVariable('usr');
    }


}
