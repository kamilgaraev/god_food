<?php get_header(); ?>
<main>
    <div class="home-decor" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
    <section class="hero"><img class="hero-chocolate" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-original.webp'); ?>" width="458" height="573" decoding="async" fetchpriority="high" alt=""><div class="hero-inner"><h1><span class="source-text-reveal"><i><?php echo esc_html(theobroma_content('hero_line_1')); ?></i></span><span class="source-text-reveal"><i><?php echo esc_html(theobroma_content('hero_line_2')); ?></i></span><span class="source-text-reveal"><i><?php echo esc_html(theobroma_content('hero_line_3')); ?></i></span></h1><p><?php echo esc_html(theobroma_content('hero_subtitle')); ?></p><a class="button" href="#catalog">В каталог</a></div></section>
    <section class="section" id="catalog"><div class="section-heading"><h2 class="source-text-reveal"><span><em><?php echo esc_html(theobroma_content('products_accent')); ?></em> <?php echo esc_html(theobroma_content('products_heading')); ?></span></h2><p class="section-note"><?php echo esc_html(theobroma_content('products_note')); ?></p></div><div class="products">
        <?php
        $homepage_products = array();
        if (function_exists('wc_get_product_id_by_sku')) {
            foreach (array('theobroma-100-68-coriander', 'theobroma-30-59-cherry-buckwheat', 'theobroma-100-65-cinnamon', 'theobroma-30-goat') as $homepage_sku) {
                $homepage_product = wc_get_product(wc_get_product_id_by_sku($homepage_sku));
                if ($homepage_product instanceof WC_Product) {
                    $homepage_products[] = $homepage_product;
                }
            }
        }
        foreach ($homepage_products as $homepage_product) {
            get_template_part('template-parts/home/product-card', null, array('product' => $homepage_product));
        }
        ?>
    </div><p style="text-align:center"><a class="button" href="#contact-form">Купить</a></p></section>
    <section class="feature" id="about"><div class="about-stage">
        <img class="about-award" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/award.webp'); ?>" loading="lazy" decoding="async" fetchpriority="low" alt="Награда Theobroma">
        <?php $story_heading = theobroma_content('story_heading'); ?>
        <div class="story"><h2><em>Theobroma</em><?php echo wp_kses_post(nl2br(esc_html(str_replace('Theobroma', '', $story_heading)))); ?></h2><p><?php echo nl2br(esc_html(theobroma_content('story_text'))); ?></p></div>
        <div class="values">
            <article class="value"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/vector-4.svg'); ?>" loading="lazy" decoding="async" fetchpriority="low" alt=""><div><h3><?php echo esc_html(theobroma_content('value_1_title')); ?></h3><p><?php echo esc_html(theobroma_content('value_1_text_1')); ?></p><p><?php echo esc_html(theobroma_content('value_1_text_2')); ?></p></div></article>
            <article class="value"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/cacao.svg'); ?>" loading="lazy" decoding="async" fetchpriority="low" alt=""><div><h3><?php echo esc_html(theobroma_content('value_2_title')); ?></h3><p><?php echo esc_html(theobroma_content('value_2_text')); ?></p></div></article>
            <article class="value"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/cube.svg'); ?>" loading="lazy" decoding="async" fetchpriority="low" alt=""><div><h3><?php echo esc_html(theobroma_content('value_3_title')); ?></h3><p><?php echo esc_html(theobroma_content('value_3_text')); ?></p></div></article>
        </div>
    </div></section>
    <section class="reviews" id="reviews"><div class="reviews-stage"><div class="section-heading"><h2 class="source-text-reveal"><span><em><?php echo esc_html(theobroma_content('reviews_accent')); ?></em> <?php echo esc_html(theobroma_content('reviews_heading')); ?></span></h2><div class="review-controls" aria-label="Навигация по отзывам"><button type="button" data-review-direction="-1" aria-label="Предыдущие отзывы">‹</button><button type="button" data-review-direction="1" aria-label="Следующие отзывы">›</button></div></div><div class="review-grid">
        <?php $site_reviews = get_posts(array('post_type' => 'theobroma_review', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => array('menu_order' => 'ASC', 'date' => 'ASC'))); ?>
        <?php foreach ($site_reviews as $site_review) : ?>
            <article class="review"><p><?php echo wp_kses_post($site_review->post_content); ?></p><time><?php echo esc_html(get_the_date('d.m.Y', $site_review)); ?></time><strong><?php echo esc_html($site_review->post_title); ?></strong></article>
        <?php endforeach; ?>
    </div><div class="reviews-button"><a class="button" href="#catalog">Купить</a></div></div></section>
    <?php get_template_part('template-parts/contact-section'); ?>
</main>
<?php get_footer(); ?>
