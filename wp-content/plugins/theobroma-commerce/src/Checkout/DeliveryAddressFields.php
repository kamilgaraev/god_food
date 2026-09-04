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
        $fields['billing']['billing_country'] = [
            'type' => 'country',
            'required' => false,
            'default' => 'RU',
            'priority' => 35,
        ];
        foreach ([
            'billing_first_name' => 10,
            'billing_phone' => 20,
            'billing_email' => 30,
            'billing_city' => 40,
        ] as $key => $priority) {
            if (isset($fields['billing'][$key])) {
                $fields['billing'][$key]['priority'] = $priority;
                $fields['billing'][$key]['class'] = ['form-row-wide'];
            }
        }
        $fields['billing']['billing_last_name'] = [
            'type' => 'text',
            'label' => '',
            'placeholder' => 'Фамилия',
            'required' => false,
            'priority' => 15,
            'class' => ['form-row-wide'],
            'autocomplete' => 'family-name',
        ];
        $fields['billing']['billing_postcode'] = [
            'type' => 'text',
            'label' => '',
            'placeholder' => 'Индекс',
            'required' => false,
            'priority' => 60,
            'class' => ['form-row-wide', 'theobroma-delivery-address'],
            'autocomplete' => 'postal-code',
        ];
        $fields['billing']['billing_address_1'] = [
            'type' => 'text',
            'label' => '',
            'placeholder' => 'Улица, дом, квартира',
            'required' => false,
            'priority' => 50,
            'class' => ['form-row-wide', 'theobroma-delivery-address'],
            'autocomplete' => 'address-line1',
        ];
        $fields['billing']['billing_address_2'] = [
            'type' => 'text',
            'label' => '',
            'placeholder' => 'Комментарий к доставке',
            'required' => false,
            'priority' => 70,
            'class' => ['form-row-wide', 'theobroma-delivery-address'],
            'autocomplete' => 'address-line2',
        ];
        if (isset($fields['billing']['billing_city'])) {
            $fields['billing']['billing_city']['placeholder'] = 'Город';
        }
        return $fields;
    }

    /** @param array<string,mixed> $data */
    public function validate(array $data, \WP_Error $errors): void
    {
        $methods = array_map('strval', (array) ($data['shipping_method'] ?? []));
        if (array_filter($methods, static fn (string $method): bool => str_contains($method, 'theobroma_ozon'))) {
            $nameError = DeliveryCustomerName::error([
                'first_name' => $data['billing_first_name'] ?? '',
                'last_name' => $data['billing_last_name'] ?? '',
            ]);
            if ($nameError !== null) {
                $errors->add('theobroma_delivery_name', $nameError);
            }
        }
        if (!$this->hasCourier($methods)) {
            return;
        }
        if (trim((string) ($data['billing_postcode'] ?? '')) === '') {
            $errors->add('theobroma_delivery_postcode', __('Укажите индекс для доставки курьером.', 'theobroma-commerce'));
        }
        if (trim((string) ($data['billing_address_1'] ?? '')) === '') {
            $errors->add('theobroma_delivery_address', __('Укажите адрес для доставки курьером.', 'theobroma-commerce'));
        }
    }

    /** @param list<string> $methods */
    private function hasCourier(array $methods): bool
    {
        foreach ($methods as $method) {
            if ((str_contains($method, 'theobroma_cdek') || str_contains($method, 'theobroma_ozon')) && str_contains($method, 'courier')) {
                return true;
            }
        }
        return false;
    }
}
