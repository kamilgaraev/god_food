<?php
if (!have_posts()) {
    status_header(404);
    exit;
}

the_post();
$article_link = (string) get_post_meta(get_the_ID(), '_theobroma_article_link', true);
$related_product_ids = array_values(array_filter(array_map('absint', (array) get_post_meta(get_the_ID(), '_theobroma_product_ids', true))));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('media-article-view'); ?>>
<?php wp_body_open(); ?>
<header class="media-article-header">
    <a class="media-article-back" href="<?php echo esc_url(theobroma_page_url('Медиа')); ?>" aria-label="Вернуться в раздел Медиа">‹</a>
    <span>Пища богов</span>
</header>
<main class="media-article">
    <h1><?php the_title(); ?></h1>
    <?php if (has_post_thumbnail()) : ?>
        <figure><?php the_post_thumbnail('full', array('loading' => 'eager', 'fetchpriority' => 'high')); ?></figure>
    <?php endif; ?>
    <div class="media-article-copy"><?php echo wp_kses_post(get_the_content()); ?></div>
    <?php if ($related_product_ids && function_exists('wc_get_product')) : ?>
        <?php $related_products = array_filter(array_map('wc_get_product', $related_product_ids)); ?>
        <?php if ($related_products) : ?>
            <section class="media-article-products" aria-labelledby="media-article-products-title">
                <h2 id="media-article-products-title">Шоколад по теме статьи</h2>
                <div class="media-article-products-grid">
                    <?php foreach ($related_products as $product) : ?>
                        <article>
                            <a href="<?php echo esc_url($product->get_permalink()); ?>" data-product-modal-link>
                                <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail', array('loading' => 'lazy'))); ?>
                            </a>
                            <h3><a href="<?php echo esc_url($product->get_permalink()); ?>" data-product-modal-link><?php echo esc_html(theobroma_frontend_product_title($product->get_name(), $product->get_id())); ?></a></h3>
                            <strong><?php echo wp_kses_post($product->get_price_html()); ?></strong>
                            <a class="button" href="<?php echo esc_url($product->get_permalink()); ?>" data-product-modal-link>Купить</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($article_link !== '') : ?>
        <a class="media-article-source" href="<?php echo esc_url($article_link); ?>" target="_blank" rel="noopener noreferrer">Читать статью</a>
    <?php endif; ?>
    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('d.m.Y')); ?></time>
</main>
<?php wp_footer(); ?>
</body>
</html>
