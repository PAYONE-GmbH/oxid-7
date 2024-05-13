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
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\OrderArticle;

class FcPayOneBasketItem extends FcPayOneBasketItem_parent
{

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;


    /**
     * init object construction
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Overrides standard oxid getArticle method
     *
     * Retrieves the article .Throws an exception if article does not exist,
     * is not buyable or visible.
     *
     * @param bool|null $blCheckProduct checks if product is buyable and visible
     * @param null $sProductId product id
     * @param bool $blDisableLazyLoading disable lazy loading
     *
     * @return OrderArticle|Article
     */
    public function getArticle($blCheckProduct = false, $sProductId = null, $blDisableLazyLoading = false): OrderArticle|Article
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blReduceStockBefore = !$oConfig->getConfigParam('blFCPOReduceStock');
        $blSuccess = $this->_oFcPoHelper->fcpoGetRequestParameter('fcposuccess');
        $sRefNr = $this->_oFcPoHelper->fcpoGetRequestParameter('refnr');

        // Leave value unchanged if it is explicitly forced from a usage.
        if (is_null($blCheckProduct) && $blSuccess && $sRefNr) {
            $blCheckProduct = !$blReduceStockBefore;
        } elseif (is_null($blCheckProduct)) {
            // Set the default value as in a Shop.
            $blCheckProduct = false;
        }

        return $this->_fcpoParentGetArticle($blCheckProduct, $sProductId, $blDisableLazyLoading);
    }

    /**
     * @param bool $blCheckProduct
     * @param string|null $sProductId
     * @param bool $blDisableLazyLoading
     * @return Article|OrderArticle
     */
    protected function _fcpoParentGetArticle(?bool $blCheckProduct = false, ?string $sProductId = null, ?bool $blDisableLazyLoading = false): Article|OrderArticle
    {
        return parent::getArticle($blCheckProduct, $sProductId, $blDisableLazyLoading);
    }

}
