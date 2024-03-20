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

use OxidEsales\Eshop\Application\Model\Order;

class FcPayOnePaymentGateway extends FcPayOnePaymentGateway_parent
{

    /**
     * Overrides standard oxid finalizeOrder method if the used payment method belongs to PAYONE.
     * Return parent's return if payment method is no PAYONE method
     *
     * Executes payment, returns true on success.
     *
     * @param float $dAmount Goods amount
     * @param Order &$oOrder User ordering object
     *
     * @extend executePayment
     * @return bool
     */
    public function executePayment($dAmount, &$oOrder): bool
    {
        // if($oOrder->isPayOnePaymentType() === false || $oOrder->isPayOneIframePayment()) {
        if ($oOrder->isPayOnePaymentType() === false) {
            return parent::executePayment($dAmount, $oOrder);
        }

        $this->_iLastErrorNo = null;
        $this->_sLastError = null;

        return $oOrder->fcHandleAuthorization(false, $this);
    }

    /**
     * Setter for last error number
     *
     * @param int $iLastErrorNr
     * @return void
     */
    public function fcpoSetLastErrorNr(int $iLastErrorNr): void
    {
        $this->_iLastErrorNo = $iLastErrorNr;
    }

    /**
     * Setter for last error text
     *
     * @param string $sLastError
     * @return void
     */
    public function fcpoSetLastError(string $sLastError): void
    {
        $this->_sLastError = $sLastError;
    }

}
