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

use Exception;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use stdClass;

class FcPayOneErrorMapping extends FcPayOneAdminDetails
{

    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_error_mapping';


    /**
     * Returns list of former configured errors
     *
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function getMappings(): array
    {
        $aMappings = $this->_fcpoGetExistingMappings();

        return $this->_fcpoAddNewMapping($aMappings);
    }

    /**
     * Returns list of all mappings
     *
     * @return array
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function _fcpoGetExistingMappings(): array
    {
        return $this->_oFcPoErrorMapping->fcpoGetExistingMappings();
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
            $oMapping->sErrorCode = '';
            $oMapping->sMappedMessage = '';
            $oMapping->sLangId = '';
            $aMappings[] = $oMapping;
        }

        return $aMappings;
    }

    /**
     * Returns list of former configured iframe errors
     *
     * @return array
     */
    public function getIframeMappings(): array
    {
        $aMappings = $this->_fcpoGetExistingIframeMappings();

        return $this->_fcpoAddNewIframeMapping($aMappings);
    }

    /**
     * Returns list of all mappings
     *
     * @return array
     */
    protected function _fcpoGetExistingIframeMappings(): array
    {
        return $this->_oFcPoErrorMapping->fcpoGetExistingMappings('iframe');
    }

    /**
     * Adds a new entry if flag has been set
     *
     * @param array $aMappings
     * @return array
     */
    protected function _fcpoAddNewIframeMapping(array $aMappings): array
    {
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('addIframe')) {
            $oMapping = new stdClass();
            $oMapping->sOxid = 'new';
            $oMapping->sErrorCode = '';
            $oMapping->sMappedMessage = '';
            $oMapping->sLangId = '';
            $aMappings[] = $oMapping;
        }

        return $aMappings;
    }

    /**
     * Requests xml base file to fetch all existing error codes and default-messages
     *
     * @param string $sType
     * @return array
     * @throws Exception
     */
    public function fcpoGetPayoneErrorMessages(string $sType = 'general'): array
    {
        return $this->_oFcPoErrorMapping->fcpoGetAvailableErrorCodes($sType);
    }

    /**
     * Returns list of language objects
     *
     * @return array
     */
    public function getLanguages(): array
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();

        return $oLang->getLanguageArray(null, true, true);
    }

    /**
     * Updating mapping into database
     *
     * @return void
     */
    public function save(): void
    {
        $oMapping = $this->fcpoGetInstance(FcPoErrorMapping::class);
        $aGeneralMappings = $this->_oFcPoHelper->fcpoGetRequestParameter("editval");
        if (is_array($aGeneralMappings) && $aGeneralMappings !== []) {
            $oMapping->fcpoUpdateMappings($aGeneralMappings, 'general');
        }
    }

    /**
     * Updating iFrame messages
     */
    public function saveIframe(): void
    {
        $oMapping = $this->fcpoGetInstance(FcPoErrorMapping::class);
        $aIframeMappings = $this->_oFcPoHelper->fcpoGetRequestParameter("editval2");
        if (is_array($aIframeMappings) && $aIframeMappings !== []) {
            $oMapping->fcpoUpdateMappings($aIframeMappings, 'iframe');
        }
    }

}
