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
     * Method will be called when returning from amazonlogin
     *
     * @return void
     */
    public function fcpoAmazonLoginReturn()
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oUtilsServer = Registry::get(UtilsServer::class);
        $sPaymentId = 'fcpoamazonpay';

        // OXID-233 : if the user is logged in, we save the id in session for later
        // AmazonPay process uses a new user, created on the fly
        // Then we need the original Id to link back the order to the initial user
        $user = Registry::getSession()->getUser();
        if ($user) {
            Registry::getSession()->setVariable('sOxidPreAmzUser', $user->getId());
        }

        // delete possible old data
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('sAmazonLoginAccessToken');

        $sAmazonLoginAccessTokenParam = $this->_oFcPoHelper->fcpoGetRequestParameter('access_token');
        $sAmazonLoginAccessTokenParam = urldecode($sAmazonLoginAccessTokenParam);
        $sAmazonLoginAccessTokenCookie = $oUtilsServer->getOxCookie('amazon_Login_accessToken');
        $blNeededDataAvailable = (bool)($sAmazonLoginAccessTokenParam || $sAmazonLoginAccessTokenCookie);

        if ($blNeededDataAvailable) {
            $sAmazonLoginAccessToken =
                ($sAmazonLoginAccessTokenParam) ? $sAmazonLoginAccessTokenParam : $sAmazonLoginAccessTokenCookie;
            $this->_oFcPoHelper->fcpoSetSessionVariable('sAmazonLoginAccessToken', $sAmazonLoginAccessToken);
            $this->_oFcPoHelper->fcpoSetSessionVariable('paymentid', $sPaymentId);
            $this->_oFcPoHelper->fcpoSetSessionVariable('_selected_paymentid', $sPaymentId);
            $oBasket = $oSession->getBasket();
            $oBasket->setPayment($sPaymentId);
        } else {
            $this->_fcpoHandleAmazonNoTokenFound();
        }

        // go ahead with rendering
        $this->render();
    }

    /**
     * Handles the case that there is no access token available/accessable
     *
     * @return void
     */
    protected function _fcpoHandleAmazonNoTokenFound()
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $aAllowedDoubleRedirectModes = array('redirect', 'auto');
        $sFCPOAmazonLoginMode = $oConfig->getConfigParam('sFCPOAmazonLoginMode');
        $blAllowedForDoubleRedirect = (in_array($sFCPOAmazonLoginMode, $aAllowedDoubleRedirectModes));

        if ($blAllowedForDoubleRedirect) {
            // we need to fetch the token from location hash (via js) and put it into a cookie first
            $this->_aViewData['blFCPOAmazonCatchHash'] = true;
            $this->render();
        } else {
            // @todo: Redirect to basket with message, currently redirect without comment
            $oUtils = $this->_oFcPoHelper->fcpoGetUtils();
            $sShopUrl = $oConfig->getShopUrl();
            $oUtils->redirect($sShopUrl . "index.php?cl=basket");
        }
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
