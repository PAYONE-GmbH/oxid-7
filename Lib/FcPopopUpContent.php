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

namespace Fatchip\PayOne\Lib;

/*
 * load OXID Framework
 */

use OxidEsales\Eshop\Core\Model\BaseModel;

function getShopBasePath()
{
    return __DIR__ . '/../../../../';
}

include_once getShopBasePath() . "/bootstrap.php";

// receive params
$sLoadUrl = filter_input(INPUT_GET, 'loadurl');
$sDuration = filter_input(INPUT_GET, 'duration');
$sUseLogin = filter_input(INPUT_GET, 'login');


/**
 * Helper script for displaying
 *
 * @author andre
 */
final class FcPopopUpContent extends BaseModel
{

    /**
     * Initialization
     *
     * @param bool $blPdfHeader
     * @param string $sDuration
     */
    public function __construct(
        private readonly string $_sUrl,
        /**
         * Duration for installment
         */
        private readonly string $_sDuration,
        /**
         * Flag if fetched content should be returned with pdf header
         */
        private readonly bool $_blPdfHeader = true,
        private readonly bool $_blUseLogin = false
    )
    {
    }

    /**
     * Fetch and return content
     *
     * 
     * @return string
     */
    public function fcpo_fetch_content()
    {
        $curlHandle = curl_init();
        $sUrl = $this->_sUrl . "&duration=" . $this->_sDuration;

        curl_setopt($curlHandle, CURLOPT_URL, $sUrl);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);

        if ($this->_blUseLogin) {
            $aCredentials = $this->_fcpoGetPayolutionCredentials();
            curl_setopt($curlHandle, CURLOPT_USERPWD, $aCredentials['user'] . ':' . $aCredentials['pass']);
        }

        $blCurlError = false;
        try {
            $sContent = curl_exec($curlHandle);
            $mInfo = curl_getinfo($curlHandle);
            if ($mInfo['http_code'] == '401') {
                $blCurlError = true;
                $sContent = $this->_fcpoReturnErrorMessage('Authentication failure! Please check your credentials in payolution settings.');
            }
        } catch (oxException $oEx) {
            $blCurlError = true;
            $sContent = $this->_fcpoReturnErrorMessage($oEx->getMessage());
        }
        curl_close($curlHandle);

        if ($this->_blPdfHeader && !$blCurlError) {
            header('Content-Type: application/pdf');
        }

        return $sContent;
    }

    /**
     * Returns configured credentials
     *
     *
     * @return array{user: string, pass: string}
     */
    private function _fcpoGetPayolutionCredentials(): array
    {
        $oConfig = $this->getConfig();
        $aCredentials = [];
        $aCredentials['user'] = (string)$oConfig->getConfigParam('sFCPOPayolutionAuthUser');
        $aCredentials['pass'] = (string)$oConfig->getConfigParam('sFCPOPayolutionAuthSecret');

        return $aCredentials;
    }

    /**
     * Formats error message to be displayed in a error box
     *
     * @return string
     */
    private function _fcpoReturnErrorMessage(string $sMessage)
    {
        $sReturn = '<p class="payolution_message_error">';
        $sReturn .= $sMessage;

        return $sReturn . '</p>';
    }
}

$oPopupContent = new FcPopopUpContent($sLoadUrl, $sDuration, true, (bool)$sUseLogin);
echo $oPopupContent->fcpo_fetch_content();
