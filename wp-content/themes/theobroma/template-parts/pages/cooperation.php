<main class="cooperation-page">
    <div class="cooperation-decor cooperation-decor-left" aria-hidden="true"></div>
    <div class="cooperation-decor cooperation-decor-right" aria-hidden="true"></div>
    <nav class="cooperation-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Сотрудничество</strong></nav>
    <h1>Сотрудничество</h1>
    <p class="cooperation-lead">Чистота, надежность, честность — три символа успешного сотрудничества.<br>Мы уверены, что Вы придерживаетесь тех же принципов и правил,<br>поэтому с удовольствием приглашаем Вас к совместному партнёрству!</p>
    <img class="cooperation-chocolate" src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/cooperation-chocolate.webp'); ?>" width="321" height="315" loading="eager" decoding="async" alt="">
    <section class="cooperation-form" aria-labelledby="cooperation-form-title">
        <h2 id="cooperation-form-title">Заполните форму</h2>
        <p>Мы отправим Вам условия сотрудничества</p>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="theobroma_contact">
            <?php wp_nonce_field('theobroma_contact', 'theobroma_contact_nonce'); ?>
            <div class="form-grid"><input type="text" name="name" placeholder="Имя" aria-label="Имя" required><div class="phone-field"><span class="phone-flag" aria-hidden="true"></span><span class="phone-triangle" aria-hidden="true"></span><span class="phone-code" aria-hidden="true">+7</span><input type="tel" name="phone" placeholder="(000) 000-00-00" aria-label="Телефон" required></div><input class="message-field" type="text" name="message" placeholder="Ваш вопрос или комментарий" aria-label="Ваш вопрос"></div>
            <label class="consent"><input type="checkbox" name="consent" value="1" required><span>Отправляя форму я даю&nbsp;<a href="<?php echo esc_url(theobroma_page_url('Согласие на обработку персональных данных')); ?>">согласие</a>&nbsp;на&nbsp;<a href="<?php echo esc_url(theobroma_page_url('Политика конфиденциальности')); ?>">обработку персональных данных</a></span></label>
            <p class="form-submit"><button class="button" type="submit">Отправить</button></p>
        </form>
    </section>
    <section class="cooperation-benefits" aria-labelledby="cooperation-benefits-title">
        <h2 id="cooperation-benefits-title"><em>Преимущества</em> при работе с нами</h2>
        <div class="cooperation-benefit-grid">
            <article><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/benefit-logistics.webp'); ?>" alt=""><h3>Быстрая и надёжная<br>логистика</h3><ul><li>Отгрузка в течение нескольких дней после согласования заказа</li><li>Своевременное пополнение полок минимизирует риск кассовых разрывов</li><li>Только свежая продукция с оптимальным сроком годности</li></ul></article>
            <article><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/benefit-natural.webp'); ?>" alt=""><h3>Натуральный<br>состав</h3><ul><li>100% чистые ингредиенты без лишних добавок</li><li>Продукт формирует доверие и привлекает аудиторию, ориентированную на качество</li><li>Высокая лояльность и повторные продажи</li></ul></article>
            <article><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/benefit-production.webp'); ?>" alt=""><h3>Собственное<br>производство</h3><ul><li>Современные технологии и полный контроль на каждом этапе</li><li>Строгие стандарты чистоты и безопасности</li><li>Стабильные поставки и выполнение заказов в срок</li></ul></article>
            <article><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/benefit-marketing.webp'); ?>" alt=""><h3>Маркетинговая<br>поддержка</h3><ul><li>Продвижение среди лидеров мнений и в соцсетях</li><li>Качественные фото, POS-материалы и бесплатные дегустации</li><li>Информационная поддержка партнёров на наших ресурсах</li></ul></article>
        </div>
    </section>
</main>
