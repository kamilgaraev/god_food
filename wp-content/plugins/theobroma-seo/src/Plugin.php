<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class Plugin
{
    public static function boot(): void
    {
        add_action('wp_head', [self::class, 'renderHead'], 2);
        add_filter('document_title_parts', [self::class, 'titleParts']);
        add_filter('wp_robots', [self::class, 'robots']);
        add_filter('wp_sitemaps_add_provider', [self::class, 'sitemapProvider'], 10, 2);
        (new SeoMetaBox())->register();
        (new SiteVerificationSettings())->register();
    }

    public static function renderHead(): void
    {
        if (is_admin() || is_feed() || is_robots() || is_trackback()) {
            return;
        }
        $document = (new WordPressDocumentResolver())->current();
        if ($document instanceof SeoDocument) {
            echo (new MetadataRenderer())->render($document); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            if (!is_singular()) {
                printf("<link rel=\"canonical\" href=\"%s\">\n", esc_url($document->canonicalUrl));
            }
        }
        echo (new SiteVerificationRenderer())->render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            get_option(SiteVerificationSettings::OPTION, '')
        );
    }

    /** @param array<string, string> $parts
     *  @return array<string, string>
     */
    public static function titleParts(array $parts): array
    {
        if (is_singular()) {
            $custom = trim((string) get_post_meta(get_queried_object_id(), '_theobroma_seo_title', true));
            if ($custom !== '') {
                $parts['title'] = $custom;
            }
        }
        return $parts;
    }

    /** @param array<string, bool|string> $robots
     *  @return array<string, bool|string>
     */
    public static function robots(array $robots): array
    {
        $privateCommercePage = (function_exists('is_cart') && is_cart())
            || (function_exists('is_checkout') && is_checkout())
            || (function_exists('is_account_page') && is_account_page());
        if ($privateCommercePage) {
            $robots['noindex'] = true;
            $robots['follow'] = true;
            unset($robots['index'], $robots['nofollow']);
        }
        return $robots;
    }

    /** @param object|false $provider
     *  @return object|false
     */
    public static function sitemapProvider(object|false $provider, string $name): object|false
    {
        return $name === 'users' ? false : $provider;
    }
}
