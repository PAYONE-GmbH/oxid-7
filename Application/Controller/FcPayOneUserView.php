<?php

/**
 * Created by PhpStorm.
 * User: andre
 * Date: 13.07.17
 * Time: 17:50
 */

namespace Fatchip\PayOne\Application\Controller;

use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use oxregistry;

class FcPayOneUserView extends FcPayOneUserView_parent
{

    /**
     * @var array<string, bool>
     */
    public $_aViewData;
    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper;

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected DatabaseInterface $_oFcpoDb;
    /** @var string */
    private const S_PAYMENT_ID = 'fcpoamazonpay';
    /** @var string[] */
    private const A_ALLOWED_DOUBLE_REDIRECT_MODES = ['redirect', 
    'auto'];


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
        $this->_oFcpoDb = DatabaseProvider::getDb();
    }

    /**
     * Method will be called when returning from amazonlogin
     *
     */
    public function fcpoAmazonLoginReturn(): void
    {
        $oSession = $this->getSession();
        $oUtilsServer = oxRegistry::get('oxUtilsServer');

        // OXID-233 : if the user is logged in, we save the id in session for later
        // AmazonPay process uses a new user, created on the fly
        // Then we need the original Id to link back the order to the initial user
        $user = oxRegistry::getSession()->getUser();
        if ($user) {
            oxRegistry::getSession()->setVariable('sOxidPreAmzUser', $user->getId());
        }

        // delete possible old data
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('sAmazonLoginAccessToken');

        $sAmazonLoginAccessTokenParam = $this->_oFcpoHelper->fcpoGetRequestParameter('access_token');
        $sAmazonLoginAccessTokenParam = urldecode((string) $sAmazonLoginAccessTokenParam);
        $sAmazonLoginAccessTokenCookie = $oUtilsServer->getOxCookie('amazon_Login_accessToken');
        $blNeededDataAvailable = $sAmazonLoginAccessTokenParam || $sAmazonLoginAccessTokenCookie;

        if ($blNeededDataAvailable) {
            $sAmazonLoginAccessToken =
                ($sAmazonLoginAccessTokenParam !== '' && $sAmazonLoginAccessTokenParam !== '0') ? $sAmazonLoginAccessTokenParam : $sAmazonLoginAccessTokenCookie;
            $this->_oFcpoHelper->fcpoSetSessionVariable('sAmazonLoginAccessToken', $sAmazonLoginAccessToken);
            $this->_oFcpoHelper->fcpoSetSessionVariable('paymentid', self::S_PAYMENT_ID);
            $this->_oFcpoHelper->fcpoSetSessionVariable('_selected_paymentid', self::S_PAYMENT_ID);
            $oBasket = $oSession->getBasket();
            $oBasket->setPayment(self::S_PAYMENT_ID);
        } else {
            $this->_fcpoHandleAmazonNoTokenFound();
        }

        // go ahead with rendering
        $this->render();
    }

    /**
     * Handles the case that there is no access token available/accessable
     *
     */
    private function _fcpoHandleAmazonNoTokenFound(): void
    {
        $oConfig = $this->getConfig();
        $sFCPOAmazonLoginMode = $oConfig->getConfigParam('sFCPOAmazonLoginMode');
        $blAllowedForDoubleRedirect = (in_array($sFCPOAmazonLoginMode, self::A_ALLOWED_DOUBLE_REDIRECT_MODES));

        if ($blAllowedForDoubleRedirect) {
            // we need to fetch the token from location hash (via js) and put it into a cookie first
            $this->_aViewData['blFCPOAmazonCatchHash'] = true;
            $this->render();
        } else {
            // @todo: Redirect to basket with message, currently redirect without comment
            $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
            $sShopUrl = $oConfig->getShopUrl();
            $oUtils->redirect($sShopUrl . "index.php?cl=basket");
        }
    }

    /**
     * Returns user error message if there is some. false if none
     *
     *
     * @return mixed string|bool
     */
    public function fcpoGetUserErrorMessage()
    {
        $mReturn = false;
        $sMessage = $this->_oFcpoHelper->fcpoGetRequestParameter('fcpoerror');
        if ($sMessage) {
            $mReturn = urldecode((string) $sMessage);
        }

        return $mReturn;
    }
}
