<?php


namespace Fatchip\PayOne\Application\Controller\Admin;

use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Lib\FcPoHelper;

class FcPayOnePaymentMain extends FcPayOnePaymentMain_parent
{
    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    protected $_oFcpoHelper;

    /**
     * List of boolean config values
     *
     * @var array
     */
    protected $_aConfBools = [];

    /**
     * fcpoconfigexport instance
     *
     * @var object
     */
    protected $_oFcpoConfigExport;


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        $this->_oFcpoConfigExport = oxNew(FcPoConfigExport::class);
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $sShopId = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sShopId);
    }

    /**
     * Loads configurations of payone and make them accessable
     *
     * 
     */
    private function _fcpoLoadConfigs($sShopId): void
    {
        $aConfigs = $this->_oFcpoConfigExport->fcpoGetConfig($sShopId);
        $this->_aConfBools = $aConfigs['bools'];
    }

    /**
     * Template getter for boolean config values
     *
     * 
     * @return array
     */
    public function fcpoGetConfBools()
    {
        return $this->_aConfBools;
    }

    /**
     * Save Method overwriting
     *
     * 
     */
    public function save(): void
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $aConfBools = $this->_oFcpoHelper->fcpoGetRequestParameter("confbools");

        if (is_array($aConfBools)) {
            foreach ($aConfBools as $sVarName => $sVarVal) {
                $oConfig->saveShopConfVar("bool", $sVarName, (bool)$sVarVal);
            }
        }

        $sShopId = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sShopId);
    }
}
