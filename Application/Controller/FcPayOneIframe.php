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

namespace Fatchip\PayOne\Application\Controller;

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Controller\FrontendController;

class FcPayOneIframe extends FrontendController
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected $_oFcpoHelper;

    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/frontend/fcpayoneiframe';

    /**
     * Order object
     *
     * @var object
     */
    protected $_oOrder;


    /**
     * Class constructor, sets all required parameters for requests.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Returns iframe url or redirects directly to it
     *
     *
     * @return mixed
     */
    public function getIframeUrl()
    {
        $sIframeUrl = $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoRedirectUrl');
        if ($sIframeUrl) {
            return $sIframeUrl;
        } else {
            /* Maybe needed for future payment-methods
            $oOrder = $this->getOrder();
            if($oOrder) {
                return $oOrder->fcHandleAuthorization(true);
            }
            */
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $oUtils = $this->_oFcpoHelper->fcpoGetUtils();
            $oUtils->redirect($oConfig->getShopCurrentURL() . '&cl=payment');
        }
    }

    /**
     * Get the height of the iframe
     *
     *
     * @return string
     */
    public function getIframeHeight()
    {
        $sHeight = null;
        $sPaymentId = $this->getPaymentType();
        if ($sPaymentId === 'fcpocreditcard_iframe') {
            $sHeight = 700;
        }
        return $sHeight;
    }

    /**
     * Get payment type
     *
     *
     * @return string
     */
    public function getPaymentType()
    {
        $oOrder = $this->getOrder();
        if ($oOrder && !empty($oOrder->oxorder__oxpaymenttype->value)) {
            $sPaymentId = $oOrder->oxorder__oxpaymenttype->value;
        } else {
            $sPaymentId = $this->_oFcpoHelper->fcpoGetSessionVariable('paymentid');
        }
        return $sPaymentId;
    }

    /**
     * Returns the order object
     *
     *
     * @return object
     */
    public function getOrder()
    {
        if ($this->_oOrder === null) {
            $sOrderId = $this->_oFcpoHelper->fcpoGetSessionVariable('sess_challenge');
            if ($sOrderId) {
                $oOrder = $this->_oFcpoHelper->getFactoryObject('oxOrder');
                if ($oOrder->load($sOrderId)) {
                    $this->_oOrder = $oOrder;
                }
            }
        }
        return $this->_oOrder;
    }

    /**
     * Returns a factory instance of given object
     *
     * @param string $sName
     * @return object oxOrder
     */
    public function getFactoryObject($sName)
    {
        return oxNew($sName);
    }

    /**
     * Get the width of the iframe
     *
     *
     * @return string
     */
    public function getIframeWidth()
    {
        $sWidth = null;
        $sPaymentId = $this->getPaymentType();
        if ($sPaymentId === 'fcpocreditcard_iframe') {
            $sWidth = 360;
        }
        return $sWidth;
    }

    /**
     * Get the style of the iframe
     *
     *
     * @return string
     */
    public function getIframeStyle()
    {
        $sStyle = null;
        $sPaymentId = $this->getPaymentType();
        if ($sPaymentId === 'fcpocreditcard_iframe') {
            $sStyle = "border:0;margin-top:20px;";
        }
        return $sStyle;
    }

    /**
     * Get the header of iframe
     *
     *
     * @return string
     */
    public function getIframeHeader()
    {
        $sHeader = null;
        $sPaymentId = $this->getPaymentType();
        if ($sPaymentId === 'fcpocreditcard_iframe') {
            $sHeader = $this->_oFcpoHelper->fcpoGetLang()->translateString('FCPO_CC_IFRAME_HEADER');
        }
        return $sHeader;
    }

    /**
     * Get text of iframe
     *
     *
     * @return mixed
     */
    public function getIframeText()
    {
        $sText = null;
        $sPaymentId = $this->getPaymentType();
        if ($sPaymentId === 'fcpocreditcard_iframe') {
            $sText = false;
        }
        return $sText;
    }
}
