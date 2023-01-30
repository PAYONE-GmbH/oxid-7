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

use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use stdClass;

class FcPayOneErrorMapping extends FcPayOneAdminDetails
{

    public $_oFcpoErrorMapping;
    public $_oFcpoHelper;
    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_error_mapping';

    /**
     * Returns list of former configured errors
     *
     *
     * @return array
     */
    public function getMappings()
    {
        $aMappings = $this->_fcpoGetExistingMappings();

        return $this->_fcpoAddNewMapping($aMappings);
    }

    /**
     * Returns list of all mappings
     *
     *
     * @return array
     */
    private function _fcpoGetExistingMappings()
    {
        return $this->_oFcpoErrorMapping->fcpoGetExistingMappings();
    }

    /**
     * Adds a new entry if flag has been set
     *
     * @return array
     */
    private function _fcpoAddNewMapping(array $aMappings)
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('add')) {
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
     *
     * @return array
     */
    public function getIframeMappings()
    {
        $aMappings = $this->_fcpoGetExistingIframeMappings();

        return $this->_fcpoAddNewIframeMapping($aMappings);
    }

    /**
     * Returns list of all mappings
     *
     *
     * @return array
     */
    private function _fcpoGetExistingIframeMappings()
    {
        return $this->_oFcpoErrorMapping->fcpoGetExistingMappings('iframe');
    }

    /**
     * Adds a new entry if flag has been set
     *
     * @return array
     */
    private function _fcpoAddNewIframeMapping(array $aMappings)
    {
        if ($this->_oFcpoHelper->fcpoGetRequestParameter('addIframe')) {
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
     *
     * @return array
     */
    public function fcpoGetPayoneErrorMessages($sType = 'general')
    {
        return $this->_oFcpoErrorMapping->fcpoGetAvailableErrorCodes($sType);
    }

    /**
     * Returns list of language objects
     *
     *
     * @return array
     */
    public function getLanguages()
    {
        $oLang = $this->_oFcpoHelper->fcpoGetLang();

        return $oLang->getLanguageArray(null, true, true);
    }

    /**
     * Updating settings into database
     *
     *
     */
    public function save(): void
    {
        $oMapping = $this->fcpoGetInstance(FcPoErrorMapping::class);
        $aGeneralMappings = $this->_oFcpoHelper->fcpoGetRequestParameter("editval");
        if (is_array($aGeneralMappings) && $aGeneralMappings !== []) {
            $oMapping->fcpoUpdateMappings($aGeneralMappings, 'general');
        }
    }

    /**
     * Updating iFrame messages
     *
     *
     */
    public function saveIframe(): void
    {
        $oMapping = $this->fcpoGetInstance(FcPoErrorMapping::class);
        $aIframeMappings = $this->_oFcpoHelper->fcpoGetRequestParameter("editval2");
        if (is_array($aIframeMappings) && $aIframeMappings !== []) {
            $oMapping->fcpoUpdateMappings($aIframeMappings, 'iframe');
        }
    }
}
