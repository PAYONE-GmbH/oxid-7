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

use OxidEsales\Facts\Edition\EditionSelector;
use OxidEsales\Facts\Facts;

$sMetadataVersion = '2.1';

$aModule = [
    'id' => 'fcpayone',
    'title' => 'PAYONE Payment für OXID eShop',
    'description' => 'Sie suchen nach der optimalen Payment-Lösung für Ihren Online-Shop?<br><br>
                        PAYONE bietet Unternehmenslösungen zur automatisierten und ganzheitlichen Abwicklung aller Zahlungsprozesse im E-Commerce. 
                        Der Payment Service Provider ist ein Unternehmen der Sparkassen-Finanzgruppe und von der Bundesanstalt für Finanzdienstleistungsaufsicht als Zahlungsinstitut zugelassen. 
                        Das Leistungsspektrum umfasst die Akzeptanz und Abwicklung nationaler und internationaler Zahlungsarten sowie alle Zahlungsdienstleistungen. 
                        Standardisierte Schnittstellen und Extensions erlauben eine einfache Integration in bestehende E-Commerce und IT-Systeme bei höchsten Sicherheitsstandards.<br><br>
                        Hier finden Sie weitere Informationen zum PAYONE Payment-Modul für OXID eShop: 
                        <a href="https://www.payone.de/plattform-integration/extensions/oxid/" style="color:darkblue;text-decoration: underline;" title="PAYONE Homepage" target="_blank">
                            https://www.payone.de
                        </a>',
    'thumbnail' => 'picture.gif',
    'version' => '1.0.0',
    'author' => 'FATCHIP GmbH',
    'email' => 'kontakt@fatchip.de',
    'url' => 'https://wiki.fatchip.de/public/faqpayone',
    'extend' => [
        // controllers admin
        OxidEsales\Eshop\Application\Controller\Admin\PaymentMain::class => Fatchip\PayOne\Application\Controller\Admin\FcPayOnePaymentMain::class,
        // controllers
        OxidEsales\Eshop\Application\Controller\BasketController::class => Fatchip\PayOne\Application\Controller\FcPayOneBasketView::class,
        OxidEsales\Eshop\Application\Controller\UserController::class => Fatchip\PayOne\Application\Controller\FcPayOneUserView::class,
        OxidEsales\Eshop\Application\Controller\OrderController::class => Fatchip\PayOne\Application\Controller\FcPayOneOrderView::class,
        OxidEsales\Eshop\Application\Controller\PaymentController::class => Fatchip\PayOne\Application\Controller\FcPayOnePaymentView::class,
        OxidEsales\Eshop\Application\Controller\ThankYouController::class => Fatchip\PayOne\Application\Controller\FcPayOneThankYouView::class,
        // models
        OxidEsales\Eshop\Application\Model\Basket::class => Fatchip\PayOne\Application\Model\FcPayOneBasket::class,
        OxidEsales\Eshop\Application\Model\BasketItem::class => Fatchip\PayOne\Application\Model\FcPayOneBasketItem::class,
        OxidEsales\Eshop\Application\Model\Order::class => Fatchip\PayOne\Application\Model\FcPayOneOrder::class,
        OxidEsales\Eshop\Application\Model\OrderArticle::class => Fatchip\PayOne\Application\Model\FcPayOneOrderArticle::class,
        OxidEsales\Eshop\Application\Model\Payment::class => Fatchip\PayOne\Application\Model\FcPayOnePayment::class,
        OxidEsales\Eshop\Application\Model\PaymentGateway::class => Fatchip\PayOne\Application\Model\FcPayOnePaymentGateway::class,
        OxidEsales\Eshop\Application\Model\User::class => Fatchip\PayOne\Application\Model\FcPayOneUser::class,
        OxidEsales\Eshop\Application\Model\Address::class => Fatchip\PayOne\Application\Model\FcPayOneAddress::class,
        // core
        OxidEsales\Eshop\Core\ViewConfig::class => Fatchip\PayOne\Core\FcPayOneViewConf::class
    ],
    'controllers' => [
        // Controller
        'FcPayOneAdminView' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminView::class,
        'FcPayOneAdminDetails' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminDetails::class,
        'FcPayOneAdminList' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdminList::class,
        'FcPayOneAdmin' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneAdmin::class,
        'fcpayone_main_ajax' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneMainAjax::class,
        'FcPayOneApiLog' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLog::class,
        'FcPayOneApiLogList' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogList::class,
        'FcPayOneApiLogMain' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneApiLogMain::class,
        'FcPayOneList' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneList::class,
        'FcPayOneLog' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneLog::class,
        'FcPayOneLogList' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneLogList::class,
        'FcPayOneMain' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneMain::class,
        'FcPayOneOrder' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneOrder::class,
        'FcPayOneProtocol' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneProtocol::class,
        'FcPayOneStatusForwarding' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneStatusForwarding::class,
        'FcPayOneStatusMapping' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneStatusMapping::class,
        'FcPayOneErrorMapping' => Fatchip\PayOne\Application\Controller\Admin\FcPayOneErrorMapping::class,
        'FcPayOneAjax' => Fatchip\PayOne\Application\Controller\FcPayOneAjax::class,
        'FcPayOneTransactionStatusBase' => Fatchip\PayOne\Application\Controller\FcPayOneTransactionStatusBase::class,
        'FcPayOneTransactionStatusHandler' => Fatchip\PayOne\Application\Controller\FcPayOneTransactionStatusHandler::class,
        'FcPayOneTransactionStatusForwarder' => Fatchip\PayOne\Application\Controller\FcPayOneTransactionStatusForwarder::class,
        // Model
        'FcPoUserFlag' => Fatchip\PayOne\Application\Model\FcPouserflag::class,
        'FcPoRequestLog' => Fatchip\PayOne\Application\Model\FcPoRequestLog::class,
        'FcPoTransactionStatus' => Fatchip\PayOne\Application\Model\FcPoTransactionStatus::class,
        'FcPoMapping' => Fatchip\PayOne\Application\Model\FcPoMapping::class,
        'FcPoErrorMapping' => Fatchip\PayOne\Application\Model\FcPoErrorMapping::class,
        'FcPoForwarding' => Fatchip\PayOne\Application\Model\FcPoForwarding::class,
        'FcPoConfigExport' => Fatchip\PayOne\Application\Model\FcPoConfigExport::class,
        'FcPoKlarna' => Fatchip\PayOne\Application\Model\FcPoKlarna::class,
        'FcPoPaypal' => Fatchip\PayOne\Application\Model\FcPoPaypal::class,
        'FcPoRatePay' => Fatchip\PayOne\Application\Model\FcPoRatePay::class,
        //Core
        'FcPoMandateDownload' => Fatchip\PayOne\Core\FcPoMandateDownload::class,
    ],
    'events' => [
        'onActivate' => Fatchip\PayOne\Core\FcPayOneEvents::class . '::onActivate',
        'onDeactivate' => Fatchip\PayOne\Core\FcPayOneEvents::class . '::onDeactivate',
    ],
];

if (class_exists('\OxidEsales\Facts\Facts')) {
    $oFacts = new Facts();
    $sShopEdition = $oFacts->getEdition();
    if ($sShopEdition == EditionSelector::ENTERPRISE) {
        $aModule['blocks'][] = [
            'template' => 'roles_bemain.tpl',
            'block' => 'admin_roles_bemain_form',
            'file' => '/views/twig/blocks/fcpo_admin_roles_bemain_form.tpl',
        ];
        $aModule['extend'][OxidEsales\Eshop\Application\Controller\Admin\RolesBackendMain::class] = Fatchip\PayOne\Application\Controller\Admin\FcPayOneRolesBeMain::class;
    }
}
