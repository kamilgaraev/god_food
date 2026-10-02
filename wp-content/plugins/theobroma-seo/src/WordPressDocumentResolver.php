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

        if (is_tax('product_cat')) {
            $term = get_queried_object();
            return $term instanceof \WP_Term ? $this->forProductCategory($term) : null;
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
            'Каталог натурального пористого шоколада и какао Theobroma. Доставка заказов по России.'
        );
        $url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/catalog/');
        $image = $this->globalSocialImage() ?: ($shopId > 0 ? $this->customValue($shopId, '_theobroma_seo_og_image') : '');
        if ($image === '') {
            $image = $this->socialImageForAttachment($shopId > 0 ? (int) get_post_thumbnail_id($shopId) : 0);
        }

        return new SeoDocument(
            title: $title,
            description: $description,
            canonicalUrl: is_string($url) ? $url : home_url('/catalog/'),
            type: 'website',
            siteName: $this->siteName(),
            imageUrl: esc_url_raw($image),
            schema: (new SchemaFactory())->collection($title, $description, (string) $url, $this->catalogItems())
        );
    }

    public function forProductCategory(\WP_Term $term): SeoDocument
    {
        $url = get_term_link($term);
        $title = trim((string) get_term_meta($term->term_id, '_theobroma_seo_title', true)) ?: $term->name;
        $customDescription = trim((string) get_term_meta($term->term_id, '_theobroma_seo_description', true));
        $description = $this->description(
            $customDescription ?: $term->description,
            sprintf('%s — натуральный шоколад Theobroma. Выберите вкус и закажите с доставкой по России.', $term->name)
        );
        $thumbnailId = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
        $image = $this->socialImageForAttachment($thumbnailId);

        return new SeoDocument(
            title: $title,
            description: $description,
            canonicalUrl: is_string($url) ? $url : home_url('/catalog/'),
            type: 'website',
            siteName: $this->siteName(),
            imageUrl: $image,
            schema: (new SchemaFactory())->collection($title, $description, (string) $url, $this->catalogItems())
        );
    }

    public function forProduct(\WC_Product $product): SeoDocument
    {
        $postId = $product->get_id();
        $title = $this->productTitle($product);

        $description = $this->customValue($postId, '_theobroma_seo_description');
        if ($description === '') {
            $details = $this->description($product->get_short_description() ?: $product->get_description(), '');
            $description = $details !== '' && mb_stripos($title, $details) === false
                ? $title . '. ' . $details
                : $title . '. Доставка по России в интернет-магазине «Пища Богов».';
        }
        $description = $this->description($description, sprintf('Купить %s в интернет-магазине «Пища Богов».', $title));

        $images = [];
        foreach (array_slice(array_values(array_filter(array_merge(
            [$product->get_image_id(), (int) $product->get_meta('_theobroma_product_detail_image_id')],
            $product->get_gallery_image_ids()
        ))), 0, 9) as $attachmentId) {
            $url = wp_get_attachment_image_url((int) $attachmentId, 'full');
            if (is_string($url) && $url !== '') {
                $images[] = $url;
            }
        }
        $customImage = $this->customValue($postId, '_theobroma_seo_og_image');

        $url = get_permalink($postId);
        $url = is_string($url) ? $url : home_url('/');
        $price = number_format((float) $product->get_price(), wc_get_price_decimals(), '.', '');
        $schema = (new SchemaFactory())->product([
            'name' => $this->customValue($postId, '_theobroma_seo_h1') ?: $product->get_name(),
            'description' => $description,
            'url' => $url,
            'sku' => $product->get_sku(),
            'images' => $images,
            'price' => $price,
            'currency' => get_woocommerce_currency(),
            'in_stock' => $product->is_in_stock(),
        ]);

        $socialImage = $this->globalSocialImage() ?: ($customImage !== ''
            ? esc_url_raw($customImage)
            : $this->socialImageForAttachment((int) ($product->get_image_id() ?: ($product->get_gallery_image_ids()[0] ?? 0))));

        return new SeoDocument(
            title: $title,
            description: $description,
            canonicalUrl: $url,
            type: 'product',
            siteName: $this->siteName(),
            imageUrl: $socialImage,
            schema: $schema
        );
    }

    public function productTitle(\WC_Product $product): string
    {
        $custom = $this->customValue($product->get_id(), '_theobroma_seo_title');
        if ($custom !== '') {
            return $custom;
        }

        $title = $product->get_name();
        $qualifier = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($product->get_short_description())));
        if ($qualifier !== '' && mb_strlen($qualifier) <= 70 && mb_stripos($title, $qualifier) === false) {
            return $title . ' — ' . $qualifier;
        }
        return $title;
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
            if ($post->post_type === 'theobroma_recipe') {
                $description = $title . '. ' . $description;
            }
        }
        $description = $this->description(
            $description,
            sprintf('%s — официальный сайт «Пища Богов».', $title)
        );
        $url = get_permalink($post);
        $url = is_string($url) ? $url : home_url('/');
        $image = $this->customValue($post->ID, '_theobroma_seo_og_image');
        $featured = get_the_post_thumbnail_url($post, 'full');
        $articleImage = $image !== '' ? $image : (is_string($featured) ? $featured : $this->defaultImage());
        if ($this->globalSocialImage() !== '') {
            $image = $this->globalSocialImage();
        } elseif ($image === '') {
            $image = $this->socialImageForAttachment((int) get_post_thumbnail_id($post));
        }

        $schema = (new SchemaFactory())->page($title, $description, $url);
        $type = 'website';
        if ($post->post_type === 'page' && $post->post_name === 'corporate-gifts' && function_exists('theobroma_corporate_questions')) {
            $schema = (new SchemaFactory())->faq(theobroma_corporate_questions(), $url);
        }
        if ($post->post_type === 'post') {
            $type = 'article';
            $author = get_the_author_meta('display_name', (int) $post->post_author);
            $schema = (new SchemaFactory())->article([
                'headline' => $title,
                'description' => $description,
                'url' => $url,
                'image' => $articleImage,
                'date_published' => get_post_time(DATE_W3C, true, $post),
                'date_modified' => get_post_modified_time(DATE_W3C, true, $post),
                'author' => is_string($author) && $author !== '' ? $author : 'Редакция Пища Богов',
                'logo' => $this->logoUrl(),
                'publisher' => $this->siteName(),
            ]);
        }
        if ($post->post_type === 'theobroma_recipe') {
            $schema = $this->recipeSchema($post, $description, $url);
        }
        if ($post->post_type === 'page' && in_array($post->post_name, ['media', 'recipes'], true)) {
            $items = [];
            foreach (get_posts(['post_type' => $post->post_name === 'media' ? 'post' : 'theobroma_recipe',
                'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order date', 'order' => 'ASC']) as $entry) {
                $items[] = ['name' => $entry->post_title, 'url' => (string) get_permalink($entry)];
            }
            $schema = (new SchemaFactory())->collection($title, $description, $url, $items);
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
            (string) get_option('theobroma_seo_home_description', get_option('blogdescription', '')),
            'Натуральный пористый шоколад Theobroma — интернет-магазин «Пища Богов».'
        );
        $logo = $this->logoUrl();

        return new SeoDocument(
            title: (string) get_option('theobroma_seo_home_title', 'Натуральный пористый шоколад — Theobroma Пища Богов'),
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
        return $this->globalSocialImage() ?: esc_url_raw(get_theme_file_uri('assets/images/social-preview.jpg'));
    }

    private function globalSocialImage(): string
    {
        return esc_url_raw(trim((string) get_option('theobroma_seo_social_image', '')));
    }

    private function socialImageForAttachment(int $attachmentId): string
    {
        if ($this->globalSocialImage() !== '') return $this->globalSocialImage();
        if ($attachmentId <= 0) {
            return $this->defaultImage();
        }

        $image = wp_get_attachment_image_url($attachmentId, 'full');
        $metadata = wp_get_attachment_metadata($attachmentId);
        if (!is_string($image) || $image === '' || !is_array($metadata)) {
            return $this->defaultImage();
        }

        $width = (int) ($metadata['width'] ?? 0);
        $height = (int) ($metadata['height'] ?? 0);
        // Portrait and square photos are unsuitable for wide link previews.
        if ($width < 600 || $height < 315 || $width / $height < 1.5) {
            return $this->defaultImage();
        }

        return esc_url_raw($image);
    }

    /** @return list<array{name:string,url:string}> */
    private function catalogItems(): array
    {
        $items = [];
        foreach (($GLOBALS['wp_query']->posts ?? []) as $post) {
            if (!$post instanceof \WP_Post || $post->post_type !== 'product' || $post->post_status !== 'publish') continue;
            $items[] = ['name' => $post->post_title, 'url' => (string) get_permalink($post)];
        }
        return $items;
    }

    private function recipeSchema(\WP_Post $post, string $description, string $url): array
    {
        $readRows = static function(string $key) use ($post): array {
            $value = get_post_meta($post->ID, $key, true);
            return is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
        };
        $ingredients = [];
        foreach ($readRows('_theobroma_ingredients') as $row) {
            if (trim((string) ($row['name'] ?? '')) !== '') $ingredients[] = trim(($row['amount'] ?? '') . ' ' . $row['name']);
        }
        $steps = [];
        foreach ($readRows('_theobroma_steps') as $row) {
            if (trim((string) ($row['text'] ?? '')) !== '') $steps[] = $row['text'];
        }
        $imageId = (int) get_post_meta($post->ID, '_theobroma_detail_image_id', true);
        $image = $imageId ? wp_get_attachment_image_url($imageId, 'full') : '';
        if (!$image) $image = get_theme_file_uri('assets/images/' . basename((string) get_post_meta($post->ID, '_theobroma_image', true)));
        preg_match('/^(\d+)\s*мин/u', (string) get_post_meta($post->ID, '_theobroma_cooking_time', true), $time);
        return (new SchemaFactory())->recipe(['name' => $this->customValue($post->ID, '_theobroma_seo_h1') ?: $post->post_title,
            'description' => $description, 'url' => $url, 'image' => (string) $image, 'author' => $this->siteName(),
            'ingredients' => $ingredients, 'steps' => $steps, 'minutes' => (int) ($time[1] ?? 0)]);
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
