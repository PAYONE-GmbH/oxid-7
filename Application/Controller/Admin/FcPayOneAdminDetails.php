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
use Fatchip\PayOne\Application\Model\FcPoRatepay;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;

class FcPayOneAdminDetails extends AdminDetailsController
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcPoHelper = null;

    /**
     * Centralized Database instance
     *
     * @var object
     */
    protected DatabaseInterface $_oFcPoDb;

    /**
     * fcpoconfigexport instance
     *
     * @var object
     */
    protected $_oFcPoConfigExport = null;

    /**
     * fcpopaypal instance
     *
     * @var object
     */

    protected $_oFcPoPayPal = null;

    /**
     * fcpopaypal instance
     *
     * @var object
     */
    protected $_oFcPoKlarna = null;

    /**
     * fcpomapping instance
     *
     * @var object
     */
    protected $_oFcPoMapping = null;

    /**
     * fcpoforwarding instance
     *
     * @var object
     */
    protected $_oFcPoForwarding = null;

    /**
     * fcporatepay instance
     *
     * @var null|object
     */
    protected $_oFcPoRatePay = null;

    /**
     * fcpoerrormapping instance
     *
     * @var null|object
     */
    protected $_oFcPoErrorMapping = null;


    /**
     * Init needed data
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
        $this->_oFcPoErrorMapping = oxNew(FcPoErrorMapping::class);
        $this->_oFcPoForwarding = oxNew(FcPoForwarding::class);
        $this->_oFcPoRatePay = oxNew(FcPoRatepay::class);
    }

    /**
     * Returns factory instance of given classname
     *
     * @param string $sClassName
     * @return object
     */
    public function fcpoGetInstance($sClassName)
    {
        return oxNew($sClassName);
    }

}
