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

use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcPayOneThankYouView extends FcPayOneThankYouView_parent
{


    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper;

    /**
     * Instance of DatabaseProvider
     *
     * @var DatabaseProvider
     */
    protected DatabaseInterface $_oFcpoDb;

    /**
     * Mandate pdf url
     *
     * @var string
     */
    protected $_sMandatePdfUrl;

    /**
     * Html for Barzahlen
     *
     * @var string
     */
    protected $_sBarzahlenHtml;

    /**
     * Flag indicates if current order is/was of type amazon
     *
     * @var bool
     */
    protected $_blIsAmazonOrder = false;


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
     * Returns generated mandate pdf url and deletes it from session afterwards
     *
     *
     * @return string
     */
    public function fcpoGetMandatePdfUrl()
    {
        $sPdfUrl = false;
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oOrder = $this->getOrder();


        if ($oOrder->oxorder__oxpaymenttype->value == 'fcpodebitnote' && $oConfig->getConfigParam('blFCPOMandateDownload')) {
            $sMandateIdentification = false;
            $oPayment = $this->_oFcpoHelper->getFactoryObject('oxPayment');
            $oPayment->load($oOrder->oxorder__oxpaymenttype->value);
            $sMode = $oPayment->fcpoGetOperationMode();

            $aMandate = $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoMandate');

            if ($aMandate && array_key_exists('mandate_identification', $aMandate)) {
                $sMandateIdentification = $aMandate['mandate_identification'];
            }


            if ($sMandateIdentification && $aMandate['mandate_status'] == 'active') {
                $oPayment->fcpoAddMandateToDb($oOrder->getId(), $sMandateIdentification);
                $sPdfUrl = $oConfig->getShopUrl() . "modules/fc/fcpayone/download.php?id=" . $oOrder->getId();
            } elseif ($sMandateIdentification && $sMode && $oOrder) {
                $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
                $sPdfUrl = $oPORequest->sendRequestGetFile($oOrder->getId(), $sMandateIdentification, $sMode);
            }

            $oUser = $this->getUser();
            if (!$oUser || !$oUser->oxuser__oxpassword->value) {
                $sPdfUrl .= '&uid=' . $this->_oFcpoHelper->fcpoGetSessionVariable('sFcpoUserId');
            }
        }
        $this->_sMandatePdfUrl = $sPdfUrl;
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoMandate');

        return $this->_sMandatePdfUrl;
    }


    /**
     * Method checks for an appointment error
     *
     *
     * @return bool
     */
    public function fcpoIsAppointedError()
    {
        $blReturn = false;
        $oOrder = $this->getOrder();

        if ($oOrder->isPayOnePaymentType() && ($oOrder->oxorder__oxfolder->value == 'ORDERFOLDER_PROBLEMS' && $oOrder->oxorder__oxtransstatus->value == 'ERROR')) {
            $blReturn = true;
        }

        return $blReturn;
    }


    /**
     * Sets userid into session berfore triggering the parent method
     *
     *
     * @return string
     */
    public function render()
    {
        $oUser = $this->getUser();
        if ($oUser) {
            $this->_oFcpoHelper->fcpoSetSessionVariable('sFcpoUserId', $oUser->getId());
        }

        $this->_fcpoHandleAmazonThankyou();
        $this->_fcpoDeleteSessionVariablesOnOrderFinish();

        return null;
    }

    /**
     * Loggs off Amazon if this is an Amazon order
     *
     */
    private function _fcpoHandleAmazonThankyou(): void
    {
        $blIsAmazonOrder = $this->_fcpoDetermineAmazonOrder();
        if ($blIsAmazonOrder) {
            $this->_blIsAmazonOrder = true;
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('sAmazonLoginAccessToken');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoAmazonWorkorderId');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoAmazonReferenceId');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoAmazonPayAddressWidgetLocked');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('amazonRefNr');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('usr');
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoAmazonPayOrderIsPending');
        }
    }

    /**
     * Checks if current order is of type amazon
     *
     *
     * @return bool
     */
    private function _fcpoDetermineAmazonOrder()
    {
        $blReturn = false;
        $sAmazonLoginAccessToken = $this->_oFcpoHelper->fcpoGetSessionVariable('sAmazonLoginAccessToken');
        if ($sAmazonLoginAccessToken) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Deletes session variables that should not last after finishing order
     *
     */
    private function _fcpoDeleteSessionVariablesOnOrderFinish(): void
    {
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('fcpoRefNr');
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('klarna_authorization_token');
        $this->_oFcpoHelper->fcpoDeleteSessionVariable('klarna_client_token');
    }

    /**
     * Returns if current order is of type amazon
     *
     *
     * @return bool
     */
    public function fcpoIsAmazonOrder()
    {
        return $this->_blIsAmazonOrder;
    }

    /**
     * Returns the html of barzahlen instructions
     *
     *
     * @return mixed
     */
    public function fcpoGetBarzahlenHtml()
    {
        if ($this->_sBarzahlenHtml === null) {
            $this->_sBarzahlenHtml = $this->_oFcpoHelper->fcpoGetSessionVariable('sFcpoBarzahlenHtml');
            // delete this from session after we have the result for one time displaying
            $this->_oFcpoHelper->fcpoDeleteSessionVariable('sFcpoBarzahlenHtml');
        }

        return $this->_sBarzahlenHtml;
    }

    /**
     * View controller getter for deciding if clearing data should be shown
     *
     *
     * @return bool
     */
    public function fcpoShowClearingData()
    {
        $oOrder = $this->getOrder();

        return $oOrder->fcpoShowClearingData($oOrder);
    }
}
