<?php
/** Customer new account email. @version 10.9.0 */
defined('ABSPATH') || exit;
do_action('woocommerce_email_header', $email_heading, $email);
$account_url = wc_get_page_permalink('myaccount');
$catalog_url = wc_get_page_permalink('shop');
?>
<p>Здравствуйте, <?php echo esc_html($user_login); ?>!</p>
<p>Рады, что вы с нами. Ваш аккаунт на <?php echo esc_html(wp_parse_url(home_url('/'), PHP_URL_HOST)); ?> создан, а значит, путь к любимому шоколаду стал короче.</p>
<p>Напомним, что внутри каждого кусочка: какао-бобы, масло какао, натуральный подсластитель и экстракт ванили. Четыре ингредиента и ничего лишнего. Мы делаем абсолютно натуральный кусковый пористый шоколад вручную, небольшими партиями, на собственной фабрике в Подмосковье.</p>
<h2>Ваши данные для входа</h2>
<p>Имя пользователя: <strong><?php echo esc_html($user_login); ?></strong></p>
<?php if ($password_generated && $set_password_url) : ?>
    <p>Пароль: <a href="<?php echo esc_url($set_password_url); ?>">задайте пароль по этой ссылке</a>.</p>
<?php else : ?>
    <p>Пароль: тот, который вы указали при регистрации.</p>
<?php endif; ?>
<p>В личном кабинете можно смотреть историю заказов, менять пароль и управлять данными доставки.</p>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:24px 0;width:100%;"><tr><td align="center"><a class="theobroma-email-button" href="<?php echo esc_url($account_url); ?>" style="display:inline-block;padding:14px 24px;background:#b0903d;border:1px solid #b0903d;border-radius:28px;color:#fff;font:600 14px/1.4 Arial,sans-serif;text-decoration:none;">Перейти в аккаунт</a></td></tr></table>
<p>А если захочется выбрать что-то для себя или в подарок, весь ассортимент ждёт вас в каталоге.</p>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:24px 0;width:100%;"><tr><td align="center"><a class="theobroma-email-button" href="<?php echo esc_url($catalog_url); ?>" style="display:inline-block;padding:14px 24px;background:#fbf7f1;border:1px solid #b0903d;border-radius:28px;color:#8a6e27;font:600 14px/1.4 Arial,sans-serif;text-decoration:none;">Выбрать шоколад</a></td></tr></table>
<p>До встречи,<br>команда Theobroma Пища Богов.<br>Натуральный шоколад без белого сахара.</p>
<?php
if (!empty($additional_content)) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}
do_action('woocommerce_email_footer', $email);
