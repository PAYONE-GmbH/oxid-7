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

namespace Fatchip\PayOne\Application\Controller\Admin;

use Fatchip\PayOne\Application\Model\FcPoTransactionStatus;
use Fatchip\PayOne\Lib\FcPoRequest;

class FcPayOneOrder extends FcPayOneAdminDetails
{

    public $_oFcpoHelper;
    /**
     * Current class template name
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_order';

    /**
     * Array with existing status of order
     *
     * @var array
     */
    private $_aStatus;

    /**
     * Holds prefix of request message to be able to translate right
     */
    private ?string $_sResponsePrefix = null;

    /**
     * Holds a current response status
     *
     * @var array
     */
    private $_aResponse;

    /**
     * Holds current status oxid
     *
     * @var string
     */
    private $_sStatusOxid;

    /**
     * Load PAYONE payment information for selected order, passes
     * it's data to Smarty engine and returns name of template file
     * "fcpayone_order.html.twig".
     *
     * @return string
     */
    public function render()
    {
        parent::render();

        $oOrder = $this->_oFcpoHelper->getFactoryObject("oxorder");

        $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            // load object
            $oOrder->load($sOxid);
            $this->_aViewData["edit"] = $oOrder;
        }

        $this->_aViewData['sHelpURL'] = $this->_oFcpoHelper->fcpoGetHelpUrl();

