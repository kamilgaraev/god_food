<?php
/** Plain text customer new account email. @version 10.9.0 */
defined('ABSPATH') || exit;
echo wp_strip_all_tags($email_heading) . "\n\n";
echo 'Здравствуйте, ' . $user_login . "!\n\n";
echo 'Рады, что вы с нами. Ваш аккаунт на ' . wp_parse_url(home_url('/'), PHP_URL_HOST) . " создан, а значит, путь к любимому шоколаду стал короче.\n\n";
echo "Напомним, что внутри каждого кусочка: какао-бобы, масло какао, натуральный подсластитель и экстракт ванили. Четыре ингредиента и ничего лишнего. Мы делаем абсолютно натуральный кусковый пористый шоколад вручную, небольшими партиями, на собственной фабрике в Подмосковье.\n\n";
echo "Ваши данные для входа\nИмя пользователя: " . $user_login . "\n";
echo $password_generated && $set_password_url
    ? 'Пароль: задайте пароль по ссылке ' . $set_password_url . "\n\n"
    : "Пароль: тот, который вы указали при регистрации.\n\n";
echo "В личном кабинете можно смотреть историю заказов, менять пароль и управлять данными доставки.\n\n";
echo 'Перейти в аккаунт: ' . wc_get_page_permalink('myaccount') . "\n\n";
echo "А если захочется выбрать что-то для себя или в подарок, весь ассортимент ждёт вас в каталоге.\n\n";
echo 'Выбрать шоколад: ' . wc_get_page_permalink('shop') . "\n\n";
echo "До встречи,\nкоманда Theobroma Пища Богов.\nНатуральный шоколад без белого сахара.\n\n";
if (!empty($additional_content)) {
    echo wp_strip_all_tags(wptexturize($additional_content)) . "\n\n";
}
echo wp_strip_all_tags(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
