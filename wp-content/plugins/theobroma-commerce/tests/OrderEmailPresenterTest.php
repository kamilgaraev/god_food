<?php

declare(strict_types=1);

namespace {
    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags(string $text): string
        {
            return strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text) ?? '');
        }
    }
}

namespace Theobroma\Commerce\Tests {
    use Theobroma\Commerce\Orders\OrderEmailPresenter;

    final class OrderEmailPresenterTest extends TestCase
    {
        public function testSnapshotsDescriptionAndSkuBeforeFirstSave(): void
        {
            $presenter = new OrderEmailPresenter();
            $product = new EmailProductStub('<p>С цельным фиником</p>', 'theobroma-30-59-date');
            $item = new EmailItemStub($product);
            $presenter->captureItem($item);
            $product->description = 'Другой состав';
            $product->sku = 'renamed';
            $presenter->captureItem($item);
            $this->assertSame(['description' => 'С цельным фиником', 'sku' => 'theobroma-30-59-date'], $presenter->itemDetails($item));
            $item->id = 10;
            $presenter->captureItem($item);
            $item->product = false;
            $this->assertSame(['description' => 'С цельным фиником', 'sku' => 'theobroma-30-59-date'], $presenter->itemDetails($item));
        }

        public function testExistingOrdersUseCatalogWithoutWritingMetadata(): void
        {
            $presenter = new OrderEmailPresenter();
            $item = new EmailItemStub(new EmailProductStub('На финиковой пудре', 'theobroma-30-date-powder'));
            $item->id = 11;
            $presenter->captureItem($item);
            $this->assertSame(['description' => 'На финиковой пудре', 'sku' => 'theobroma-30-date-powder'], $presenter->itemDetails($item));
            $this->assertSame([], $item->metadata);
        }

        public function testEmptySnapshotDoesNotAcquireLaterCatalogDescription(): void
        {
            $presenter = new OrderEmailPresenter();
            $product = new EmailProductStub('', '');
            $item = new EmailItemStub($product);
            $presenter->captureItem($item);
            $product->description = 'Added after checkout';
            $product->sku = 'later-sku';
            $this->assertSame(['description' => '', 'sku' => ''], $presenter->itemDetails($item));
        }

        public function testVariationFallsBackToParentShortDescription(): void
        {
            $GLOBALS['theobroma_delivery_runtime_products'][9999] = new EmailProductStub('С малиной', 'parent');
            try {
                $item = new EmailItemStub(new EmailProductStub('', 'variation-30', 9999));
                $this->assertSame(['description' => 'С малиной', 'sku' => 'variation-30'], (new OrderEmailPresenter())->itemDetails($item));
            } finally {
                unset($GLOBALS['theobroma_delivery_runtime_products'][9999]);
            }
        }

        public function testDescriptionIsPlainTextAndDoesNotRepeatTheName(): void
        {
            $presenter = new OrderEmailPresenter();
            $item = new EmailItemStub(new EmailProductStub('<p>На&nbsp;кокосовом сахаре</p><p>с корицей<script>unwanted()</script></p>', 'sku'));
            $this->assertSame('На кокосовом сахаре с корицей', $presenter->itemDetails($item)['description']);
            $item->name = 'Горький шоколад на кокосовом сахаре с корицей';
            $this->assertSame('', $presenter->itemDetails($item)['description']);
        }

        public function testOnlyEmailRowsReceiveDetailsAndTextOnlyLayout(): void
        {
            $presenter = new OrderEmailPresenter();
            $order = new EmailOrderStub();
            $item = new EmailItemStub(new EmailProductStub('С малиной', 'berry-30'));
            $arguments = ['order' => $order, 'show_sku' => true, 'show_image' => true];
            $this->assertSame($arguments, $presenter->itemArguments($arguments));
            $this->assertSame('', $this->itemOutput($presenter, $item, $order));
            $presenter->beginOrderTable($order);
            $this->assertSame(false, $presenter->itemArguments($arguments)['show_image']);
            $this->assertSame(false, $presenter->itemArguments($arguments)['show_sku']);
            $html = $this->itemOutput($presenter, $item, $order);
            $this->assertTrue(str_contains($html, 'С малиной'));
            $this->assertTrue(str_contains($html, 'Артикул: berry-30'));
            $this->assertTrue(!str_contains($html, '<img'));
            $presenter->endOrderTable();
            $this->assertSame('', $this->itemOutput($presenter, $item, $order));
        }

        public function testNestedEmailTableRestoresOuterContext(): void
        {
            $presenter = new OrderEmailPresenter();
            $outer = new EmailOrderStub();
            $inner = new EmailOrderStub();
            $item = new EmailItemStub(new EmailProductStub('С клубникой', 'berry'));
            $presenter->beginOrderTable($outer);
            $presenter->beginOrderTable($inner);
            $this->assertSame('', $this->itemOutput($presenter, $item, $outer));
            $presenter->endOrderTable();
            $this->assertTrue(str_contains($this->itemOutput($presenter, $item, $outer), 'С клубникой'));
            $presenter->endOrderTable();
        }

