<?php

namespace Fatchip\PayOne\Application\Controller\Admin;

use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Lib\FcPoHelper;

class FcPayOnePaymentMain extends FcPayOnePaymentMain_parent
{
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

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
    protected $_oFcPoConfigExport = null;


    /**
     * init object construction
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoConfigExport = oxNew(FcPoConfigExport::class);
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $sShopId = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sShopId);
    }

    /**
     * Loads configurations of payone and make them accessable
     *
     * @param string $sShopId
     * @return void
     */
    protected function _fcpoLoadConfigs(string $sShopId): void
    {
        $aConfigs = $this->_oFcPoConfigExport->fcpoGetConfig($sShopId);
        $this->_aConfBools = $aConfigs['bools'];
    }

    /**
     * Template getter for boolean config values
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
     * @return void
     */
    public function save()
    {
        parent::save();

        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $aConfBools = $this->_oFcPoHelper->fcpoGetRequestParameter("confbools");

        if (is_array($aConfBools)) {
            foreach ($aConfBools as $sVarName => $sVarVal) {
                $oConfig->saveShopConfVar("bool", $sVarName, (bool)$sVarVal);
            }
        }

        $sShopId = $oConfig->getShopId();
        $this->_fcpoLoadConfigs($sShopId);
    }
}
