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

namespace Fatchip\PayOne;

use Fatchip\PayOne\Lib\FcPoHelper;

$mKey = filter_input(INPUT_GET, 'key', FILTER_NULL_ON_FAILURE);
$blIsValidCall = ($mKey && md5((string)$mKey) === '5fce785e30dbf6e1181d452c6057bfd3');

if ($blIsValidCall) {
    if (!function_exists('getShopBasePath')) {
        /**
         * Returns shop base path.
         *
         * @return string
         */
        function getShopBasePath(): string
        {
            return dirname(__FILE__) . '/../../../';
        }
    }

    set_include_path(get_include_path() . PATH_SEPARATOR . getShopBasePath());

    /**
     * Returns true.
     *
     * @return bool
     */
    if (!function_exists('isAdmin')) {
        function isAdmin(): bool
        {
            return true;
        }
    }

    error_reporting(E_ALL ^ E_NOTICE);

    // Including main ADODB include
    include_once getShopBasePath() . 'bootstrap.php';

    echo FcPoHelper::fcpoGetStaticModuleVersion();
}
