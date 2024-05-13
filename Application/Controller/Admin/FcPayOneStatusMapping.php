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

use Fatchip\PayOne\Application\Model\FcPoMapping;
use OxidEsales\Eshop\Application\Model\Payment;
use stdClass;

class FcPayOneStatusMapping extends FcPayOneAdminDetails
{

    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_status_mapping';

    /**
     * Returns list of former configured mappings
     *
     * @return array
     */
    public function getMappings(): array
    {
        $aMappings = $this->_fcpoGetExistingMappings();

        return $this->_fcpoAddNewMapping($aMappings);
    }

    /**
     * Requests database for existing mappings and returns an array of mapping objects
     *
     * @return array
     */
    protected function _fcpoGetExistingMappings(): array
    {
        return $this->_oFcPoMapping->fcpoGetExistingMappings();
    }

    /**
     * Adds a new entry if flag has been set
     *
     * @param array $aMappings
     * @return array
     */
    protected function _fcpoAddNewMapping(array $aMappings): array
    {
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('add')) {
            $oMapping = new stdClass();
            $oMapping->sOxid = 'new';
            $oMapping->sPaymentType = '';
            $oMapping->sPayoneStatusId = '';
            $oMapping->sShopStatusId = '';
            $aMappings[] = $oMapping;
        }

        return $aMappings;
    }

    /**
     * Returns a list of payment types
     *
     * @return array
     */
    public function getPaymentTypeList(): array
    {
        $oPayment = oxNew(Payment::class);

        return $oPayment->fcpoGetPayonePaymentTypes();
    }

    /**
     * Returns a list of shop states
     *
     * @return array
     */
    public function getShopStatusList(): array
    {
        return $this->_oFcPoHelper->fcpoGetConfig()->getConfigParam('aOrderfolder');
    }

    /**
     * Updating mappings into database
     *
     * @return void
     */
    public function save(): void
    {
        $oMapping = $this->fcpoGetInstance(FcPoMapping::class);
        $aMappings = $this->_oFcPoHelper->fcpoGetRequestParameter("editval");
        if (is_array($aMappings) && $aMappings !== []) {
            $oMapping->fcpoUpdateMappings($aMappings);
        }
    }

}
