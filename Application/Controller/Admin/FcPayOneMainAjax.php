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

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Controller\Admin\ListComponentAjax;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Model\BaseModel;

/**
 * Handles the complete communication with the PAYONE API
 */
class FcPayOneMainAjax extends ListComponentAjax
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Columns array
     *
     * @var array
     */
    protected $_aColumns = [
        'container1' => [    // field, table, visible, multilanguage, ident
            ['oxtitle', 'oxcountry', 1, 1, 0],
            ['oxisoalpha2', 'oxcountry', 1, 0, 0],
            ['oxisoalpha3', 'oxcountry', 0, 0, 0],
            ['oxunnum3', 'oxcountry', 0, 0, 0],
            ['oxid', 'oxcountry', 0, 0, 1]
        ],
        'container2' => [
            ['oxtitle', 'oxcountry', 1, 1, 0],
            ['oxisoalpha2', 'oxcountry', 1, 0, 0],
            ['oxisoalpha3', 'oxcountry', 0, 0, 0],
            ['oxunnum3', 'oxcountry', 0, 0, 0],
            ['oxid', 'fcpopayment2country', 0, 0, 1]
        ]
    ];


    /**
     * Class constructor, sets all required parameters for requests.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Adds chosen country to payment
     *
     * @return void
     */
    public function addpaycountry(): void
    {
        $aChosenCntr = $this->getActionIds('oxcountry.oxid');
        $soxId = $this->_oFcPoHelper->fcpoGetRequestParameter('synchoxid');
        $sType = $this->_oFcPoHelper->fcpoGetRequestParameter('type');
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('all')) {
            $sCountryTable = $this->getViewName('oxcountry');
            $aChosenCntr = $this->_getAll($this->addFilter("select $sCountryTable.oxid " . $this->getQuery()));
        }
        if ($soxId && $soxId != "-1" && is_array($aChosenCntr)) {
            foreach ($aChosenCntr as $sChosenCntr) {
                $oObject2Payment = oxNew(BaseModel::class);
                $oObject2Payment->init('fcpopayment2country');
                $oObject2Payment->fcpopayment2country__fcpo_paymentid = new Field($soxId);
                $oObject2Payment->fcpopayment2country__fcpo_countryid = new Field($sChosenCntr);
                $oObject2Payment->fcpopayment2country__fcpo_type = new Field($sType);
                $oObject2Payment->save();
            }
        }
    }

    /**
     * Returns SQL query for data to fetch
     *
     * @return string
     */
    protected function getQuery(): string
    {
        // looking for table/view
        $sCountryTable = $this->getViewName('oxcountry');
        $sCountryId = $this->_oFcPoHelper->fcpoGetRequestParameter('oxid');
        $sSynchCountryId = $this->_oFcPoHelper->fcpoGetRequestParameter('synchoxid');
        $sType = $this->_oFcPoHelper->fcpoGetRequestParameter('type');

        // category selected or not ?
        if (!$sCountryId) {
            // which fields to load ?
            $sQAdd = " from $sCountryTable where $sCountryTable.oxactive = '1' ";
        } else {
            $sQAdd = " from fcpopayment2country left join $sCountryTable on $sCountryTable.oxid=fcpopayment2country.fcpo_countryid ";
            $sQAdd .= "where $sCountryTable.oxactive = '1' and fcpopayment2country.fcpo_paymentid = '$sCountryId' and fcpopayment2country.fcpo_type = '{$sType}' ";
        }

        if ($sSynchCountryId && $sSynchCountryId != $sCountryId) {
            $sQAdd .= "and $sCountryTable.oxid not in ( ";
            $sQAdd .= "select $sCountryTable.oxid from fcpopayment2country left join $sCountryTable on $sCountryTable.oxid=fcpopayment2country.fcpo_countryid ";
            $sQAdd .= "where fcpopayment2country.fcpo_paymentid = '$sSynchCountryId' and fcpopayment2country.fcpo_type = '{$sType}' ) ";
        }

        return $sQAdd;
    }

    /**
     * Removes chosen country from payment
     *
     * @return void
     * @throws DatabaseConnectionException
     */
    public function removepaycountry(): void
    {
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $aChosenCntr = $this->getActionIds('fcpopayment2country.oxid');
        if ($this->_oFcPoHelper->fcpoGetRequestParameter('all')) {
            $sQ = $this->addFilter("delete fcpopayment2country.* " . $this->getQuery());
            $oDb->execute($sQ);
        } elseif (is_array($aChosenCntr)) {
            $sQ = "delete from fcpopayment2country where fcpopayment2country.oxid in (" . implode(", ", $oDb->quoteArray($aChosenCntr)) . ") ";
            $oDb->execute($sQ);
        }
    }

}


class_alias(\Fatchip\PayOne\Application\Controller\Admin\FcPayOneMainAjax::class, 'fcpayone_main_ajax');
