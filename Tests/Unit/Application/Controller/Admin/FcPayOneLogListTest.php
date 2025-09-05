<?php

namespace Fatchip\PayOne\Tests\Unit\Application\Controller\Admin;

use Fatchip\PayOne\Application\Controller\Admin\FcPayOneLogList;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Tests\Unit\ConfigUnitTestCase;
use OxidEsales\Eshop\Application\Controller\Admin\AdminListController;
use OxidEsales\Eshop\Core\Model\ListModel;

class FcPayOneLogListTest extends ConfigUnitTestCase
{
    public function testGetPortalId()
    {
        $oFcPayOneLogList = new FcPayOneLogList();

        $oMockConfig = $this->getMockBuilder('oxConfig')->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneLogList, '_oFcPoHelper', $oFcPoHelper);

        $sExpected = 'someValue';

        $this->assertEquals($sExpected, $oFcPayOneLogList->getPortalId());
    }

    public function testGetSubAccountId()
    {
        $oFcPayOneLogList = new FcPayOneLogList();

        $oMockConfig = $this->getMockBuilder('oxConfig')->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn('someValue');

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $this->invokeSetAttribute($oFcPayOneLogList, '_oFcPoHelper', $oFcPoHelper);

        $sExpected = 'someValue';

        $this->assertEquals($sExpected, $oFcPayOneLogList->getSubAccountId());
    }

    public function testPrepareWhereQuery()
    {
        $oFcPayOneLogList = $this->getMockBuilder(FcPayOneLogList::class)->disableOriginalConstructor()->getMock();
        $oFcPayOneLogList->method('getSubAccountId')->willReturn('mysubaccountid');
        $oFcPayOneLogList->method('getPortalId')->willReturn('myportalid');
        $oFcPayOneLogList->method('getSecInvoicePortalId')->willReturn('mysecinvportalid');
        $oFcPayOneLogList->method('getBNPLPortalId')->willReturn('mybnplportalid');

        $sExpectString = " AND fcpotransactionstatus.fcpo_portalid IN ('myportalid','mysecinvportalid','mybnplportalid') AND fcpotransactionstatus.fcpo_aid = 'mysubaccountid' ";

        $this->assertEquals($sExpectString, $this->invokeMethod($oFcPayOneLogList, '_prepareWhereQuery', array(array(),'')));
    }

    public function testGetListFilter()
    {
        $oFcPayOneLogList = new FcPayOneLogList();

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(['someValue']);
        $this->invokeSetAttribute($oFcPayOneLogList, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneLogList, '_aListFilter', null);

        $aExpect = ['someValue'];

        $this->assertEquals($aExpect, $oFcPayOneLogList->getListFilter());
    }

    public function testGetListSorting()
    {
        $oMockListObject = $this->getMockBuilder(ListModel::class)->disableOriginalConstructor()->getMock();
        $oMockAdminListObject = $this->getMockBuilder(AdminListController::class)->disableOriginalConstructor()->getMock();
        $oMockAdminListObject->method('getItemListBaseObject')->willReturn($oMockListObject);

        $oFcPayOneLogList = $this->getMockBuilder(FcPayOneLogList::class)
            ->setMethods(['getItemListBaseObject'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneLogList->method('getItemListBaseObject')->willReturn($oMockAdminListObject);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(false);
        $this->invokeSetAttribute($oFcPayOneLogList, '_oFcPoHelper', $oFcPoHelper);
        $this->invokeSetAttribute($oFcPayOneLogList, '_aCurrSorting', null);
        $this->invokeSetAttribute($oFcPayOneLogList, '_sDefSortField', 'sortValue');

        $aExpect[''] = array('sortValue'=>'asc');

        $this->assertEquals($aExpect, $oFcPayOneLogList->getListSorting());
    }

    public function testFcGetInputName()
    {
        $oFcPayOneLogList = new FcPayOneLogList();

        $sExpect = 'where[Table][Field]';

        $this->assertEquals($sExpect, $oFcPayOneLogList->fcGetInputName('Table', 'Field'));
    }

    public function testFcGetWhereValue_emptyListFilter()
    {
        $aWhere = [];
        $oFcPayOneLogList = $this->getMockBuilder(FcPayOneLogList::class)
            ->setMethods(['getListFilter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneLogList->method('getListFilter')->willReturn($aWhere);

        $sExpect = '';

        $this->assertEquals($sExpect, $oFcPayOneLogList->fcGetWhereValue('Table', 'Field'));
    }

    public function testFcGetWhereValue_filledListFilter()
    {
        $aWhere = ['Table' => ['Field' => 'someValue']];
        $oFcPayOneLogList = $this->getMockBuilder(FcPayOneLogList::class)
            ->setMethods(['getListFilter'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneLogList->method('getListFilter')->willReturn($aWhere);

        $sExpect = 'someValue';

        $this->assertEquals($sExpect, $oFcPayOneLogList->fcGetWhereValue('Table', 'Field'));
    }

    public function testFcGetSortingJavascript()
    {
        $oFcPayOneLogList = new FcPayOneLogList();

        $sExpect = "Javascript:top.oxid.admin.setSorting( document.search, 'Table', 'Field', 'asc');document.search.submit();";

        $this->assertEquals($sExpect, $oFcPayOneLogList->fcGetSortingJavascript('Table', 'Field'));
    }
}
