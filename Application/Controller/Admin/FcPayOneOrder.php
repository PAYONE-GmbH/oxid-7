<?php

namespace Fatchip\PayOne\Application\Controller\Admin;


use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Application\Model\Order;

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
class FcPayOneOrder extends FcPayOneAdminDetails
{

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
    protected $_aStatus = null;

    /**
     * Holds the authorization method
     *
     * @var array
     */
    protected $_sAuthorizationMethod = null;

    /**
     * Holds prefix of request message to be able to translate right
     *
     * @var array
     */
    protected $_sResponsePrefix = null;

    /**
     * Holds a current response status
     *
     * @var array
     */
    protected $_aResponse = null;

    /**
     * Holds current status oxid
     *
     * @var string
     */
    protected $_sStatusOxid = null;

    /**
     * Load PAYONE payment information for selected order, passes
     * it's data to Smarty engine and returns name of template file
     * "fcpayone_order.tpl".
     *
     * @return string
     */
    public function render()
    {
        parent::render();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $oCur = $oConfig->getActShopCurrencyObject();
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);

        $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            // load object
            $oOrder->load($sOxid);
            $this->_aViewData["edit"] = $oOrder;
            $this->_aViewData["oShadowBasket"] = $oOrder->fcpoGetShadowBasket(true);
        }

        $this->_aViewData['sHelpURL'] = $this->_oFcPoHelper->fcpoGetHelpUrl();
        $this->_aViewData['currency'] = $oCur;

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
        $sOrderOxid = $this->_oFcPoHelper->fcpoGetRequestParameter('oxid');

        if ($sStatusOxid && $sOrderOxid) {
            $oOrder = $this->fcpoGetInstance(Order::class);
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
            $sStatusOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("status_oxid");
            $this->_sStatusOxid = $sStatusOxid ? $sStatusOxid : '-1';
        }


        return $this->_sStatusOxid;
    }

    /**
     * Returns the payment request method Auth/Preauthorization
     *
     * @return string
     */
    public function getAuthorizationMethod()
    {
        if (!$this->_sAuthorizationMethod) {
            $this->_sAuthorizationMethod = '';
            $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
            if ($sOxid != "-1" && isset($sOxid)) {
                $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
                $oOrder->load($sOxid);
            }

            if ($oOrder) {
                $this->_sAuthorizationMethod = $oOrder->getAuthorizationMethod();
            }
        }

        return $this->_sAuthorizationMethod;
    }

    /**
     * Returns formatted/sorted txstatus entries
     *
     * @return array{capture: array<int, array{oxid: mixed, date: mixed, amount: mixed}>, debit: array<int, array{oxid:
     *                        mixed, date: mixed, amount: mixed}>, paid: array<int, array{oxid: mixed, date: mixed,
     *                        amount: mixed}>, totalCapture: float|int, totalDebit: float|int, totalBalance: mixed}
     */
    public function getCaptureDebitEntries(): array
    {
        $aEntries = [
            'capture' => [],
            'debit' => [],
            'paid' => [],
            'totalCapture' => 0,
            'totalDebit' => 0,
            'totalBalance' => 0
        ];

        $dLastReceivable = 0.0;
        $dLastPayment = 0.0;
        foreach ($this->getStatus() as $oStatus) {
            $dReceivable = $oStatus->fcpotransactionstatus__fcpo_receivable->value;
            $dPayment = $oStatus->fcpotransactionstatus__fcpo_receivable->value - $oStatus->fcpotransactionstatus__fcpo_balance->value;

            if ($dLastPayment !== $dPayment) {
                $dPaymentAmount = $dPayment - $dLastPayment;
            } else {
                $dPaymentAmount = $oStatus->fcpotransactionstatus__fcpo_receivable->value;
            }

            if ($oStatus->fcpotransactionstatus__fcpo_txaction->value == 'capture') {
                $aEntries['capture'][] = [
                    'oxid' => $oStatus->fcpotransactionstatus__oxid->value,
                    'date' => $oStatus->fcpotransactionstatus__fcpo_txtime->value,
                    'amount' => $dPaymentAmount,
                ];
                $aEntries['totalCapture'] += $dPaymentAmount;

                $dLastReceivable = $dReceivable;
                $dLastPayment = $dPayment;

                $aEntries['totalBalance'] = $dLastReceivable;
            } elseif ($oStatus->fcpotransactionstatus__fcpo_txaction->value == 'debit') {
                $aEntries['debit'][] = [
                    'oxid' => $oStatus->fcpotransactionstatus__oxid->value,
                    'date' => $oStatus->fcpotransactionstatus__fcpo_txtime->value,
                    'amount' => $dPaymentAmount,
                ];
                $aEntries['totalDebit'] += $dPaymentAmount;

                $dLastReceivable = $dReceivable;
                $dLastPayment = $dPayment;

                $aEntries['totalBalance'] = $dLastReceivable;
            } elseif ($oStatus->fcpotransactionstatus__fcpo_txaction->value == 'paid') {
                $aEntries['paid'][] = [
                    'oxid' => $oStatus->fcpotransactionstatus__oxid->value,
                    'date' => $oStatus->fcpotransactionstatus__fcpo_txtime->value,
                    'amount' => $dPaymentAmount,
                ];

                $dLastReceivable = $dReceivable;
                $dLastPayment = $dPayment;
                $aEntries['totalBalance'] = $dLastReceivable;
            }
        }

        return $aEntries;
    }

    /**
     * Get all transaction status for the given order
     *
     * @return array
     */
    public function getStatus()
    {
        if (!$this->_aStatus) {
            $this->_aStatus = [];
            $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
            if ($sOxid != "-1" && isset($sOxid)) {
                $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
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
     * @return null
     */
    public function capture(): void
    {
        $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
            $oOrder->load($sOxid);

            $blSettleAccount = $this->_oFcPoHelper->fcpoGetRequestParameter("capture_settleaccount");
            $blSettleAccount = ($blSettleAccount === null) ? true : (bool)$blSettleAccount;

            $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);

            $sAmount = $this->_oFcPoHelper->fcpoGetRequestParameter('capture_amount');
            if ($sAmount) {
                $dAmount = str_replace(',', '.', (string)$sAmount);
                $oResponse = $oPORequest->sendRequestCapture($oOrder, $dAmount, $blSettleAccount);
            } elseif ($aPositions = $this->_oFcPoHelper->fcpoGetRequestParameter('capture_positions')) {
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
     * @return void
     */
    public function debit(): void
    {
        $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
            $oOrder->load($sOxid);

            $sBankCountry = $this->_oFcPoHelper->fcpoGetRequestParameter('debit_bankcountry');
            $sBankAccount = $this->_oFcPoHelper->fcpoGetRequestParameter('debit_bankaccount');
            $sBankCode = $this->_oFcPoHelper->fcpoGetRequestParameter('debit_bankcode');
            $sBankAccountHolder = $this->_oFcPoHelper->fcpoGetRequestParameter('debit_bankaccountholder');
            $sAmount = $this->_oFcPoHelper->fcpoGetRequestParameter('debit_amount');
            $sCancellationReason = $this->_oFcPoHelper->fcpoGetRequestParameter('bnpl_cancellation_reason');

            $oPoRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
            $oResponse = null;
            if (in_array($oOrder->oxorder__oxpaymenttype->value, ['fcpopl_secinvoice', 'fcpopl_secinstallment'])) {
                $oPoRequest->addParameter('addPayData[cancellation_reason]', $sCancellationReason);
            }

            if ($sAmount) {
                $dAmount = (double)str_replace(',', '.', (string)$sAmount);

                // amount for credit entry has to be negative
                if ($dAmount > 0) {
                    $dAmount = (double)$dAmount * -1;
                }

                if ($dAmount < 0) {
                    $oResponse = $oPoRequest->sendRequestDebit($oOrder, $dAmount, $sBankCountry, $sBankAccount, $sBankCode, $sBankAccountHolder);
                }
            } elseif ($aPositions = $this->_oFcPoHelper->fcpoGetRequestParameter('debit_positions')) {
                $dAmount = 0;
                foreach ($aPositions as $sOrderArtKey => $aOrderArt) {
                    if ($aOrderArt['debit'] == '0') {
                        unset($aPositions[$sOrderArtKey]);
                        continue;
                    }
                    $dAmount += (double)$aOrderArt['price'];
                }
                $oResponse = $oPoRequest->sendRequestDebit($oOrder, $dAmount, $sBankCountry, $sBankAccount, $sBankCode, $sBankAccountHolder, $aPositions);
            }

            $this->_sResponsePrefix = 'FCPO_DEBIT_';
            $this->_aResponse = $oResponse;
        }
    }

    /**
     * Gets the url of mandate pdf
     *
     * @return string
     */
    public function fcpoGetMandatePdfUrl()
    {
        $sPdfUrl = '';
        $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");

        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
            $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
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
        $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blFCPOMandateDownload = $oConfig->getConfigParam('blFCPOMandateDownload');

        if ($blFCPOMandateDownload) {
            $oOrder = $this->fcpoGetInstance(Order::class);
            $oOrder->load($sOxid);
            $sFilename = $oOrder->fcpoGetMandateFilename();

            if ($sFilename) {
                $sPath = getShopBasePath() . 'modules/fc/fcpayone/mandates/' . $sFilename;

                if (!$this->_oFcPoHelper->fcpoFileExists($sPath)) {
                    $this->_redownloadMandate($sFilename);
                }

                if ($this->_oFcPoHelper->fcpoFileExists($sPath) && !$blUnitTest) {
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
     * @return void
     */
    protected function _redownloadMandate($sMandateFilename)
    {
        $sOxid = $this->_oFcPoHelper->fcpoGetRequestParameter("oxid");
        if ($sOxid != "-1" && isset($sOxid)) {
            $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
            $blLoaded = $oOrder->load($sOxid);
            if ($blLoaded) {
                $sMandateIdentification = str_replace('.pdf', '', $sMandateFilename);

                $oPORequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
                $oPORequest->sendRequestGetFile($oOrder->getId(), $sMandateIdentification, $oOrder->oxorder__fcpomode->value);
            }
        }
    }

    /**
     * Returns request message if there is a relevant one
     *
     * @return string
     */
    public function fcpoGetRequestMessage()
    {
        $sReturn = "";

        if ($this->_aResponse && is_array($this->_aResponse) && $this->_sResponsePrefix) {
            $oLang = $this->_oFcPoHelper->fcpoGetLang();
            if ($this->_aResponse['status'] == 'APPROVED') {
                $sReturn = '<span style="color: green;">' . $oLang->translateString($this->_sResponsePrefix . 'APPROVED', null, true) . '</span>';
            } elseif ($this->_aResponse['status'] == 'ERROR') {
                $sReturn = '<span style="color: red;">' . $oLang->translateString($this->_sResponsePrefix . 'ERROR', null, true) . $this->_aResponse['errormessage'] . '</span>';
            }
        }

        return $sReturn;
    }

}
