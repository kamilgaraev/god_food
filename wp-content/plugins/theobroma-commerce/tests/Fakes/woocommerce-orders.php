<?php

declare(strict_types=1);

if (!class_exists('WC_Abstract_Order')) {
    abstract class WC_Abstract_Order
    {
        /** @param list<object> $lineItems @param list<object> $shippingItems */
        public function __construct(
            private readonly array $lineItems,
            private readonly array $shippingItems = []
        ) {
        }

        public function get_items(string $type = 'line_item'): array
        {
            return $type === 'line_item' ? $this->lineItems : $this->shippingItems;
        }
    }
}
if (!class_exists('WC_Order')) {
    class WC_Order extends WC_Abstract_Order {}
}
if (!class_exists('WC_Order_Refund')) {
    class WC_Order_Refund extends WC_Abstract_Order {}
}
