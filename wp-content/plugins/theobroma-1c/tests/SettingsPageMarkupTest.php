<?php
declare(strict_types=1);

namespace Theobroma\OneC\Tests;

final class SettingsPageMarkupTest
{
    public function testAdminPageHasStructuredCardsAndExplainsOzonScope(): void
    {
        $source = $this->source();
        foreach ([
            'theobroma-1c__hero',
            'theobroma-1c__grid',
            'theobroma-1c-card',
            'Подключение к 1С',
            'Последние обмены',
            'Не импортирует заказы Ozon',
            'Обменов пока не было',
        ] as $needle) {
            $this->contains($needle, $source);
        }
    }

    public function testStylesAreLoadedOnlyOnPluginAdminScreen(): void
    {
        $source = $this->source();
        foreach (['admin_enqueue_scripts', 'woocommerce_page_theobroma-1c', 'assets/admin.css'] as $needle) {
            $this->contains($needle, $source);
        }
    }

    private function source(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/src/Admin/SettingsPage.php');
    }

    private function contains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException('Missing admin UI marker: ' . $needle);
        }
    }
}
