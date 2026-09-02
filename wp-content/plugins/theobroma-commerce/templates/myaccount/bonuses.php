<?php
defined('ABSPATH') || exit;

$typeLabels = [
    'accrue' => 'Начислено за заказ',
    'reserve' => 'Зарезервировано для заказа',
    'spend' => 'Списано при оплате',
    'release' => 'Резерв возвращён',
    'reverse-accrual' => 'Корректировка после возврата',
    'restore-spend' => 'Списанные бонусы возвращены',
];
?>
<section class="theobroma-bonuses">
    <p class="account-eyebrow">Программа лояльности</p>
    <h1>Ваши бонусы</h1>
    <div class="theobroma-bonus-summary">
        <div><span>Доступно</span><strong><?php echo wp_kses_post(wc_price($availableKopecks / 100)); ?></strong></div>
        <div><span>В резерве</span><strong><?php echo wp_kses_post(wc_price($reservedKopecks / 100)); ?></strong></div>
    </div>
    <p class="theobroma-bonus-rules">Начисляем 5% от оплаченной стоимости товаров после перевода заказа в статус «Выполнено». При следующем заказе бонусами можно оплатить до 20% стоимости товаров: укажите сумму в блоке «Использовать бонусы» при оформлении и нажмите «Применить». Один бонус равен одному рублю.</p>

    <h2>История операций</h2>
    <?php if ($entries === []) : ?>
        <p class="theobroma-bonus-empty">Операций пока нет. Бонусы появятся после первого выполненного заказа.</p>
    <?php else : ?>
        <div class="theobroma-bonus-history">
            <?php foreach ($entries as $entry) :
                $amount = max(0, (int) ($entry->context['amount_kopecks'] ?? abs($entry->totalDeltaKopecks())));
                $positive = in_array($entry->type, ['accrue', 'release', 'restore-spend'], true);
                $order = $entry->orderId > 0 ? wc_get_order($entry->orderId) : false;
                $ownedOrder = $order instanceof WC_Order && (int) $order->get_customer_id() === get_current_user_id();
                ?>
                <article class="theobroma-bonus-entry">
                    <div>
                        <strong><?php echo esc_html($typeLabels[$entry->type] ?? 'Операция с бонусами'); ?></strong>
                        <time datetime="<?php echo esc_attr(mysql2date(DATE_W3C, $entry->createdAt, false)); ?>"><?php echo esc_html(mysql2date('d.m.Y H:i', $entry->createdAt)); ?></time>
                    </div>
                    <?php if ($ownedOrder) : ?>
                        <a href="<?php echo esc_url($order->get_view_order_url()); ?>">Заказ №<?php echo esc_html((string) $entry->orderId); ?></a>
                    <?php endif; ?>
                    <span class="<?php echo $positive ? 'is-positive' : 'is-negative'; ?>"><?php echo $positive ? '+' : '−'; ?><?php echo wp_kses_post(wc_price($amount / 100)); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($page > 1 || $hasNext) : ?>
            <nav class="theobroma-bonus-pagination" aria-label="Страницы истории бонусов">
                <?php if ($page > 1) : ?><a href="<?php echo esc_url(add_query_arg('bonus-page', $page - 1)); ?>">← Назад</a><?php endif; ?>
                <?php if ($hasNext) : ?><a href="<?php echo esc_url(add_query_arg('bonus-page', $page + 1)); ?>">Далее →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
