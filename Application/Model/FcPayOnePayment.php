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

use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\DatabaseProvider;
use stdClass;

class FcPayOnePayment extends Payment
{

    private const A_PAYMENT_TYPES = ['fcpoinvoice',
    'fcpopayadvance',
    'fcpodebitnote',
    'fcpocashondel',
    'fcpocreditcard',
    'fcpopaypal',
    'fcpopaypal_express',
    'fcpoklarna',
    'fcpoklarna_invoice',
    'fcpoklarna_installments',
    'fcpoklarna_directdebit',
    'fcpobarzahlen',
    'fcpopaydirekt',
    'fcpopo_bill',
    'fcpopo_debitnote',
    'fcpopo_installment',
    'fcporp_bill',
    'fcpoamazonpay',
    'fcpo_secinvoice',
    'fcpopaydirekt_express',
    'fcpo_sofort',
    'fcpo_giropay',
    'fcpo_eps',
    'fcpo_pf_finance',
    'fcpo_pf_card',
    'fcpo_ideal',
    'fcpo_p24',
    'fcpo_bancontact',
    'fcporp_debitnote',
    'fcpo_alipay',
    'fcpo_trustly',
    'fcpo_wechatpay'];
    private const A_REDIRECT_PAYMENTS = ['fcpopaypal',
    'fcpopaypal_express',
    'fcpoklarna',
    'fcpoklarna_invoice',
    'fcpoklarna_installments',
    'fcpoklarna_directdebit',
    'fcpopaydirekt',
    'fcpo_sofort',
    'fcpo_giropay',
    'fcpo_eps',
    'fcpo_pf_finance',
    'fcpo_pf_card',
    'fcpo_ideal',
    'fcpo_p24',
    'fcpo_bancontact',
    'fcpo_alipay',
    'fcpo_wechatpay'];

    /*
     * Array of all payment method IDs belonging to PAYONE
     *
     * @var array
     */
    /**
     * Array of online payments
     *
     * @var string[]
     */
    private const A_ONLINE_PAYMENTS = ['fcpo_sofort',
    'fcpo_giropay',
    'fcpo_eps',
    'fcpo_pf_finance',
    'fcpo_pf_card',
    'fcpo_ideal',
    'fcpo_p24',
    'fcpo_bancontact',
    'fcpo_trustly'];
    private const A_IFRAME_PAYMENT_TYPES = [];
    private const A_FRONTEND_API_PAYMENT_TYPES = [];
    /**
     * Helper object for dealing with different shop versions
     *
     * @var object
     */
    private $_oFcpoHelper;
    /**
     * Database object instance
     *
     * @var object
     */
    private readonly DatabaseInterface $_oFcpoDb;
    private const A_PAYMENTS_NO_AUTHORIZE = ['fcpobarzahlen',
    'fcpopo_bill',
    'fcpopo_debitnote',
    'fcporp_bill', 'fcporp_debitnote'];

    /**
     * List of payments that are not foreseen to be shown as regular payment
     * selection
     *
     * @var array
     */
    private const A_EXPRESS_PAYMENTS = ['fcpomasterpass', 'fcpoamazonpay', 'fcpopaydirekt_express'];


    /**
     * init object construction
     *
     * @return null
     */
    public function __construct()
    {
        parent::__construct();
        $this->_oFcpoHelper = oxNew(FcPoHelper::class);
        $this->_oFcpoDb = DatabaseProvider::getDb();
    }

    public static function fcIsPayOneOnlinePaymentType($sPaymentId): bool
    {
        return in_array($sPaymentId, self::A_ONLINE_PAYMENTS);
    }


    public static function fcIsPayOnePaymentType($sPaymentId): bool
    {
        return in_array($sPaymentId, self::A_PAYMENT_TYPES);
    }

    public static function fcIsPayOneRedirectType($sPaymentId)
    {
        $blReturn = in_array($sPaymentId, self::A_REDIRECT_PAYMENTS);
        $oHelper = oxNew(FcPoHelper::class);

        $blDynFlaggedAsRedirectPayment =
            (bool)$oHelper->fcpoGetSessionVariable('blDynFlaggedAsRedirectPayment');
        $blUseDynamicFlag = (
            !$blReturn &&
            $blDynFlaggedAsRedirectPayment
        );

        if ($blUseDynamicFlag) {
            // overwrite static value
            $blReturn = $blDynFlaggedAsRedirectPayment;
        }

        return $blReturn;
    }

    public static function fcIsPayOneIframePaymentType($sPaymentId): bool
    {
        return in_array($sPaymentId, self::A_IFRAME_PAYMENT_TYPES);
    }

