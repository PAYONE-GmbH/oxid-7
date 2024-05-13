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
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Exception\StandardException;
use OxidEsales\Eshop\Core\Model\BaseModel;

class FcPoPaypal extends BaseModel
{

    /**
     * Collects messages of different types
     *
     * @var array
     */
    protected array $_aAdminMessages = [];

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
     * Path of payone images
     *
     * @var string
     */
    protected string $_sPayPalExpressLogoPath = 'out/modules/fcpayone/img/';


    /**
     * Init needed data
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();

        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
    }

    /**
     * Method returns collected messages back to controller
     *
     * @return array
     */
    public function fcpoGetMessages(): array
    {
        return $this->_aAdminMessages;
    }

    /**
     * Method requests database for fetching PayPal logos and return this data in an array
     *
     * @return array[]
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
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
     * @param array $aLogo
     * @return array
     */
    protected function _fcpoAddLogoPath(string $sPoLogo, array $aLogo): array
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
     * Validates the existence and availability of paypalexpress logo
     *
     * @param string $sPoLogo
     * @return bool
     */
    protected function _fcpoGetLogoEnteredAndExisting(string $sPoLogo): bool
    {
        return !empty($sPoLogo) &&
            $this->_oFcPoHelper->fcpoFileExists(getShopBasePath() . $this->_sPayPalExpressLogoPath . $sPoLogo);
    }

    /**
     * Updates a given set of logos into database
     *
     * @param array $aLogos
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoUpdatePayPalLogos(array $aLogos): void
    {
        foreach ($aLogos as $iId => $aLogo) {
            $oDb = $this->_oFcPoHelper->fcpoGetDb();
            $sLogoQuery = $this->_handleUploadPaypalExpressLogo($iId);

            $sQuery = " UPDATE
                                fcpopayoneexpresslogos
                            SET
                                FCPO_ACTIVE = " . DatabaseProvider::getDb()->quote($aLogo['active']) . ",
                                FCPO_LANGID = " . DatabaseProvider::getDb()->quote($aLogo['langid']) . "
                                $sLogoQuery
                            WHERE
                                oxid = " . DatabaseProvider::getDb()->quote($iId);

            $oDb->execute($sQuery);
            $this->_fcpoTriggerUpdateLogos();
        }
    }

    /**
     * Handle the uploading of PayPal logos
     *
     * @param int $iId
     * @return string
     */
    protected function _handleUploadPaypalExpressLogo(int $iId): string
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
     * @param int $iId
     * @param array $aFiles
     * @return bool
     */
    protected function _fcpoValidateFile(int $iId, array $aFiles): bool
    {
        return $aFiles &&
            array_key_exists('logo_' . $iId, $aFiles) &&
            $aFiles['logo_' . $iId]['error'] == 0;
    }

    /**
     * Handles the upload file
     *
     * @param int $iId
     * @param array $aFiles
     * @return string
     * @throws DatabaseConnectionException
     */
    protected function _fcpoHandleFile(int $iId, array $aFiles): string
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
     * @param int $iId
     * @param array $aFiles
     * @return mixed
     * @throws StandardException
     */
    protected function _fcpoFetchMediaUrl(int $iId, array $aFiles): mixed
    {
        $oUtilsFile = $this->_oFcPoHelper->fcpoGetUtilsFile();

        return $oUtilsFile->processFile('logo_' . $iId, $this->_sPayPalExpressLogoPath);
    }

    /**
     * Do the update on database
     *
     * @return void
     * @throws DatabaseErrorException
     * @throws DatabaseConnectionException
     */
    protected function _fcpoTriggerUpdateLogos(): void
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
     * @throws DatabaseErrorException
     */
    public function fcpoAddPaypalExpressLogo(): void
    {
        $sQuery = "INSERT INTO fcpopayoneexpresslogos (FCPO_ACTIVE, FCPO_LANGID, FCPO_LOGO, FCPO_DEFAULT) VALUES (0, 0, '', 0)";
        $this->_oFcPoDb->execute($sQuery);
    }

}