        return $this->_sThisTemplate;
    }

    /**
     * Returns current status object if given
     *
     * @params void
     * @return mixed
     */
    public function fcpoGetCurrentStatus()
    {
        $oReturn = false;
        $sStatusOxid = $this->fcpoGetStatusOxid();
        $sOrderOxid = $this->_oFcpoHelper->fcpoGetRequestParameter('oxid');

        if ($sStatusOxid && $sOrderOxid) {
            $oOrder = $this->fcpoGetInstance('oxOrder');
            $oTransactionStatus = $this->fcpoGetInstance(FcPoTransactionStatus::class);

            $oOrder->load($sOrderOxid);
            $oTransactionStatus->load($sStatusOxid);
            if ($oOrder->oxorder__fcpotxid->value == $oTransactionStatus->fcpotransactionstatus__fcpo_txid->value) {
                $oReturn = $oTransactionStatus;
            } else {
                $this->_sStatusOxid = '-1';
            }
        }

        return $oReturn;
    }

    /**
     * Returns the current status oxid
     *
     * @params void
     * @return mixed
     */
    public function fcpoGetStatusOxid()
    {
        if ($this->_sStatusOxid === '' || $this->_sStatusOxid === '0') {
            $sStatusOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("status_oxid");
            $this->_sStatusOxid = $sStatusOxid ?: '-1';
        }


        return $this->_sStatusOxid;
    }

    /**
     * Get all transaction status for the given order
     *
     *
     * @return array
     */
    public function getStatus()
    {
        $oOrder = null;
        if (!$this->_aStatus) {
            $this->_aStatus = [];
            $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");
            if ($sOxid != "-1" && isset($sOxid)) {
                $oOrder = $this->_oFcpoHelper->getFactoryObject('oxorder');
                $oOrder->load($sOxid);
            }

            if ($oOrder) {
                $this->_aStatus = $oOrder->fcpoGetStatus();
            }
        }
        return $this->_aStatus;
    }

    /**
     * Triggers capture request to PAYONE API and displays the result
     *
     *
     * @return null
     */
    public function capture(): void
    {
        $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcpoHelper->getFactoryObject("oxorder");
            $oOrder->load($sOxid);

            $blSettleAccount = $this->_oFcpoHelper->fcpoGetRequestParameter("capture_settleaccount");
            $blSettleAccount = ($blSettleAccount === null) ? true : (bool)$blSettleAccount;

            $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);

            $sAmount = $this->_oFcpoHelper->fcpoGetRequestParameter('capture_amount');
            if ($sAmount) {
                $dAmount = str_replace(',',
    '.', (string) $sAmount);
                $oResponse = $oPORequest->sendRequestCapture($oOrder, $dAmount, $blSettleAccount);
            } elseif ($aPositions = $this->_oFcpoHelper->fcpoGetRequestParameter('capture_positions')) {
                $dAmount = 0;
                foreach ($aPositions as $sOrderArtKey => $aOrderArt) {
                    if ($aOrderArt['capture'] == '0') {
                        unset($aPositions[$sOrderArtKey]);
                        continue;
                    }
                    $dAmount += $aOrderArt['price'];
                }

                $oResponse = $oPORequest->sendRequestCapture($oOrder, $dAmount, $blSettleAccount, $aPositions);
            }

            $this->_sResponsePrefix = 'FCPO_CAPTURE_';
            $this->_aResponse = $oResponse;
            $oOrder->fcpoSendClearingDataAfterCapture();
        }
    }

    /**
     * Triggers debit request to PAYONE API and displays the result
     *
     *
     * @return null
     */
    public function debit(): void
    {
        $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcpoHelper->getFactoryObject("oxorder");
            $oOrder->load($sOxid);

            $sBankCountry = $this->_oFcpoHelper->fcpoGetRequestParameter('debit_bankcountry');
            $sBankAccount = $this->_oFcpoHelper->fcpoGetRequestParameter('debit_bankaccount');
            $sBankCode = $this->_oFcpoHelper->fcpoGetRequestParameter('debit_bankcode');
            $sBankaccountholder = $this->_oFcpoHelper->fcpoGetRequestParameter('debit_bankaccountholder');
            $sAmount = $this->_oFcpoHelper->fcpoGetRequestParameter('debit_amount');

            $oPORequest = $this->_oFcpoHelper->getFactoryObject(FcPoRequest::class);
            if ($sAmount) {
                $dAmount = (double)str_replace(',',
    '.', (string) $sAmount);

                // amount for credit entry has to be negative
                if ($dAmount > 0) {
                    $dAmount *= -1;
                }

                if ($dAmount < 0) {
                    $oResponse = $oPORequest->sendRequestDebit($oOrder, $dAmount, $sBankCountry, $sBankAccount, $sBankCode, $sBankaccountholder);
                }
            } elseif ($aPositions = $this->_oFcpoHelper->fcpoGetRequestParameter('debit_positions')) {
                $dAmount = 0;
                foreach ($aPositions as $sOrderArtKey => $aOrderArt) {
                    if ($aOrderArt['debit'] == '0') {
                        unset($aPositions[$sOrderArtKey]);
                        continue;
                    }
                    $dAmount += (double)$aOrderArt['price'];
                }
                $oResponse = $oPORequest->sendRequestDebit($oOrder, $dAmount, $sBankCountry, $sBankAccount, $sBankCode, $sBankaccountholder, $aPositions);
            }

            $this->_sResponsePrefix = 'FCPO_DEBIT_';
            $this->_aResponse = $oResponse;
        }
    }

    /**
     * Gets the url of mandate pdf
     *
     *
     * @return string
     */
    public function fcpoGetMandatePdfUrl()
    {
        $sPdfUrl = '';
        $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");

        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcpoHelper->getFactoryObject("oxorder");
            $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
            $oOrder->load($sOxid);

            $sPdfUrl = false;

            $blFCPOMandateDownload = $oConfig->getConfigParam('blFCPOMandateDownload');

            if ($oOrder->oxorder__oxpaymenttype->value == 'fcpodebitnote' && $blFCPOMandateDownload) {
                $sFile = $oOrder->fcpoGetMandateFilename();
                if ($sFile) {
                    $sPdfUrl = $this->getViewConfig()->getSelfLink() . 'cl=fcpayone_order&amp;fnc=download&amp;oxid=' . $sOxid;
                }
            }
        }
        return $sPdfUrl;
    }

    /**
     * The download of mandate
     *
     * @param bool $blUnitTest
     */
    public function download($blUnitTest = false): void
    {
        $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blFCPOMandateDownload = $oConfig->getConfigParam('blFCPOMandateDownload');

        if ($blFCPOMandateDownload) {
            $oOrder = $this->fcpoGetInstance("oxOrder");
            $oOrder->load($sOxid);
            $sFilename = $oOrder->fcpoGetMandateFilename();

            if ($sFilename) {
                $sPath = getShopBasePath() . 'modules/fc/fcpayone/mandates/' . $sFilename;

                if (!$this->_oFcpoHelper->fcpoFileExists($sPath)) {
                    $this->_redownloadMandate($sFilename);
                }

                if ($this->_oFcpoHelper->fcpoFileExists($sPath) && !$blUnitTest) {
                    header("Content-Type: application/pdf");
                    header("Content-Disposition: attachment; filename=\"{$sFilename}\"");
                    readfile($sPath);
                }
            }
        }

        if (!$blUnitTest) {
            exit();
        }
    }

    /**
     * Trigger redownloading the mandate
     *
     * @param string $sMandateFilename
     */
    private function _redownloadMandate($sMandateFilename): void
    {
        $sOxid = $this->_oFcpoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcpoHelper->getFactoryObject("oxOrder");
            $blLoaded = $oOrder->load($sOxid);
            if ($blLoaded) {
                $sMandateIdentification = str_replace('.pdf',
    '', $sMandateFilename);

                $oPORequest = $this->_oFcpoHelper->getFactoryObject('fcporequest');
                $oPORequest->sendRequestGetFile($oOrder->getId(), $sMandateIdentification, $oOrder->oxorder__fcpomode->value);
            }
        }
    }

    /**
     * Returns request message if there is a relevant one
     *
     *
     * @return string
     */
    public function fcpoGetRequestMessage()
    {
        $sReturn = "";

        if ($this->_aResponse && is_array($this->_aResponse) && $this->_sResponsePrefix) {
            $oLang = $this->_oFcpoHelper->fcpoGetLang();
            if ($this->_aResponse['status'] == 'APPROVED') {
                $sReturn = '<span style="color: green;">' . $oLang->translateString($this->_sResponsePrefix . 'APPROVED', null, true) . '</span>';
            } elseif ($this->_aResponse['status'] == 'ERROR') {
                $sReturn = '<span style="color: red;">' . $oLang->translateString($this->_sResponsePrefix . 'ERROR', null, true) . $this->_aResponse['errormessage'] . '</span>';
            }
        }

        return $sReturn;
    }
}
