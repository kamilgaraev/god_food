<?php

declare(strict_types=1);

namespace {
    if (!function_exists('__')) {
        function __(string $text, string $domain = ''): string { return $text; }
    }
    if (!function_exists('esc_attr')) {
        function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
    }
    if (!function_exists('esc_html')) {
        function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES); }
    }

    if (!class_exists('WC_AJAX')) {
        final class WC_AJAX
        {
            public static function get_endpoint(string $request): string
            {
                return 'https://example.test/?wc-ajax=' . $request;
            }
        }
    }

    if (!class_exists('WC_Shipping_Rate')) {
        final class WC_Shipping_Rate
        {
            /** @param array<string,mixed> $meta */
            public function __construct(private string $id, private array $meta = []) {}
            public function get_id(): string { return $this->id; }
            /** @return array<string,mixed> */
            public function get_meta_data(): array { return $this->meta; }
        }
    }
}

namespace Theobroma\Commerce\Tests {
    use Theobroma\Commerce\Checkout\DeliverySelector;

    final class DeliverySelectorTest extends TestCase
    {
        public function testLoadsAssetsOnAnyStorefrontPageForModalCheckout(): void
        {
            $selector = new DeliverySelector();

            $this->assertTrue($selector->shouldLoadAssets(false));
            $this->assertSame(false, $selector->shouldLoadAssets(true));
        }

        public function testReplacesCachedBootstrapRateLabel(): void
        {
            $selector = new DeliverySelector();

            $this->assertSame(
                'Ozon Доставка',
                $selector->bootstrapRateLabel(
                    'theobroma_ozon:1',
                    ['theobroma_requires_selection' => 'yes'],
                    'Ozon Доставка — выбрать способ'
                )
            );
            $this->assertSame(
                'СДЭК',
                $selector->bootstrapRateLabel(
                    'theobroma_cdek:2',
                    ['theobroma_requires_selection' => 'yes'],
                    'СДЭК — выбрать способ'
                )
            );
        }

        public function testUsesWooAjaxForQuotesThatDependOnTheCustomerCart(): void
        {
            $selector = new DeliverySelector();

            $this->assertSame(
                'https://example.test/?wc-ajax=theobroma_delivery_quote',
                $selector->quoteUrl()
            );
        }

        public function testShowsConfirmedDeliverySummaryNextToChangeAction(): void
        {
            $selector = new DeliverySelector();
            $rate = new \WC_Shipping_Rate('theobroma_ozon:1', [
                'theobroma_delivery_kind' => 'pickup',
                'theobroma_pickup_address' => 'Казань, Спартаковская улица, 12',
            ]);

            ob_start();
            $selector->button($rate, 0);
            $html = (string) ob_get_clean();

            $this->assertTrue(str_contains($html, 'theobroma-delivery-selection'));
            $this->assertTrue(str_contains($html, 'Доставка выбрана'));
            $this->assertTrue(str_contains($html, 'Пункт выдачи'));
            $this->assertTrue(str_contains($html, 'Казань, Спартаковская улица, 12'));
            $this->assertTrue(str_contains($html, 'Изменить доставку'));
        }

        public function testKeepsSelectionSummaryHiddenBeforeDeliveryIsConfirmed(): void
        {
            $selector = new DeliverySelector();
            $rate = new \WC_Shipping_Rate('theobroma_ozon:1', [
                'theobroma_requires_selection' => 'yes',
            ]);

            ob_start();
            $selector->button($rate, 0);
            $html = (string) ob_get_clean();

            $this->assertSame(false, str_contains($html, 'theobroma-delivery-selection'));
            $this->assertTrue(str_contains($html, 'Выбрать пункт или курьера'));
        }
    }
}
