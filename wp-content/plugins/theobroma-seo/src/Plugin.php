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

    /** @param array<string, string> $parts
     *  @return array<string, string>
     */
    public static function titleParts(array $parts): array
    {
        if (is_singular()) {
            $custom = trim((string) get_post_meta(get_queried_object_id(), '_theobroma_seo_title', true));
            if ($custom !== '') {
                $parts['title'] = $custom;
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
