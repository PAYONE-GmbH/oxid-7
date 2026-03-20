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

use Doctrine\DBAL\Connection;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use stdClass;

class FcPayOnePayment extends \OxidEsales\Eshop\Application\Model\Payment
{

    /**
     * @var array|string[]
     */
    protected static array $_aPaymentTypes = [
        'fcpoinvoice',
        'fcpopayadvance',
        'fcpodebitnote',
        'fcpocashondel',
        'fcpocreditcard',
        'fcpocreditcardv2',
        'fcpopaypal',
        'fcpopaypal_express',
        'fcpopaypalv2',
        'fcpopaypalv2_express',
        'fcpoklarna',
        'fcpoklarna_invoice',
        'fcpoklarna_installments',
        'fcpoklarna_directdebit',
        'fcpobarzahlen',
        'fcpopo_bill',
        'fcpopo_debitnote',
        'fcpopo_installment',
        'fcporp_bill',
        'fcpo_secinvoice',
        'fcpo_sofort',
        'fcpo_eps',
        'fcpo_pf_finance',
        'fcpo_pf_card',
        'fcpo_ideal',
        'fcpo_p24',
        'fcpo_bancontact',
        'fcporp_debitnote',
        'fcpo_alipay',
        'fcpo_trustly',
        'fcpo_wechatpay',
        'fcpo_apple_pay',
        'fcporp_installment',
        'fcpopl_secinvoice',
        'fcpopl_secinstallment',
        'fcpopl_secdebitnote',
        'fcpo_wero',
        'fcpo_googlepay',
    ];

    /**
     * @var array|string[]
     */
    protected static array $_aRedirectPayments = [
        'fcpopaypal',
        'fcpopaypal_express',
        'fcpopaypalv2',
        'fcpopaypalv2_express',
        'fcpoklarna',
        'fcpoklarna_invoice',
        'fcpoklarna_installments',
        'fcpoklarna_directdebit',
        'fcpo_sofort',
        'fcpo_eps',
        'fcpo_pf_finance',
        'fcpo_pf_card',
        'fcpo_ideal',
        'fcpo_p24',
        'fcpo_bancontact',
        'fcpo_alipay',
        'fcpo_wechatpay',
        'fcpo_wero',
        'fcpo_googlepay',
    ];

    /**
     * Array of online payments
     *
     * @var string[]
     */
    protected static array $_aOnlinePayments = [
        'fcpo_sofort',
        'fcpo_eps',
        'fcpo_pf_finance',
        'fcpo_pf_card',
        'fcpo_ideal',
        'fcpo_p24',
        'fcpo_bancontact',
        'fcpo_trustly',
    ];

    /**
     * @var array
     */
    protected static array $_aIframePaymentTypes = [];

    /**
     * @var array
     */
    protected static array $_aFrontendApiPaymentTypes = [];

    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Database object instance
     *
     * @var Connection
     */
    protected Connection $_oFcPoDb;

    /**
     * @var array|string[]
     */
    protected array $_aPaymentsNoAuthorize = [
        'fcpobarzahlen',
        'fcpopo_bill',
        'fcpopo_debitnote',
        'fcporp_bill',
        'fcporp_debitnote',
    ];

    /**
     * List of payments that are not foreseen to be shown as regular payment
     * selection
     *
     * @var array
     */
    protected array $_aExpressPayments = [
        'fcpomasterpass'
    ];


    /**
     * init object construction
     *
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = $this->_oFcPoHelper->fcpoGetPdoDb();
    }

    /**
     * @param string $sPaymentId
     * @return bool
     */
    public static function fcIsPayOneOnlinePaymentType(string $sPaymentId): bool
    {
        return in_array($sPaymentId, self::$_aOnlinePayments);
    }

    /**
     * @param string $sPaymentId
     * @return bool
     */
    public static function fcIsPayOnePaymentType(string $sPaymentId): bool
    {
        return in_array($sPaymentId, self::$_aPaymentTypes);
    }

    /**
     * @param string $sPaymentId
     * @return bool
     */
    public static function fcIsPayOneRedirectType(string $sPaymentId): bool
    {
        $blReturn = in_array($sPaymentId, self::$_aRedirectPayments) !== false;
        $oHelper = oxNew(FcPoHelper::class);

        $blDynFlaggedAsRedirectPayment =
            (bool)$oHelper->fcpoGetSessionVariable('blDynFlaggedAsRedirectPayment');
        $blUseDynamicFlag = (
            !$blReturn &&
            $blDynFlaggedAsRedirectPayment === true
        );

        if ($blUseDynamicFlag) {
            // overwrite static value
            $blReturn = $blDynFlaggedAsRedirectPayment;
        }

        return $blReturn;
    }

