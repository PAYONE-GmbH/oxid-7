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
 * @link      http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version   OXID eShop CE
 */

namespace Fatchip\PayOne\Application\Controller;

use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;

class FcPayOneBasketView extends FcPayOneBasketView_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper = null;

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
     * Paydirekt Express picture
     * @var string|null
     */
    protected $_sPaydirektExpressPic = null;

    /**
     * init object construction
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Overloading render method for checking on amazon logoff
     *
     * @return string
     */
    public function render()
    {
        $this->_fcpoCheckForAmazonLogoff();
        return parent::render();
    }

    /**
     * Returns basket error message if there is some. false if none
     *
     * @return mixed string|bool
     */
    public function fcpoGetBasketErrorMessage()
    {
        $mReturn = false;
        $sMessage = $this->_oFcpoHelper->fcpoGetRequestParameter('fcpoerror');
        if ($sMessage) {
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            $sMessage = urldecode($sMessage);
            $sMessage = $oLang->translateString($sMessage);
            $mReturn = $sMessage;
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('payerrortext');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('payerror');
        }

        return $mReturn;
    }

    /**
     * Method checks for param fcpoamzaction and logoff from Amazon Session if
     * value is set to logoff
     *
     * @return void
     */
    protected function _fcpoCheckForAmazonLogoff()
    {
        $sAmzAction = $this->_oFcpoHelper->fcpoGetRequestParameter('fcpoamzaction');
        if ($sAmzAction == 'logoff') {
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('sAmazonLoginAccessToken');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoAmazonWorkorderId');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoAmazonReferenceId');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('amazonRefNr');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoRefNr');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('usr');
        }
    }

    /**
     * Returns wether paypal express is active or not
     *
     * @return boolean
     */
    protected function _fcpoIsPayPalExpressActive()
    {
        $oBasket = $this->_oFcpoHelper->getFactoryObject('oxBasket');
        return $oBasket->fcpoIsPayPalExpressActive();
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
     * Finally fetches needed values and set attribute value
     *
     * @return mixed
     */
    protected function _fcpoGetPayPalExpressPic()
    {
        $sPayPalExpressPic = false;
        $oBasket = $this->_oFcpoHelper->getFactoryObject('oxBasket');
        $sPic = $oBasket->fcpoGetPayPalExpressPic();

        $sPaypalExpressLogoPath = getShopBasePath() . $this->_sPayPalExpressLogoPath . $sPic;
        $blLogoPathExists = $this->_oFcpoHelper->fcpoFileExists($sPaypalExpressLogoPath);

        if ($blLogoPathExists) {
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
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
        $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aOutput = $oRequest->sendRequestGenericPayment();

        if ($aOutput['status'] == 'ERROR') {
            $this->_iLastErrorNo = $aOutput['errorcode'];
            $this->_sLastError = $aOutput['customermessage'];
            return false;
        } elseif ($aOutput['status'] == 'REDIRECT') {
            $this->_oFcpoHelper->fcpoSetSessionVariable('fcpoWorkorderId', $aOutput['workorderid']);
            $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
            $oUtils->redirect($aOutput['redirecturl'], false);
        }
    }

    /**
     * Public getter for paydirekt express picture
     *
     * @return mixed
     */
    public function fcpoGetPaydirektExpressPic()
    {
        if ($this->_sPaydirektExpressPic === null) {
            $this->_sPaydirektExpressPic = false;
            if ($this->_fcpoIsPaydirektExpressActive()) {
                $this->_sPaydirektExpressPic =
                    $this->_fcpoGetPaydirektExpressPic();
            }
        }
        return $this->_sPaydirektExpressPic;
    }
    /**
     * Returns if paydirekt express is set active
     *
     * @return bool
     */
    protected function _fcpoIsPaydirektExpressActive()
    {
        $oPayment = $this->_oFcpoHelper->getFactoryObject('oxpayment');
        $oPayment->load('fcpopaydirekt_express');
        $blIsActive = (bool) $oPayment->oxpayments__oxactive->value;
        return $blIsActive;
    }
    /**
     * Returns actual Paydirekt picture. Using paypal express path due its
     * actually the same
     *
     * @return mixed
     */
    protected function _fcpoGetPaydirektExpressPic()
    {
        $sPaydirektExpressPic = false;
        $oBasket = $this->_oFcpoHelper->getFactoryObject('oxBasket');
        $sPic = $oBasket->fcpoGetPaydirektExpressPic();
        $sPaydirektExpressLogoPath =
            getShopBasePath() .
            $this->_sPayPalExpressLogoPath .
            $sPic;
        $blLogoPathExists =
            $this->_oFcpoHelper->fcpoFileExists($sPaydirektExpressLogoPath);
        if ($blLogoPathExists) {
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $sShopURL = $oConfig->getCurrentShopUrl(false);
            $sPaydirektExpressPic =
                $sShopURL . $this->_sPayPalExpressLogoPath . $sPic;
        }
        return $sPaydirektExpressPic;
    }
    /**
     * Calling paydirekt express and deliver REDIRECT or false if not available
     *
     * @return boolean|void
     */
    public function fcpoUsePaydirektExpress()
    {
        $this->fcpoLogoutUser();

        $oRequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
        $aOutput = $oRequest->sendRequestPaydirektCheckout();
        $blIsRedirect = ($aOutput['status'] == 'REDIRECT');
        if ($blIsRedirect) {
            $this->_oFcpoHelper
                ->fcpoSetSessionVariable('fcpoWorkorderId', $aOutput['workorderid']);
            $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
            $oUtils->redirect($aOutput['redirecturl'], false);
            return;
        }
        $this->_iLastErrorNo = $aOutput['errorcode'];
        $this->_sLastError = $aOutput['customermessage'];

        return false;
    }


    /**
     * Logout user
     *
     * @return void
     */
    public function fcpoLogoutUser()
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oSession->deleteVariable('usr');
    }
}
