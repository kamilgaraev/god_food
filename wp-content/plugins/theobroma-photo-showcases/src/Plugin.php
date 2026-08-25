<?php

declare(strict_types=1);

namespace Theobroma\PhotoShowcases;

final class Plugin
{
    private static ?self $instance = null;

    private function __construct(
        private readonly Settings $settings = new Settings(),
        private readonly DefaultImages $defaultImages = new DefaultImages(),
        private readonly Renderer $renderer = new Renderer()
    ) {
    }

    public static function boot(): self
    {
        $plugin = self::instance();
        add_action('wp_enqueue_scripts', array($plugin, 'enqueueFrontendAssets'));
        if (is_admin()) {
            (new AdminPage($plugin->settings, $plugin->defaultImages))->register();
        }

        return $plugin;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function html(string $location): string
    {
        return $this->renderer->html($location, $this->resolvedSettings());
    }

    public function enqueueFrontendAssets(): void
    {
        if (!is_front_page() && !is_page('Корпоративные подарки')) {
            return;
        }

        wp_enqueue_style(
            'theobroma-photo-showcases',
            plugins_url('assets/frontend.css', THEOBROMA_PHOTO_SHOWCASES_FILE),
            array(),
            THEOBROMA_PHOTO_SHOWCASES_VERSION
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function resolvedSettings(): array
    {
        $saved = get_option(Settings::OPTION, null);
        $settings = $this->settings->sanitize($saved);

        if (is_array($saved)) {
            return $settings;
        }

        $ids = $this->defaultImages->ids(5);
        $rows = array_map(
            static fn (int $attachmentId): array => array(
                'attachment_id' => $attachmentId,
                'alt' => '',
                'caption' => '',
            ),
            $ids
        );
        $settings['home']['images'] = $rows;
        $settings['corporate']['images'] = array_slice(array_reverse($rows), 0, 3);

        return $settings;
    }
}