    /**
     * @param string $sPaymentId
     * @return bool
     */
    public static function fcIsPayOneIframePaymentType(string $sPaymentId): bool
    {
        return in_array($sPaymentId, self::$_aIframePaymentTypes);
    }

    /**
     * @param string $sPaymentId
     * @return bool
     */
    public static function fcIsPayOneFrontendApiPaymentType(string $sPaymentId): bool
    {
        return in_array($sPaymentId, self::$_aFrontendApiPaymentTypes);
    }

    /**
     * Checks if this payment is foreseen to be shown as standard
     * payment selection
     *
     * @param string|null $sPaymentId
     * @return bool
     */
    public function fcpoShowAsRegularPaymentSelection(string $sPaymentId = null): bool
    {
        $sPaymentId = $sPaymentId ?: $this->getId();
        return !in_array($sPaymentId, $this->_aExpressPayments);
    }

    /**
     * Determines the operation mode ( live or test ) used in this order based on the payment (sub) method
     *
     * @param string $sType payment subtype ( Visa, MC, etc.). Default is ''
     *
     * @return string
     */
    public function fcpoGetOperationMode(string $sType = ''): string
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        $blLivemode = $this->oxpayments__fcpolivemode->value;

        if ($sType != '') {
            $sPaymentId = $this->getId();

            $aMap = [
                'fcpocreditcard' => $oConfig->getConfigParam('blFCPOCC' . $sType . 'Live'),
            ];

            if (in_array($sPaymentId, array_keys($aMap))) {
                $blLivemode = $aMap[$sPaymentId];
            }
        }

        // Click2Pay is available only as live mode
        if ($this->getId() == 'fcpocreditcardv2') {
            $blLivemode = true;
        }


