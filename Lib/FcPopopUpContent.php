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

namespace Fatchip\PayOne\Lib;

/*
 * load OXID Framework
 */

use Exception;
use OxidEsales\Eshop\Core\Model\BaseModel;

function getShopBasePath()
{
    return dirname(__FILE__).'/../../../../';
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
class FcPoPopUpContent extends BaseModel
{

    /**
     * url to be fetched
     *
     * @var string
     */
    protected $_sUrl = null;

    /**
     * Flag that indicates, that login should be used
     *
     * @var bool
     */
    protected $_blUseLogin = null;

    /**
     * Flag if fetched content should be returned with pdf header
     *
     * @var bool
     */
    protected $_blPdfHeader = null;

    /**
     * Duration for installment
     *
     * @var string
     */
    protected $_sDuration = null;

    /**
     * @var FcPoHelper
     */
    protected $_oFcpoHelper;

    /**
     * Initialization
     *
     * @param string $sUrl
     * @param bool   $blUseLogin
     */
    public function __construct($sUrl, $sDuration, $blPdfHeader=true, $blUseLogin=false)
    {
        $this->_sUrl = $sUrl;
        $this->_blUseLogin = $blUseLogin;
        $this->_blPdfHeader = $blPdfHeader;
        $this->_sDuration = $sDuration;
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Fetch and return content
     *
     * @return string
     */
    public function fcpo_fetch_content()
    {
        $resCurl = curl_init();
        $sUrl = $this->_sUrl."&duration=".$this->_sDuration;

        curl_setopt($resCurl, CURLOPT_URL, $sUrl);
        curl_setopt($resCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($resCurl, CURLOPT_FOLLOWLOCATION, true);

        if ($this->_blUseLogin) {
            $aCredentials = $this->_fcpoGetPayolutionCredentials();
            curl_setopt($resCurl, CURLOPT_USERPWD, $aCredentials['user'].':'.$aCredentials['pass']);
        }

        $blCurlError = false;
        try {
            $sContent = curl_exec($resCurl);
            $mInfo = curl_getinfo($resCurl);
            if ($mInfo['http_code'] == '401') {
                $blCurlError = true;
                $sContent = $this->_fcpoReturnErrorMessage('Authentication failure! Please check your credentials in payolution settings.');
            }
        } catch (Exception $oEx) {
            $blCurlError = true;
            $sContent = $this->_fcpoReturnErrorMessage($oEx->getMessage());
        }
        curl_close($resCurl);

        if ($this->_blPdfHeader && !$blCurlError) {
            header('Content-Type: application/pdf');
        }

        return $sContent;
    }

    /**
     * Formats error message to be displayed in a error box
     *
     * @param  string $sMessage
     * @return string
     */
    protected function _fcpoReturnErrorMessage($sMessage)
    {
        $sReturn  = '<p class="payolution_message_error">';
        $sReturn .= $sMessage;
        $sReturn .= '</p>';

        return $sReturn;
    }


    /**
     * Returns configured credentials
     *
     * @return array
     */
    protected function _fcpoGetPayolutionCredentials(): array
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();

        $aCredentials = [];
        $aCredentials['user'] = (string)$oConfig->getConfigParam('sFCPOPayolutionAuthUser');
        $aCredentials['pass'] = (string)$oConfig->getConfigParam('sFCPOPayolutionAuthSecret');

        return $aCredentials;
    }
}

$oPopupContent = new FcPoPopUpContent($sLoadUrl, $sDuration, true, (bool)$sUseLogin);
echo $oPopupContent->fcpo_fetch_content();
