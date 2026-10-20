<?php
$process = theobroma_corporate_sections()['process'];
if (!$process['enabled'] || $process['items'] === array()) {
    return;
}
?>
<section class="cg-process" aria-labelledby="cg-process-title">
    <div class="cg-shell">
        <header class="cg-section-intro">
            <h2 id="cg-process-title"><?php echo theobroma_corporate_section_heading($process); ?></h2>
            <?php if ($process['intro'] !== '') : ?><p class="cg-section-description"><?php echo esc_html($process['intro']); ?></p><?php endif; ?>
        </header>
        <div class="cg-process-layout">
            <aside class="cg-factory" aria-label="О нашем производстве">
                <p class="cg-factory-heading"><?php echo esc_html($process['factory_title']); ?></p>
                <p class="cg-factory-stat"><strong><?php echo esc_html($process['factory_value']); ?></strong><span><?php echo esc_html($process['factory_unit']); ?></span></p>
                <p class="cg-factory-description"><?php echo esc_html($process['factory_text']); ?></p>
                <span class="cg-factory-mark" aria-hidden="true">Theobroma</span>
            </aside>
            <ol class="cg-process-steps" role="list">
                <?php foreach ($process['items'] as $index => $step) : ?>
                <li><span class="cg-step-number" aria-hidden="true"><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span><div><h3><?php echo esc_html($step['title']); ?></h3><p><?php echo esc_html($step['text']); ?></p></div></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php if ($process['timing_text'] !== '') : ?>
        <div class="cg-process-timing"><span aria-hidden="true">↳</span><div><h3><?php echo esc_html($process['timing_title']); ?></h3><p><?php echo esc_html($process['timing_text']); ?></p></div></div>
        <?php endif; ?>
    </div>
</section>
