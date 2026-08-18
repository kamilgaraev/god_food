<?php

declare(strict_types=1);

function theobroma_order_button_text(): string {
    return 'Заказать';
}

add_filter('woocommerce_order_button_text', 'theobroma_order_button_text');
