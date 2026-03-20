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
use Fatchip\PayOne\Lib\FcPoRequest;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Model\BaseModel;

class FcPoRatePay extends BaseModel
{
    /**
     * Helper object for dealing with different shop versions
     *
     * @var FcPoHelper
     */
    protected FcPoHelper $_oFcPoHelper;

    /**
     * Centralized Database instance
     *
     * @var Connection
     */
    protected Connection $_oFcPoDb;


    /**
     * Init needed data
     * @throws DatabaseConnectionException
     */
    public function __construct()
    {
        parent::__construct();

        $this->_oFcPoHelper = oxNew(FcPoHelper::class);
        $this->_oFcPoDb = $this->_oFcPoHelper->fcpoGetPdoDb();
    }

    /**
     * Add/Update Ratepay profile
     *
     * @param string $sOxid
     * @param array $aRatePayData
     * @return void
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoInsertProfile(string $sOxid, array $aRatePayData): void
    {
        if (array_key_exists('delete', $aRatePayData)) {
            $sQuery = "DELETE FROM fcporatepay WHERE oxid = :sOxid";
            $this->_oFcPoDb->executeStatement($sQuery, [
                'sOxid' => $sOxid,
            ]);
        } else {
            $sQuery = " UPDATE
                            fcporatepay
                        SET
                            shopid = :iShopid,
                            currency = :sCurrency,
                            oxpaymentid = :sPaymentId
                        WHERE
                            oxid = :sOxid";

            $this->_oFcPoDb->executeStatement($sQuery, [
                'iShopid' => $aRatePayData['shopid'],
                'sCurrency' => $aRatePayData['currency'],
                'sPaymentId' => $aRatePayData['paymentid'],
                'sOxid' => $sOxid
            ]);
            $this->_fcpoUpdateRatePayProfile($sOxid);
        }
    }

    /**
     * Requests and updates payment information for given shop_id
     *
     * @param string $sOxid
     * @return void
     */
    protected function _fcpoUpdateRatePayProfile(string $sOxid): void
    {
        $aRatePayData = $this->fcpoGetProfileData($sOxid);
        $oRequest = $this->_oFcPoHelper->getFactoryObject(FcPoRequest::class);
        $aResponse = $oRequest->sendRequestRatePayProfile($aRatePayData);
        if (isset($aResponse['status']) && $aResponse['status'] == 'OK') {
            $this->_fcpoUpdateRatePayProfileByResponse($sOxid, $aResponse);
        }
    }

    /**
     * Returns profiledata by id
     *
     * @param string $sOxid
     * @return array
     * @throws DatabaseConnectionException
     */
    public function fcpoGetProfileData(string $sOxid): array
    {
        $sQuery = "SELECT * FROM fcporatepay WHERE OXID = :sOxid";
        return $this->_oFcPoDb->fetchAssociative($sQuery, [
            'sOxid' => $sOxid
        ]);
    }

