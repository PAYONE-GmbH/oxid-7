<?php

namespace Fatchip\PayOne\Tests\Unit;

use Fatchip\PayOne\Application\Model\FcPayOneBasketItem;
use Fatchip\PayOne\Lib\FcPoHelper;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Core\Config;

class FcPayOneBasketItemTest extends FcBaseUnitTestCase
{
    public function testGetArticle_Exception()
    {
        $oFcPayOneBasketItem = new FcPayOneBasketItem();

        $this->wrapExpectException('oxArticleException');

        $this->assertEquals(null, $oFcPayOneBasketItem->getArticle(true, 'someNumber'));
    }

    public function testGetArticle()
    {
        $oMockArticle = $this->getMockBuilder(Article::class)
            ->disableOriginalConstructor()->getMock();

        $oFcPayOneBasketItem = $this->getMockBuilder(FcPayOneBasketItem::class)
            ->setMethods(['_fcpoParentGetArticle'])
            ->disableOriginalConstructor()->getMock();
        $oFcPayOneBasketItem->method('_fcpoParentGetArticle')->willReturn($oMockArticle);

        $oMockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getConfigParam'])
            ->disableOriginalConstructor()->getMock();
        $oMockConfig->method('getConfigParam')->willReturn(false);

        $oFcPoHelper = $this->getMockBuilder(FcPoHelper::class)->disableOriginalConstructor()->getMock();
        $oFcPoHelper->method('fcpoGetConfig')->willReturn($oMockConfig);
        $oFcPoHelper->method('fcpoGetRequestParameter')->willReturn(true);
        $this->invokeSetAttribute($oFcPayOneBasketItem, '_oFcPoHelper', $oFcPoHelper);

        $this->assertEquals($oMockArticle, $oFcPayOneBasketItem->getArticle(true, 'someNumber'));
    }
}