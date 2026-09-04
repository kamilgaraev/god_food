<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Checkout;

final class DeliveryCustomerName
{
    public static function error(array $person): ?string
    {
        foreach (['first_name' => 'Имя', 'last_name' => 'Фамилия', 'middle_name' => 'Отчество'] as $key => $label) {
            $value = trim((string) ($person[$key] ?? ''));
            if ($value === '' && $key === 'middle_name') {
                continue;
            }
            if (preg_match('/^[\p{L}\p{Zs}\p{Pd}]{1,50}$/u', $value) !== 1 || preg_match('/\p{L}/u', $value) !== 1) {
                return 'Поле «' . $label . '»: укажите настоящее имя буквами, до 50 символов. Допустимы пробелы и дефисы. Email укажите в поле электронной почты.';
            }
        }
        return null;
    }
}
