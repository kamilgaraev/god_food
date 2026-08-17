<?php
get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$gift_url = theobroma_page_url('Корпоративные подарки');
$where_url = theobroma_page_url('Где купить');
$cooperation_url = theobroma_page_url('Сотрудничество');
$homepage_products = theobroma_homepage_products();
$cacao_groups = theobroma_home_cacao_groups();
$cacao_profiles = theobroma_cacao_profiles();
$cacao_scale = array(
    59 => array('percentage' => 55, 'label' => 'мягкий'),
    70 => array('percentage' => 72, 'label' => 'классический'),
    80 => array('percentage' => 85, 'label' => 'глубокий'),
    68 => array('percentage' => 92, 'label' => 'строгий'),
    65 => array('percentage' => 99, 'label' => 'чистый'),
);
$cacao_options = array();
foreach ($cacao_scale as $actual_percentage => $display) {
    if (isset($cacao_groups[$actual_percentage])) {
        $cacao_options[$actual_percentage] = array_merge($display, array('group' => $cacao_groups[$actual_percentage]));
    }
}
$default_percentage = isset($cacao_groups[70]) ? 70 : (int) (array_key_first($cacao_groups) ?? 0);
$default_group = $default_percentage > 0 ? $cacao_groups[$default_percentage] : null;
$default_product = is_array($default_group) ? $default_group['representative'] : null;
$default_profile = $cacao_profiles[$default_percentage] ?? array('label' => '', 'description' => '');
$default_display = $cacao_scale[$default_percentage] ?? array('percentage' => $default_percentage, 'label' => $default_profile['label']);
$default_image_id = $default_product instanceof WC_Product
    ? (int) ($default_product->get_meta('_theobroma_product_detail_image_id', true) ?: $default_product->get_image_id())
    : 0;
