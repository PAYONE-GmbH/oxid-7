<?php

namespace Fatchip\PayOne;

use OxidEsales\Eshop\Core\Registry;

class FcCheckChecksum
{

    /**
     * @var string
     */
    protected string $_sModuleId;

    /**
     * @var string
     */
    protected string $_sModuleName;

    /**
     * @var string
     */
    protected string $_sModuleVersion;

    /**
     * @var bool
     */
    protected bool $_blGotModuleInfo;

    /**
     * @var string
     */
    protected string $_sShopSystem;

    /**
     * @var string
     */
    protected $_sVersionCheckUrl = 'http://version.fatchip.de/fcVerifyChecksum.php';

    /**
     * @param bool $blOutput
     * @return bool|string|void
     * @throws \JsonException
     */
    public function checkChecksumXml(bool $blOutput = false)
    {
        if (ini_get('allow_url_fopen') == 0) {
            die("Cant verify checksums, allow_url_fopen is not activated on customer-server!");
        } elseif (!function_exists('curl_init')) {
            die("Cant verify checksums, curl is not activated on customer-server!");
        }

        $aFiles = $this->_getFilesToCheck();
        $aChecksums = $this->_checkFiles($aFiles);
        $sResult = $this->_getCheckResults($aChecksums);
        if ($blOutput === true) {
            if ($sResult == 'correct') {
                echo $sResult;
            } else {
                $aErrors = json_decode(stripslashes($sResult));
                if (is_null($aErrors)) {
                    $aErrors = json_decode($sResult);
                }
                if (is_array($aErrors)) {
                    foreach ($aErrors as $aError) {
                        echo $aError . '<br>';
                    }
                }
            }
        }
        return $sResult;
    }

    /**
     * @return array|mixed
     * @throws \JsonException
     */
    protected function _getFilesToCheck(): mixed
    {
        $aFiles = [];
        if (file_exists($this->_getBasePath() . 'metadata.php')) {
            $this->_handleMetadata($this->_getBasePath() . 'metadata.php');
        }
        if (file_exists($this->_getBasePath() . 'composer.json')) {
            $this->_handleComposerJson($this->_getBasePath() . 'composer.json');
        }
        if ($this->_blGotModuleInfo === true) {
            $sRequestUrl = $this->_sVersionCheckUrl . '?module=' . $this->_sModuleId . '&version=' . $this->_sModuleVersion;
            $sResponse = file_get_contents($sRequestUrl);
            if ($sResponse) {
                $aFiles = json_decode($sResponse, null, 512, JSON_THROW_ON_ERROR);
            }
        }
        return $aFiles;
    }

    /**
     * @return string
     */
    protected function _getBasePath(): string
    {
        return dirname(__FILE__) . '/';
    }

    /**
     * @param string $sFilePath
     * @return void
     */
    protected function _handleMetadata(string $sFilePath): void
    {
        include $sFilePath;
        if (isset($aModule)) {
            if (isset($aModule['id'])) {
                $this->_sModuleId = $aModule['id'];
            }
            if (isset($aModule['title'])) {
                $this->_sModuleName = $aModule['title'];
            }
            if (isset($aModule['version'])) {
                $this->_sModuleVersion = $aModule['version'];
            }
            $this->_sShopSystem = 'oxid';
            $this->_blGotModuleInfo = true;
        }
    }

    /**
     * @param string $sFilePath
     * @return void
     * @throws \JsonException
     */
    protected function _handleComposerJson(string $sFilePath): void
    {
        $sFile = file_get_contents($sFilePath);
        if (!empty($sFile)) {
            $aFile = json_decode($sFile, true, 512, JSON_THROW_ON_ERROR);

            // decide which shopsystem
            $blIsOxid = (isset($aFile['type']) && $aFile['type'] == 'oxideshop-module');
            if ($blIsOxid) {
                $this->_sShopSystem = 'oxid';
            } else {
                $this->_sShopSystem = 'magento2';
                if (isset($aFile['name'])) {
                    $this->_sModuleId = preg_replace('#[^A-Za-z0-9]#', '_', (string)$aFile['name']);
                    $this->_sModuleName = $aFile['name'];
                }
                if (isset($aFile['version'])) {
                    $this->_sModuleVersion = $aFile['version'];
                }
            }

            $this->_blGotModuleInfo = true;
        }
    }

    /**
     * @param array $aFiles
     * @return string[]|false[]
     */
    protected function _checkFiles(array $aFiles): array
    {
        $aChecksums = [];
        foreach ($aFiles as $aFile) {
            $sFullFilePath = $this->_getShopBasePath() . $aFile;
            if (file_exists($sFullFilePath)) {
                $aChecksums[md5((string)$aFile)] = md5_file($sFullFilePath);
            }
        }
        return $aChecksums;
    }

    /**
     * @return string
     */
    protected function _getShopBasePath(): string
    {
        if ($this->_sShopSystem == 'oxid') {
            return $this->_getBasePath() . '/../../../';
        } elseif ($this->_sShopSystem == 'magento2') {
            return $this->_getBasePath() . '../../../../';
        } else {
            return $this->_getBasePath();
        }
    }

    /**
     * @param array $aChecksums
     * @return bool|string
     * @throws \JsonException
     */
    protected function _getCheckResults(array $aChecksums): bool|string
    {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $this->_sVersionCheckUrl);
        curl_setopt($curlHandle, CURLOPT_HEADER, false);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt(
            $curlHandle, CURLOPT_POSTFIELDS, [
                'checkdata' => json_encode($aChecksums, JSON_THROW_ON_ERROR),    // you'll have to change the name, here, I suppose
                'module' => $this->_sModuleId,
                'version' => $this->_sModuleVersion,
            ]
        );
        $sResult = curl_exec($curlHandle);
        curl_close($curlHandle);

        return $sResult;
    }

}

if (!isset($blOutput) || $blOutput) {
    try {
        $oScript = new FcCheckChecksum();
        $oScript->checkChecksumXml(true);
    } catch (\JsonException $oEx) {
        $oLogger = Registry::getLogger();
        $oLogger->error($oEx->getTraceAsString());
    }
}
