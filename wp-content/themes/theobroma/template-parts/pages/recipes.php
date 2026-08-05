<main class="recipes-page">
    <section class="recipes-intro">
        <nav class="recipes-breadcrumb" aria-label="Хлебные крошки"><a href="<?php echo esc_url(home_url('/')); ?>">Главная</a><span>/</span><strong>Фирменные рецепты</strong></nav>
        <h1><em>Фирменные рецепты</em> с какао</h1>
        <p class="recipes-lead">Фирменные рецепты, которые помогут вам приготовить вкуснейший напиток<br>или выпечку на основе нашего какао-порошка</p>
        <?php
        $recipes = new WP_Query(array(
            'post_type' => 'theobroma_recipe',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => array('menu_order' => 'ASC', 'date' => 'ASC'),
            'no_found_rows' => true,
        ));
        $legacy_titles = array(
            'classic' => "Какао\nклассический",
            'marshmallow' => "Какао с\nмаршмеллоу",
            'banana' => "Рецепт бананового\nсмузи с какао",
        );
        $extra_rows = max(0, (int) ceil($recipes->post_count / 3) - 1);
        ?>
        <div class="recipe-grid"<?php echo $extra_rows ? ' style="margin-bottom:' . esc_attr((string) ($extra_rows * 403)) . 'px"' : ''; ?>>
            <?php while ($recipes->have_posts()) : $recipes->the_post();
                $recipe_id = get_the_ID();
                $slug = get_post_field('post_name', $recipe_id);
                $card_title = (string) get_post_meta($recipe_id, '_theobroma_card_title', true);
                $card_title = $card_title ?: ($legacy_titles[$slug] ?? get_the_title());
                $time = (string) get_post_meta($recipe_id, '_theobroma_cooking_time', true) ?: '5 минут';
                $image_id = absint(get_post_meta($recipe_id, '_theobroma_card_image_id', true));
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
                $has_legacy_image = in_array($slug, array('classic', 'marshmallow', 'banana'), true);
                if (!$image_url && !$has_legacy_image) {
                    $image_url = get_template_directory_uri() . '/assets/images/recipe-classic.jpg';
                }
                $image_style = $image_url ? ' style="background-image:url(' . esc_url($image_url) . ')"' : '';
                $image_class = $has_legacy_image && !$image_url ? ' recipe-image-' . sanitize_html_class($slug) : '';
                ?>
                <a class="recipe-card" href="<?php the_permalink(); ?>">
                    <h2><?php echo nl2br(esc_html($card_title)); ?></h2>
                    <span class="recipe-image<?php echo esc_attr($image_class); ?>"<?php echo $image_style; ?>><b><?php echo esc_html($time); ?></b></span>
                    <p><?php echo nl2br(esc_html(get_the_excerpt())); ?></p>
                </a>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
    <?php get_template_part('template-parts/contact-section'); ?>
</main>
