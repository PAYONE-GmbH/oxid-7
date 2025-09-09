<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogList;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;
use OxidEsales\Eshop\Application\Controller\Admin\AdminListController;
use OxidEsales\Eshop\Core\Model\ListModel;

class FcPayOneApiLogListTest extends ConfigUnitTestCase
{
    public function testGetPortalId()
    {
        $oFcPayOneApiLogList = new FcPayOneApiLogList();

        $oMockConfig = $this->getMockBuilder('oxConfig')->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneApiLogList, '_oFcPoHelper', $oFcPoHelper);

        $sExpected = 'someValue';

        $this->assertEquals($sExpected, $oFcPayOneApiLogList->getPortalId());
    }

    public function testGetSubAccountId()
    {
        $oFcPayOneApiLogList = new FcPayOneApiLogList();

        $oMockConfig = $this->getMockBuilder('oxConfig')->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneApiLogList, '_oFcPoHelper', $oFcPoHelper);

        $sExpected = 'someValue';

        $this->assertEquals($sExpected, $oFcPayOneApiLogList->getSubAccountId());
    }

    public function testPrepareWhereQuery()
    {
        $oFcPayOneApiLogList = $this->getMockBuilder(FcPayOneApiLogList::class)->disableOriginalConstructor()->getMock();
        $oFcPayOneApiLogList->method('getSubAccountId')->willReturn('mysubaccountid');
        $oFcPayOneApiLogList->method('getPortalId')->willReturn('myportalid');

        $sExpectString = " AND fcporequestlog.fcpo_portalid = 'myportalid' AND fcporequestlog.fcpo_aid = 'mysubaccountid' ";

        $this->assertEquals($sExpectString, $this->invokeMethod($oFcPayOneApiLogList, '_prepareWhereQuery', [[], '']));
    }

    public function testGetListFilter()
    {
        $oFcPayOneApiLogList = new FcPayOneApiLogList();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(['someValue']);
        $this->invokeSetAttribute($oFcPayOneApiLogList, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneApiLogList, '_aListFilter', null);

        $aExpect = ['someValue'];

        $this->assertEquals($aExpect, $oFcPayOneApiLogList->getListFilter());
    }

    public function testGetListSorting()
    {
        $oMockListObject = $this->getMockBuilder(ListModel::class)->disableOriginalConstructor()->getMock();
        $oMockAdminListObject = $this->getMockBuilder(AdminListController::class)->disableOriginalConstructor()->getMock();
        $oMockAdminListObject->method('getItemListBaseObject')->willReturn($oMockListObject);

        $oFcPayOneApiLogList = $this->getMockBuilder(FcPayOneApiLogList::class)
            ->setMethods(['getItemListBaseObject'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneApiLogList->method('getItemListBaseObject')->willReturn($oMockAdminListObject);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneApiLogList, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneApiLogList, '_aCurrSorting', null);
        $this->invokeSetAttribute($oFcPayOneApiLogList, '_sDefSortField', 'sortValue');

        $aExpect[''] = ['sortValue' => 'desc'];

        $this->assertEquals($aExpect, $oFcPayOneApiLogList->getListSorting());
    }

    public function testFcGetInputName()
    {
        $oFcPayOneApiLogList = new FcPayOneApiLogList();

        $sExpect = 'where[Table][Field]';

        $this->assertEquals($sExpect, $oFcPayOneApiLogList->fcGetInputName('Table', 'Field'));
    }

    public function testFcGetWhereValue_emptyListFilter()
    {
        $aWhere = [];
        $oFcPayOneApiLogList = $this->getMockBuilder(FcPayOneApiLogList::class)
            ->setMethods(['getListFilter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneApiLogList->method('getListFilter')->willReturn($aWhere);

        $sExpect = '';

        $this->assertEquals($sExpect, $oFcPayOneApiLogList->fcGetWhereValue('Table', 'Field'));
    }

    public function testFcGetWhereValue_filledListFilter()
    {
        $aWhere = ['Table' => ['Field' => 'someValue']];
        $oFcPayOneApiLogList = $this->getMockBuilder(FcPayOneApiLogList::class)
            ->setMethods(['getListFilter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneApiLogList->method('getListFilter')->willReturn($aWhere);

        $sExpect = 'someValue';

        $this->assertEquals($sExpect, $oFcPayOneApiLogList->fcGetWhereValue('Table', 'Field'));
    }

    public function testFcGetSortingJavascript()
    {
        $oFcPayOneApiLogList = new FcPayOneApiLogList();

        $sExpect = "Javascript:top.oxid.admin.setSorting( document.search, 'Table', 'Field', 'asc');document.search.submit();";

        $this->assertEquals($sExpect, $oFcPayOneApiLogList->fcGetSortingJavascript('Table', 'Field'));
    }
}
