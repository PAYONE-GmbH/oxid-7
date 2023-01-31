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
 * @link      http://www.payone.de
 * @copyright (C) Payone GmbH
 * @version   OXID eShop CE
 */

$sMetadataVersion = '2.1';

$aModule = [
    'id'            => 'fcpayone',
    'title'         => 'PAYONE Payment für OXID eShop',
    'description'   => 'Sie suchen nach der optimalen Payment-Lösung für Ihren Online-Shop?<br><br>
                        PAYONE bietet Unternehmenslösungen zur automatisierten und ganzheitlichen Abwicklung aller Zahlungsprozesse im E-Commerce. 
                        Der Payment Service Provider ist ein Unternehmen der Sparkassen-Finanzgruppe und von der Bundesanstalt für Finanzdienstleistungsaufsicht als Zahlungsinstitut zugelassen. 
                        Das Leistungsspektrum umfasst die Akzeptanz und Abwicklung nationaler und internationaler Zahlungsarten sowie alle Zahlungsdienstleistungen. 
                        Standardisierte Schnittstellen und Extensions erlauben eine einfache Integration in bestehende E-Commerce und IT-Systeme bei höchsten Sicherheitsstandards.<br><br>
                        Hier finden Sie weitere Informationen zum PAYONE Payment-Modul für OXID eShop: 
                        <a href="https://www.payone.de/plattform-integration/extensions/oxid/" style="color:darkblue;text-decoration: underline;" title="PAYONE Homepage" target="_blank">
                            https://www.payone.de
                        </a>',
    'thumbnail'     => 'picture.gif',
    'version'       => '1.5.0',
    'author'        => 'FATCHIP GmbH',
    'email'         => 'kontakt@fatchip.de',
    'url'           => 'https://wiki.fatchip.de/public/faqpayone',
    'extend'        => [
        // controllers admin
        OxidEsales\Eshop\Application\Controller\Admin\PaymentMain::class    => Fatchip\PayOne\Application\Controller\Admin\FcPayOnePaymentMain::class,
        // controllers
        OxidEsales\Eshop\Application\Controller\BasketController::class     => Fatchip\PayOne\Application\Controller\FcPayOneBasketView::class,
        OxidEsales\Eshop\Application\Controller\UserController::class       => Fatchip\PayOne\Application\Controller\FcPayOneUserView::class,
        OxidEsales\Eshop\Application\Controller\OrderController::class      => Fatchip\PayOne\Application\Controller\FcPayOneOrderView::class,
        OxidEsales\Eshop\Application\Controller\PaymentController::class    => Fatchip\PayOne\Application\Controller\FcPayOnePaymentView::class,
        OxidEsales\Eshop\Application\Controller\ThankYouController::class   => Fatchip\PayOne\Application\Controller\FcPayOneThankYouView::class,
        // models
        OxidEsales\Eshop\Application\Model\Basket::class                    => Fatchip\PayOne\Application\Model\FcPayOneBasket::class,
        OxidEsales\Eshop\Application\Model\BasketItem::class                => Fatchip\PayOne\Application\Model\FcPayOneBasketItem::class,
        OxidEsales\Eshop\Application\Model\Order::class                     => Fatchip\PayOne\Application\Model\FcPayOneOrder::class,
        OxidEsales\Eshop\Application\Model\OrderArticle::class              => Fatchip\PayOne\Application\Model\FcPayOneOrderArticle::class,
        OxidEsales\Eshop\Application\Model\Payment::class                   => Fatchip\PayOne\Application\Model\FcPayOnePayment::class,
        OxidEsales\Eshop\Application\Model\PaymentGateway::class            => Fatchip\PayOne\Application\Model\FcPayOnePaymentGateway::class,
        OxidEsales\Eshop\Application\Model\User::class                      => Fatchip\PayOne\Application\Model\FcPayOneUser::class,
        OxidEsales\Eshop\Application\Model\Address::class                   => Fatchip\PayOne\Application\Model\FcPayOneAddress::class,
        // core
        OxidEsales\Eshop\Core\ViewConfig::class                             => Fatchip\PayOne\Core\FcPayOneViewConf::class
    ],
    'controllers'         => [
        // controllers -> admin
        'FcPayOneAdminView'                => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminView::class,
        'FcPayOneAdminDetails'             => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminDetails::class,
        'FcPayOneAdminList'                => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminList::class,
        'FcPayOneMainAjax'                 => Fatchip\PayOne\Application\Controller\Admin\FcPayOneMainAjax::class,
        'FcPayOneAdmin'                    => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdmin::class,
        'FcPayOneApiLog'                   => Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLog::class,
        'FcPayOneApiLogList'               => Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogList::class,
        'FcPayOneApiLogMain'               => Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogMain::class,
        'FcPayOneBoni'                     => Fatchip\PayOne\Application\Controller\Admin\FcPayOneBoni::class,
        'FcPayOneBoniList'                 => Fatchip\PayOne\Application\Controller\Admin\FcPayOneBoniList::class,
        'FcPayOneBoniMain'                 => Fatchip\PayOne\Application\Controller\Admin\FcPayOneBoniMain::class,
        'FcPayOneList'                     => Fatchip\PayOne\Application\Controller\Admin\FcPayOneList::class,
        'FcPayOneLog'                      => Fatchip\PayOne\Application\Controller\Admin\FcPayOneLog::class,
        'FcPayOneLogList'                  => Fatchip\PayOne\Application\Controller\Admin\FcPayOneLogList::class,
        'FcPayOneMain'                     => Fatchip\PayOne\Application\Controller\Admin\FcPayOneMain::class,
        'FcPayOneOrder'                    => Fatchip\PayOne\Application\Controller\Admin\FcPayOneOrder::class,
        'FcPayOneProtocol'                 => Fatchip\PayOne\Application\Controller\Admin\FcPayOneProtocol::class,
        'FcPayOneStatusForwarding'         => Fatchip\PayOne\Application\Controller\Admin\FcPayOneStatusForwarding::class,
        'FcPayOneStatusMapping'            => Fatchip\PayOne\Application\Controller\Admin\FcPayOneStatusMapping::class,
        'FcPayOneErrorMapping'             => Fatchip\PayOne\Application\Controller\Admin\FcPayOneErrorMapping::class,
        // Controller
        'FcPayOneIframe'                    => Fatchip\PayOne\Application\Controller\FcPayOneIframe::class,
        // Model
        'FcPoUserFlag'                      => Fatchip\PayOne\Application\Model\FcPouserflag::class,
        'FcPoRequestLog'                    => Fatchip\PayOne\Application\Model\FcPoRequestLog::class,
        'FcPoTransactionStatus'             => Fatchip\PayOne\Application\Model\FcPoTransactionStatus::class,
        'FcPoMapping'                       => Fatchip\PayOne\Application\Model\FcPoMapping::class,
        'FcPoErrorMapping'                  => Fatchip\PayOne\Application\Model\FcPoErrorMapping::class,
        'FcPoForwarding'                    => Fatchip\PayOne\Application\Model\FcPoForwarding::class,
        'FcPoConfigExport'                  => Fatchip\PayOne\Application\Model\FcPoConfigExport::class,
        'FcPoKlarna'                        => Fatchip\PayOne\Application\Model\FcPoKlarna::class,
        'FcPoPaypal'                        => Fatchip\PayOne\Application\Model\FcPoPaypal::class,
        'FcPayOneAjax'                      => Fatchip\PayOne\Application\Model\FcPayOneAjax::class,
        'FcPoRatepay'                       => Fatchip\PayOne\Application\Model\FcPoRatepay::class,
       // libs
        'FcPoHelper'                        => Fatchip\PayOne\Lib\FcPoHelper::class,
        'FcPoRequest'                       => Fatchip\PayOne\Lib\FcPoRequest::class,
        'FcPoParamSparser'                  => Fatchip\PayOne\Lib\FcPoParamSparser::class,
        // Core
        'FcPayOneEvents'                    => Fatchip\PayOne\Core\FcPayOneEvents::class,
    ],
    'events'        => [
        'onActivate'                        => Fatchip\PayOne\Core\FcPayOneEvents::class.'::onActivate',
        'onDeactivate'                      => Fatchip\PayOne\Core\FcPayOneEvents::class.'::onDeactivate',
    ],
   /* 'blocks'        => [
        [
            'template' => 'layout/base.tpl',
            'block' => 'base_js',
            'file' => '/views/twig/blocks/fcpo_base_js_extend.html.twig'
        ],
        [
            'template' => 'layout/base.tpl',
            'block' => 'base_style',
            'file' => '/views/twig/blocks/fcpo_base_css_extend.tpl'
        ],
        [
            'template' => 'page/checkout/basket.tpl',
            'block' => 'checkout_basket_main',
            'file' => '/views/twig/blocks/fcpo_basket_override.tpl'
        ],
        [
            'template' => 'widget/minibasket/minibasket.tpl',
            'block' => 'widget_minibasket_total',
            'file' => '/views/twig/blocks/fcpo_minibasket_total_override.tpl',
        ],
        [
            'template' => 'page/checkout/order.tpl',
            'block' => 'checkout_order_address',
            'file' => '/views/twig/blocks/fcpo_order_override.tpl'
        ],
        [
            'template' => 'page/checkout/user.tpl',
            'block' => 'checkout_user_main',
            'file' => '/views/twig/blocks/fcpo_user_override.tpl'
        ],
        [
            'template' => '_formparams.tpl',
            'block' => 'admin_formparams',
            'file' => '/views/twig/blocks/fcpo_admin_formparams.tpl',
        ],
        [
            'template' => 'page/checkout/payment.tpl',
            'block' => 'change_payment',
            'file' => '/views/twig/blocks/fcpo_payment_override.tpl',
        ],
        [
            'template' => 'page/checkout/payment.tpl',
            'block' => 'select_payment',
            'file' => '/views/twig/blocks/fcpo_payment_select_override.tpl',
        ],
        [
            'template' => 'page/checkout/order.tpl',
            'block' => 'order_basket',
            'file' => '/views/twig/blocks/fcpo_order_basket_override.tpl',
        ],
        [
            'template' => 'page/checkout/order.tpl',
            'block' => 'checkout_order_errors',
            'file' => '/views/twig/blocks/fcpo_order_checkout_order_errors.tpl'
        ],
        [
            'template' => 'page/checkout/thankyou.tpl',
            'block' => 'checkout_thankyou_proceed',
            'file' => '/views/twig/blocks/fcpo_thankyou_checkout_thankyou.tpl',
        ],
        [
            'template' => 'email/html/order_cust.tpl',
            'block' => 'email_html_order_cust_paymentinfo',
            'file' => '/views/twig/blocks/fcpo_email_html_order_cust_paymentinfo.tpl',
        ],
        [
            'template' => 'email/plain/order_cust.tpl',
            'block' => 'email_plain_order_cust_paymentinfo',
            'file' => '/views/twig/blocks/fcpo_email_plain_order_cust_paymentinfo.tpl',
        ],
        [
            'template' => 'order_list.tpl',
            'block' => 'admin_order_list_colgroup',
            'file' => '/views/twig/blocks/fcpo_admin_order_list_colgroup.tpl',
        ],
        [
            'template' => 'order_list.tpl',
            'block' => 'admin_order_list_filter',
            'file' => '/views/twig/blocks/fcpo_admin_order_list_filter.tpl',
        ],
        [
            'template' => 'order_list.tpl',
            'block' => 'admin_order_list_sorting',
            'file' => '/views/twig/blocks/fcpo_admin_order_list_sorting.tpl',
        ],
        [
            'template' => 'order_list.tpl',
            'block' => 'admin_order_list_item',
            'file' => '/views/twig/blocks/fcpo_admin_order_list_item.tpl',
        ],
        [
            'template' => 'payment_list.tpl',
            'block' => 'admin_payment_list_filter',
            'file' => '/views/twig/blocks/fcpo_admin_payment_list_filter.tpl',
        ],
        [
            'template' => 'payment_main.tpl',
            'block' => 'admin_payment_main_form',
            'file' => '/views/twig/blocks/fcpo_admin_payment_main_form.tpl',
        ],
        [
            'template' => 'page/checkout/basket.tpl',
            'block' => 'basket_btn_next_top',
            'file' => '/views/twig/blocks/fcpo_basket_btn_next.tpl',
        ],
        [
            'template' => 'page/checkout/basket.tpl',
            'block' => 'basket_btn_next_bottom',
            'file' => '/views/twig/blocks/fcpo_basket_btn_next_bottom.tpl',
        ],
        [
            'template' => 'page/checkout/payment.tpl',
            'block' => 'checkout_payment_errors',
            'file' => '/views/twig/blocks/fcpo_payment_errors.tpl',
        ],
        [
            'template' => 'page/checkout/basket.tpl',
            'block' => 'checkout_basket_main',
            'file' => '/views/twig/blocks/fcpo_basket_errors.tpl',
        ],
    ],*/
];

if (class_exists('\OxidEsales\Facts\Facts')) {
    $oFacts = new \OxidEsales\Facts\Facts();
    $sShopEdition = $oFacts->getEdition();
    if ($sShopEdition == \OxidEsales\Facts\Edition\EditionSelector::ENTERPRISE) {
        $aModule['blocks'][] = [
                'template' => 'roles_bemain.tpl',
                'block' => 'admin_roles_bemain_form',
                'file' => '/views/twig/blocks/fcpo_admin_roles_bemain_form.tpl',
        ];
        $aModule['extend'][OxidEsales\Eshop\Application\Controller\Admin\RolesBackendMain::class] = Fatchip\PayOne\Application\Controller\Admin\FcPayOneRolesBeMain::class;
    }
}
