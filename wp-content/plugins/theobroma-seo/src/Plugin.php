<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class Plugin
{
    public static function boot(): void
    {
        add_action('wp_head', [self::class, 'renderHead'], 2);
        add_action('template_redirect', [self::class, 'serveLlms'], 0);
        add_action('template_redirect', [self::class, 'legacyRedirects'], 0);
        add_filter('document_title_parts', [self::class, 'titleParts']);
        add_filter('wp_robots', [self::class, 'robots']);
        add_filter('wp_sitemaps_add_provider', [self::class, 'sitemapProvider'], 10, 2);
        add_filter('wp_sitemaps_posts_query_args', [self::class, 'sitemapPosts'], 10, 2);
        add_filter('wp_sitemaps_taxonomies_query_args', [self::class, 'sitemapTerms'], 10, 2);
        add_filter('wp_sitemaps_taxonomies', [self::class, 'sitemapTaxonomies']);
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

    public static function serveLlms(): void
    {
        $path = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if ($path !== wp_parse_url(home_url('/llms.txt'), PHP_URL_PATH)) {
            return;
        }
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo (new LlmsRenderer())->render(home_url('/'));
        exit;
    }

    public static function legacyRedirects(): void
    {
        if (is_admin() || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) return;
        $path = rawurldecode((string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        $path = '/' . trim($path, '/') . '/';
        foreach ((array) get_option('theobroma_seo_redirects', []) as $source => $target) {
            if ($path !== '/' . trim(rawurldecode((string) $source), '/') . '/') continue;
            if (!is_string($target) || !str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, '\\')) return;
            wp_safe_redirect(home_url($target), 301, 'Theobroma SEO');
            exit;
        }
    }

    /** @param array<string, string> $parts
     *  @return array<string, string>
     */
    public static function titleParts(array $parts): array
    {
        if (is_front_page()) {
            return ['title' => (new WordPressDocumentResolver())->forSite()->title];
        }
        if ((function_exists('is_shop') && is_shop()) || is_tax('product_cat')) {
            $document = (new WordPressDocumentResolver())->current();
            if ($document) return ['title' => $document->title];
        }
        if (is_singular()) {
            $custom = trim((string) get_post_meta(get_queried_object_id(), '_theobroma_seo_title', true));
            if ($custom !== '') {
                return ['title' => $custom];
            } elseif (is_singular('product') && function_exists('wc_get_product')) {
                $product = wc_get_product(get_queried_object_id());
                if ($product instanceof \WC_Product) {
                    $parts['title'] = (new WordPressDocumentResolver())->productTitle($product);
                }
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
        if ($privateCommercePage || is_404() || is_tax('product_cat', 'misc')) {
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

    /** @param array<string, mixed> $args
     *  @return array<string, mixed>
     */
    public static function sitemapPosts(array $args, string $postType): array
    {
        $excluded = $args['post__not_in'] ?? [];
        if ($postType === 'page') {
            foreach (['marketplace', 'sample-page', 'offer', 'policy-2'] as $slug) {
                $page = get_page_by_path($slug);
                if ($page instanceof \WP_Post) {
                    $excluded[] = $page->ID;
                }
            }
            if (function_exists('wc_get_page_id')) {
                foreach (['cart', 'checkout', 'myaccount'] as $pageName) {
                    $pageId = (int) wc_get_page_id($pageName);
                    if ($pageId > 0) {
                        $excluded[] = $pageId;
                    }
                }
            }
        } elseif ($postType === 'post') {
            $helloWorld = get_page_by_path('привет-мир', OBJECT, 'post');
            if ($helloWorld instanceof \WP_Post) {
                $excluded[] = $helloWorld->ID;
            }
        }
        $args['post__not_in'] = array_values(array_unique(array_map('intval', $excluded)));
        return $args;
    }

    /** @param array<string, mixed> $args
     *  @return array<string, mixed>
     */
    public static function sitemapTerms(array $args, string $taxonomy): array
    {
        if ($taxonomy === 'product_cat') {
            $args['hide_empty'] = true;
            $misc = get_term_by('slug', 'misc', 'product_cat');
            if ($misc instanceof \WP_Term) {
                $args['exclude'] = array_merge((array) ($args['exclude'] ?? []), [$misc->term_id]);
            }
        }
        return $args;
    }

    /** @param array<string, object> $taxonomies
     *  @return array<string, object>
     */
    public static function sitemapTaxonomies(array $taxonomies): array
    {
        // Editorial posts live under /media/; the WordPress category archive repeats them.
        unset($taxonomies['category']);
        return $taxonomies;
    }
}
