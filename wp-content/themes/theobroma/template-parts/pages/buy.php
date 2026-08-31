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
                <article class="buy-location">
                    <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/buy-aviapark.jpg'); ?>" width="520" height="240" loading="eager" decoding="async" alt="Бутик Theobroma в ТЦ Авиапарк">
                    <h2>ТЦ «Авиапарк»</h2>
                    <p>Ежедневно 10:00–22:00</p>
                    <a class="button" href="https://yandex.ru/maps/?text=%D0%A2%D0%A6%20%D0%90%D0%B2%D0%B8%D0%B0%D0%BF%D0%B0%D1%80%D0%BA" target="_blank" rel="noopener">Как добраться</a>
                </article>
            </section>

            <section class="buy-panel" id="bulletcities3" role="tabpanel" aria-labelledby="buy-tab-3" hidden>
                <?php
                $partners = array(
                    array('ashanti.png', 'Ashanti', 'Москва'),
                    array('jagannath.png', 'Джаганнат', 'Москва'),
                    array('white-clouds.png', 'Белые облака', 'Москва'),
                    array('vidzhai.png', 'Виджай', 'Москва'),
                    array('green-cardamon.png', 'Green Cardamon', 'Москва'),
                    array('sattva.png', 'Sattva', 'Москва'),
                    array('delikateska.png', 'Деликатеска', 'Москва'),
                    array('naturalista.png', 'Naturalista', 'Самара'),
                    array('ukrop.png', 'Укроп', 'Челябинск'),
                    array('kunzhut.png', 'Кунжут', 'Челябинск'),
                    array('mishkin-gostinets.png', 'Мишкин гостинец', 'Нижний Тагил'),
                );
                ?>
                <div class="buy-partner-grid buy-russia-grid">
                    <?php foreach ($partners as $partner) : ?>
                        <article class="buy-partner-card"><img class="buy-partner-logo" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/partners/' . $partner[0]); ?>" width="160" height="80" loading="lazy" decoding="async" alt="<?php echo esc_attr($partner[1]); ?>"><span>· <?php echo esc_html($partner[2]); ?> ·</span></article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </section>
    <?php get_template_part('template-parts/contact-section'); ?>
</main>
