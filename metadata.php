<?php

use OxidEsales\Eshop\Application\Controller\Admin\PaymentMain;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOnePaymentMain;
use OxidEsales\Eshop\Application\Controller\BasketController;
use Fatchip\PayOne\Application\Controller\FcPayOneBasketView;
use OxidEsales\Eshop\Application\Controller\UserController;
use Fatchip\PayOne\Application\Controller\FcPayOneUserView;
use OxidEsales\Eshop\Application\Controller\OrderController;
use Fatchip\PayOne\Application\Controller\FcPayOneOrderView;
use OxidEsales\Eshop\Application\Controller\PaymentController;
use Fatchip\PayOne\Application\Controller\FcPayOnePaymentView;
use OxidEsales\Eshop\Application\Controller\ThankYouController;
use Fatchip\PayOne\Application\Controller\FcPayOneThankYouView;
use OxidEsales\Eshop\Application\Model\Basket;
use Fatchip\PayOne\Application\Model\FcPayOneBasket;
use OxidEsales\Eshop\Application\Model\BasketItem;
use Fatchip\PayOne\Application\Model\FcPayOneBasketItem;
use OxidEsales\Eshop\Application\Model\Order;
use Fatchip\PayOne\Application\Model\FcPayOneOrder;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use Fatchip\PayOne\Application\Model\FcPayOneOrderArticle;
use OxidEsales\Eshop\Application\Model\Payment;
use Fatchip\PayOne\Application\Model\FcPayOnePayment;
use OxidEsales\Eshop\Application\Model\PaymentGateway;
use Fatchip\PayOne\Application\Model\FcPayOnePaymentGateway;
use OxidEsales\Eshop\Application\Model\User;
use Fatchip\PayOne\Application\Model\FcPayOneUser;
use OxidEsales\Eshop\Application\Model\Address;
use Fatchip\PayOne\Application\Model\FcPayOneAddress;
use OxidEsales\Eshop\Core\ViewConfig;
use Fatchip\PayOne\Core\FcPayOneViewConf;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminView;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminDetails;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminList;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneMainAjax;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdmin;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLog;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogList;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogMain;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneBoni;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneBoniList;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneBoniMain;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneList;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneLog;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneLogList;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneMain;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneProtocol;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneStatusForwarding;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneStatusMapping;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneErrorMapping;
use Fatchip\PayOne\Application\Controller\FcPayOneIframe;
use Fatchip\PayOne\Application\Model\FcPouserflag;
use Fatchip\PayOne\Application\Model\FcPoRequestLog;
use Fatchip\PayOne\Application\Model\FcPoTransactionStatus;
use Fatchip\PayOne\Application\Model\FcPoMapping;
use Fatchip\PayOne\Application\Model\FcPoErrorMapping;
use Fatchip\PayOne\Application\Model\FcPoForwarding;
use Fatchip\PayOne\Application\Model\FcPoConfigExport;
use Fatchip\PayOne\Application\Model\FcPoKlarna;
use Fatchip\PayOne\Application\Model\FcPoPaypal;
use Fatchip\PayOne\Application\Model\FcPayOneAjax;
use Fatchip\PayOne\Application\Model\FcPoRatepay;
use Fatchip\PayOne\Lib\FcPoHelper;
use Fatchip\PayOne\Lib\FcPoRequest;
use Fatchip\PayOne\Lib\FcPoParamSparser;
use Fatchip\PayOne\Core\FcPayOneEvents;
use OxidEsales\Eshop\Application\Controller\Admin\RolesBackendMain;
use Fatchip\PayOne\Application\Controller\Admin\FcPayOneRolesBeMain;
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

use OxidEsales\Facts\Edition\EditionSelector;
use OxidEsales\Facts\Facts;

$sMetadataVersion = '2.1';

