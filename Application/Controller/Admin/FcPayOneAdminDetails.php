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

use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Application\Model\FcPoForwarding;
use Fatchip\PayOne\Application\Model\FcPoKlarna;
use Fatchip\PayOne\Application\Model\FcPoMapping;
use Fatchip\PayOne\Application\Model\FcPoPaypal;
use Fatchip\PayOne\Application\Model\FcPoRatePay;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use stdClass;

class FcPayOneAdminDetails extends AdminDetailsController
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Centralized Database instance
     *
     * @var DatabaseInterface
     */
    protected DatabaseInterface $_oFcPoDb;

    /**
     * fcpoconfigexport instance
     *
     * @var FcPoConfigExport
     */
    protected FcPoConfigExport $_oFcPoConfigExport;

    /**
     * fcpopaypal instance
     *
     * @var FcPoPaypal
     */

    protected FcPoPaypal $_oFcPoPayPal;

    /**
     * fcpopaypal instance
     *
     * @var FcPoKlarna
     */
    protected FcPoKlarna $_oFcPoKlarna;

    /**
     * fcpomapping instance
     *
     * @var FcPoMapping
     */
    protected FcPoMapping $_oFcPoMapping;

    /**
     * fcpoforwarding instance
     *
     * @var FcPoForwarding
     */
    protected FcPoForwarding $_oFcPoForwarding;

    /**
     * fcporatepay instance
     *
     * @var FcPoRatePay
     */
    protected FcPoRatePay $_oFcPoRatePay;

    /**
     * fcpoerrormapping instance
     *
     * @var FcPoErrorMapping
     */
    protected FcPoErrorMapping $_oFcPoErrorMapping;


    /**
     * Init needed data
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
        $this->_oFcPoConfigExport = oxNew(FcPoConfigExport::class);
        $this->_oFcPoPayPal = oxNew(FcPoPayPal::class);
        $this->_oFcPoKlarna = oxNew(FcPoKlarna::class);
        $this->_oFcPoMapping = oxNew(FcPoMapping::class);
        $this->_oFcPoForwarding = oxNew(FcPoForwarding::class);
        $this->_oFcPoRatePay = oxNew(FcPoRatePay::class);
        $this->_oFcPoErrorMapping = oxNew(FcPoErrorMapping::class);
    }

    /**
     * Returns factory instance of given classname
     *
     * @param string $sClassName
     * @return object
     */
    public function fcpoGetInstance(string $sClassName): object
    {
        return oxNew($sClassName);
    }

    /**
     * Returns payone status list
     *
     * @return stdClass[]
     */
    public function getPayoneStatusList(): array
    {
        $aPayoneStatusList = $this->_oFcPoHelper->fcpoGetPayoneStatusList();

        $aNewList = [];
        foreach ($aPayoneStatusList as $sPayoneStatusId) {
            $oStatus = new stdClass();
            $oStatus->sId = $sPayoneStatusId;
            $oStatus->sTitle = $this->_oFcPoHelper->fcpoGetLang()->translateString('fcpo_status_' . $sPayoneStatusId, null, true);
            $aNewList[] = $oStatus;
        }

        return $aNewList;
    }

}
