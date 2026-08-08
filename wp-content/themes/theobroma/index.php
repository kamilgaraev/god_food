<?php
get_header();

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$gift_url = theobroma_page_url('Корпоративные подарки');
$where_url = theobroma_page_url('Где купить');
$cooperation_url = theobroma_page_url('Сотрудничество');
$homepage_products = theobroma_homepage_products();
$cacao_groups = theobroma_home_cacao_groups();
$cacao_profiles = theobroma_cacao_profiles();
$default_percentage = isset($cacao_groups[70]) ? 70 : (int) (array_key_first($cacao_groups) ?? 0);
$default_group = $default_percentage > 0 ? $cacao_groups[$default_percentage] : null;
$default_product = is_array($default_group) ? $default_group['representative'] : null;
$default_profile = $cacao_profiles[$default_percentage] ?? array('label' => '', 'description' => '');
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
            <picture>
                <source
                    media="(max-width: 800px)"
                    srcset="<?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-240.webp'); ?> 240w, <?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-360.webp'); ?> 360w"
                    sizes="240px"
                >
                <img class="home-hero__chocolate" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/hero-chocolate-360.webp'); ?>" width="360" height="450" fetchpriority="high" alt="Пористые куски натурального шоколада Theobroma">
            </picture>
            <div class="home-hero__lead">
                <p>Необычный кусковой пористый шоколад. Четыре ингредиента. Текстура, которой нет ни у одной плитки в магазине.</p>
                <div class="home-hero__actions">
                    <a class="home-button home-button--primary" href="#cacao-selector">Выберите свой вкус</a>
                    <a class="home-button home-button--secondary" href="<?php echo esc_url($shop_url); ?>">Перейти в каталог</a>
                </div>
            </div>
            <div class="home-hero__trust" aria-label="Гликемический индекс продукта 35">
                <strong>ГИ 35</strong>
                <span>гликемический индекс</span>
            </div>
        </div>
    </section>

    <div class="home-benefit-strip" aria-label="Преимущества Theobroma">
        <div>
            <span>Без белого сахара</span><i aria-hidden="true"></i>
            <span>Без заменителей какао-масла</span><i aria-hidden="true"></i>
            <span>Своя фабрика</span><i aria-hidden="true"></i>
            <span>Бесплатная доставка от 2 500 ₽</span>
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
                <p class="home-cacao__intro">От мягких 59% до глубоких 80%. Выберите крепость — покажем вкус и доступные варианты.</p>
                <?php if ($cacao_groups) : ?>
                    <div class="home-cacao__tabs" role="tablist" aria-label="Процент какао">
                        <?php foreach ($cacao_groups as $percentage => $group) : ?>
                            <?php
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
                                data-title="<?php echo esc_attr(mb_convert_case($profile['label'], MB_CASE_TITLE, 'UTF-8') . ' ' . $percentage . '%'); ?>"
                                data-description="<?php echo esc_attr($profile['description']); ?>"
                                data-fact="<?php echo esc_attr(wp_strip_all_tags($product->get_short_description())); ?>"
                                data-price="<?php echo esc_attr('от ' . wp_strip_all_tags(wc_price($group['minimum_price']))); ?>"
                                data-url="<?php echo esc_url(theobroma_cacao_catalog_url($percentage)); ?>"
                                data-image="<?php echo esc_url($image_url); ?>"
                                data-image-alt="<?php echo esc_attr($product->get_name()); ?>"
                            ><strong><?php echo esc_html((string) $percentage); ?>%</strong><span><?php echo esc_html($profile['label']); ?></span></button>
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
                        <h3 data-cacao-title><?php echo esc_html(mb_convert_case($default_profile['label'], MB_CASE_TITLE, 'UTF-8') . ' ' . $default_percentage . '%'); ?></h3>
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
                        <?php foreach ($cacao_groups as $percentage => $group) : ?>
                            <li><a href="<?php echo esc_url(theobroma_cacao_catalog_url($percentage)); ?>"><?php echo esc_html($percentage . '% — ' . ($cacao_profiles[$percentage]['label'] ?? 'шоколад')); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </noscript>
            <?php endif; ?>
        </div>
    </section>

    <section class="home-composition" aria-labelledby="home-composition-title">
        <div class="home-composition__intro"><p class="home-kicker">Состав</p><h2 id="home-composition-title">Читать этикетку приятно</h2><p>Какао-бобы, какао-масло, натуральный сахар и ваниль. Всё.</p></div>
        <dl>
            <div><dt>0%</dt><dd>белого сахара</dd></div>
            <div><dt>0</dt><dd>заменителей какао-масла</dd></div>
            <div><dt>35</dt><dd>гликемический индекс</dd></div>
            <div><dt>4</dt><dd>ингредиента в плитке</dd></div>
        </dl>
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
                <a href="https://www.ozon.ru/brand/theobroma-100844204/" target="_blank" rel="noopener">Ozon</a>
                <a href="https://www.wildberries.ru/brands/theobroma" target="_blank" rel="noopener">Wildberries</a>
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