$aModule = [
    'id' => 'fcpayone',
    'title' => 'PAYONE Payment für OXID eShop',
    'description' => 'Sie suchen nach der optimalen Payment-L&ouml;sung f&uuml;r Ihren Online-Shop?<br><br>
                        PAYONE bietet Unternehmensl&ouml;sungen zur automatisierten und ganzheitlichen Abwicklung aller Zahlungsprozesse im E-Commerce. 
                        Der Payment Service Provider ist ein Unternehmen der Sparkassen-Finanzgruppe und von der Bundesanstalt f&uuml;r Finanzdienstleistungsaufsicht als Zahlungsinstitut zugelassen. 
                        Das Leistungsspektrum umfasst die Akzeptanz und Abwicklung nationaler und internationaler Zahlungsarten sowie alle Zahlungsdienstleistungen. 
                        Standardisierte Schnittstellen und Extensions erlauben eine einfache Integration in bestehende E-Commerce und IT-Systeme bei h&ouml;chsten Sicherheitsstandards.<br><br>
                        Hier finden Sie weitere Informationen zum PAYONE Payment-Modul f&uuml;r OXID eShop: 
                        <a href="https://www.payone.de/plattform-integration/extensions/oxid/" style="color:darkblue;text-decoration: underline;" title="PAYONE Homepage" target="_blank">
                            https://www.payone.de
                        </a>',
    'thumbnail' => 'picture.gif',
    'version' => '1.5.0',
    'author' => 'FATCHIP GmbH',
    'email' => 'kontakt@fatchip.de',
    'url' => 'https://wiki.fatchip.de/public/faqpayone',
    'extend' => [
        // controllers admin
        PaymentMain::class => FcPayOnePaymentMain::class,
        // controllers
        BasketController::class => FcPayOneBasketView::class,
        UserController::class => FcPayOneUserView::class,
        OrderController::class => FcPayOneOrderView::class,
        PaymentController::class => FcPayOnePaymentView::class,
        ThankYouController::class => FcPayOneThankYouView::class,
        // models
        Basket::class => FcPayOneBasket::class,
        BasketItem::class => FcPayOneBasketItem::class,
        Order::class => FcPayOneOrder::class,
        OrderArticle::class => FcPayOneOrderArticle::class,
        Payment::class => FcPayOnePayment::class,
        PaymentGateway::class => FcPayOnePaymentGateway::class,
        User::class => FcPayOneUser::class,
        Address::class => FcPayOneAddress::class,
        // core
        ViewConfig::class => FcPayOneViewConf::class
    ],
    'controllers' => [
        // controllers -> admin
        'FcPayOneAdminView' => FcPayOneAdminView::class,
        'FcPayOneAdminDetails' => FcPayOneAdminDetails::class,
        'FcPayOneAdminList' => FcPayOneAdminList::class,
        'FcPayOneMainAjax' => FcPayOneMainAjax::class,
        'FcPayOneAdmin' => FcPayOneAdmin::class,
        'FcPayOneApiLog' => FcPayOneApiLog::class,
        'FcPayOneApiLogList' => FcPayOneApiLogList::class,
        'FcPayOneApiLogMain' => FcPayOneApiLogMain::class,
        'FcPayOneBoni' => FcPayOneBoni::class,
        'FcPayOneBoniList' => FcPayOneBoniList::class,
        'FcPayOneBoniMain' => FcPayOneBoniMain::class,
        'FcPayOneList' => FcPayOneList::class,
        'FcPayOneLog' => FcPayOneLog::class,
        'FcPayOneLogList' => FcPayOneLogList::class,
        'FcPayOneMain' => FcPayOneMain::class,
        'FcPayOneOrder' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneOrder::class,
        'FcPayOneProtocol' => FcPayOneProtocol::class,
        'FcPayOneStatusForwarding' => FcPayOneStatusForwarding::class,
        'FcPayOneStatusMapping' => FcPayOneStatusMapping::class,
        'FcPayOneErrorMapping' => FcPayOneErrorMapping::class,
        // Controller
        'FcPayOneIframe' => FcPayOneIframe::class,
        // Model
        'FcPoUserFlag' => FcPouserflag::class,
        'FcPoRequestLog' => FcPoRequestLog::class,
        'FcPoTransactionStatus' => FcPoTransactionStatus::class,
        'FcPoMapping' => FcPoMapping::class,
        'FcPoErrorMapping' => FcPoErrorMapping::class,
        'FcPoForwarding' => FcPoForwarding::class,
        'FcPoConfigExport' => FcPoConfigExport::class,
        'FcPoKlarna' => FcPoKlarna::class,
        'FcPoPaypal' => FcPoPaypal::class,
        'FcPayOneAjax' => FcPayOneAjax::class,
        'FcPoRatepay' => FcPoRatepay::class,
        // libs
        'FcPoHelper' => FcPoHelper::class,
        'FcPoRequest' => FcPoRequest::class,
        'FcPoParamSparser' => FcPoParamSparser::class,
        // Core
        'FcPayOneEvents' => FcPayOneEvents::class,
    ],
    /*'templates' => [
        // frontend
        'fcpayoneiframe.html.twig' => '/frontend/tpl/fcpayoneiframe.html.twig',
        // admin
        'fcpayone_popup_main.html.twig' => '/admin/tpl/popups/fcpayone_popup_main.html.twig',
        'fcpayone' => '/admin/tpl/fcpayone.html.twig',
        'fcpayone_apilog' => '/admin/tpl/fcpayone_apilog.html.twig',
        'fcpayone_apilog_list' => '/admin/tpl/fcpayone_apilog_list.html.twig',
        'fcpayone_apilog_main' => '/admin/tpl/fcpayone_apilog_main.html.twig',
        'fcpayone_boni.html.twig' => '/admin/tpl/fcpayone_boni.html.twig',
        'fcpayone_boni_list.html.twig' => '/admin/tpl/fcpayone_boni_list.html.twig',
        'fcpayone_boni_main.html.twig' => '/admin/tpl/fcpayone_boni_main.html.twig',
        'fcpayone_cc_preview.html.twig' => '/admin/tpl/fcpayone_cc_preview.html.twig',
        'fcpayone_list.html.twig' => '/admin/tpl/fcpayone_list.html.twig',
        'fcpayone_log.html.twig' => '/admin/tpl/fcpayone_log.html.twig',
        'fcpayone_log_list.html.twig' => '/admin/tpl/fcpayone_log_list.html.twig',
        'fcpayone_main.html.twig' => '/admin/tpl/fcpayone_main.html.twig',
        'fcpayone_order.html.twig' => '/admin/tpl/fcpayone_order.html.twig',
        'fcpayone_protocol.html.twig' => '/admin/tpl/fcpayone_protocol.html.twig',
        'fcpayone_status_forwarding.html.twig' => '/admin/tpl/fcpayone_status_forwarding.html.twig',
        'fcpayone_status_mapping.html.twig' => '/admin/tpl/fcpayone_status_mapping.html.twig',
        'fcpayone_error_mapping.html.twig' => '/admin/tpl/fcpayone_error_mapping.html.twig',
    ],*/
    'events' => [
        'onActivate' => FcPayOneEvents::class . '::onActivate',
        'onDeactivate' => FcPayOneEvents::class . '::onDeactivate',
    ],
    'blocks' => [
        [
            'template' => 'layout/base.html.twig',
            'block' => 'base_js',
            'file' => 'blocks/fcpo_base_js_extend.html.twig'
        ],
        [
            'template' => 'layout/base.html.twig',
            'block' => 'base_style',
            'file' => 'blocks/fcpo_base_css_extend.html.twig'
        ],
        [
            'template' => 'page/checkout/basket.html.twig',
            'block' => 'checkout_basket_main',
            'file' => 'blocks/fcpo_basket_override.html.twig'
        ],
        [
            'template' => 'widget/minibasket/minibasket.html.twig',
            'block' => 'widget_minibasket_total',
            'file' => 'blocks/fcpo_minibasket_total_override.html.twig',
        ],
        [
            'template' => 'page/checkout/order.html.twig',
            'block' => 'checkout_order_address',
            'file' => 'blocks/fcpo_order_override.html.twig'
        ],
        [
            'template' => 'page/checkout/user.html.twig',
            'block' => 'checkout_user_main',
            'file' => 'blocks/fcpo_user_override.html.twig'
        ],
        [
            'template' => '_formparams.html.twig',
            'block' => 'admin_formparams',
            'file' => 'blocks/fcpo_admin_formparams.html.twig',
        ],
        [
            'template' => 'page/checkout/payment.html.twig',
            'block' => 'change_payment',
            'file' => 'blocks/fcpo_payment_override.html.twig',
        ],
        [
            'template' => 'page/checkout/payment.html.twig',
            'block' => 'select_payment',
            'file' => 'blocks/fcpo_payment_select_override.html.twig',
        ],
        [
            'template' => 'page/checkout/order.html.twig',
            'block' => 'order_basket',
            'file' => 'blocks/fcpo_order_basket_override.html.twig',
        ],
        [
            'template' => 'page/checkout/order.html.twig',
            'block' => 'checkout_order_errors',
            'file' => 'blocks/fcpo_order_checkout_order_errors.html.twig'
        ],
        [
            'template' => 'page/checkout/thankyou.html.twig',
            'block' => 'checkout_thankyou_proceed',
            'file' => 'blocks/fcpo_thankyou_checkout_thankyou.html.twig',
        ],
        [
            'template' => 'email/html/order_cust.html.twig',
            'block' => 'email_html_order_cust_paymentinfo',
            'file' => 'blocks/fcpo_email_html_order_cust_paymentinfo.html.twig',
        ],
        [
            'template' => 'email/plain/order_cust.html.twig',
            'block' => 'email_plain_order_cust_paymentinfo',
            'file' => 'blocks/fcpo_email_plain_order_cust_paymentinfo.html.twig',
        ],
        [
            'template' => 'order_list.html.twig',
            'block' => 'admin_order_list_colgroup',
            'file' => 'blocks/fcpo_admin_order_list_colgroup.html.twig',
        ],
        [
            'template' => 'order_list.html.twig',
            'block' => 'admin_order_list_filter',
            'file' => 'blocks/fcpo_admin_order_list_filter.html.twig',
        ],
        [
            'template' => 'order_list.html.twig',
            'block' => 'admin_order_list_sorting',
            'file' => 'blocks/fcpo_admin_order_list_sorting.html.twig',
        ],
        [
            'template' => 'order_list.html.twig',
            'block' => 'admin_order_list_item',
            'file' => 'blocks/fcpo_admin_order_list_item.html.twig',
        ],
        [
            'template' => 'payment_list.html.twig',
            'block' => 'admin_payment_list_filter',
            'file' => 'blocks/fcpo_admin_payment_list_filter.html.twig',
        ],
        [
            'template' => 'payment_main.html.twig',
            'block' => 'admin_payment_main_form',
            'file' => 'blocks/fcpo_admin_payment_main_form.html.twig',
        ],
        [
            'template' => 'page/checkout/basket.html.twig',
            'block' => 'basket_btn_next_top',
            'file' => 'blocks/fcpo_basket_btn_next.html.twig',
        ],
        [
            'template' => 'page/checkout/basket.html.twig',
            'block' => 'basket_btn_next_bottom',
            'file' => 'blocks/fcpo_basket_btn_next_bottom.html.twig',
        ],
        [
            'template' => 'page/checkout/payment.html.twig',
            'block' => 'checkout_payment_errors',
            'file' => 'blocks/fcpo_payment_errors.html.twig',
        ],
        [
            'template' => 'page/checkout/basket.html.twig',
            'block' => 'checkout_basket_main',
            'file' => 'blocks/fcpo_basket_errors.html.twig',
        ],
    ],
];

if (class_exists('\\' . Facts::class)) {
    $oFacts = new Facts();
    $sShopEdition = $oFacts->getEdition();
    if ($sShopEdition == EditionSelector::ENTERPRISE) {
        $aModule['blocks'][] = [
            'template' => 'roles_bemain.html.twig',
            'block' => 'admin_roles_bemain_form',
            'file' => 'blocks/fcpo_admin_roles_bemain_form.html.twig',
        ];
        $aModule['extend'][RolesBackendMain::class] = FcPayOneRolesBeMain::class;
    }
}
