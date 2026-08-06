<?php

declare(strict_types=1);

namespace Theobroma\Seo;

final class WordPressDocumentResolver
{
    public function current(): ?SeoDocument
    {
        if (is_singular('product') && function_exists('wc_get_product')) {
            $product = wc_get_product(get_queried_object_id());
            return $product instanceof \WC_Product ? $this->forProduct($product) : null;
        }

        if (function_exists('is_shop') && is_shop()) {
            return $this->forShop();
        }

        if (is_front_page()) {
            return $this->forSite();
        }

        if (is_singular()) {
            $post = get_post(get_queried_object_id());
            return $post instanceof \WP_Post ? $this->forPost($post) : null;
        }

        return null;
    }

    public function forShop(): SeoDocument
    {
        $shopId = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
        $title = $shopId > 0 ? $this->customValue($shopId, '_theobroma_seo_title') : '';
        if ($title === '') {
            $title = $shopId > 0 ? get_the_title($shopId) : '';
        }
        if ($title === '') {
            $title = 'Каталог';
        }

        $description = $shopId > 0 ? $this->customValue($shopId, '_theobroma_seo_description') : '';
        $description = $this->description(
            $description,
            'Каталог натурального пористого шоколада, какао и семян чиа Theobroma. Доставка заказов по России.'
        );
        $url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/catalog/');
        $image = $shopId > 0 ? $this->customValue($shopId, '_theobroma_seo_og_image') : '';
        if ($image === '' && $shopId > 0) {
            $featured = get_the_post_thumbnail_url($shopId, 'full');
            $image = is_string($featured) ? $featured : '';
        }
        if ($image === '') {
            $image = $this->defaultImage();
        }

        return new SeoDocument(
            title: $title,
            description: $description,
            canonicalUrl: is_string($url) ? $url : home_url('/catalog/'),
            type: 'website',
            siteName: $this->siteName(),
            imageUrl: esc_url_raw($image)
        );
    }

    public function forProduct(\WC_Product $product): SeoDocument
    {
        $postId = $product->get_id();
        $title = $this->customValue($postId, '_theobroma_seo_title');
        if ($title === '') {
            $title = $product->get_name();
        }

        $description = $this->customValue($postId, '_theobroma_seo_description');
        if ($description === '') {
            $description = $product->get_short_description() ?: $product->get_description();
        }
        $description = $this->description($description, sprintf('Купить %s в интернет-магазине «Пища Богов».', $title));

        $images = [];
        foreach (array_slice(array_values(array_filter(array_merge(
            [$product->get_image_id()],
            $product->get_gallery_image_ids()
        ))), 0, 9) as $attachmentId) {
            $url = wp_get_attachment_image_url((int) $attachmentId, 'full');
            if (is_string($url) && $url !== '') {
                $images[] = $url;
            }
        }
        $customImage = $this->customValue($postId, '_theobroma_seo_og_image');
        if ($customImage !== '') {
            array_unshift($images, esc_url_raw($customImage));
            $images = array_values(array_unique($images));
        }

        $url = get_permalink($postId);
        $url = is_string($url) ? $url : home_url('/');
        $price = number_format((float) $product->get_price(), wc_get_price_decimals(), '.', '');
        $schema = (new SchemaFactory())->product([
            'name' => $title,
            'description' => $description,
            'url' => $url,
            'sku' => $product->get_sku(),
            'images' => $images,
            'price' => $price,
            'currency' => get_woocommerce_currency(),
            'in_stock' => $product->is_in_stock(),
        ]);

        return new SeoDocument(
            title: $title,
            description: $description,
            canonicalUrl: $url,
            type: 'product',
            siteName: $this->siteName(),
            imageUrl: $images[0] ?? $this->defaultImage(),
            schema: $schema
        );
    }

    public function forPost(\WP_Post $post): SeoDocument
    {
        $title = $this->customValue($post->ID, '_theobroma_seo_title');
        if ($title === '') {
            $title = get_the_title($post);
        }
        $description = $this->customValue($post->ID, '_theobroma_seo_description');
        if ($description === '') {
            $description = $post->post_excerpt ?: $post->post_content;
        }
        $description = $this->description(
            $description,
            sprintf('%s — официальный сайт «Пища Богов».', $title)
        );
        $url = get_permalink($post);
        $url = is_string($url) ? $url : home_url('/');
        $image = $this->customValue($post->ID, '_theobroma_seo_og_image');
        if ($image === '') {
            $featured = get_the_post_thumbnail_url($post, 'full');
            $image = is_string($featured) ? $featured : $this->defaultImage();
        }

        $schema = [];
        $type = 'website';
        if ($post->post_type === 'post') {
            $type = 'article';
            $author = get_the_author_meta('display_name', (int) $post->post_author);
            $schema = (new SchemaFactory())->article([
                'headline' => $title,
                'description' => $description,
                'url' => $url,
                'image' => $image,
                'date_published' => get_post_time(DATE_W3C, true, $post),
                'date_modified' => get_post_modified_time(DATE_W3C, true, $post),
                'author' => is_string($author) && $author !== '' ? $author : 'Редакция Пища Богов',
                'logo' => $this->logoUrl(),
            ]);
        }

        return new SeoDocument(
            title: $title,
            description: $description,
            canonicalUrl: $url,
            type: $type,
            siteName: $this->siteName(),
            imageUrl: esc_url_raw($image),
            schema: $schema
        );
    }

    public function forSite(): SeoDocument
    {
        $url = home_url('/');
        $description = $this->description(
            (string) get_option('blogdescription', ''),
            'Натуральный пористый шоколад Theobroma — интернет-магазин «Пища Богов».'
        );
        $logo = $this->logoUrl();

        return new SeoDocument(
            title: $this->siteName(),
            description: $description,
            canonicalUrl: $url,
            type: 'website',
            siteName: $this->siteName(),
            imageUrl: $this->defaultImage(),
            schema: (new SchemaFactory())->site([
                'url' => $url,
                'name' => $this->siteName(),
                'description' => $description,
                'logo' => $logo,
            ])
        );
    }

    private function description(string $source, string $fallback): string
    {
        $plain = html_entity_decode(wp_strip_all_tags(strip_shortcodes($source)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = trim((string) preg_replace('/\s+/u', ' ', $plain));
        if ($plain === '') {
            $plain = $fallback;
        }
        if (mb_strlen($plain) <= 160) {
            return $plain;
        }

        $short = mb_substr($plain, 0, 157);
        $space = mb_strrpos($short, ' ');
        return rtrim($space !== false ? mb_substr($short, 0, $space) : $short, ".,;: \t\n\r\0\x0B") . '…';
    }

    private function customValue(int $postId, string $key): string
    {
        return trim((string) get_post_meta($postId, $key, true));
    }

    private function siteName(): string
    {
        $name = trim((string) get_bloginfo('name'));
        return $name !== '' ? $name : 'Пища Богов';
    }

    private function defaultImage(): string
    {
        return esc_url_raw(get_theme_file_uri('assets/images/hero-bg-original.jpg'));
    }

    private function logoUrl(): string
    {
        $customLogoId = (int) get_theme_mod('custom_logo');
        if ($customLogoId > 0) {
            $url = wp_get_attachment_image_url($customLogoId, 'full');
            if (is_string($url) && $url !== '') {
                return esc_url_raw($url);
            }
        }

        return esc_url_raw(get_theme_file_uri('assets/images/logo.png'));
    }
}
