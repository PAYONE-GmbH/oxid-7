<?php

namespace Fatchip\PayOne\Application\Controller;

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;

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
class FcPayOneThankYouView extends FcPayOneThankYouView_parent
{


    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoHelper = null;

    /**
     * Instance of DatabaseProvider
     *
     * @var object
     */
    protected $_oFcPoDb = null;

    /**
     * Mandate pdf url
     *
     * @var string
     */
    protected $_sMandatePdfUrl = null;

    /**
     * Html for Barzahlen
     *
     * @var string
     */
    protected $_sBarzahlenHtml = null;

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
     * Returns generated mandate pdf url and deletes it from session afterwards
     *
     * @return string
     */
    public function fcpoGetMandatePdfUrl()
    {
        $sPdfUrl = false;
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oOrder = $this->getOrder();


        if ($oOrder->oxorder__oxpaymenttype->value == 'fcpodebitnote' && $oConfig->getConfigParam('blFCPOMandateDownload')) {
            $sMandateIdentification = false;
            $oPayment = $this->_oFcPoHelper->getFactoryObject(Payment::class);
            $oPayment->load($oOrder->oxorder__oxpaymenttype->value);
            $sMode = $oPayment->fcpoGetOperationMode();

            $aMandate = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoMandate');

            if ($aMandate && array_key_exists('mandate_identification', $aMandate) !== false) {
                $sMandateIdentification = $aMandate['mandate_identification'];
            }


            if ($sMandateIdentification && $aMandate['mandate_status'] == 'active') {
                $oPayment->fcpoAddMandateToDb($oOrder->getId(), $sMandateIdentification);
                $sPdfUrl = $oConfig->getShopUrl() . "modules/fc/fcpayone/download.php?id=" . $oOrder->getId();
            } elseif ($sMandateIdentification && $sMode && $oOrder) {
                $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
                $sPdfUrl = $oPORequest->sendRequestGetFile($oOrder->getId(), $sMandateIdentification, $sMode);
            }

            $oUser = $this->getUser();
            if (!$oUser || !$oUser->oxuser__oxpassword->value) {
                $sPdfUrl .= '&uid=' . $this->_oFcPoHelper->fcpoGetSessionVariable('sFcpoUserId');
            }
        }
        $this->_sMandatePdfUrl = $sPdfUrl;
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoMandate');

        return $this->_sMandatePdfUrl;
    }


    /**
     * Method checks if any error occured (appointment-error, fraud etc.)
     *
     * @return bool
     */
    public function fcpoOrderHasProblems()
    {
        $oOrder = $this->getOrder();
        $blIsPayone = $oOrder->isPayOnePaymentType();

        return $blIsPayone &&
        $oOrder->oxorder__oxfolder->value == 'ORDERFOLDER_PROBLEMS' &&
        $oOrder->oxorder__oxtransstatus->value == 'ERROR';
    }


    /**
     * Sets userid into session berfore triggering the parent method
     *
     * @return string
     */
    public function render()
    {
        $oUser = $this->getUser();
        if ($oUser) {
            $this->_oFcPoHelper->fcpoSetSessionVariable('sFcpoUserId', $oUser->getId());
        }

        $this->_fcpoDeleteSessionVariablesOnOrderFinish();

        return parent::render();
    }

    /**
     * Deletes session variables that should not last after finishing order
     *
     * @return void
     */
    protected function _fcpoDeleteSessionVariablesOnOrderFinish()
    {
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('fcpoRefNr');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('klarna_authorization_token');
        $this->_oFcPoHelper->fcpoDeleteSessionVariable('klarna_client_token');
    }

    /**
     * Returns the html of barzahlen instructions
     *
     * @return mixed
     */
    public function fcpoGetBarzahlenHtml()
    {
        if ($this->_sBarzahlenHtml === null) {
            $this->_sBarzahlenHtml = $this->_oFcPoHelper->fcpoGetSessionVariable('sFcpoBarzahlenHtml');
            // delete this from session after we have the result for one time displaying
            $this->_oFcPoHelper->fcpoDeleteSessionVariable('sFcpoBarzahlenHtml');
        }

        return $this->_sBarzahlenHtml;
    }

    /**
     * View controller getter for deciding if clearing data should be shown
     *
     * @return bool
     */
    public function fcpoShowClearingData()
    {
        $oOrder = $this->getOrder();

        return $oOrder->fcpoShowClearingData($oOrder);
    }
}
