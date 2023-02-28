<?php

namespace Fatchip\PayOne\Application\Model;

use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Model\BaseModel;


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
class FcPoPaypal extends BaseModel
{

    /**
     * Collects messages of different types
     *
     * @var array
     */
    protected $_aAdminMessages = [];

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected $_oFcPoHelper = null;

    /**
     * Centralized Database instance
     *
     * @var object
     */
    protected $_oFcPoDb = null;

    /**
     * Path of payone images
     *
     * @var string
     */
    protected $_sPayPalExpressLogoPath = 'out/modules/fcpayone/img/';

    /**
     * Init needed data
     */
    public function __construct()
    {
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
    }

    /**
     * Method returns collected messages back to controller
     *
     * @return array
     */
    public function fcpoGetMessages()
    {
        return $this->_aAdminMessages;
    }

    /**
     * Method requests database for fetching paypal logos and return this data in an array
     *
     * @return mixed[][]
     */
    public function fcpoGetPayPalLogos(): array
    {
        $sQuery = "SELECT oxid, fcpo_active, fcpo_langid, fcpo_logo, fcpo_default FROM fcpopayoneexpresslogos";
        $oDb = $this->_oFcPoHelper->fcpoGetDb();
        $aRows = $oDb->getAll($sQuery);
        $aLogos = [];

        foreach ($aRows as $aRow) {
            $sOxid = $aRow[0];
            $sPoActive = $aRow[1];
            $sPoLangId = $aRow[2];
            $sPoLogo = $aRow[3];
            $sPoDefault = $aRow[4];

            $aLogo = [];
            $aLogo['oxid'] = $sOxid;
            $aLogo['active'] = (bool)$sPoActive;
            $aLogo['langid'] = $sPoLangId;
            $aLogo['logo'] = '';

            $aLogo = $this->_fcpoAddLogoPath($sPoLogo, $aLogo);
            $aLogo['default'] = (bool)$sPoDefault;
            $aLogos[] = $aLogo;
        }

        return $aLogos;
    }

    /**
     * Add logo path if dependencies are fulfilled
     *
     * @param string $sPoLogo
     * @param array  $aLogo
     * @return array
     */
    protected function _fcpoAddLogoPath($sPoLogo, $aLogo)
    {
        $blLogoEnteredAndExisting = $this->_fcpoGetLogoEnteredAndExisting($sPoLogo);
        if ($blLogoEnteredAndExisting) {
            $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
            $sShopURL = $oConfig->getCurrentShopUrl(false);
            $aLogo['logo'] = $sShopURL . $this->_sPayPalExpressLogoPath . $sPoLogo;
        }

        return $aLogo;
    }

    /**
     * Validates the existance and availablility of paypalexpress logo
     *
     * @param string $sPoLogo
     */
    protected function _fcpoGetLogoEnteredAndExisting($sPoLogo): bool
    {
        return !empty($sPoLogo) &&
            $this->_oFcPoHelper->fcpoFileExists(getShopBasePath() . $this->_sPayPalExpressLogoPath . $sPoLogo);
    }

    /**
     * Updates a given set of logos into database
     *
     * @param array $aLogos
     */
    public function fcpoUpdatePayPalLogos($aLogos): void
    {
        foreach ($aLogos as $iId => $aLogo) {
            $oDb = $this->_oFcPoHelper->fcpoGetDb();
            $sLogoQuery = $this->_handleUploadPaypalExpressLogo($iId);

            $sQuery = " UPDATE
                                fcpopayoneexpresslogos
                            SET
                                FCPO_ACTIVE = " . DatabaseProvider::getDb()->quote($aLogo['active']) . ",
                                FCPO_LANGID = " . DatabaseProvider::getDb()->quote($aLogo['langid']) . "
                                {$sLogoQuery}
                            WHERE
                                oxid = " . DatabaseProvider::getDb()->quote($iId);

            $oDb->execute($sQuery);
            $this->_fcpoTriggerUpdateLogos();
        }
    }

    /**
     * Handle the uploading of paypal logos
     *
     * @param int $iId
     * @return string
     */
    protected function _handleUploadPaypalExpressLogo($iId)
    {
        $sLogoQuery = '';
        $aFiles = $this->_oFcPoHelper->fcpoGetFiles();

        $blFileValid = $this->_fcpoValidateFile($iId, $aFiles);
        if ($blFileValid) {
            // $sFilename = $aFiles['logo_' . $iId]['name'];
            $sLogoQuery = $this->_fcpoHandleFile($iId, $aFiles);
        }

        return $sLogoQuery;
    }

    /**
     * Method checks if all needed data of file is available
     *
     * @param int   $iId
     * @param array $aFiles
     */
    protected function _fcpoValidateFile($iId, $aFiles): bool
    {
        return $aFiles &&
            array_key_exists('logo_' . $iId, $aFiles) &&
            $aFiles['logo_' . $iId]['error'] == 0;
    }

    /**
     * Handles the upload file
     *
     * @param int   $iId
     * @param array $aFiles
     * @return string
     */
    protected function _fcpoHandleFile($iId, $aFiles)
    {
        $sLogoQuery = '';

        $sMediaUrl = $this->_fcpoFetchMediaUrl($iId, $aFiles);

        if ($sMediaUrl) {
            $sLogoQuery = ", FCPO_LOGO = " . DatabaseProvider::getDb()->quote(basename((string)$sMediaUrl));
            $this->_aAdminMessages["blLogoAdded"] = true;
        }

        return $sLogoQuery;
    }

    /**
     * Grabs the media url form data and returns it
     *
     * @param int   $iId
     * @param array $aFiles
     */
    protected function _fcpoFetchMediaUrl($iId, $aFiles)
    {
        $oUtilsFile = $this->_oFcPoHelper->fcpoGetUtilsFile();
        if ($this->_oFcPoHelper->fcpoGetIntShopVersion() < 4530) {
            $sMediaUrl = $oUtilsFile->handleUploadedFile($aFiles['logo_' . $iId], $this->_sPayPalExpressLogoPath);
        } else {
            $sMediaUrl = $oUtilsFile->processFile('logo_' . $iId, $this->_sPayPalExpressLogoPath);
        }

        return $sMediaUrl;
    }

    /**
     * Do the update on database
     *
     * @return void
     */
    protected function _fcpoTriggerUpdateLogos()
    {
        $iDefault = $this->_oFcPoHelper->fcpoGetRequestParameter('defaultlogo');
        if ($iDefault) {
            $sQuery = "UPDATE fcpopayoneexpresslogos SET fcpo_default = 0";
            $this->_oFcPoDb->execute($sQuery);

            $sQuery = "UPDATE fcpopayoneexpresslogos SET fcpo_default = 1 WHERE oxid = " . DatabaseProvider::getDb()->quote($iDefault);
            $this->_oFcPoDb->execute($sQuery);
        }
    }

    /**
     * Add a new empty paypal-logo entry into database
     */
    public function fcpoAddPaypalExpressLogo(): void
    {
        $sQuery = "INSERT INTO fcpopayoneexpresslogos (FCPO_ACTIVE, FCPO_LANGID, FCPO_LOGO, FCPO_DEFAULT) VALUES (0, 0, '', 0)";
        $this->_oFcPoDb->execute($sQuery);
    }

}
