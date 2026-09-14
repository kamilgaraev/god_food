<?php
$boutiques = function_exists('theobroma_buy_get_entries') ? theobroma_buy_get_entries('theobroma_boutique') : array();
$partners = function_exists('theobroma_buy_get_entries') ? theobroma_buy_get_entries('theobroma_partner') : array();
?>
<main class="buy-page">
    <section class="buy-intro">
        <div class="buy-decor buy-decor-left" aria-hidden="true"></div>
        <div class="buy-decor buy-decor-right" aria-hidden="true"></div>
        <nav class="buy-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Где купить</strong></nav>
        <h1><em>Покупайте</em> нашу продукцию</h1>
        <p class="buy-lead">В розничных и интернет магазинах наших партнёров</p>

        <nav class="buy-tabs" role="tablist" aria-label="Тип магазина">
            <button id="buy-tab-1" type="button" role="tab" aria-controls="bulletcities1" aria-selected="true">Бутики</button>
            <button id="buy-tab-3" type="button" role="tab" aria-controls="bulletcities3" aria-selected="false" tabindex="-1">Вся Россия</button>
        </nav>

        <div class="buy-panels">
            <section class="buy-panel" id="bulletcities1" role="tabpanel" aria-labelledby="buy-tab-1">
                <?php if ($boutiques !== array()) : ?>
                    <div class="buy-location-grid">
                        <?php foreach ($boutiques as $boutique) : ?>
                            <?php
                            $boutique_id = $boutique->ID;
                            $image_url = theobroma_buy_image_url($boutique_id, 'full');
                            $address = theobroma_buy_meta('address', $boutique_id);
                            $hours = theobroma_buy_meta('hours', $boutique_id);
                            $map_url = theobroma_buy_meta('map_url', $boutique_id);
                            ?>
                            <article class="buy-location">
                                <?php if ($image_url !== '') : ?><img src="<?php echo esc_url($image_url); ?>" width="520" height="240" loading="eager" decoding="async" alt="<?php echo esc_attr(get_the_title($boutique_id)); ?>">
                                <?php else : ?><div class="buy-location-image-fallback" aria-hidden="true"><span class="dashicons dashicons-store"></span></div><?php endif; ?>
                                <h2><?php echo esc_html(get_the_title($boutique_id)); ?></h2>
                                <?php if ($address !== '') : ?><p class="buy-location-address"><?php echo esc_html($address); ?></p><?php endif; ?>
                                <?php if ($hours !== '') : ?><p><?php echo esc_html($hours); ?></p><?php endif; ?>
                                <?php if ($map_url !== '') : ?><a class="button" href="<?php echo esc_url($map_url); ?>" target="_blank" rel="noopener">Как добраться</a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="buy-empty-state">Бутики скоро появятся.</p>
                <?php endif; ?>
            </section>

            <section class="buy-panel" id="bulletcities3" role="tabpanel" aria-labelledby="buy-tab-3" hidden>
                <?php if ($partners !== array()) : ?>
                    <div class="buy-partner-grid buy-russia-grid">
                        <?php foreach ($partners as $partner) : ?>
                            <?php
                            $partner_id = $partner->ID;
                            $image_url = theobroma_buy_image_url($partner_id, 'full');
                            $store_url = theobroma_buy_meta('store_url', $partner_id);
                            $partner_content = static function () use ($partner_id, $image_url): void {
                                if ($image_url !== '') {
                                    echo '<img class="buy-partner-logo" src="' . esc_url($image_url) . '" width="160" height="80" loading="lazy" decoding="async" alt="' . esc_attr(get_the_title($partner_id)) . '">';
                                } else {
                                    echo '<span class="buy-partner-logo buy-partner-logo--empty" aria-hidden="true"><span class="dashicons dashicons-store"></span></span>';
                                }
                            };
                            ?>
                            <?php if ($store_url !== '') : ?><a class="buy-partner-card" href="<?php echo esc_url($store_url); ?>" target="_blank" rel="noopener">
                            <?php else : ?><article class="buy-partner-card">
                            <?php endif; ?>
                                <?php $partner_content(); ?>
                                <div class="buy-partner-meta">
                                    <strong><?php echo esc_html(get_the_title($partner_id)); ?></strong>
                                    <?php $city = theobroma_buy_meta('city', $partner_id); ?>
                                    <?php if ($city !== '') : ?><span class="buy-partner-city">· <?php echo esc_html($city); ?> ·</span><?php endif; ?>
                                </div>
                            <?php if ($store_url !== '') : ?></a><?php else : ?></article><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="buy-empty-state">Партнёры скоро появятся.</p>
                <?php endif; ?>
            </section>
        </div>
    </section>
    <?php get_template_part('template-parts/contact-section'); ?>
</main>
