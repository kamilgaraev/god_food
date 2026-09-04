<?php
declare(strict_types=1);

/** Conservative image protection: even a stored image reference keeps the product. */
function prune_product_decision(object $product): array
{
    $images = (int) $product->get_image_id('edit') > 0
        || count(array_filter($product->get_gallery_image_ids('edit'), static fn($id) => (int) $id > 0)) > 0
        || (int) $product->get_meta('_theobroma_product_detail_image_id', true) > 0
        || trim((string) $product->get_meta('_theobroma_product_image_source', true)) !== ''
        || preg_match('/<img\b/i', $product->get_description('edit').' '.$product->get_short_description('edit')) === 1;
    $missing = [];
    foreach (['length', 'width', 'height'] as $dimension) {
        $value = $product->{'get_'.$dimension}('edit');
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) { $missing[] = $dimension; }
    }
    return [
        'remove' => $product->get_status('edit') === 'publish' && $product->get_type() === 'simple' && !$images && $missing !== [],
        'has_images' => $images,
        'missing_dimensions' => $missing,
    ];
}

function prune_incomplete_products(bool $apply): array
{
    $protected = []; $candidates = [];
    foreach (wc_get_products(['status'=>'publish','limit'=>-1,'orderby'=>'ID','order'=>'ASC']) as $product) {
        $decision = prune_product_decision($product);
        if (!$decision['remove']) {
            // No setters or save calls for protected products.
            $protected[$product->get_id()] = wp_json_encode($product->get_data());
            continue;
        }
        $candidates[] = ['id'=>$product->get_id(),'sku'=>$product->get_sku(),'name'=>$product->get_name(),'missing'=>$decision['missing_dimensions']];
    }
    $changed = []; $skipped = [];
    if ($apply) {
        foreach ($candidates as $candidate) {
            clean_post_cache($candidate['id']);
            $product = wc_get_product($candidate['id']);
            // Recheck current fields immediately before saving.
            if (!$product || !prune_product_decision($product)['remove']) { $skipped[] = $candidate['id']; continue; }
            $product->set_status('draft');
            $product->update_meta_data('_theobroma_catalog_excluded', 'missing_dimensions_without_images');
            $product->save();
            $changed[] = $product->get_id();
        }
        foreach ($protected as $id=>$before) {
            clean_post_cache($id);
            $product = wc_get_product($id);
            if (!$product || wp_json_encode($product->get_data()) !== $before) {
                throw new RuntimeException('Protected product changed during the operation: '.$id.'; check concurrent edits.');
            }
        }
        foreach ($changed as $id) {
            if (wc_get_product($id)->get_status('edit') !== 'draft') { throw new RuntimeException('Could not unpublish product '.$id); }
        }
    }
    return ['mode'=>$apply?'applied':'preview','candidates'=>count($candidates),'protected'=>count($protected),'unpublished'=>count($changed),'skipped_after_recheck'=>$skipped,'products'=>$candidates];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        require_once (getenv('WORDPRESS_ROOT') ?: '/var/www/html').'/wp-load.php';
        if (!function_exists('wc_get_products')) { throw new RuntimeException('WooCommerce unavailable'); }
        $apply = in_array('--apply', $argv, true);
        $report = prune_incomplete_products($apply);
        echo json_encode($report, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
        if (in_array('--verify', $argv, true) && $report['candidates'] !== 0) { exit(1); }
    } catch (Throwable $error) { fwrite(STDERR, $error->getMessage()."\n"); exit(1); }
}