    /**
     * Collects profile information and save it into profile
     *
     * @param string $sOxid
     * @param array $aResponse
     * @return void
     * @throws DatabaseErrorException
     */
    protected function _fcpoUpdateRatePayProfileByResponse(string $sOxid, array $aResponse): void
    {
        $sQuery = "
            UPDATE fcporatepay SET
                `merchant_name` = :sMerchantName,
                `merchant_status` = :sMerchantStatus,
                `shop_name` = :sShopName,
                `name` = :sName,
                `type` = :sType,
                `activation_status_elv` = :sActivationStatusElv,
                `activation_status_installment` = :sActivationStatusInstallment,
                `activation_status_invoice` = :sActivationStatusInvoice,
                `activation_status_prepayment` = :sActivationStatusPrepayment,
                `amount_min_longrun` = :sAmountMinLongrun,
                `b2b_pq_full` = :sB2bPqFull,
                `b2b_pq_light` = :sB2bPqLight,
                `b2b_elv` = :sB2bElv,
                `b2b_installment` = :sB2bInstallment,
                `b2b_invoice` = :sB2bInvoice,
                `b2b_prepayment` = :sB2bPrepayment,
                `country_code_billing` = :sCountryCodeBilling,
                `country_code_delivery` = :sCountryCodeDelivery,
                `delivery_address_pq_full` = :sDeliveryAddressPqFull,
                `delivery_address_pq_light` = :sDeliveryAddressPqLight,
                `delivery_address_elv` = :sDeliveryAddressElv,
                `delivery_address_installment` = :sDeliveryAddressInstallment,
                `delivery_address_invoice` = :sDeliveryAddressInvoice,
                `delivery_address_prepayment` = :sDeliveryAddressPrepayment,
                `device_fingerprint_snippet_id` = :sDeviceFingerprintSnippetId,
                `eligibility_device_fingerprint` = :sEligibilityDeviceFingerprint,
                `eligibility_ratepay_elv` = :sEligibilityRatepayElv,
                `eligibility_ratepay_installment` = :sEligibilityRatepayInstallment,
                `eligibility_ratepay_invoice` = :sEligibilityRatepayInvoice,
                `eligibility_ratepay_pq_full` = :sEligibilityRatepayPqFull,
                `eligibility_ratepay_pq_light` = :sEligibilityRatepayPqLight,
                `eligibility_ratepay_prepayment` = :sEligibilityRatepayPrepayment,
                `interest_rate_merchant_towards_bank` = :sInterestRateMerchantTowardsBank,
                `interestrate_default` = :sInterestrateDefault,
                `interestrate_max` = :sInterestrateMax,
                `interestrate_min` = :sInterestrateMin,
                `min_difference_dueday` = :sMinDifferenceDueday,
                `month_allowed` = :sMonthAllowed,
                `month_longrun` = :sMonthLongrun,
                `month_number_max` = :sMonthNumberMax,
                `month_number_min` = :sMonthNumberMin,
                `payment_amount` = :sPaymentAmount,
                `payment_firstday` = :sPaymentFirstday,
                `payment_lastrate` = :sPaymentLastrate,
                `rate_min_longrun` = :sRateMinLongrun,
                `rate_min_normal` = :sRateMinNormal,
                `service_charge` = :sServiceCharge,
                `tx_limit_elv_max` = :sTxLimitElvMax,
                `tx_limit_elv_min` = :sTxLimitElvMin,
                `tx_limit_installment_max` = :sTxLimitInstallmentMax,
                `tx_limit_installment_min` = :sTxLimitInstallmentMin,
                `tx_limit_invoice_max` = :sTxLimitInvoiceMax,
                `tx_limit_invoice_min` = :sTxLimitInvoiceMin,
                `tx_limit_prepayment_max` = :sTxLimitPrepaymentMax,
                `txLimitPrepaymentMin` = :sTxLimitPrepaymentMin,
                `valid_payment_firstdays` = :sValidPaymentFirstdays
            WHERE 
                OXID = :sOxid
        ";
        $aParams = [
            'sMerchantName' => $aResponse['add_paydata[merchant-name]'],
            'sMerchantStatus' => $aResponse['add_paydata[merchant-status]'],
            'sShopName' => $aResponse['add_paydata[shop-name]'],
            'sName' => $aResponse['add_paydata[name]'],
            'sType' => $aResponse['add_paydata[type]'],
            'sActivationStatusElv' => $aResponse['add_paydata[activation-status-elv]'],
            'sActivationStatusInstallment' => $aResponse['add_paydata[activation-status-installment]'],
            'sActivationStatusInvoice' => $aResponse['add_paydata[activation-status-invoice]'],
            'sActivationStatusPrepayment' => $aResponse['add_paydata[activation-status-prepayment]'],
            'sAmountMinLongrun' => $aResponse['add_paydata[amount-min-longrun]'],
            'sB2bPqFull' => $aResponse['add_paydata[b2b-PQ-full]'],
            'sB2bPqLight' => $aResponse['add_paydata[b2b-PQ-light]'] ?? '',
            'sB2bElv' => $aResponse['add_paydata[b2b-elv]'],
            'sB2bInstallment' => $aResponse['add_paydata[b2b-installment]'],
            'sB2bInvoice' => $aResponse['add_paydata[b2b-invoice]'],
            'sB2bPrepayment' => $aResponse['add_paydata[b2b-prepayment]'],
            'sCountryCodeBilling' => $aResponse['add_paydata[country-code-billing]'],
            'sCountryCodeDelivery' => $aResponse['add_paydata[country-code-delivery]'],
            'sDeliveryAddressPqFull' => $aResponse['add_paydata[delivery-address-PQ-full]'],
            'sDeliveryAddressPqLight' => $aResponse['add_paydata[delivery-address-PQ-light]'] ?? '',
            'sDeliveryAddressElv' => $aResponse['add_paydata[delivery-address-elv]'],
            'sDeliveryAddressInstallment' => $aResponse['add_paydata[delivery-address-installment]'],
            'sDeliveryAddressInvoice' => $aResponse['add_paydata[delivery-address-invoice]'],
            'sDeliveryAddressPrepayment' => $aResponse['add_paydata[delivery-address-prepayment]'],
            'sDeviceFingerprintSnippetId' => $aResponse['add_paydata[device-fingerprint-snippet-id]'] ?? '',
            'sEligibilityDeviceFingerprint' => $aResponse['add_paydata[eligibility-device-fingerprint]'] ?? '',
            'sEligibilityRatepayElv' => $aResponse['add_paydata[eligibility-ratepay-elv]'],
            'sEligibilityRatepayInstallment' => $aResponse['add_paydata[eligibility-ratepay-installment]'],
            'sEligibilityRatepayInvoice' => $aResponse['add_paydata[eligibility-ratepay-invoice]'],
            'sEligibilityRatepayPqFull' => $aResponse['add_paydata[eligibility-ratepay-pq-full]'],
            'sEligibilityRatepayPqLight' => $aResponse['add_paydata[eligibility-ratepay-pq-light]'] ?? '',
            'sEligibilityRatepayPrepayment' => $aResponse['add_paydata[eligibility-ratepay-prepayment]'],
            'sInterestRateMerchantTowardsBank' => $aResponse['add_paydata[interest-rate-merchant-towards-bank]'],
            'sInterestrateDefault' => $aResponse['add_paydata[interestrate-default]'],
            'sInterestrateMax' => $aResponse['add_paydata[interestrate-max]'],
            'sInterestrateMin' => $aResponse['add_paydata[interestrate-min]'],
            'sMinDifferenceDueday' => $aResponse['add_paydata[min-difference-dueday]'],
            'sMonthAllowed' => $aResponse['add_paydata[month-allowed]'],
            'sMonthLongrun' => $aResponse['add_paydata[month-longrun]'],
            'sMonthNumberMax' => $aResponse['add_paydata[month-number-max]'],
            'sMonthNumberMin' => $aResponse['add_paydata[month-number-min]'],
            'sPaymentAmount' => $aResponse['add_paydata[payment-amount]'],
            'sPaymentFirstday' => $aResponse['add_paydata[payment-firstday]'],
            'sPaymentLastrate' => $aResponse['add_paydata[payment-lastrate]'],
            'sRateMinLongrun' => $aResponse['add_paydata[rate-min-longrun]'],
            'sRateMinNormal' => $aResponse['add_paydata[rate-min-normal]'],
            'sServiceCharge' => $aResponse['add_paydata[service-charge]'],
            'sTxLimitElvMax' => $aResponse['add_paydata[tx-limit-elv-max]'],
            'sTxLimitElvMin' => $aResponse['add_paydata[tx-limit-elv-min]'],
            'sTxLimitInstallmentMax' => $aResponse['add_paydata[tx-limit-installment-max]'],
            'sTxLimitInstallmentMin' => $aResponse['add_paydata[tx-limit-installment-min]'],
            'sTxLimitInvoiceMax' => $aResponse['add_paydata[tx-limit-invoice-max]'],
            'sTxLimitInvoiceMin' => $aResponse['add_paydata[tx-limit-invoice-min]'],
            'sTxLimitPrepaymentMax' => $aResponse['add_paydata[tx-limit-prepayment-max]'],
            'sTxLimitPrepaymentMin' => $aResponse['add_paydata[tx-limit-prepayment-min]'],
            'sValidPaymentFirstdays' => $aResponse['add_paydata[valid-payment-firstdays]'],
            'sOxid' => $sOxid
        ];

        $this->_oFcPoDb->executeStatement($sQuery, $aParams);
    }

