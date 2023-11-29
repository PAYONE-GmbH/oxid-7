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
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Model\BaseModel;
use stdClass;

class FcPoTransactionStatus extends BaseModel
{

    public $fcpotransactionstatus__fcpo_txaction;
    public $fcpotransactionstatus__fcpo_txreceivable;
    public $fcpotransactionstatus__fcpo_balance;
    public $fcpotransactionstatus__fcpo_txid;
    public $fcpotransactionstatus__fcpo_clearingtype;
    public $fcpotransactionstatus__fcpo_cardtype;

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Instance of oxid database
     *
     * @var DatabaseInterface
     */
    protected DatabaseInterface $_oFcPoDb;

    /**
     * Object core table name
     *
     * @var string
     */
    protected string $_sCoreTbl = 'fcpotransactionstatus';

    /**
     * Current class name
     *
     * @var string
     */
    protected $_sClassName = 'fcpotransactionstatus';


    /**
     * Class constructor
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();

        $this->init('fcpotransactionstatus');
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = DatabaseProvider::getDb();
    }

    /**
     * Get translated description text of the transaction action
     *
     * @return string
     */
    public function getAction(): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sAction = $this->fcpotransactionstatus__fcpo_txaction->value;
        $dReceivable = (double)$this->fcpotransactionstatus__fcpo_txreceivable->value;
        $dBalance = (double)$this->fcpotransactionstatus__fcpo_balance->value;

        if ($sAction == 'paid' && ($dReceivable + $dBalance) < 0) {
            $sAction = 'overpaid';
        }

