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

class FcPayOneApilogList extends FcPayoneAdminList
{

    public $_oFcpoHelper;

    /**
     * Current class template name
     *
     * @var string
     */
    protected $_sThisTemplate = '@fcpayone/admin/fcpayone_apilog_list';

    /**
     * Returns sorting fields array
     *
     * @return array
     */
    public function getListSorting()
    {
        if ($this->_aCurrSorting === null) {
            $this->_aCurrSorting = $this->_oFcpoHelper->fcpoGetRequestParameter('sort');

            if (!$this->_aCurrSorting && $this->_sDefSortField && ($baseModel = $this->getItemListBaseObject())) {
                $this->_aCurrSorting[$baseModel->getCoreTableName()] = [$this->_sDefSortField => "asc"];
            }
        }

        return $this->_aCurrSorting;
    }

    /**
     * Return input name for searchfields in list by shop-version
     *
     * @return string
     */
    public function fcGetInputName($sTable, $sField)
    {
        if ($this->_oFcpoHelper->fcpoGetIntShopVersion() >= 4500) {
            return "where[{$sTable}][{$sField}]";
        }
        return "where[{$sTable}.{$sField}]";
    }

    /**
     * Return input form value for searchfields in list by shop-version
     *
     * @return string
     */
    public function fcGetWhereValue($sTable, $sField)
    {
        $aWhere = $this->getListFilter();
        if ($this->_oFcpoHelper->fcpoGetIntShopVersion() >= 4500) {
            return $aWhere[$sTable][$sField];
        }
        return $aWhere[$sTable . '.' . $sField];
    }

    /**
     * Returns list filter array
     *
     * @return array
     */
    public function getListFilter()
    {
        if ($this->_aListFilter === null) {
            $this->_aListFilter = $this->_oFcpoHelper->fcpoGetRequestParameter("where");
        }

        return $this->_aListFilter;
    }

    /**
     * Return needed javascript for sorting in list by shop-version
     *
     * @return string
     */
    public function fcGetSortingJavascript($sTable, $sField)
    {
        if ($this->_oFcpoHelper->fcpoGetIntShopVersion() >= 4500) {
            return "Javascript:top.oxid.admin.setSorting( document.search, '{$sTable}', 
    '{$sField}', 
    'asc');document.search.submit();";
        }
        return "Javascript:document.search.sort.value='{$sTable}.{$sField}';document.search.submit();";
    }

    /**
     * Filter log entries, show only log entries of configured PAYONE account
     *
     * @param array  $aWhere SQL condition array
     * @param string $sQ     SQL query string
     *
     * @return string
     */
    private function _prepareWhereQuery($aWhere, $sQ)
    {
        $sQ = parent::_prepareWhereQuery($aWhere, $sQ);
        $sPortalId = $this->getPortalId();
        $sAid = $this->getSubAccountId();
        return $sQ . " AND fcporequestlog.fcpo_portalid = '{$sPortalId}' AND fcporequestlog.fcpo_aid = '{$sAid}' ";
    }

    /**
     * Get config parameter PAYONE portal ID
     *
     * @return $string
     */
    public function getPortalId()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOPortalID');
    }

    /**
     * Get config parameter PAYONE sub-account ID
     *
     * @return $string
     */
    public function getSubAccountId()
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        return $oConfig->getConfigParam('sFCPOSubAccountID');
    }
}
