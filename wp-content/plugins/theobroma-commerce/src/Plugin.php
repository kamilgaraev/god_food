<?php

declare(strict_types=1);

namespace Theobroma\Commerce;

use Theobroma\Commerce\Admin\SettingsPage;
use Theobroma\Commerce\Catalog\ProductWeightBackfill;
use Theobroma\Commerce\Checkout\PickupPointFields;
use Theobroma\Commerce\Checkout\DeliveryAddressFields;
use Theobroma\Commerce\Rest\CdekPointsController;
use Theobroma\Commerce\Rest\CdekWebhookController;
use Theobroma\Commerce\Orders\CdekOrderLifecycle;
use Theobroma\Commerce\Orders\OzonOrderLifecycle;
use Theobroma\Commerce\Loyalty\WooLoyaltyLifecycle;
use Theobroma\Commerce\Loyalty\LoyaltyCheckout;
use Theobroma\Commerce\Loyalty\LoyaltyAccountEndpoint;
use Theobroma\Commerce\Products\OzonProductFields;
use Theobroma\Commerce\Shipping\CdekShippingMethod;
use Theobroma\Commerce\Shipping\OzonShippingMethod;
use Theobroma\Commerce\Wishlist\WishlistController;
use Theobroma\Commerce\Infrastructure\MailTransport;

final class Plugin
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted || !class_exists('WooCommerce')) {
            return;
        }
        self::$booted = true;

        add_filter('woocommerce_shipping_methods', [self::class, 'shippingMethods']);
        (new SettingsPage())->register();
        (new PickupPointFields())->register();
        (new DeliveryAddressFields())->register();
        (new CdekPointsController())->register();
        (new CdekWebhookController())->register();
        (new CdekOrderLifecycle())->register();
        (new OzonOrderLifecycle())->register();
        (new WooLoyaltyLifecycle())->register();
        (new LoyaltyCheckout())->register();
        (new LoyaltyAccountEndpoint())->register();
        (new OzonProductFields())->register();
        (new ProductWeightBackfill())->register();
        (new WishlistController())->register();
        MailTransport::fromEnvironment()->register();
    }

    /** @param array<string, class-string> $methods
     *  @return array<string, class-string>
     */
    public static function shippingMethods(array $methods): array
    {
        $methods['theobroma_cdek'] = CdekShippingMethod::class;
        $methods['theobroma_ozon'] = OzonShippingMethod::class;
        return $methods;
    }
}
