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
 * @link      http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version   OXID eShop CE
 */
 
namespace Fatchip\PayOne\Application\Controller\Admin;

use OxidEsales\Eshop\Core\DatabaseProvider;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Application\Model as PayOneModel;

class FcPayOneAdminDetails extends \OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController
{
    
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected $_oFcpoHelper = null;
    
    /**
     * Centralized Database instance
     *
     * @var DatabaseProvider
     */
    protected $_oFcpoDb = null;
    
    /**
     * FcPoConfigExport instance
     *
     * @var FcPoConfigExport
     */
    protected $_oFcpoConfigExport = null;
    
    /**
     * FcPoPaypal instance
     *
     * @var FcPoPaypal
     */

    protected $_oFcpoPayPal = null;

    /**
     * FcPoKlarna instance
     *
     * @var FcPoKlarna
     */
    protected $_oFcpoKlarna = null;

    /**
     * FcPoMapping instance
     *
     * @var FcPoMapping
     */
    protected $_oFcpoMapping = null;

    /**
     * FcPoForwarding instance
     *
     * @var FcPoForwarding
     */
    protected $_oFcpoForwarding = null;

    /**
     * FcPoRatePay instance
     *
     * @var null|FcPoRatePay
     */
    protected $_oFcpoRatePay = null;

    /**
     * FcPoErrorMapping instance
     *
     * @var null|FcPoErrorMapping
     */
    protected $_oFcpoErrorMapping = null;
    

    /**
     * Init needed data
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
        $this->_oFcpoDb     = DatabaseProvider::getDb();
        $this->_oFcpoConfigExport = oxNew(PayOneModel\FcPoConfigExport::class);
        $this->_oFcpoPayPal = oxNew(PayOneModel\FcPoPaypal::class);
        $this->_oFcpoKlarna = oxNew(PayOneModel\FcPoKlarna::class);
        $this->_oFcpoMapping = oxNew(PayOneModel\FcPoMapping::class);
        $this->_oFcpoErrorMapping = oxNew(PayOneModel\FcPoErrorMapping::class);
        $this->_oFcpoForwarding = oxNew(PayOneModel\FcPoForwarding::class);
        $this->_oFcpoRatePay = oxNew(PayOneModel\FcPoRatePay::class);
    }
    
    /**
     * Returns factory instance of given classname
     *
     * @param  string $sClassName
     * @return object
     */
    public function fcpoGetInstance($sClassName)
    {
        return oxNew($sClassName);
    }
}
