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

class FcPayOneOrderArticle extends FcPayOneOrderArticle_parent
{

    public $oxorderarticles__oxamount;
    public $oxorderarticles__oxartid;
    public $oxorderarticles__oxstorno;
    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper;

    /**
     * Flag for redirecting after save
     *
     * @var bool
     */
    protected $_blIsRedirectAfterSave;

    /**
     * Flag for finishing order completely
     *
     * @var bool
     */
    protected $_blFinishingSave = true;

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
     * Sets flag for finishing save
     */
    public function fcpoSetFinishingSave(bool $blFinishingSave): void
    {
        $this->_blFinishingSave = $blFinishingSave;
    }

    /**
     * Overrides standard oxid save method
     *
     * Saves order article object. If saving succeded - updates
     * article stock information if oxOrderArticle::isNewOrderItem()
     * returns TRUE. Returns saving status
     *
     * @return bool
     */
    public function save()
    {
        $oOrder = $this->getOrder();
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blPresaveOrder = $oConfig->getConfigParam('blFCPOPresaveOrder');

        # @see https://integrator.payone.de/jira/browse/OX6-87
        if (is_null($oOrder)) {
            return null;
        }

        $blUseParentOrderMethod = (
            $blPresaveOrder === false ||
            $oOrder->isPayOnePaymentType() === false
        );

        if ($blUseParentOrderMethod) {
            return null;
        }

        $blBefore = $this->_fcpoGetBefore();
        $blReduceStockAfterRedirect = $this->_fcCheckReduceStockAfterRedirect();
        if ($blReduceStockAfterRedirect) {
            $this->updateArticleStock($this->oxorderarticles__oxamount->value * (-1), $oConfig->getConfigParam('blAllowNegativeStock'));
        }

        // ordered articles
        if (($blSave = oxBase::save()) && $this->isNewOrderItem() || $blBefore === false) {
            if ($oConfig->getConfigParam('blUseStock') && $oConfig->getConfigParam('blPsBasketReservationEnabled')) {
                $this->getSession()
                    ->getBasketReservations()
                    ->commitArticleReservation(
                        $this->oxorderarticles__oxartid->value,
                        $this->oxorderarticles__oxamount->value
                    );
            }

            $this->_setOrderFiles();

            // marking object as "non new" disable further stock changes
            $this->setIsNewOrderItem(false);
        }

        return $blSave;
    }

    /**
     * Returns wether payone order should be pre-saved
     *
     * 
     * @return bool
     */
    private function _fcpoGetBefore()
    {
        $blFinishingSave = $this->_blFinishingSave;
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        $blReduceStockBefore = !(bool)$oConfig->getConfigParam('blFCPOReduceStock');

        // evaluate answer
        $blBefore = (
            $this->_oFcpoHelper->fcpoGetRequestParameter('fcposuccess') &&
            $this->_oFcpoHelper->fcpoGetRequestParameter('refnr') ||
            (
                $blFinishingSave &&
                $blPresaveOrder &&
                $blReduceStockBefore === false
            )
        );

        return $blBefore;
    }

    /**
     * Method checks conditions for reducing stock after using a redirect payment
     * It depends on settings and payment method
     *
     * 
     * @return boolean
     */
    private function _fcCheckReduceStockAfterRedirect()
    {
        if (isAdmin()) {
            return false;
        }

        $oOrder = $this->getOrder();
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $sPaymentId = $oBasket->getPaymentId();
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        $blIsRedirectPayment = FcPayOnePayment::fcIsPayOneRedirectType($sPaymentId);
        $blIsRedirectAfterSave = $this->_isRedirectAfterSave($oOrder);
        $blReduceStockBefore = !(bool)$oConfig->getConfigParam('blFCPOReduceStock');

        $blReturn = false;
        if (!$blIsRedirectPayment) {
            $blReturn = true;
        } elseif (!$blReduceStockBefore && $blIsRedirectAfterSave) {
            $blReturn = true;
        }

        return $blReturn;
    }

    /**
     * Returns true if this request is the return to the shop from a payment provider where the user has been
     * redirected to
     *
     * @return bool
     */
    private function _isRedirectAfterSave($oOrder)
    {
        if ($this->_blIsRedirectAfterSave === null) {
            $this->_blIsRedirectAfterSave = false;
            $sSuccess = $this->_oFcpoHelper->fcpoGetRequestParameter('fcposuccess');
            $sRefNr = $this->_oFcpoHelper->fcpoGetRequestParameter('refnr');
            $sTxid =
                $oOrder->oxorder__fcpotxid->value ?: $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoTxid');

            $blUseRedirectAfterSave = (
                $sSuccess && $sRefNr && $sTxid
            );

            if ($blUseRedirectAfterSave) {
                $this->_blIsRedirectAfterSave = true;
            }
        }

        return $this->_blIsRedirectAfterSave;
    }

    /**
     * Deletes order article object. If deletion succeded - updates
     * article stock information. Returns deletion status
     *
     * @param string $sOXID Article id
     *
     * @return bool
     */
    public function delete($sOXID = null)
    {
        $oSession = $this->_oFcpoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $sPaymentId = $oBasket->getPaymentId();
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        if ($sPaymentId) {
            $oPayment = oxNew('oxpayment');
            $oPayment->load($sPaymentId);
            if (!$this->_fcpoIsPayonePaymentType($oPayment->getId())) {
                return null;
            }
        }

        $blDelete = $this->_fcpoProcessBaseDelete($sOXID);
        if ($blDelete) {
            $blReduceStockBefore = !(bool)$oConfig->getConfigParam('blFCPOReduceStock');
            if ($this->oxorderarticles__oxstorno->value != 1 && $blReduceStockBefore) {
                $this->updateArticleStock($this->oxorderarticles__oxamount->value, $oConfig->getConfigParam('blAllowNegativeStock'));
            }
        }

        return $blDelete;
    }

    /**
     * Returns wether given paymentid is of payone type
     *
     * @param string $sId
     * @param bool   $blIFrame
     * @return bool
     */
    private function _fcpoIsPayonePaymentType($sId, $blIFrame = false)
    {
        if ($blIFrame) {
            $blReturn = FcPayOnePayment::fcIsPayOnePaymentType($sId);
        } else {
            $blReturn = FcPayOnePayment::fcIsPayOneIframePaymentType($sId);
        }

        return $blReturn;
    }

    /**
     * Processes the base version fo delete method and returns its result
     *
     * @param string $sOXID
     * @return mixed
     */
    private function _fcpoProcessBaseDelete($sOXID)
    {
        return oxBase::delete($sOXID);
    }
}
