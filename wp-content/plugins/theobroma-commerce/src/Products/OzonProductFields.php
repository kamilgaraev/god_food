<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Products;

final class OzonProductFields
{
    private const SKU_META = '_theobroma_ozon_sku';

    public function register(): void
    {
        add_action('woocommerce_product_options_sku', [$this, 'render']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
    }

    public function render(): void
    {
        woocommerce_wp_text_input([
            'id' => self::SKU_META,
            'label' => __('Ozon SKU', 'theobroma-commerce'),
            'description' => __('Числовой SKU товара в Ozon. Обязателен для Ozon Доставки.', 'theobroma-commerce'),
            'desc_tip' => true,
            'type' => 'text',
            'custom_attributes' => [
                'inputmode' => 'numeric',
                'pattern' => '[0-9]*',
            ],
        ]);
    }

    public function save(\WC_Product $product): void
    {
        if (!isset($_POST[self::SKU_META])) {
            return;
        }

        $value = preg_replace('/\D+/', '', (string) wp_unslash($_POST[self::SKU_META])) ?? '';
        if ($value === '') {
            $product->delete_meta_data(self::SKU_META);
            return;
        }
        $product->update_meta_data(self::SKU_META, $value);
    }
}