$default_image_url = $default_image_id ? (string) wp_get_attachment_image_url($default_image_id, 'large') : '';
?>
<main<?php echo is_front_page() ? ' id="theobroma-main" tabindex="-1"' : ''; ?>>
    <section class="home-hero" aria-labelledby="home-hero-title">
        <div class="home-hero__shell">
            <p class="home-eyebrow">Абсолютно натуральный</p>
            <h1 id="home-hero-title">ШОКОЛАД</h1>
            <div class="home-hero__lead">
                <p>Четыре ингредиента. Пористая кусковая текстура, которой нет ни у одной плитки в магазине.</p>
                <div class="home-hero__actions">
                    <a class="home-button home-button--primary" href="#cacao-selector">Выберите свой вкус</a>
                    <a class="home-button home-button--secondary" href="<?php echo esc_url($gift_url); ?>">Подарочные наборы</a>
                </div>
            </div>
            <div class="home-hero__trust" aria-label="Гликемический индекс 35 вместо 70, рейтинг 4,9 по 1 200 отзывам">
                <div>
                    <strong>ГИ 35</strong>
                    <span>вместо 70</span>
                </div>
                <div>
                    <strong>4,9</strong>
                    <span>1 200 отзывов</span>
                </div>
            </div>
        </div>
    </section>

    <?php $home_benefits = array('Без белого сахара', 'Без заменителей какао-масла', 'Своя фабрика', 'Бесплатная доставка от 2 500 ₽'); ?>
    <div class="home-benefit-strip" role="group" aria-label="Преимущества Theobroma: <?php echo esc_attr(implode(', ', $home_benefits)); ?>">
        <div class="home-benefit-strip__track" aria-hidden="true">
            <?php for ($group_index = 0; $group_index < 2; $group_index++) : ?>
                <div class="home-benefit-strip__group">
                    <?php for ($repeat_index = 0; $repeat_index < 3; $repeat_index++) : ?>
                        <?php foreach ($home_benefits as $home_benefit) : ?>
                            <span><?php echo esc_html($home_benefit); ?></span><i></i>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <section class="home-catalog" id="catalog" aria-labelledby="home-catalog-title">
        <div class="home-section-heading">
            <h2 id="home-catalog-title">Каталог</h2>
            <a href="<?php echo esc_url($shop_url); ?>">Весь каталог</a>
        </div>
        <?php if ($homepage_products) : ?>
            <div class="home-product-grid">
                <?php foreach ($homepage_products as $index => $homepage_product) : ?>
                    <?php get_template_part('template-parts/home/product-card', null, array('product' => $homepage_product, 'bestseller' => $index === 0)); ?>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="home-empty-state">Популярные товары скоро появятся в каталоге.</p>
        <?php endif; ?>
        <div class="home-catalog__footer"><a class="home-button home-button--primary" href="<?php echo esc_url($shop_url); ?>">Перейти в каталог</a></div>
    </section>

    <section class="home-cacao" id="cacao-selector" aria-labelledby="home-cacao-title">
        <div class="home-cacao__shell">
            <div class="home-cacao__selector">
                <p class="home-kicker">Дегустационная шкала — выберите процент</p>
                <h2 id="home-cacao-title">Ваш процент какао</h2>
                <p class="home-cacao__intro">От мягких 55% до чистых 99%. Выберите крепость — покажем вкус, сахар и повод.</p>
                <?php if ($cacao_options) : ?>
                    <div class="home-cacao__tabs" role="tablist" aria-label="Процент какао">
                        <?php foreach ($cacao_options as $percentage => $option) : ?>
                            <?php
                            $group = $option['group'];
                            $product = $group['representative'];
                            $profile = $cacao_profiles[$percentage] ?? array('label' => '', 'description' => '');
                            $image_id = (int) ($product->get_meta('_theobroma_product_detail_image_id', true) ?: $product->get_image_id());
                            $image_url = $image_id ? (string) wp_get_attachment_image_url($image_id, 'large') : '';
                            $selected = $percentage === $default_percentage;
                            ?>
                            <button
                                type="button"
                                role="tab"
                                id="home-cacao-tab-<?php echo esc_attr((string) $percentage); ?>"
                                aria-selected="<?php echo $selected ? 'true' : 'false'; ?>"
                                aria-controls="home-cacao-panel"
                                tabindex="<?php echo $selected ? '0' : '-1'; ?>"
                                data-cacao-option="<?php echo esc_attr((string) $percentage); ?>"
                                data-percent="<?php echo esc_attr((string) $percentage); ?>"
                                data-title="<?php echo esc_attr(mb_convert_case($option['label'], MB_CASE_TITLE, 'UTF-8') . ' ' . $option['percentage'] . '%'); ?>"
                                data-description="<?php echo esc_attr($profile['description']); ?>"
                                data-fact="<?php echo esc_attr(wp_strip_all_tags($product->get_short_description())); ?>"
                                data-price="<?php echo esc_attr('от ' . wp_strip_all_tags(wc_price($group['minimum_price']))); ?>"
                                data-url="<?php echo esc_url(theobroma_cacao_catalog_url($percentage)); ?>"
                                data-image="<?php echo esc_url($image_url); ?>"
                                data-image-alt="<?php echo esc_attr($product->get_name()); ?>"
                            ><strong><?php echo esc_html((string) $option['percentage']); ?>%</strong><span><?php echo esc_html($option['label']); ?></span></button>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="home-empty-state">Подборка шоколада временно недоступна.</p>
                <?php endif; ?>
            </div>

            <?php if ($default_product instanceof WC_Product && is_array($default_group)) : ?>
                <div class="home-cacao__panel" id="home-cacao-panel" role="tabpanel" aria-labelledby="home-cacao-tab-<?php echo esc_attr((string) $default_percentage); ?>" aria-live="polite" data-cacao-panel>
                    <div class="home-cacao__image-wrap">
                        <img src="<?php echo esc_url($default_image_url); ?>" alt="<?php echo esc_attr($default_product->get_name()); ?>" loading="lazy" decoding="async" fetchpriority="low">
                    </div>
                    <div class="home-cacao__copy">
                        <h3 data-cacao-title><?php echo esc_html(mb_convert_case($default_display['label'], MB_CASE_TITLE, 'UTF-8') . ' ' . $default_display['percentage'] . '%'); ?></h3>
                        <p class="home-cacao__description" data-cacao-description><?php echo esc_html($default_profile['description']); ?></p>
                        <p class="home-cacao__fact" data-cacao-fact><?php echo esc_html(wp_strip_all_tags($default_product->get_short_description())); ?></p>
                        <div class="home-cacao__buy">
                            <a class="home-button home-button--primary" href="<?php echo esc_url(theobroma_cacao_catalog_url($default_percentage)); ?>">Купить</a>
                            <strong><?php echo wp_kses_post('от ' . wc_price($default_group['minimum_price'])); ?></strong>
                        </div>
                    </div>
                </div>
                <noscript>
                    <ul class="home-cacao__noscript">
                        <?php foreach ($cacao_options as $percentage => $option) : ?>
                            <li><a href="<?php echo esc_url(theobroma_cacao_catalog_url($percentage)); ?>"><?php echo esc_html($option['percentage'] . '% — ' . $option['label']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </noscript>
            <?php endif; ?>
        </div>
    </section>

    <section class="home-composition" aria-labelledby="home-composition-title">
        <div class="home-composition__shell">
            <div class="home-composition__intro"><p class="home-kicker">Состав</p><h2 id="home-composition-title">Читать этикетку приятно</h2><p>Какао-бобы, какао-масло, натуральный сахар и ваниль. Всё.</p></div>
            <dl>
                <div><dt>0%</dt><dd>белого сахара</dd></div>
                <div><dt>0</dt><dd>заменителей какао-масла</dd></div>
                <div><dt>35</dt><dd>гликемический индекс</dd></div>
                <div><dt>4</dt><dd>ингредиента в плитке</dd></div>
            </dl>
        </div>
    </section>

    <section class="home-promo-grid" aria-label="Подарки и точки продаж">
        <article class="home-promo-card home-promo-card--gift">
            <h2>Подарок, который не стыдно вручить</h2>
            <p>Наборы в крафтовой коробке с открыткой. Для компаний — от 20 штук с логотипом и своей вкладкой.</p>
            <a class="home-button" href="<?php echo esc_url($gift_url); ?>">Собрать набор</a>
        </article>
        <article class="home-promo-card home-promo-card--where">
            <h2>Где купить</h2>
            <nav aria-label="Маркетплейсы Theobroma">
                <a href="https://www.ozon.ru/seller/theobroma-pishcha-bogov/produkty-pitaniya-9200/?miniapp=seller_60476" target="_blank" rel="noopener">Ozon</a>
                <a href="https://www.wildberries.ru/seller/260547" target="_blank" rel="noopener">Wildberries</a>
                <a href="<?php echo esc_url($where_url); ?>">Яндекс Маркет</a>
                <a href="<?php echo esc_url($where_url); ?>">ВкусВилл</a>
            </nav>
            <p>Розничные партнёры в 14 городах и оптовые поставки с фабрики. <a href="<?php echo esc_url($cooperation_url); ?>">Запросить прайс</a></p>
        </article>
    </section>

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