        return $blLivemode ? 'live' : 'test';
    }

    /**
     * Adds dynvalues to the payone payment type
     *
     * @extend getDynValues
     * @return array dyn values
     */
    public function getDynValues(): array
    {
        $aDynValues = parent::getDynValues();
        return $this->_fcGetDynValues($aDynValues);
    }

    /**
     * Adds dynvalues for debitcard payment-method
     *
     * @param array|null $aDynValues dynvalues
     * @return array dynvalues (might be modified)
     */
    protected function _fcGetDynValues(?array $aDynValues): array
    {
        $oConfig = $this->_oFcPoHelper->fcpoGetConfig();
        if ((bool)$oConfig->getConfigParam('sFCPOSaveBankdata') === true) {
            if ($this->getId() == 'fcpodebitnote') {
                if (!is_array($aDynValues)) {
                    $aDynValues = [];
                }
                $oDynValue = new stdClass();
                $oDynValue->name = 'fcpo_elv_blz';
                $oDynValue->value = '';
                $aDynValues[] = $oDynValue;
                $oDynValue = new stdClass();
                $oDynValue->name = 'fcpo_elv_ktonr';
                $oDynValue->value = '';
                $aDynValues[] = $oDynValue;
                $oDynValue = new stdClass();
                $oDynValue->name = 'fcpo_elv_iban';
                $oDynValue->value = '';
                $aDynValues[] = $oDynValue;
                $oDynValue = new stdClass();
                $oDynValue->name = 'fcpo_elv_bic';
                $oDynValue->value = '';
                $aDynValues[] = $oDynValue;
            }
        }
        return $aDynValues;
    }

    /**
     * Returns the isoalpha of a country by offering an id
     *
     * @param string $sCountryId
     * @return string
     * @throws DatabaseConnectionException
     */
    public function fcpoGetCountryIsoAlphaById(string $sCountryId): string
    {
        $sQuery = "SELECT oxisoalpha2 FROM oxcountry WHERE oxid = :sOxid";
        return $this->_oFcPoDb->fetchOne($sQuery, [
            'sOxid' => $sCountryId,
        ]);
    }

    /**
     * Returns the name of a country by offering an id
     *
     * @param string $sCountryId
     * @return string
     * @throws DatabaseConnectionException
     */
    public function fcpoGetCountryNameById(string $sCountryId): string
    {
        $sQuery = "SELECT oxtitle FROM oxcountry WHERE oxid = :sOxid";
        return $this->_oFcPoDb->fetchOne($sQuery, [
            'sOxid' => $sCountryId,
        ]);
    }

    /**
     * Method assigns a certain mandate to an order
     *
     * @param string $sOrderId
     * @param string $sMandateIdentification
     * @return void
     * @throws DatabaseConnectionException|DatabaseErrorException
     */
    public function fcpoAddMandateToDb(string $sOrderId, string $sMandateIdentification): void
    {
        $sMandateIdentification = basename($sMandateIdentification . '.pdf');

        $sQuery = "
            INSERT INTO fcpopdfmandates 
            (
                OXORDERID,
                FCPO_FILENAME
            )
            VALUES
            (
                :sOrderId,
                :sMandateId
            )
        ";
        $this->_oFcPoDb->executeStatement($sQuery, [
            'sOrderId' => $sOrderId,
            'sMandateId' => $sMandateIdentification,
        ]);
    }

    /**
     * Returns user paymentid
     *
     * @param string $sUserOxid
     * @param string $sPaymentType
     * @return string|bool
     * @throws DatabaseConnectionException
     */
    public function fcpoGetUserPaymentId(string $sUserOxid, string $sPaymentType): string|bool
    {
        $sQuery = "
            SELECT
                oxpaymentid
            FROM
                oxorder
            WHERE
                oxpaymenttype = :sPaymentType
            AND 
                oxuserid = :sUserId
            ORDER BY
                oxorderdate DESC
        ";
        return $this->_oFcPoDb->fetchOne($sQuery, [
            'sPaymentType' => $sPaymentType,
            'sUserId' => $sUserOxid,
        ]);
    }

    /**
     * Check database if the user is allowed to use the given payment method and re
     *
     * @param string $sSubPaymentId ID of the sub payment method ( Visa, MC, etc. )
     * @param string $sType payment type PAYONE
     * @param string $sUserBillCountryId
     * @param string $sUserDelCountryId
     * @return bool
     */
    public function isPaymentMethodAvailableToUser(string $sSubPaymentId, string $sType, string $sUserBillCountryId, string $sUserDelCountryId): bool
    {
        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        $oQuery
            ->select('COUNT(*)')
            ->from('fcpopayment2country')
            ->where('fcpo_paymentid = :sPaymentId')
            ->andWhere('fcpo_type = :sType')
            ->setParameters([
                'sPaymentId' => $sSubPaymentId,
                'sType' => $sType,
            ])
            ->getMaxResults(1);
        $iBaseCount = $oQuery->execute()->fetchOne();

        if ($iBaseCount <= 0) {
            return 1;
        }

        $oExpressionBuilder = $this->_oFcPoDb->getExpressionBuilder();
        if ($sUserDelCountryId !== '' && $sUserBillCountryId != $sUserDelCountryId) {
            $oQuery
                ->andWhere(
                    $oExpressionBuilder->or(
                        $oExpressionBuilder->eq('fcpo_countryid', ':sCountryIdBill'),
                        $oExpressionBuilder->eq('fcpo_countryid', ':sCountryIdDel'),
                    )
                )
                ->setParameters([
                    'sCountryIdBill' => $sUserBillCountryId,
                    'sCountryIdDel' => $sUserDelCountryId
                ]);
        } else {
            $oQuery
                ->andWhere('fcpo_countryid = :sCountryIdBill')
                ->setParameter('sCountryIdBill', $sUserBillCountryId);
        }

        return $oQuery->execute()->fetchOne() > 0 ? 1 : 0;
    }

    /**
     * Check if a creditworthiness check has to be done
     * ( Has to be done if from boni is greater zero )
     *
     * @return bool
     */
    public function fcBoniCheckNeeded(): bool
    {
        return $this->oxpayments__oxfromboni->value > 0;
    }

    /**
     * Returns mandate text from session if available
     *
     * @return string|bool
     */
    public function fcpoGetMandateText(): string|bool
    {
        $aMandate = $this->_oFcPoHelper->fcpoGetSessionVariable('fcpoMandate');

        $blMandateTextValid = (
            $aMandate &&
            array_key_exists('mandate_status', $aMandate) !== false &&
            $aMandate['mandate_status'] == 'pending' &&
            array_key_exists('mandate_text', $aMandate) !== false
        );

        $mReturn = false;
        if ($blMandateTextValid) {
            $mReturn = urldecode($aMandate['mandate_text']);
        }

        return $mReturn;
    }

    /**
     * Determines the operation mode ( live or test ) used for this payment based on payment or form data
     *
     * @param array $aDynvalue form data
     *
     * @return string
     */
    public function fcpoGetMode(array $aDynvalue): string
    {
        $sReturn = '';
        $sId = $this->getId();
        $blIdAffected = in_array($sId, ['fcpocreditcard']);

        if ($blIdAffected) {
            $aMap = ['fcpocreditcard' => $aDynvalue['fcpo_ccmode']];

            $sReturn = $aMap[$sId];
        }

        return $sReturn;
    }

    /**
     * Returns a list of payment types
     *
     * @return array
     * @throws DatabaseErrorException
     */
    public function fcpoGetPayonePaymentTypes(): array
    {
        $aPaymentTypes = [];

        $sQuery = "SELECT oxid, oxdesc FROM oxpayments WHERE fcpoispayone = 1";
        $aRows = $this->_oFcPoDb->fetchAllAssociative($sQuery);
        foreach ($aRows as $aRow) {
            $sOxid = (isset($aRow['oxid'])) ? $aRow['oxid'] : $aRow[0];
            $sDesc = (isset($aRow['oxdesc'])) ? $aRow['oxdesc'] : $aRow[1];

            $oPaymentType = new stdClass();
            $oPaymentType->sId = $sOxid;
            $oPaymentType->sTitle = $sDesc;
            $aPaymentTypes[] = $oPaymentType;
        }

        return $aPaymentTypes;
    }

    /**
     * Returning red payments
     *
     * @return string
     * @throws DatabaseErrorException
     */
    public function fcpoGetRedPayments(): string
    {
        $sPayments = '';
        $sQuery = 'SELECT oxid FROM oxpayments WHERE fcpoispayone = 1 AND oxfromboni <= 100';
        $aRows = $this->_oFcPoDb->fetchAllAssociative($sQuery);
        foreach ($aRows as $aRow) {
            $sPayment = (isset($aRow[0])) ? $aRow[0] : $aRow['oxid'];
            $sPayments .= $sPayment . ',';
        }
        return rtrim($sPayments, ',');
    }

    /**
     * Returning yellow payments
     *
     * @return string
     * @throws DatabaseErrorException
     */
    public function fcpoGetYellowPayments(): string
    {
        $sPayments = '';
        $sQuery = 'SELECT oxid FROM oxpayments WHERE fcpoispayone = 1 AND oxfromboni > 100 AND oxfromboni <= 300';
        $aRows = $this->_oFcPoDb->fetchAllAssociative($sQuery);
        foreach ($aRows as $aRow) {
            $sPayment = (isset($aRow[0])) ? $aRow[0] : $aRow['oxid'];
            $sPayments .= $sPayment . ',';
        }
        return rtrim($sPayments, ',');
    }

    /**
     * Public getter for checking if current payment is allowed for authorization
     *
     * @return bool
     */
    public function fcpoAuthorizeAllowed(): bool
    {
        $sPaymentId = $this->oxpayments__oxid->value;
        $blCurrentPaymentAffected = in_array($sPaymentId, $this->_aPaymentsNoAuthorize);
        return !$blCurrentPaymentAffected;
    }

    /**
     * Returns countries assigned to given campaign id
     *
     * @param string $sCampaignId
     * @return array
     * @throws DatabaseErrorException
     */
    protected function _fcGetCountries(string $sCampaignId): array
    {
        $aCountries = [];

        $sQuery = "SELECT fcpo_countryid FROM fcpopayment2country WHERE fcpo_paymentid = :sPaymentId";
        $aRows = $this->_oFcPoDb->fetchAllNumeric($sQuery, [
            'sPaymentId' => 'KLR_' . $sCampaignId
        ]);
        foreach ($aRows as $aRow) {
            $aCountries[] = $aRow[0];
        }

        return $aCountries;
    }

    /**
     * Sets add flag to false if conditions doesn't match
     *
     * @param bool $blAdd
     * @param string $sNeedle
     * @param array $aHaystack
     * @return bool
     */
    protected function _fcpoCheckAddCampaign(bool $blAdd, string $sNeedle, array $aHaystack): bool
    {
        if (in_array($sNeedle, $aHaystack) === false) {
            $blAdd = false;
        }

        return $blAdd;
    }

}
