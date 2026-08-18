<section class="contact" id="contact-form">
    <div class="section">
        <div class="contact-card">
            <h2 class="source-text-reveal"><span><?php echo esc_html(theobroma_content('contact_heading')); ?> <em><?php echo esc_html(theobroma_content('contact_accent')); ?></em></span></h2>
            <?php if (isset($_GET['contact']) && $_GET['contact'] === 'sent') : ?>
                <p class="form-message"><?php echo esc_html(theobroma_content('contact_success')); ?></p>
            <?php endif; ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="theobroma_contact">
                <?php wp_nonce_field('theobroma_contact', 'theobroma_contact_nonce'); ?>
                <?php theobroma_contact_antispam_fields(); ?>
                <div class="form-grid">
                    <input type="text" name="name" placeholder="Имя" aria-label="Имя" required>
                    <div class="phone-field">
                        <input type="tel" name="phone" value="+7" placeholder="+7 (000) 000-00-00" inputmode="tel" autocomplete="tel" maxlength="18" aria-label="Телефон" required>
                    </div>
                    <input class="message-field" type="text" name="message" placeholder="Ваш вопрос или комментарий" aria-label="Ваш вопрос">
                </div>
                <label class="consent">
                    <input type="checkbox" name="consent" value="1" required>
                    <span>Отправляя форму я даю <a href="<?php echo esc_url(theobroma_page_url('Согласие на обработку персональных данных')); ?>">согласие</a> на <a href="<?php echo esc_url(theobroma_page_url('Политика конфиденциальности')); ?>">обработку персональных данных</a></span>
                </label>
                <p class="form-submit"><button class="button" type="submit">Отправить</button></p>
            </form>
        </div>
    </div>
</section>
