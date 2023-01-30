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
use OxidEsales\Eshop\Core\ShopVersion;
use OxidEsales\Eshop\Core\Utils;
use OxidEsales\Eshop\Core\UtilsDate;
use OxidEsales\Eshop\Core\UtilsFile;
use OxidEsales\Eshop\Core\UtilsObject;
use OxidEsales\Eshop\Core\UtilsServer;
use OxidEsales\Eshop\Core\UtilsView;
use OxidEsales\Eshop\Core\ViewConfig;

final class FcPoHelper extends BaseModel
{
    /**
     * oxconfig instance
     */
    private static ?Config $_oConfig = null;

    /**
     * oxconfig instance
     */
    private static ?Session $_oSession = null;

    /**
     * Flags if shop uses registry
     */
    private static ?bool $_blUseRegistry = null;

    /** @var array */
    private const A_MODULE = [];

    /**
     * static Getter for config instance
     *
     * @return Config
     */
    public static function fcpoGetStaticConfig(): Config
    {
        return self::_useRegistry() ? Registry::getConfig() : Config::getInstance();
    }

    /**
     * Static getter for checking newer available methods and classes in shop
     *
     *
     * @return bool
     */
    private static function _useRegistry(): bool
    {
        if (self::$_blUseRegistry === null) {
            self::$_blUseRegistry = false;
            if (class_exists('Registry')) {
                $config = Registry::getConfig();
                if (method_exists($config, 'getRequestParameter')) {
                    self::$_blUseRegistry = true;
                }
            }
        }
        return self::$_blUseRegistry;
    }

    /**
     * Method returns current module version
     *
     * @return string
     */
    public static function fcpoGetStaticModuleVersion(): string
    {
        return '1.0.1';
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
        if ($sIncludePath !== '' && $sIncludePath !== '0') {
            include_once $sIncludePath;
        }

        return new $sClassName();
    }

    /**
     * Wrapper method for getting a session variable
     */
    public function fcpoGetSessionVariable(string $sVariable): mixed
    {
        return $this->getSession()->getVariable($sVariable);
    }

    /**
     * Returns shop version
     */
    public function fcpoGetShopVersion(): int
    {
        return oxNew(ShopVersion::class)->getVersion();
    }

    /**
     * oxConfig instance getter
     */
    public function fcpoGetConfig(): Config
    {
        if (self::$_oConfig == null) {
            self::$_oConfig = Registry::getConfig();
        }

        return self::$_oConfig;
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
     * @param string $sValue
     * @return void
     */
    public function fcpoSetSessionVariable(string $sVariable, string $sValue): void
    {
        $this->getSession()->setVariable($sVariable, $sValue);
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
     * @return Session|null
     */
    public function fcpoGetSession(): ?Session
    {
        return $this->getSession();
    }

    /**
     * Getter for database instance
     *
     * @param $blAssoc bool
     * @return DatabaseInterface|null
     * @throws DatabaseConnectionException
     */
    public function fcpoGetDb(bool $blAssoc = false): ?DatabaseInterface
    {
        if ($blAssoc) {
            return DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_ASSOC);
        } else {
            return DatabaseProvider::getDb(DatabaseProvider::FETCH_MODE_NUM);
        }
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
     * @return mixed
     */
    public function fcpoGetLang(): mixed
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
     * Returns an instance of UtilsObject
     *
     * @return UtilsObject
     */
    public function fcpoGetUtilsObject(): UtilsObject
    {
        return Registry::get(UtilsObject::class);
    }

    /**
     * Returns an instance of Utils
     *
     * @return Utils
     */
    public function fcpoGetUtils(): Utils
    {
        return Registry::getUtils();
    }

    /**
     * Returns an instance of UtilsView
     *
     * @return UtilsView
     */
    public function fcpoGetUtilsView(): UtilsView
    {
        return Registry::get(UtilsView::class);
    }

    /**
     * Returns an instance of ViewConfig
     *
     * @return ViewConfig
     */
    public function fcpoGetViewConfig(): ViewConfig
    {
        return Registry::get(ViewConfig::class);
    }

    /**
     * Returns an instance of UtilsServer
     *
     * @return UtilsServer
     */
    public function fcpoGetUtilsServer(): UtilsServer
    {
        return Registry::get(UtilsServer::class);
    }

    /**
     * Returns an instance of UtilsDate
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
        include_once __DIR__ . "/../metadata.php";
        return self::A_MODULE['version'];
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
     * @return void
     */
    public function fcpoHeader(string $sContent): void
    {
        header($sContent);
    }

    /**
     * Wrapper for php exit on being able to be mocked
     *
     * @return void
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
        $shopVersion = $oConfig->getActiveView()->getShopVersion();

        return $sEdition . $shopVersion;
    }

    /**
     * Returns shop version as integer
     *
     * @return int
     */
    public function fcpoGetIntShopVersion(): int
    {
        $oConfig = $this->fcpoGetConfig();
        $sVersion = $oConfig->getActiveShop()->oxshops__oxversion->value;
        $iVersion = (int)str_replace('.', 
    '', (string) $sVersion);
        // fix for ce/pe 4.10.0+
        if ($iVersion > 1000) {
            $iVersion *= 10;
        } else {
            while ($iVersion < 1000) {
                $iVersion *= 10;
            }
        }
        return $iVersion;
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
     * @return string[]
     */
    public function fcpoGetPayoneStatusList(): array
    {
        return ['appointed', 
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
    'invoice'];
    }

    /**
     * Returns a static instance of given object name
     *
     * @param $sObjectName
     * @return mixed
     */
    public function getStaticInstance($sObjectName): mixed
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
     * @param BasketItem|float $mValue
     * @return int
     */
    public function fcpoGetCentPrice(BasketItem|float $mValue): int
    {
        $oConfig = $this->fcpoGetConfig();
        if ($mValue instanceof BasketItem) {
            $oPrice = $mValue->getPrice();
            $bruttoPrice = $oPrice->getBruttoPrice();
            $dAmount = $mValue->getAmount();
            $dBruttoPrice = round($bruttoPrice / $dAmount, 2);
        } else {
            $dBruttoPrice = $mValue;
        }

        $actShopCurrencyObject = $oConfig->getActShopCurrencyObject();
        $dFactor = 10.0 ** $actShopCurrencyObject->decimal;

        return $dBruttoPrice * $dFactor;
    }

    /**
     * Returns path to modules dir
     *
     * @param bool $absolute mode - absolute/relative path
     */
    public function getModulesDir(bool $absolute = true): string
    {
        if ($absolute) {
            $oConfig = $this->fcpoGetConfig();
            return $oConfig->getConfigParam('sShopDir') . 'modules/';
        } else {
            return 'modules/';
        }
    }
}
