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
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Core\Database\Adapter\DatabaseInterface;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Exception\DatabaseConnectionException;
use OxidEsales\Eshop\Core\Exception\DatabaseErrorException;
use OxidEsales\Eshop\Core\Model\BaseModel;
use stdClass;

/**
 * Error mapping model
 *
 * @author andre
 */
class FcPoErrorMapping extends BaseModel
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
     * Requests database for existing mappings and returns an array of mapping objects
     *
     * @param string $sType
     * @return stdClass[]
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoGetExistingMappings(string $sType = 'general'): array
    {
        $aMappings = [];

        $blIsValidType = $this->_fcpoValidateMappingType($sType);

        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        $oQuery
            ->select('oxid', 'fcpo_error_code', 'fcpo_lang_id', 'fcpo_mapped_message')
            ->from('fcpoerrormapping');
        if ($blIsValidType) {
            $oQuery
                ->where('fcpo_error_type = :sType')
                ->setParameter('sType', $sType);
        }
        $oQuery->orderBy('oxid', 'ASC');

        $aRows = $oQuery->execute()->fetchAllAssociative();
        foreach ($aRows as $aRow) {
            // collect data
            $sOxid = $aRow['oxid'];
            $sErrorCode = $aRow['fcpo_error_code'];
            $sLangId = $aRow['fcpo_lang_id'];
            $sMappedMessage = $aRow['fcpo_mapped_message'];

            // create object
            $oMapping = new stdClass();
            $oMapping->sOxid = $sOxid;
            $oMapping->sErrorCode = $sErrorCode;
            $oMapping->sLangId = $sLangId;
            $oMapping->sMappedMessage = $sMappedMessage;
            $aMappings[] = $oMapping;
        }

        return $aMappings;
    }

    /**
     * Checks if the submitted type is valid
     *
     * @param string $sType
     * @return bool
     */
    protected function _fcpoValidateMappingType(string $sType): string
    {
        $aValidTypes = ['general', 'iframe'];

        return in_array($sType, $aValidTypes);
    }

    /**
     * Extracts all error codes from xml file adn returns them as array
     *
     * @param string $sType
     * @return array
     * @throws Exception
     */
    public function fcpoGetAvailableErrorCodes(string $sType = 'general'): array
    {
        $mReturn = [];
        $sErrorXmlPath = false;
        if ($sType == 'general') {
            $sErrorXmlPath = VENDOR_PATH . "payone-gmbh/oxid-7/payoneerrors.xml";
            $sErrorXmlPath = str_replace('//', '/', $sErrorXmlPath);
        } elseif ($sType == 'iframe') {
            $sErrorXmlPath = VENDOR_PATH . "payone-gmbh/oxid-7/iframeerrors.xml";
            $sErrorXmlPath = str_replace('//', '/', $sErrorXmlPath);
        }

        if (file_exists($sErrorXmlPath)) {
            try {
                $oXml = simplexml_load_file($sErrorXmlPath);
                $mReturn = $this->_fcpoParseXml($oXml);
            } catch (Exception $ex) {
                throw $ex;
            }
        }

        return $mReturn;
    }

    /**
     * Parses and formats essential information, so it can be passed into frontend
     *
     * @param object $oXml
     * @return stdClass[]
     */
    protected function _fcpoParseXml(object $oXml): array
    {
        $oUBase = $this->_oFcPoHelper->getFactoryObject(FrontendController::class);
        $sAbbr = $oUBase->getActiveLangAbbr();
        $sMessageEntry = "error_message_" . $sAbbr;
        $aEntries = [];

        foreach ($oXml->entry as $oXmlEntry) {
            $sErrorCode = (string)$oXmlEntry->error_code;
            $sErrorMessage = (string)$oXmlEntry->$sMessageEntry;
            if (empty($sErrorCode)) {
                continue;
            }

            $oEntry = new stdClass();
            $oEntry->sErrorCode = $sErrorCode;
            $oEntry->sErrorMessage = $sErrorMessage;

            $aEntries[] = $oEntry;
        }

        return $aEntries;
    }

    /**
     * Updates current set of mappings into database
     *
     * @param array $aMappings
     * @param string $sType
     * @throws DatabaseConnectionException
     * @throws DatabaseErrorException
     */
    public function fcpoUpdateMappings(array $aMappings, string $sType): void
    {
        // iterate through mappings
        foreach ($aMappings as $sMappingId => $aData) {
            $oQuery = $this->_fcpoGetQuery($sMappingId, $aData, $sType);
            $oQuery->execute();
        }
    }

    /**
     * Returns the matching query for updating/adding data
     *
     * @param string $sMappingId
     * @param array $aData
     * @param string $sType
     * @return QueryBuilder
     * @throws DatabaseConnectionException
     */
    protected function _fcpoGetQuery(string $sMappingId, array $aData, string $sType): QueryBuilder
    {
        if (array_key_exists('delete', $aData)) {
            $oQuery = $this->_oFcPoDb->createQueryBuilder();
            $oQuery
                ->delete('fcpoerrormapping')
                ->where('oxid = :sOxid')
                ->setParameter('sOxid', $sMappingId);
        } else {
            $oQuery = $this->_fcpoGetUpdateQuery($sMappingId, $aData, $sType);
        }

        return $oQuery;
    }

    /**
     * Returns whether an insert or update query, depending on data
     *
     * @param string $sMappingId
     * @param array $aData
     * @param string $sType
     * @return QueryBuilder
     */
    protected function _fcpoGetUpdateQuery(string $sMappingId, array $aData, string $sType): QueryBuilder
    {
        $blValidNewEntry = $this->_fcpoIsValidNewEntry($sMappingId, $aData['sErrorCode'], $aData['sLangId'], $aData['sMappedMessage']);

        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        if ($blValidNewEntry) {
            $oQuery
                ->insert('fcpoerrormapping')
                ->values(
                    [
                        'fcpo_error_code' => ':sErrorCode',
                        'fcpo_lang_id' => ':sLangId',
                        'fcpo_mapped_message' => ':sMappedMessage',
                        'fcpo_error_type' => ':sType'
                    ]
                )
                ->setParameters(
                    [
                        'sErrorCode' => $aData['sErrorCode'],
                        'sLangId' => $aData['sLangId'],
                        'sMappedMessage' => $aData['sMappedMessage'],
                        'sType' => $sType
                    ]
                );
        } else {
            $oQuery
                ->update('fcpoerrormapping')
                ->set('fcpo_error_code', ':sErrorCode')
                ->set('fcpo_lang_id', ':sLangId')
                ->set('fcpo_mapped_message', ':sMappedMessage')
                ->set('fcpo_error_type', ':sType')
                ->where('oxid = :sOxid')
                ->setParameters(
                    [
                        'sErrorCode' => $aData['sErrorCode'],
                        'sLangId' => $aData['sLangId'],
                        'sMappedMessage' => $aData['sMappedMessage'],
                        'sType' => $sType,
                        'sOxid' => $sMappingId
                    ]
                );
        }

        return $oQuery;
    }

    /**
     * Checks if current entry is new and complete
     *
     * @param string $sMappingId
     * @param string $sErrorCode
     * @param string $sLangId
     * @param string $sMappedMessage
     * @return bool
     */
    protected function _fcpoIsValidNewEntry(string $sMappingId, string $sErrorCode, string $sLangId, string $sMappedMessage): bool
    {
        $blComplete = (!empty($sPayoneStatus) || !empty($sLangId) || !empty($sMappedMessage));

        return $sMappingId == 'new' && $blComplete;
    }

    /**
     * Fetches mapped error message by error code
     *
     * @param string $sErrorCode
     * @return string
     */
    public function fcpoFetchMappedErrorMessage(string $sErrorCode): string
    {
        $oUBase = $this->_oFcPoHelper->getFactoryObject(FrontendController::class);
        $oLang = $this->_oFcPoHelper->fcpoGetLang();
        $sAbbr = $oUBase->getActiveLangAbbr();
        $aLanguages = $oLang->getLanguageArray(null, true, true);
        $sLangId = false;
        foreach ($aLanguages as $aLanguage) {
            if ($aLanguage->abbr == $sAbbr) {
                $sLangId = $aLanguage->id;
            }
        }

        $sMappedMessage = '';
        if ($sLangId) {
            $oQuery = $this->_fcpoGetSearchQuery($sErrorCode, $sLangId);
            $sMappedMessage = $oQuery->execute()->fetchOne();
        }

        return $sMappedMessage;
    }

    /**
     * Returns Query for searching a certain mapping
     *
     * @param string $sErrorCode
     * @param string $sLangId
     * @return QueryBuilder
     */
    protected function _fcpoGetSearchQuery(string $sErrorCode, string $sLangId): QueryBuilder
    {
        $oQuery = $this->_oFcPoDb->createQueryBuilder();
        
        $oQuery
            ->select('fcpo_mapped_message')
            ->from('fcpoerrormapping')
            ->where('fcpo_error_code = :sErrorCode')
            ->where('fcpo_lang_id = :sLangId')
            ->setParameter('sErrorCode', $sErrorCode)
            ->setParameter('sLangId', $sLangId)
            ->setMaxResults(1);
        
        return $oQuery;
    }

    /**
     * Converts a simplexml object into array
     *
     * @param object $oXml
     * @param array $aOut
     * @return array
     */
    protected function _fcpoXml2Array(object $oXml, array $aOut = []): array
    {
        foreach ((array)$oXml as $iIndex => $node) {
            $aOut[$iIndex] = (is_object($node)) ? $this->_fcpoXml2Array($node) : $node;
        }

        return $aOut;
    }

}
