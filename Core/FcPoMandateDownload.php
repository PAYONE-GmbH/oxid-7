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

namespace Fatchip\PayOne\Core;

use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use JetBrains\PhpStorm\NoReturn;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;

set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

/**
 * Description of fcPayOneMandateDownload
 *
 * @author Robert
 */
class FcPoMandateDownload extends FrontendController
{
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;


    /**
     * init object construction
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
    }

    /**
     * Render overloading
     *
     */
    public function render()
    {
        parent::render();
        $this->_fcpoMandateDownloadAction();
        exit();
    }

    /**
     * Triggers download action for mandate
     *
     * @return void
     * @throws DatabaseConnectionException
     */
    protected function _fcpoMandateDownloadAction(): void
    {
        $oQuery = $this->_fcpoGetMandateQuery();
        $aResult = $oQuery->execute()->fetchNumeric();

        if (!is_array($aResult)) {
            echo 'Permission denied!';
            return;
        }

        $sFilename = (string)$aResult[0];
        $sOrderId = (string)$aResult[1];
        $sPaymentId = (string)$aResult[2];

        $sPath = getShopBasePath() . 'modules/fc/fcpayone/mandates/' . $sFilename;

        if (!file_exists($sPath)) {
            $this->_redownloadMandate($sFilename, $sOrderId, $sPaymentId);
        }

        if (!file_exists($sPath)) {
            echo 'Error: File not found!';#
            return;
        }

        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=\"$sFilename\"");
        readfile($sPath);
    }

    /**
     * Return query for fetching mandate mandatory information
     *
     * @return QueryBuilder
     * @throws DatabaseConnectionException
     */
    protected function _fcpoGetMandateQuery(): QueryBuilder
    {
        $oQuery = $this->_oFcPoHelper->fcpoGetPdoDb()->createQueryBuilder();

        $oQuery
            ->select('a.fcpo_filename', 'b.oxid', 'b.fcpomode', 'b.oxpaymenttype')
            ->from('fcpopdfmandates', 'a')
            ->innerJoin('a', 'oxorder', 'b', 'a.oxorderid = b.oxid')
            ->getMaxResults(1);

        $sUserId = $this->_fcpoGetUserId();

        $sOrderId = $this->_oFcPoHelper->fcpoGetRequestParameter('id');
        if ($sOrderId) {
            $oQuery
                ->where('b.oxid = :sOxid AND b.oxuserid = :sUserId')
                ->setParameter('sOxid', $sOrderId)
                ->setParameter('sUserId', $sUserId);
            return $oQuery;
        }
        
        $oQuery
            ->where('b.oxuserid = :sUserId')
            ->setParameter('sUserId', $sUserId)
            ->orderBy('b.oxorderdate', 'DESC');
        return $oQuery;
    }

    /**
     * Returns user id which has been sent directly as param
     * or fetch userid from other sources
     *
     * @return mixed
     */
    protected function _fcpoGetUserId(): mixed
    {
        $sUserId = $this->_oFcPoHelper->fcpoGetRequestParameter('uid');
        $oUser = $this->getUser();
        if ($oUser) {
            $sUserId = $oUser->getId();
        }

        return $sUserId;
    }

    /**
     * Re-download existing mandate from payone platform
     *
     * @param string $sMandateFilename
     * @param string $sOrderId
     * @param string $sPaymentId
     *
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    protected function _redownloadMandate(string $sMandateFilename, string $sOrderId, string $sPaymentId): void
    {
        $sMandateIdentification = str_replace('.pdf', '', $sMandateFilename);
        $oPayment = oxNew(Payment::class);
        $oPayment->load($sPaymentId);
        $sMode = $oPayment->fcpoGetOperationMode();

        $oPORequest = oxNew(FcPoRequest::class);
        $oPORequest->sendRequestGetFile($sOrderId, $sMandateIdentification, $sMode);
    }

}
