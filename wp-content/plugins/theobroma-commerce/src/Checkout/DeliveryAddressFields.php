<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliveryAddressFields
{
    public function register(): void
    {
        add_filter('woocommerce_checkout_fields', [$this, 'fields'], 50);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate'], 20, 2);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function fields(array $fields): array
    {
        $fields['billing'] ??= [];
        $fields['billing']['billing_postcode'] = [
            'type' => 'text',
            'label' => '',
            'placeholder' => 'Индекс',
            'required' => false,
            'priority' => 45,
            'class' => ['form-row-wide', 'theobroma-delivery-address'],
            'autocomplete' => 'postal-code',
        ];
        $fields['billing']['billing_address_1'] = [
            'type' => 'text',
            'label' => '',
            'placeholder' => 'Улица, дом, квартира',
            'required' => false,
            'priority' => 46,
            'class' => ['form-row-wide', 'theobroma-delivery-address'],
            'autocomplete' => 'address-line1',
        ];
        $fields['billing']['billing_address_2'] = [
            'type' => 'text',
            'label' => '',
            'placeholder' => 'Подъезд, этаж, комментарий курьеру',
            'required' => false,
            'priority' => 47,
            'class' => ['form-row-wide', 'theobroma-delivery-address'],
            'autocomplete' => 'address-line2',
        ];
        return $fields;
    }

    /** @param array<string,mixed> $data */
    public function validate(array $data, \WP_Error $errors): void
    {
        $methods = array_map('strval', (array) ($data['shipping_method'] ?? []));
        if (!$this->hasCourier($methods)) {
            return;
        }
        if (trim((string) ($data['billing_postcode'] ?? '')) === '') {
            $errors->add('theobroma_cdek_postcode', __('Укажите индекс для доставки курьером СДЭК.', 'theobroma-commerce'));
        }
        if (trim((string) ($data['billing_address_1'] ?? '')) === '') {
            $errors->add('theobroma_cdek_address', __('Укажите адрес для доставки курьером СДЭК.', 'theobroma-commerce'));
        }
    }

    /** @param list<string> $methods */
    private function hasCourier(array $methods): bool
    {
        foreach ($methods as $method) {
            if (str_contains($method, 'theobroma_cdek') && str_contains($method, 'courier')) {
                return true;
            }
        }
        return false;
    }
}