    /**
     * Returns an array with Ratepay profiles
     *
     * @param string|null $sPaymentId (optional)
     * @return array<int|string, mixed>
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoGetRatePayProfiles(string $sPaymentId = null): array
    {
        $aReturn = [];

        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        $oQuery
            ->select('*')
            ->from('fcporatepay');

        if (is_string($sPaymentId)) {
            $oQuery
                ->where('OXPAYMENTID = :sPaymentId')
                ->setParameter('sPaymentId', $sPaymentId);
        }

        $aRatePayProfiles = $oQuery->execute()->fetchAllAssociative();

        foreach ($aRatePayProfiles as $aRatePayProfile) {
            $sOxid = $aRatePayProfile['OXID'];
            $aReturn[$sOxid] = $aRatePayProfile;
        }

        return $aReturn;
    }

    /**
     * Add Ratepay shop
     *
     * @return void
     * @throws DatabaseErrorException
     */
    public function fcpoAddRatePayProfile(): void
    {
        $oUtilsObject = $this->_oFcPoHelper->fcpoGetUtilsObject();
        $sNewOxid = $oUtilsObject->generateUId();
        $sQuery = "
            INSERT INTO fcporatepay 
            (
                `OXID`,
                `OXPAYMENTID`,
                `shopid`,
                `merchant_name`,
                `merchant_status`,
                `shop_name`,
                `name`,
                `currency`,
                `type`,
                `activation_status_elv`,
                `activation_status_installment`,
                `activation_status_invoice`,
                `activation_status_prepayment`,
                `amount_min_longrun`,
                `b2b_pq_full`,
                `b2b_pq_light`,
                `b2b_elv`,
                `b2b_installment`,
                `b2b_invoice`,
                `b2b_prepayment`,
                `country_code_billing`,
                `country_code_delivery`,
                `delivery_address_pq_full`,
                `delivery_address_pq_light`,
                `delivery_address_elv`,
                `delivery_address_installment`,
                `delivery_address_invoice`,
                `delivery_address_prepayment`,
                `device_fingerprint_snippet_id`,
                `eligibility_device_fingerprint`,
                `eligibility_ratepay_elv`,
                `eligibility_ratepay_installment`,
                `eligibility_ratepay_invoice`,
                `eligibility_ratepay_pq_full`,
                `eligibility_ratepay_pq_light`,
                `eligibility_ratepay_prepayment`,
                `interest_rate_merchant_towards_bank`,
                `interestrate_default`,
                `interestrate_max`,
                `interestrate_min`,
                `min_difference_dueday`,
                `month_allowed`,
                `month_longrun`,
                `month_number_max`,
                `month_number_min`,
                `payment_amount`,
                `payment_firstday`,
                `payment_lastrate`,
                `rate_min_longrun`,
                `rate_min_normal`,
                `service_charge`,
                `tx_limit_elv_max`,
                `tx_limit_elv_min`,
                `tx_limit_installment_max`,
                `tx_limit_installment_min`,
                `tx_limit_invoice_max`,
                `tx_limit_invoice_min`,
                `tx_limit_prepayment_max`,
                `txLimitPrepaymentMin`,
                `valid_payment_firstdays`
            ) 
            VALUES 
            (
                :sNewOxid, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
            )
        ";
        $this->_oFcPoDb->executeStatement($sQuery, [
            'sNewOxid' => $sNewOxid,
        ]);
    }

    /**
     * Returns matching profiledata by giving paymentid
     *
     * @param string $sPaymentId
     * @return array
     * @throws DatabaseConnectionException
     */
    public function fcpoGetProfileDataByPaymentId(string $sPaymentId): array
    {
        $sQuery = "SELECT * FROM fcporatepay WHERE OXPAYMENTID = :sPaymentId LIMIT 1";
        $sOxid = $this->_oFcPoDb->fetchOne($sQuery, [
            'sPaymentId' => $sPaymentId,
        ]);
        $aProfile = [];
        if ($sOxid) {
            $aProfile = $this->fcpoGetProfileData($sOxid);
        }

        return $aProfile;
    }

    /**
     * Helper method that returns field-names of ratepay-table
     *
     * @return array
     * @throws DatabaseConnectionException
     */
    public function fcpoGetFields(): array
    {
        $sQuery = "SHOW FIELDS FROM fcporatepay";
        $aRow = $this->_oFcPoDb->fetchAssociative($sQuery);
        $aReturn = [];

        if (count($aRow) > 0) {
            $aReturn = $aRow;
        }
        return $aReturn;
    }

}
