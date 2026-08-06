<main class="corporate-gifts-page">
    <section class="corporate-gifts-hero">
        <p class="corporate-gifts-eyebrow">Theobroma для бизнеса</p>
        <h1>Корпоративные подарки<br><em>со вкусом заботы</em></h1>
        <p>Создадим шоколадные подарки для клиентов, команды и партнёров: от готовых наборов до брендированных решений под ваш повод.</p>
        <a class="button" href="#corporate-request">Обсудить проект</a>
    </section>
    <section class="corporate-gifts-benefits" aria-label="Преимущества корпоративных подарков">
        <article><h2>Натуральный состав</h2><p>Шоколад без белого сахара и искусственных добавок.</p></article>
        <article><h2>Ваше брендирование</h2><p>Подберём оформление, открытки и формат набора под задачу.</p></article>
        <article><h2>Тираж и логистика</h2><p>Согласуем минимальный заказ, сроки производства и доставку.</p></article>
    </section>
    <section class="corporate-gifts-request" id="corporate-request" aria-labelledby="corporate-request-title">
        <div><p class="corporate-gifts-eyebrow">Индивидуальный расчёт</p><h2 id="corporate-request-title">Расскажите о вашем проекте</h2><p>Менеджер свяжется с вами, уточнит детали и подготовит предложение.</p></div>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="theobroma_contact"><input type="hidden" name="request_type" value="corporate_gift">
            <?php wp_nonce_field('theobroma_contact', 'theobroma_contact_nonce'); ?>
            <input name="name" required placeholder="Имя" aria-label="Имя"><input name="company" placeholder="Компания" aria-label="Компания"><input type="email" name="email" required placeholder="E-mail" aria-label="E-mail"><input type="tel" name="phone" required placeholder="Телефон" aria-label="Телефон"><select name="gift_type" aria-label="Тип подарка"><option value="">Тип подарка</option><option>Для клиентов</option><option>Для команды</option><option>Для партнёров</option></select><input name="volume" placeholder="Количество подарков" aria-label="Количество подарков"><select name="branding" aria-label="Брендирование"><option value="">Нужно ли брендирование?</option><option>Да</option><option>Нет</option></select><textarea name="message" placeholder="Повод, пожелания, сроки" aria-label="Комментарий"></textarea>
            <label><input type="checkbox" name="consent" value="1" required> Я согласен на обработку персональных данных</label><button class="button" type="submit">Отправить заявку</button>
        </form>
    </section>
</main>