    public static function fcIsPayOneFrontendApiPaymentType($sPaymentId): bool
    {
        return in_array($sPaymentId, self::A_FRONTEND_API_PAYMENT_TYPES);
    }

    /**
     * Checks if this payment is foreseen to be shown as standard
     * payment selection
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function fcpoShowAsRegularPaymentSelection($sPaymentId = false)
    {
        $sPaymentId = $sPaymentId ?: $this->getId();

        return !in_array($sPaymentId, self::A_EXPRESS_PAYMENTS);
    }

    /**
     * Determines the operation mode ( live or test ) used in this order based on the payment (sub) method
     *
     * @param string $sType payment subtype ( Visa, MC, etc.). Default is ''
     *
     * @return bool
     */
    public function fcpoGetOperationMode($sType = '')
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $blLivemode = $this->oxpayments__fcpolivemode->value;

        if ($sType != '') {
            $sPaymentId = $this->getId();

            $aMap = ['fcpocreditcard' => $oConfig->getConfigParam('blFCPOCC' . $sType . 'Live')];

            if (array_key_exists($sPaymentId, $aMap)) {
                $blLivemode = $aMap[$sPaymentId];
            }
        }

        return ($blLivemode == true) ? 'live' : 'test';
    }

    /**
     * Adds dynvalues to the payone payment type
     *
     * @extend getDynValues
     *
     * @return array dyn values
     */
    public function getDynValues()
    {
        $aDynValues = parent::getDynValues();

        return $this->_fcGetDynValues($aDynValues);
    }

    /**
     * Adds dynvalues for debitcard payment-method
     *
     * @param array $aDynValues dynvalues
     * @return array dynvalues (might be modified)
     */
    private function _fcGetDynValues(array $aDynValues)
    {
        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        if ((bool)$oConfig->getConfigParam('sFCPOSaveBankdata') && $this->getId() == 'fcpodebitnote') {
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
        return $aDynValues;
    }

    /**
     * Returns the isoalpa of a country by offering an id
     *
     * @param string $sCountryId
     * @return string
     */
    public function fcpoGetCountryIsoAlphaById($sCountryId)
    {
        $sQuery = "SELECT oxisoalpha2 FROM oxcountry WHERE oxid = " . DatabaseProvider::getDb()->quote($sCountryId);

        return $this->_oFcpoDb->GetOne($sQuery);
    }

    /**
     * Returns the isoalpa of a country by offering an id
     *
     * @param string $sCountryId
     * @return string
     */
    public function fcpoGetCountryNameById($sCountryId)
    {
        $sQuery = "SELECT oxtitle FROM oxcountry WHERE oxid = " . DatabaseProvider::getDb()->quote($sCountryId);

        return $this->_oFcpoDb->GetOne($sQuery);
    }

    /**
     * Method assigns a certain mandate to an order
     *
     * @param string $sOrderId
     * @param string $sMandateIdentification
     */
    public function fcpoAddMandateToDb($sOrderId, $sMandateIdentification): void
    {
        $sOrderId = DatabaseProvider::getDb()->quote($sOrderId);
        $sMandateIdentification = DatabaseProvider::getDb()->quote(basename($sMandateIdentification . '.pdf'));

        $sQuery = "INSERT INTO fcpopdfmandates (OXORDERID, FCPO_FILENAME) VALUES (" . $sOrderId . ", " . $sMandateIdentification . ")";
        $this->_oFcpoDb->Execute($sQuery);
    }

    /**
     * Returns the Klarna StoreID for the current bill country
     *
     * @return string
     */
    public function fcpoGetKlarnaStoreId()
    {
        $oUser = $this->getUser();
        $sBillCountryId = $oUser->oxuser__oxcountryid->value;

        $sQuery = " SELECT 
                        b.fcpo_storeid 
                    FROM 
                        fcpopayment2country AS a
                    INNER JOIN
                        fcpoklarnastoreids AS b ON a.fcpo_type = b.oxid
                    WHERE 
                        a.fcpo_paymentid = 'KLV' AND 
                        a.fcpo_countryid = " . DatabaseProvider::getDb()->quote($sBillCountryId) . " 
                    LIMIT 1";
        $sStoreId = $this->_oFcpoDb->GetOne($sQuery);

        return $sStoreId ?: 0;
    }

    /**
     * Returns user paymentid
     *
     * @param string $sUserOxid
     * @param string $sPaymentType
     * @return mixed
     */
    public function fcpoGetUserPaymentId($sUserOxid, $sPaymentType)
    {
        $database = DatabaseProvider::getDb();
        $sQ = 'select oxpaymentid from oxorder where oxpaymenttype=' . $database->quote($sPaymentType) . ' and
                oxuserid=' . $database->quote($sUserOxid) . ' order by oxorderdate desc';

        return $this->_oFcpoDb->GetOne($sQ);
    }

    /**
     * Check database if the user is allowed to use the given payment method and re
     *
     * @param string $sSubPaymentId ID of the sub payment method ( Visa, MC, etc. )
     * @param string $sType         payment type PAYONE
     *
     * @return bool
     */
    public function isPaymentMethodAvailableToUser($sSubPaymentId, $sType, $sUserBillCountryId, $sUserDelCountryId)
    {
        $sBaseQuery = "SELECT COUNT(*) FROM fcpopayment2country WHERE fcpo_paymentid = '{$sSubPaymentId}' AND fcpo_type = '{$sType}'";
        if ($sUserDelCountryId !== false && $sUserBillCountryId != $sUserDelCountryId) {
            $sWhereCountry = "AND (fcpo_countryid = '{$sUserBillCountryId}' || fcpo_countryid = '{$sUserDelCountryId}')";
        } else {
            $sWhereCountry = "AND fcpo_countryid = '{$sUserBillCountryId}'";
        }
        $sQuery = "SELECT IF(({$sBaseQuery} LIMIT 1) > 0,IF(({$sBaseQuery} {$sWhereCountry} LIMIT 1) > 0,1,0),1)";

        return $this->_oFcpoDb->GetOne($sQuery);
    }

    /**
     * Check if a creditworthiness check has to be done
     * ( Has to be done if from boni is greater zero )
     *
     * @return bool
     */
    public function fcBoniCheckNeeded()
    {
        return $this->oxpayments__oxfromboni->value > 0;
    }

    /**
     * Returns mandate text from session if available
     *
     * @return mixed
     */
    public function fcpoGetMandateText()
    {
        $aMandate = $this->_oFcpoHelper->fcpoGetSessionVariable('fcpoMandate');

        $blMandateTextValid = (
            $aMandate &&
            array_key_exists('mandate_status', $aMandate) &&
            $aMandate['mandate_status'] == 'pending' &&
            array_key_exists('mandate_text', $aMandate)
        );

        $mReturn = false;
        if ($blMandateTextValid) {
            $mReturn = urldecode((string) $aMandate['mandate_text']);
        }

        return $mReturn;
    }

    /**
     * Returning klarna campaigns
     *
     * @param bool $blGetAll
     * @return array<int|string, mixed[]>
     */
    public function fcpoGetKlarnaCampaigns($blGetAll = false): array
    {
        $aStoreIds = [];

        $sQuery = "
            SELECT 
                oxid, 
                fcpo_campaign_code, 
                fcpo_campaign_title, 
                fcpo_campaign_language, 
                fcpo_campaign_currency
            FROM 
                 fcpoklarnacampaigns 
            ORDER BY oxid ASC";

        $aRows = $this->_oFcpoDb->getAll($sQuery);
        foreach ($aRows as $aRow) {
            $aCampaign = $this->_fcpoGetKlarnaCampaignArray($aRow);
            $blAdd = ($blGetAll) ? true : $this->_fcpoCheckKlarnaCampaignsResult($aRow[0], $aCampaign);

            if ($blAdd) {
                $aStoreIds[$aRow[0]] = $aCampaign;
            }
        }
        return $aStoreIds;
    }

    /**
     * Method returns campaign array on db request result
     *
     * @param array $aRow
     * @return array
     */
    private function _fcpoGetKlarnaCampaignArray($aRow)
    {
        $aCampaign = ['code' => $aRow[1], 'title' => $aRow[2], 'language' => unserialize($aRow[3]), 'currency' => unserialize($aRow[4])];

        $aCampaign = $this->_fcpoSetArrayDefault($aCampaign, 'language');

        return $this->_fcpoSetArrayDefault($aCampaign, 'currency');
    }

    /**
     * Sets given index to empty array if no array has been detected
     *
     * @return array
     */
    private function _fcpoSetArrayDefault(array $aCampaign, string $sIndex)
    {
        if (!is_array($aCampaign[$sIndex])) {
            $aCampaign[$sIndex] = [];
        }

        return $aCampaign;
    }

    /**
     * Method evaluates result of klarna campaign data and returns if it can be added
     *
     * @param string $sCountryOxid
     * @return boolean
     */
    private function _fcpoCheckKlarnaCampaignsResult($sCountryOxid, array $aCampaign)
    {
        $blAdd = true;

        $oConfig = $this->_oFcpoHelper->fcpoGetConfig();
        $oLang = $this->_oFcpoHelper->fcpoGetLang();
        $sCurrLanguage = $oLang->getLanguageAbbr();
        $oUser = $this->getUser();
        $sCurrCountry = $oUser->oxuser__oxcountryid->value;
        $oCurrency = $oConfig->getActShopCurrencyObject();
        $sCurrCurrency = $oCurrency->name;

        $aConnectedCountries = $this->_fcGetCountries($sCountryOxid);
        $blAdd = $this->_fcpoCheckAddCampaign($blAdd, $sCurrCountry, $aConnectedCountries);
        $blAdd = $this->_fcpoCheckAddCampaign($blAdd, $sCurrLanguage, $aCampaign['language']);

        return $this->_fcpoCheckAddCampaign($blAdd, $sCurrCurrency, $aCampaign['currency']);
    }

    /**
     * Returns countries assigned to given campaign id
     *
     * @param string $sCampaignId
     */
    private function _fcGetCountries($sCampaignId): array
    {
        $aCountries = [];

        $sQuery = "SELECT fcpo_countryid FROM fcpopayment2country WHERE fcpo_paymentid = 'KLR_{$sCampaignId}'";
        $aRows = $this->_oFcpoDb->getAll($sQuery);
        foreach ($aRows as $aRow) {
            $aCountries[] = $aRow[0];
        }

        return $aCountries;
    }

    /**
     * Sets add flag to false if conditions doesn't match
     *
     * @param string  $sNeedle
     * @param array   $aHaystack
     * @return boolean
     */
    private function _fcpoCheckAddCampaign(bool $blAdd, $sNeedle, $aHaystack)
    {
        if (!in_array($sNeedle, $aHaystack)) {
            $blAdd = false;
        }

        return $blAdd;
    }

    /**
     * Determines the operation mode ( live or test ) used for this payment based on payment or form data
     *
     * @param object $oPayment  payment object
     * @param string $aDynvalue form data
     *
     * @return string
     */
    public function fcpoGetMode($aDynvalue)
    {
        $sReturn = '';
        $sId = $this->getId();
        $blIdAffected = $sId == 'fcpocreditcard';

        if ($blIdAffected) {
            $aMap = ['fcpocreditcard' => $aDynvalue['fcpo_ccmode']];

            $sReturn = $aMap[$sId];
        }

        return $sReturn;
    }

    /**
     * Returns a list of payment types
     *
     *
     * @return \stdClass[]
     */
    public function fcpoGetPayonePaymentTypes(): array
    {
        $aPaymentTypes = [];

        $sQuery = "SELECT oxid, oxdesc FROM oxpayments WHERE fcpoispayone = 1";
        $aRows = $this->_oFcpoDb->getAll($sQuery);
        foreach ($aRows as $aRow) {
            $sOxid = $aRow['oxid'] ?? $aRow[0];
            $sDesc = $aRow['oxdesc'] ?? $aRow[1];

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
     */
    public function fcpoGetRedPayments(): string
    {
        $sPayments = '';
        $sQuery = 'SELECT oxid FROM oxpayments WHERE fcpoispayone = 1 AND oxfromboni <= 100';
        $aRows = $this->_oFcpoDb->getAll($sQuery);
        foreach ($aRows as $aRow) {
            $sPayment = $aRow[0] ?? $aRow['oxid'];
            $sPayments .= $sPayment . ',';
        }

        return rtrim($sPayments, ',');
    }

    /**
     * Returning yellow payments
     *
     *
     * @return void
     */
    public function fcpoGetYellowPayments(): string
    {
        $sPayments = '';
        $sQuery = 'SELECT oxid FROM oxpayments WHERE fcpoispayone = 1 AND oxfromboni > 100 AND oxfromboni <= 300';
        $aRows = $this->_oFcpoDb->getAll($sQuery);
        foreach ($aRows as $aRow) {
            $sPayment = $aRow[0] ?? $aRow['oxid'];
            $sPayments .= $sPayment . ',';
        }

        return rtrim($sPayments, ',');
    }

    /**
     * Public getter for checking if current payment is allowed for authorization
     *
     *
     * @return bool
     */
    public function fcpoAuthorizeAllowed()
    {
        $sPaymentId = $this->oxpayments__oxid->value;
        $blCurrentPaymentAffected = in_array($sPaymentId, self::A_PAYMENTS_NO_AUTHORIZE);

        return !$blCurrentPaymentAffected;
    }
}
