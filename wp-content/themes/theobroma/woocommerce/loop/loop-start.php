<?php
/**
 * Product loop wrapper aligned with the homepage product grid.
 */

defined('ABSPATH') || exit;
?>
<ul class="products home-product-grid columns-<?php echo esc_attr((string) wc_get_loop_prop('columns')); ?>">
