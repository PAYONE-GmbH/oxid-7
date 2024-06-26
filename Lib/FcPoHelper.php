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

use Exception;
use JetBrains\PhpStorm\NoReturn;
use OxidEsales\Eshop\Application\Model\BasketItem;
use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Model\BaseModel;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\Session;
use OxidEsales\Eshop\Core\Utils;
use OxidEsales\Eshop\Core\UtilsDate;
use OxidEsales\Eshop\Core\UtilsFile;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Core\UtilsServer;
use OxidEsales\Eshop\Core\UtilsView;
use OxidEsales\Eshop\Core\ViewConfig;

class FcPoHelper extends BaseModel
{

    /**
     * Config instance
     *
     * @var Config|null
     */
    private static ?Config $_oConfig = null;

    /**
     * Session instance
     *
     * @var Session|null
     */
    private static ?Session $_oSession = null;


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a factory instance of given object
     *
     * @param string $sName
     * @return object
     */
    public function getFactoryObject(string $sName): object
    {
        return oxNew($sName);
    }

    /**
     * Wrapper for ini get calls
     *
     * @param string $sConfigVar
     * @return string|bool
     */
    public function fcpoIniGet(string $sConfigVar): string|bool
    {
        return ini_get($sConfigVar);
    }

    /**
     * Wrapper for returning if function with given name exists
     *
     * @param string $sFunctionName
     * @return bool
     */
    public function fcpoFunctionExists(string $sFunctionName): bool
    {
        return function_exists($sFunctionName);
    }

    /**
     * Wrapper for returning if file in given path exists
     *
     * @param string $sFilePath
     * @return bool
     */
    public function fcpoFileExists(string $sFilePath): bool
    {
        return file_exists($sFilePath);
    }

    /**
     * Creates an instance of a class
     *
     * @param string $sClassName
     * @param string $sIncludePath optional
     * @return object
     * @throws Exception
     */
    public function fcpoGetInstance(string $sClassName, string $sIncludePath = ""): object
    {
        if ($sIncludePath) {
            include_once $sIncludePath;
        }
        return new $sClassName();
    }

    /**
     * Wrapper method for getting a session variable
     *
     * @param string $sVariable
     * @return mixed
     */
    public function fcpoGetSessionVariable(string $sVariable): mixed
    {
        return $this->getSession()->getVariable($sVariable);
    }

    /**
     * Session instance getter
     *
     * @return Session
     */
    public function getSession(): Session
    {
        if (self::$_oSession == null) {
            self::$_oSession = Registry::getSession();
        }

        return self::$_oSession;
    }

    /**
     * Wrapper method for setting a session variable
     *
     * @param string $sVariable
     * @param mixed|null $mValue
     * @return void
     */
    public function fcpoSetSessionVariable(string $sVariable, mixed $mValue = null): void
    {
        $this->getSession()->setVariable($sVariable, $mValue);
    }

    /**
     * Wrapper method for setting a session variable
     *
     * @param string $sVariable
     * @return void
     */
    public function fcpoDeleteSessionVariable(string $sVariable): void
    {
        $this->getSession()->deleteVariable($sVariable);
    }

    /**
     * Getter for session instance
     *
     * @return Session
     */
    public function fcpoGetSession(): Session
    {
        return $this->getSession();
    }

    /**
     * Getter for database instance
     *
     * @param bool $blAssoc with assoc mode
     * @return DatabaseInterface
     * @throws DatabaseConnectionException
     */
    public function fcpoGetDb(bool $blAssoc = false): DatabaseInterface
    {
        return DatabaseProvider::getDb($blAssoc ? DatabaseProvider::FETCH_MODE_ASSOC : DatabaseProvider::FETCH_MODE_NUM);
    }

    /**
     * Wrapper method for getting a request parameter
     *
     * @param string $sParameter
     * @return mixed
     */
    public function fcpoGetRequestParameter(string $sParameter): mixed
    {
        $oRequest = Registry::get(Request::class);
        return $oRequest->getRequestParameter($sParameter);
    }

    /**
     * Returns a language Instance
     *
     * @return Language
     */
    public function fcpoGetLang(): Language
    {
        return Registry::get(Language::class);
    }

    /**
     * Returns a utilsfile instance
     *
     * @return UtilsFile
     */
    public function fcpoGetUtilsFile(): UtilsFile
    {
        return Registry::get(UtilsFile::class);
    }

    /**
     * Returns a utilsobject instance
     *
     * @return UtilsObject
     */
    public function fcpoGetUtilsObject(): UtilsObject
    {
        return Registry::get(UtilsObject::class);
    }

    /**
     * Returns an instance of oxutils
     *
     * @return Utils
     */
    public function fcpoGetUtils(): Utils
    {
        return Registry::getUtils();
    }

    /**
     * Returns an instance of oxutilsview
     *
     * @return UtilsView
     */
    public function fcpoGetUtilsView(): UtilsView
    {
        return Registry::get(UtilsView::class);
    }

    /**
     * Returns an instance of oxviewvonfig
     *
     * @return ViewConfig
     */
    public function fcpoGetViewConfig(): ViewConfig
    {
        return Registry::get(ViewConfig::class);
    }

    /**
     * Returns an instance of oxutilserver
     *
     * @return UtilsServer
     */
    public function fcpoGetUtilsServer(): UtilsServer
    {
        return Registry::get(UtilsServer::class);
    }

