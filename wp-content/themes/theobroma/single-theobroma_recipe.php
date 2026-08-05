<?php
declare(strict_types=1);

get_header();

while (have_posts()) {
    the_post();
    $post_id = get_the_ID();
    $ingredients = theobroma_recipe_rows($post_id, '_theobroma_ingredients');
    $steps = theobroma_recipe_rows($post_id, '_theobroma_steps');
    $accent = (string) get_post_meta($post_id, '_theobroma_accent', true);
    $heading = (string) get_post_meta($post_id, '_theobroma_heading', true);
    $image = (string) get_post_meta($post_id, '_theobroma_image', true);
    $detail_image_id = absint(get_post_meta($post_id, '_theobroma_detail_image_id', true));
    $detail_image_url = $detail_image_id ? wp_get_attachment_image_url($detail_image_id, 'full') : '';
    $layout = (string) get_post_meta($post_id, '_theobroma_layout', true);
    $product_ids = array_values(array_filter(array_map('absint', (array) get_post_meta($post_id, '_theobroma_product_ids', true))));
    ?>
    <main class="recipe-detail recipe-detail-<?php echo esc_attr($layout ?: 'standard'); ?>">
        <section class="recipe-detail-intro">
            <nav class="recipe-detail-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><a href="<?php echo esc_url(theobroma_page_url('Рецепты')); ?>">Рецепты</a><span>/</span><strong><?php the_title(); ?></strong></nav>
            <h1><em><?php echo esc_html($accent ?: 'Рецепт'); ?></em> <?php echo esc_html($heading ?: get_the_title()); ?></h1>
            <p class="recipe-detail-lead"><?php echo esc_html(get_the_excerpt()); ?></p>
            <i class="recipe-detail-decor recipe-detail-decor-left" aria-hidden="true"></i>
            <i class="recipe-detail-decor recipe-detail-decor-right" aria-hidden="true"></i>
            <div class="recipe-detail-columns">
                <article class="recipe-ingredients">
                    <h2>Ингредиенты для одной кружки:</h2>
                    <dl>
                        <?php foreach ($ingredients as $row) : ?>
                            <div><dt><?php echo esc_html($row['name'] ?? ''); ?></dt><dd><?php echo esc_html($row['amount'] ?? ''); ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </article>
                <article class="recipe-method">
                    <h2>Рецепт приготовления</h2>
                    <ol>
                        <?php foreach ($steps as $index => $row) : ?>
                            <li><b><?php echo esc_html((string) ($index + 1)); ?> шаг</b><p><?php echo esc_html($row['text'] ?? ''); ?></p></li>
                        <?php endforeach; ?>
                    </ol>
                    <?php $method_image = $detail_image_url ?: ($image ? get_template_directory_uri() . '/assets/images/' . $image : get_template_directory_uri() . '/assets/images/recipe-classic-detail.jpg'); ?>
                    <img src="<?php echo esc_url($method_image); ?>" width="1000" height="556" loading="eager" decoding="async" alt="<?php echo esc_attr(get_the_title()); ?>">
                </article>
            </div>
            <section class="recipe-product-promo">
                <h2><em>Какао-порошок</em> натуральный</h2>
                <div class="recipe-product-grid">
                    <?php if ($product_ids && function_exists('wc_get_product')) : ?>
                        <?php foreach ($product_ids as $product_id) : $product = wc_get_product($product_id); if (!$product) { continue; } ?>
                            <article>
                                <a href="<?php echo esc_url($product->get_permalink()); ?>"><?php echo $product->get_image('woocommerce_single', array('loading' => 'eager')); ?></a>
                                <p><?php echo wp_kses_post(wpautop($product->get_short_description())); ?></p>
                                <strong><?php echo wp_kses_post($product->get_price_html()); ?></strong>
                            </article>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?php for ($card = 0; $card < 3; $card++) : ?>
                            <article><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/recipe-product.jpg'); ?>" width="720" height="536" loading="eager" decoding="async" alt="Какао-порошок натуральный"><p>Прекрасный выбор ценителей<br>высококачественного и вкусного какао</p><strong>320₽</strong></article>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
                <a class="button" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')); ?>">Купить</a>
            </section>
        </section>
        <?php get_template_part('template-parts/contact-section'); ?>
    </main>
    <?php
}

get_footer();
