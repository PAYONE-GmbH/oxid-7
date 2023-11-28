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
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Application\Model\Payment;

class FcPayOneOrderArticle extends FcPayOneOrderArticle_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Flag for redirecting after save
     *
     * @var bool
     */
    protected ?bool $_blIsRedirectAfterSave = null;

    /**
     * Flag for finishing order completely
     *
     * @var bool
     */
    protected bool $_blFinishingSave = true;


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Sets flag for finishing save
     *
     * @param bool $blFinishingSave
     * @return void
     */
    public function fcpoSetFinishingSave(bool $blFinishingSave): void
    {
        $this->_blFinishingSave = $blFinishingSave;
    }

    /**
     * Overrides standard oxid save method
     *
     * Saves order article object. If saving succeeded - updates
     * article stock information if oxOrderArticle::isNewOrderItem()
     * returns TRUE. Returns saving status
     *
     * @return bool
     */
    public function save(): bool
    {
        $oOrder = $this->getOrder();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blPresaveOrder = $oConfig->getConfigParam('blFCPOPresaveOrder');

        # @see https://integrator.payone.de/jira/browse/OX6-87
        if (is_null($oOrder)) {
            return parent::save();
        }

        $blUseParentOrderMethod = (
            $blPresaveOrder === false ||
            $oOrder->isPayOnePaymentType() === false
        );

        if ($blUseParentOrderMethod) {
            return parent::save();
        }

        $blBefore = $this->_fcpoGetBefore();
        $blReduceStockAfterRedirect = $this->_fcCheckReduceStockAfterRedirect();
        if ($blReduceStockAfterRedirect) {
            $this->updateArticleStock($this->oxorderarticles__oxamount->value * (-1), $oConfig->getConfigParam('blAllowNegativeStock'));
        }

        // ordered articles
        if (($blSave = parent::save()) && $this->isNewOrderItem() || $blBefore === false) {
            if ($oConfig->getConfigParam('blUseStock')) {
                if ($oConfig->getConfigParam('blPsBasketReservationEnabled')) {
                    $this->getSession()
                        ->getBasketReservations()
                        ->commitArticleReservation(
                            $this->oxorderarticles__oxartid->value,
                            $this->oxorderarticles__oxamount->value
                        );
                }
            }

            $this->setOrderFiles();

            // marking object as "non new" disable further stock changes
            $this->setIsNewOrderItem(false);
        }

        return $blSave;
    }

    /**
     * Returns whether payone order should be pre-saved
     *
     * @return bool
     */
    protected function _fcpoGetBefore(): bool
    {
        $blFinishingSave = $this->_blFinishingSave;
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blPresaveOrder = (bool)$oConfig->getConfigParam('blFCPOPresaveOrder');
        $blReduceStockBefore = !(bool)$oConfig->getConfigParam('blFCPOReduceStock');

        // evaluate answer
        return (
            $this->_oFcPoHelper->fcpoGetRequestParameter('fcposuccess') &&
            $this->_oFcPoHelper->fcpoGetRequestParameter('refnr') ||
            (
                $blFinishingSave === true &&
                $blPresaveOrder === true &&
                $blReduceStockBefore === false
            )
        );
    }

    /**
     * Method checks conditions for reducing stock after using a redirect payment
     * It depends on settings and payment method
     *
     * @return bool
     */
    protected function _fcCheckReduceStockAfterRedirect(): bool
    {
        if (isAdmin()) return false;

        $oOrder = $this->getOrder();
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $sPaymentId = $oBasket->getPaymentId();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

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
     * @param Order $oOrder
     * @return bool
     */
    protected function _isRedirectAfterSave(Order $oOrder): bool
    {
        if ($this->_blIsRedirectAfterSave === null) {
            $this->_blIsRedirectAfterSave = false;
            $sSuccess = $this->_oFcPoHelper->fcpoGetRequestParameter('fcposuccess');
            $sRefNr = $this->_oFcPoHelper->fcpoGetRequestParameter('refnr');
            $sTxid = $oOrder->oxorder__fcpotxid->value ?? $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoTxid');

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
     * Deletes order article object. If deletion succeeded - updates
     * article stock information. Returns deletion status
     *
     * @param string|null $sOXID Article id
     *
     * @return bool
     */
    public function delete(string $sOXID = null): bool
    {
        $oSession = $this->_oFcPoHelper->fcpoGetSession();
        $oBasket = $oSession->getBasket();
        $sPaymentId = $oBasket->getPaymentId();
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();

        if ($sPaymentId) {
            $oPayment = oxNew(Payment::class);
            $oPayment->load($sPaymentId);
            if ($this->_fcpoIsPayonePaymentType($oPayment->getId()) === false) {
                return parent::delete($sOXID);
            }
        }

        $blDelete = $this->_fcpoProcessBaseDelete($sOXID);
        if ($blDelete) {
            $blReduceStockBefore = !(bool)$oConfig->getConfigParam('blFCPOReduceStock');
            if ($this->oxorderarticles__oxstorno->value != 1 && $blReduceStockBefore !== false) {
                $this->updateArticleStock($this->oxorderarticles__oxamount->value, $oConfig->getConfigParam('blAllowNegativeStock'));
            }
        }

        return $blDelete;
    }

    /**
     * Returns whether given paymentid is of payone type
     *
     * @param string $sId
     * @param bool $blIFrame
     * @return bool
     */
    protected function _fcpoIsPayonePaymentType(string $sId, bool $blIFrame = false): bool
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
    protected function _fcpoProcessBaseDelete(string $sOXID): mixed
    {
        return parent::delete($sOXID);
    }

}