    /**
     * Returns an instance of oxUtilsDate
     *
     * @return UtilsDate
     */
    public function fcpoGetUtilsDate(): UtilsDate
    {
        return Registry::get(UtilsDate::class);
    }

    /**
     * Method returns current module version
     *
     * @return string
     */
    public function fcpoGetModuleVersion(): string
    {
        $aModule = [];
        include_once VENDOR_PATH . "payone-gmbh/oxid-7/metadata.php";
        if (empty($aModule['version'])) {
            return self::fcpoGetStaticModuleVersion();
        }
        return $aModule['version'];
    }

    /**
     * Method returns current module version
     *
     * @return string
     */
    public static function fcpoGetStaticModuleVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Getter for config instance
     *
     * @return Config
     */
    public function fcpoGetConfig(): Config
    {
        if (self::$_oConfig == null) {
            self::$_oConfig = Registry::getConfig();
        }

        return self::$_oConfig;
    }

    /**
     * Returns path to vendor dir
     *
     * @return string
     */
    public function getVendorDir(): string
    {
        return VENDOR_PATH;
    }

    /**
     * Returns the superglobal $_FILES
     *
     * @return array
     */
    public function fcpoGetFiles(): array
    {
        return $_FILES;
    }

    /**
     * Processing and returning result string
     *
     * @param string $sContent
     * @return string
     */
    public function fcpoProcessResultString(string $sContent): string
    {
        return $sContent;
    }

    /**
     * Output content as header
     *
     * @param string $sContent
     */
    public function fcpoHeader(string $sContent)
    {
        header($sContent);
    }

    /**
     * Wrapper for php exit on being able to be mocked
     *
     */
    #[NoReturn] public function fcpoExit()
    {
        exit;
    }

    /**
     * Returns if incoming class name exists or not
     *
     * @param string $sClassName
     * @return bool
     */
    public function fcpoCheckClassExists(string $sClassName): bool
    {
        return class_exists($sClassName);
    }

    /**
     * Returns current integrator version
     *
     * @return string
     */
    public function fcpoGetIntegratorVersion(): string
    {
        $oConfig = $this->fcpoGetConfig();
        $sEdition = $oConfig->getActiveShop()->oxshops__oxedition->value;
        $sVersion = $oConfig->getActiveView()->getShopVersion();
        return $sEdition . $sVersion;
    }

    /**
     * Returns the current shop name
     *
     * @return string
     */
    public function fcpoGetShopName(): string
    {
        $oConfig = $this->fcpoGetConfig();

        return $oConfig->getActiveShop()->oxshops__oxname->value;
    }

    /**
     * Returns help url
     *
     * @return string
     */
    public function fcpoGetHelpUrl(): string
    {
        return "https://www.payone.de";
    }

    /**
     * Returns the list of possible tx statuses
     *
     * @return array
     */
    public function fcpoGetPayoneStatusList(): array
    {
        return [
            'appointed',
            'capture',
            'paid',
            'underpaid',
            'cancelation',
            'refund',
            'debit',
            'reminder',
            'vauthorization',
            'vsettlement',
            'transfer',
            'invoice',
        ];
    }

    /**
     * Returns a static instance of given object name
     *
     * @param string $sObjectName
     * @return object
     */
    public function getStaticInstance(string $sObjectName): object
    {
        return Registry::get($sObjectName);
    }

    /**
     * Loads shop version and formats it in a certain way
     *
     * @return string
     */
    public function fcpoGetIntegratorId(): string
    {
        $oConfig = $this->fcpoGetConfig();

        $sEdition = $oConfig->getActiveShop()->oxshops__oxedition->value;
        if ($sEdition == 'CE') {
            return '2027000';
        } elseif ($sEdition == 'PE') {
            return '2028000';
        } elseif ($sEdition == 'EE') {
            return '2029000';
        }
        return '';
    }

    /**
     * Item price in smallest available unit
     *
     * @param double|BasketItem $mValue
     * @return float|int
     */
    public function fcpoGetCentPrice(float|BasketItem $mValue): float|int
    {
        $oConfig = $this->fcpoGetConfig();
        if ($mValue instanceof BasketItem) {
            $oPrice = $mValue->getPrice();
            $dBruttoPricePosSum = $oPrice->getBruttoPrice();
            $dAmount = $mValue->getAmount();
            $dBruttoPrice = round($dBruttoPricePosSum / $dAmount, 2);
        } else {
            $dBruttoPrice = $mValue;
        }

        $oCur = $oConfig->getActShopCurrencyObject();
        $dFactor = (double)pow(10, $oCur->decimal);

        return $dBruttoPrice * $dFactor;
    }

    /**
     * Generates a Universally Unique Identifier (UUID)
     *
     * @return string
     * @throws Exception
     */
    public function fcpoGenerateUUIDv4(): string
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Checks if BNPL Portal and Key are configured
     *
     * @return bool
     */
    public function fcpoIsBNPLConfigured(): bool
    {
        $oConfig = $this->fcpoGetConfig();
        return !empty($oConfig->getConfigParam('sFCPOPLPortalId')) &&
            !empty($oConfig->getConfigParam('sFCPOPLPortalKey'));
    }

    /**
     * Convert a price from cent format (xxxxxx) into precision-2 decimal format (x.xxx,xx)
     * for display purpose
     *
     * @param int $iAmount
     * @return string
     */
    public function fcpoPriceFromCentToDec(int $iAmount): string
    {
        return number_format($iAmount / 100, 2, ',', '.');
    }
}
