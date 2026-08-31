<main class="samples-page">
    <div class="samples-orbit samples-orbit-left" aria-hidden="true"></div>
    <div class="samples-orbit samples-orbit-right" aria-hidden="true"></div>

    <nav class="samples-breadcrumb" aria-label="Хлебные крошки">
        <a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Пробники шоколада</strong>
    </nav>

    <section class="samples-hero" aria-labelledby="samples-title">
        <div class="samples-hero-copy">
            <p class="samples-eyebrow">Для ресторанов, кофеен и кондитерских</p>
            <h1 id="samples-title">Запросить<br><em>пробники шоколада</em></h1>
            <p class="samples-lead">Познакомьтесь с шоколадом Theobroma до первой поставки. Соберём пробный набор продукции, чтобы вы могли оценить вкус, состав и то, как шоколад работает в вашем меню.</p>
            <ul class="samples-promises" aria-label="Что входит в предложение">
                <li>Натуральный состав</li>
                <li>Подбор под формат заведения</li>
                <li>Связь с менеджером</li>
            </ul>
        </div>
        <div class="samples-hero-visual" aria-hidden="true">
            <span class="samples-stamp">Пробный<br>набор</span>
            <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/cooperation-chocolate.webp'); ?>" width="321" height="315" loading="eager" decoding="async" alt="">
        </div>
    </section>

    <section class="samples-form-section" id="contact-form" aria-labelledby="samples-form-title">
        <div class="samples-form-intro">
            <p class="samples-eyebrow">Оставьте заявку</p>
            <h2 id="samples-form-title">Расскажите о вашем заведении</h2>
            <p>Название компании и ИНН нужны, чтобы мы сразу поняли формат сотрудничества и подготовили корректное предложение. Менеджер свяжется с вами для уточнения состава набора и доставки.</p>
            <div class="samples-form-note"><strong>Что дальше?</strong><span>Проверим заявку, уточним детали и согласуем отправку пробной продукции.</span></div>
        </div>

        <div class="samples-form-card">
            <?php if (($_GET['contact'] ?? '') === 'sent') : ?>
                <p class="samples-form-status is-success" role="status">Спасибо! Заявка отправлена. Мы свяжемся с вами.</p>
            <?php elseif (($_GET['contact'] ?? '') === 'error') : ?>
                <p class="samples-form-status is-error" role="alert">Не удалось отправить заявку. Проверьте обязательные поля.</p>
            <?php endif; ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="theobroma_contact">
                <input type="hidden" name="request_type" value="chocolate_samples">
                <?php wp_nonce_field('theobroma_contact', 'theobroma_contact_nonce'); ?>
                <?php theobroma_contact_antispam_fields(); ?>

                <div class="samples-form-grid">
                    <label class="samples-field samples-field-wide"><span>Название компании <b>*</b></span><input type="text" name="company" autocomplete="organization" maxlength="160" required></label>
                    <label class="samples-field"><span>ИНН <b>*</b></span><input type="text" name="inn" inputmode="numeric" autocomplete="off" pattern="[0-9]{10}|[0-9]{12}" maxlength="12" title="Введите 10 или 12 цифр" required></label>
                    <label class="samples-field"><span>Тип заведения</span><select name="venue_type"><option value="">Выберите вариант</option><option>Ресторан</option><option>Кофейня</option><option>Кондитерская</option><option>Отель</option><option>Другое</option></select></label>
                    <label class="samples-field"><span>Город</span><input type="text" name="city" autocomplete="address-level2" maxlength="120"></label>
                    <label class="samples-field"><span>Контактное лицо <b>*</b></span><input type="text" name="name" autocomplete="name" maxlength="120" required></label>
                    <label class="samples-field"><span>Телефон <b>*</b></span><span class="phone-field"><input type="tel" name="phone" inputmode="tel" autocomplete="tel" maxlength="18" required></span></label>
                    <label class="samples-field"><span>E-mail</span><input type="email" name="email" autocomplete="email" maxlength="254"></label>
                    <label class="samples-field samples-field-wide"><span>Комментарий</span><textarea name="message" rows="3" maxlength="2000" placeholder="Например, какой шоколад используете и сколько у вас точек"></textarea></label>
                </div>

                <label class="consent samples-consent"><input type="checkbox" name="consent" value="1" required><span>Отправляя форму, я даю <a href="<?php echo esc_url(theobroma_page_url('Согласие на обработку персональных данных')); ?>">согласие</a> на <a href="<?php echo esc_url(theobroma_page_url('Политика конфиденциальности')); ?>">обработку персональных данных</a></span></label>
                <button class="button samples-submit" type="submit">Запросить пробники</button>
            </form>
        </div>
    </section>

    <section class="samples-steps" aria-labelledby="samples-steps-title">
        <p class="samples-eyebrow">Просто и по делу</p>
        <h2 id="samples-steps-title">Как получить пробную продукцию</h2>
        <div class="samples-steps-grid">
            <article><span>01</span><h3>Заполните форму</h3><p>Укажите компанию, ИНН и контакты — это займёт пару минут.</p></article>
            <article><span>02</span><h3>Обсудим задачу</h3><p>Уточним формат заведения, ассортимент и предпочтения по вкусу.</p></article>
            <article><span>03</span><h3>Отправим набор</h3><p>Согласуем состав пробников и удобный способ получения продукции.</p></article>
        </div>
    </section>
</main>
