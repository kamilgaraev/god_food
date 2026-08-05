<main class="buy-page">
    <section class="buy-intro">
        <div class="buy-decor buy-decor-left" aria-hidden="true"></div>
        <div class="buy-decor buy-decor-right" aria-hidden="true"></div>
        <nav class="buy-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Где купить</strong></nav>
        <h1><em>Покупайте</em> нашу продукцию</h1>
        <p class="buy-lead">В розничных и интернет магазинах наших партнёров</p>
        <nav class="buy-tabs" aria-label="Тип магазина"><a class="is-active" href="#boutiques">Бутики</a><a href="<?php echo esc_url(theobroma_page_url('Маркетплейсы')); ?>">Маркетплейсы</a><a href="#russia">Вся Россия</a></nav>
        <article class="buy-location" id="boutiques">
            <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/buy-aviapark.jpg'); ?>" width="520" height="240" loading="eager" decoding="async" alt="Бутик Theobroma в ТЦ Авиапарк">
            <h2>ТЦ «Авиапарк»</h2>
            <p>Ежедневно 10:00–22:00</p>
            <a class="button" href="https://yandex.ru/maps/?text=%D0%A2%D0%A6%20%D0%90%D0%B2%D0%B8%D0%B0%D0%BF%D0%B0%D1%80%D0%BA" target="_blank" rel="noopener">Как добраться</a>
        </article>
    </section>
    <?php get_template_part('template-parts/contact-section'); ?>
</main>
