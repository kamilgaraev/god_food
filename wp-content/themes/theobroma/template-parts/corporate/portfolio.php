<?php
$portfolio = theobroma_corporate_sections()['portfolio'];
$cards = array();
foreach ($portfolio['items'] as $item) {
    $image = theobroma_corporate_portfolio_image($item);
    if ($image !== '') {
        $cards[] = array('item' => $item, 'image' => $image);
    }
}
if (!$portfolio['enabled'] || $cards === array()) {
    return;
}
?>
<section class="cg-portfolio cg-shell" aria-labelledby="cg-portfolio-title" data-cg-carousel>
    <header class="cg-section-intro">
        <h2 id="cg-portfolio-title"><?php echo theobroma_corporate_section_heading($portfolio); ?></h2>
        <?php if ($portfolio['intro'] !== '') : ?><p class="cg-section-description"><?php echo esc_html($portfolio['intro']); ?></p><?php endif; ?>
    </header>
    <div class="cg-portfolio-track" data-cg-track tabindex="0" role="region" aria-label="Примеры подарков Theobroma">
        <?php foreach ($cards as $card) : ?>
        <article class="cg-portfolio-card">
            <div class="cg-portfolio-photo"><?php echo $card['image']; ?></div>
            <h3><?php echo esc_html($card['item']['title']); ?></h3>
            <?php if ($card['item']['text'] !== '') : ?><p><?php echo esc_html($card['item']['text']); ?></p><?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
    <footer class="cg-portfolio-footer">
        <a class="cg-text-link" href="#corporate-request">Обсудить ваш проект <span aria-hidden="true">↗</span></a>
        <?php if (count($cards) > 1) : ?>
        <div class="cg-portfolio-controls"><button type="button" data-cg-direction="-1" aria-label="Предыдущие примеры подарков">←</button><button type="button" data-cg-direction="1" aria-label="Следующие примеры подарков">→</button></div>
        <?php endif; ?>
    </footer>
</section>