        return $oLang->translateString('fcpo_action_' . $sAction, null, true);
    }

    /**
     * Get translated for payment type of transaction
     *
     * @return string
     */
    public function getClearingtype(): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sTxid = $this->fcpotransactionstatus__fcpo_txid->value;
        $sClearingType = $this->fcpotransactionstatus__fcpo_clearingtype->value;
        $oOrder = $this->_fcpoGetOrderByTxid($sTxid);
        $sPaymentType = $oOrder->oxorder__oxpaymenttype->value;
        $sTransSuffix = ($sClearingType == 'fnc') ? $sPaymentType : $sClearingType;

        return $oLang->translateString('fcpo_clearingtype_' . $sTransSuffix, null, true);
    }

    /**
     * Returns order object by txid
     *
     * @param string $sTxid
     * @return Order
     */
    protected function _fcpoGetOrderByTxid(string $sTxid): Order
    {
        $sOxid = $this->_oFcPoDb->getOne("SELECT oxid FROM oxorder WHERE fcpotxid = '$sTxid'");
        $oOrder = $this->_oFcPoHelper->getFactoryObject(Order::class);
        $oOrder->load($sOxid);

        return $oOrder;
    }

    /**
     * Get name of credit card abbreviation
     *
     * @return string
     */
    public function getCardtype(): string
    {
        $aMatchMap = ['V' => 'Visa', 'M' => 'Mastercard', 'A' => 'Amex', 'D' => 'Diners', 'J' => 'JCB', 'O' => 'Maestro International', 'U' => 'Maestro UK', 'B' => 'Carte Bleue'];

        $sCardType = $this->fcpotransactionstatus__fcpo_cardtype->value;

        return (isset($aMatchMap[$sCardType])) ? $aMatchMap[$sCardType] : $sCardType;
    }

    /**
     * Get translated name of the payment action by currenct receivable money amount
     *
     * @param float $fReceivable receivable amount
     *
     * @return string
     */
    public function getDisplayNameReceivable(float $fReceivable): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sLangAppointed = $this->_fcpoGetLangIdent($fReceivable, 'fcpo_receivable_appointed1', 'fcpo_receivable_appointed2');
        $sLangReminder = $this->_fcpoGetLangIdent($fReceivable, 'fcpo_receivable_reminder', '');
        $sLangDebit = $this->_fcpoGetLangIdent($fReceivable, 'fcpo_receivable_debit1', 'fcpo_receivable_debit2');

        $aMatchMap = ['cancelation' => 'fcpo_receivable_cancelation', 'appointed' => $sLangAppointed, 'capture' => 'fcpo_receivable_capture', 'refund' => $sLangDebit, 'debit' => $sLangDebit, 'reminder' => $sLangReminder];

        $sTxAction = $this->fcpotransactionstatus__fcpo_txaction->value;
        $sLangIdent = $this->_fcpoGetMapAction($sTxAction, $aMatchMap, 'FCPO_RECEIVABLE');

        return $oLang->translateString($sLangIdent, null, true);
    }

    /**
     * This method decides if given option1 or 2 will be used by checking if given value
     *
     * @param float $fValue
     * @param string $sOption1
     * @param string $sOption2
     * @return string
     */
    protected function _fcpoGetLangIdent(float $fValue, string $sOption1, string $sOption2): string
    {
        return ($fValue > 0) ? $sOption1 : $sOption2;
    }

    /**
     * Returns a certain action of a given map
     *
     * @param string $sTxAction
     * @param array $aMatchMap
     * @param string $sDefault
     * @return string
     */
    protected function _fcpoGetMapAction(string $sTxAction, array $aMatchMap, string $sDefault): string
    {
        return (isset($aMatchMap[$sTxAction])) ? $aMatchMap[$sTxAction] : $sDefault;
    }

    /**
     * Get translated name of the payment action by paid money amount
     *
     * @param float $fPayment paid amount
     *
     * @return string
     */
    public function getDisplayNamePayment(float $fPayment): string
    {
        $oLang = $this->_oFcPoHelper->fcpoGetLang();

        $sLangCapture = $this->_fcpoGetLangIdent($fPayment, 'fcpo_payment_capture1', 'fcpo_payment_capture2');
        $sLangPaid = $this->_fcpoGetLangIdent($fPayment, 'fcpo_payment_paid1', 'fcpo_payment_paid2');
        $sLangUnderpaid = $this->_fcpoGetLangIdent($fPayment, 'fcpo_payment_underpaid1', 'fcpo_payment_underpaid2');
        $sLangDebit = $this->_fcpoGetLangIdent($fPayment, 'fcpo_payment_debit1', 'fcpo_payment_debit2');

        $aMatchMap = ['capture' => $sLangCapture, 'cancelation' => $sLangPaid, 'paid' => $sLangPaid, 'underpaid' => $sLangUnderpaid, 'refund' => $sLangDebit, 'debit' => $sLangDebit, 'transfer' => 'fcpo_payment_transfer'];

        $sTxAction = $this->fcpotransactionstatus__fcpo_txaction->value;
        $sLangIdent = $this->_fcpoGetMapAction($sTxAction, $aMatchMap, 'fcpo_payment');

        return $oLang->translateString($sLangIdent, null, true);
    }

    /**
     * Template getter for returning forward redirects
     * @throws DatabaseErrorException
     */
    public function fcpoGetForwardRedirects(): array|false
    {
        $sStatusmessageId = $this->getId();
        $sQuery = "
            SELECT 
                sf.FCPO_URL,
                sfq.FCTRIES,
                sfq.FCLASTTRY,
                sfq.FCFULFILLED,
                sfq.FCLASTREQUEST,
                sfq.FCLASTRESPONSE,
                sfq.FCRESPONSEINFO
            FROM fcpostatusforwardqueue sfq
            LEFT JOIN fcpostatusforwarding sf ON (sfq.FCSTATUSFORWARDID = sf.OXID)
            WHERE sfq.FCSTATUSMESSAGEID='$sStatusmessageId'  
        ";

        $aRows = $this->_oFcPoDb->GetAll($sQuery);

        if (!is_array($aRows) || count($aRows) == 0) {
            return false;
        }
        $aForwardRedirects = [];
        foreach ($aRows as $aRow) {
            $oForwardRedirect = new stdClass();
            $oForwardRedirect->targetUrl = $aRow['FCPO_URL'];
            $oForwardRedirect->tries = $aRow['FCTRIES'];
            $oForwardRedirect->lastTry = $aRow['FCLASTTRY'];
            $oForwardRedirect->fulfilled = $aRow['FCFULFILLED'];
            $sDetails =
                "REQUEST\n=======\n" .
                $aRow['FCLASTREQUEST'] . "\n\n" .
                "RESPONSE\n========\n" .
                $aRow['FCLASTRESPONSE'] . "\n\n" .
                "REQUESTINFO\n===========\n" .
                $aRow['FCRESPONSEINFO'] . "\n\n";
            $oForwardRedirect->details = $sDetails;

            $aForwardRedirects[] = $oForwardRedirect;
        }

        return $aForwardRedirects;
    }

}