        public function testHtmlEscapingAndPlainTextOutput(): void
        {
            $presenter = new OrderEmailPresenter();
            $order = new EmailOrderStub();
            $item = new EmailItemStub(new EmailProductStub('<b>Малина &amp; фундук</b>', 'sku&30'));
            $presenter->beginOrderTable($order);
            $html = $this->itemOutput($presenter, $item, $order);
            $this->assertTrue(str_contains($html, 'Малина &amp; фундук'));
            $this->assertTrue(str_contains($html, 'Артикул: sku&amp;30'));
            $plain = $this->itemOutput($presenter, $item, $order, true);
            $this->assertSame("\nМалина & фундук\nАртикул: sku&30\n", $plain);
            $presenter->endOrderTable();
        }

        public function testMissingProductProducesNoInventedDescription(): void
        {
            $presenter = new OrderEmailPresenter();
            $item = new EmailItemStub(false);
            $presenter->captureItem($item);
            $this->assertSame([], $item->metadata);
            $this->assertSame(['description' => '', 'sku' => ''], $presenter->itemDetails($item));
        }

        public function testCarrierAndDeliveryKindComeFromShippingLines(): void
        {
            $presenter = new OrderEmailPresenter();
            foreach (['ozon' => 'Ozon', 'cdek' => 'СДЭК'] as $provider => $label) {
                foreach (['pickup' => 'пункт выдачи', 'courier' => 'курьер'] as $kind => $kindLabel) {
                    $order = new EmailOrderStub([new EmailShippingStub('theobroma_' . $provider, $kind)]);
                    $this->assertSame([$label . ' — ' . $kindLabel], $presenter->deliveryLabels($order));
                }
            }
        }

        public function testMultiplePackagesAreDeduplicatedWithoutHidingOtherCarriers(): void
        {
            $order = new EmailOrderStub([
                new EmailShippingStub('theobroma_ozon', 'pickup'),
                new EmailShippingStub('theobroma_ozon', 'pickup'),
                new EmailShippingStub('theobroma_cdek', 'courier'),
            ]);
            $this->assertSame(['Ozon — пункт выдачи', 'СДЭК — курьер'], (new OrderEmailPresenter())->deliveryLabels($order));
        }

        public function testLegacyFallbackDoesNotOverrideActualShippingMethod(): void
        {
            $presenter = new OrderEmailPresenter();
            $meta = ['_theobroma_delivery_provider' => 'ozon', '_theobroma_delivery_kind' => 'pickup'];
            $this->assertSame(['Ozon — пункт выдачи'], $presenter->deliveryLabels(new EmailOrderStub([], $meta)));
            $this->assertSame(['СДЭК'], $presenter->deliveryLabels(new EmailOrderStub([new EmailShippingStub('theobroma_cdek')], $meta)));
            $this->assertSame(['Самовывоз'], $presenter->deliveryLabels(new EmailOrderStub([new EmailShippingStub('local_pickup', '', 'Самовывоз')], $meta)));
            $this->assertSame([], $presenter->deliveryLabels(new EmailOrderStub()));
        }

        public function testDeliverySummaryIsReadableInAdminCustomerAndPlainEmails(): void
        {
            $presenter = new OrderEmailPresenter();
            $order = new EmailOrderStub([new EmailShippingStub('theobroma_cdek', 'pickup')]);
            foreach ([false, true] as $admin) {
                ob_start();
                $presenter->renderDelivery($order, $admin, false);
                $html = (string) ob_get_clean();
                $this->assertTrue(str_contains($html, 'Служба доставки'));
                $this->assertTrue(str_contains($html, 'СДЭК — пункт выдачи'));
                ob_start();
                $presenter->renderDelivery($order, $admin, true);
                $this->assertSame("\nСлужба доставки: СДЭК — пункт выдачи\n", ob_get_clean());
            }
        }

        private function itemOutput(OrderEmailPresenter $presenter, EmailItemStub $item, EmailOrderStub $order, bool $plain = false): string
        {
            ob_start();
            $presenter->renderItem(1, $item, $order, $plain);
            return (string) ob_get_clean();
        }
    }

    final class EmailProductStub
    {
        public function __construct(public string $description, public string $sku, private int $parentId = 0) {}
        public function get_short_description(): string { return $this->description; }
        public function get_sku(): string { return $this->sku; }
        public function get_parent_id(): int { return $this->parentId; }
    }

    final class EmailItemStub
    {
        public int $id = 0;
        public string $name = 'Молочный шоколад 30г';
        public array $metadata = [];
        public function __construct(public mixed $product) {}
        public function get_id(): int { return $this->id; }
        public function get_product(): mixed { return $this->product; }
        public function get_name(): string { return $this->name; }
        public function meta_exists(string $key): bool { return array_key_exists($key, $this->metadata); }
        public function add_meta_data(string $key, mixed $value, bool $unique): void { $this->metadata[$key] = $value; }
        public function get_meta(string $key, bool $single): mixed { return $this->metadata[$key] ?? ''; }
    }

    final class EmailShippingStub
    {
        public function __construct(private string $method, private string $kind = '', private string $name = '') {}
        public function get_method_id(): string { return $this->method; }
        public function get_name(): string { return $this->name; }
        public function get_meta(string $key, bool $single): string { return $key === 'theobroma_delivery_kind' ? $this->kind : ''; }
    }

    final class EmailOrderStub
    {
        public function __construct(private array $shipping = [], private array $metadata = []) {}
        public function get_items(string $type): array { return $this->shipping; }
        public function get_meta(string $key, bool $single): mixed { return $this->metadata[$key] ?? ''; }
    }
}
