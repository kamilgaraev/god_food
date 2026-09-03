<?php
declare(strict_types=1);

/** Read cell values only; formulas, links and document instructions are never executed. */
function ozon_catalog_read(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) { throw new RuntimeException('Cannot open XLSX: ' . $path); }
    $xml = static function (string $name) use ($zip): SimpleXMLElement {
        $body = $zip->getFromName($name);
        if (!is_string($body) || str_contains($body, '<!DOCTYPE')) { throw new RuntimeException('Invalid XLSX part: ' . $name); }
        $doc = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET);
        if ($doc === false) { throw new RuntimeException('Invalid XML: ' . $name); }
        return $doc;
    };
    try {
        $strings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            foreach ($xml('xl/sharedStrings.xml')->si as $item) {
                $strings[] = implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]')));
            }
        }
        $sheetPath = null;
        $book = $xml('xl/workbook.xml');
        foreach ($book->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] !== 'Товары') { continue; }
            $relationship = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($xml('xl/_rels/workbook.xml.rels')->Relationship as $rel) {
                if ((string) $rel['Id'] === $relationship) {
                    $target = (string) $rel['Target'];
                    if (str_contains($target, '..') || (string) $rel['TargetMode'] === 'External') { throw new RuntimeException('Unsafe sheet relationship'); }
                    $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
                }
            }
        }
        if ($sheetPath === null) { throw new RuntimeException('Missing Товары worksheet'); }
        $headers = []; $rows = [];
        foreach ($xml($sheetPath)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                $col = $match[0];
                $type = (string) $cell['t'];
                $cells[$col] = $type === 's' ? ($strings[(int) $cell->v] ?? '') : ($type === 'inlineStr' ? implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]'))) : (string) $cell->v);
            }
            if (($cells['A'] ?? '') === 'Артикул') { $headers = $cells; continue; }
            if ($headers === [] || trim($cells['A'] ?? '') === '') { continue; }
            $record = [];
            foreach ($headers as $col => $name) { $record[$name] = trim($cells[$col] ?? ''); }
            $rows[] = $record;
        }
        if ($rows === [] || !isset($rows[0]['Ozon Product ID'], $rows[0]['SKU'], $rows[0]['Название товара'])) { throw new RuntimeException('Unexpected product report format'); }
        return $rows;
    } finally { $zip->close(); }
}

function ozon_catalog_plan(array $row): array
{
    $map = [
        '430032'=>'cacao-200','184257'=>'cacao-100','697482'=>'cacao-400',
        'ГШ70200'=>'200-70','ГШ65200'=>'200-65-cinnamon','ГШ80200'=>'200-80','ГШ68200'=>'200-68-coriander',
        'ГШ70100'=>'100-70','ГШ80100'=>'100-80','ГШ65100'=>'100-65-cinnamon','ГШ68100'=>'100-68-coriander',
        'МШнКМ100'=>'100-goat','МШнКОРМ100'=>'100-cow','МШнКОРМ200'=>'200-cow','МШнКМ200'=>'200-goat',
        'МШнКозМ30_1'=>'30-goat','МШсЦФ30_1'=>'30-whole-hazelnut','МШФИИ30_1'=>'30-hazelnut-raisin',
        'ГШЦФ5930_1'=>'30-59-date','ГШВИМ5930_1'=>'30-59-cherry-almond','МШнКМ30_1'=>'30-date-powder',
        'ГШВИЗГ5930_1'=>'30-59-cherry-buckwheat','МШМ30_1'=>'30-raspberry','ГШнКС7030_1'=>'30-70','ГШнКС8030_1'=>'30-80',
    ];
    $article = $row['Артикул']; $name = $row['Название товара']; $ozon = $row['SKU'];
    $price = str_replace([' ', ','], ['', '.'], $row['Текущая цена с учетом скидки, ₽'] ?? '');
    if ($article === '' || $name === '' || !ctype_digit($ozon) || !ctype_digit($row['Ozon Product ID']) || !is_numeric($price) || (float) $price <= 0) { throw new RuntimeException('Invalid product: ' . $article); }
    $net = null; $quantity = null;
    if (preg_match('/(?:(\d+)\s*[*хx]\s*)?(\d+)\s*г(?=\s|,|$)/u', $name, $m)) {
        $quantity = (int) ($m[1] !== '' ? $m[1] : 1); $net = $quantity * (int) $m[2];
    }
    $shipping = array_map(static fn($key) => (float) str_replace(',', '.', $row[$key] ?? ''), ['Длина упаковки, см','Ширина упаковки, см','Высота упаковки, см','Вес брутто, г']);
    $validShipping = min($shipping) > 0 && $net !== null && $shipping[3] >= $net;
    $note = $validShipping ? 'source' : ($shipping[3] > 0 ? 'gross_below_net' : 'missing');
    $category = str_starts_with($name, 'Кофе') ? ['coffee','Кофе'] : (str_starts_with($name, 'Какао') ? ['cacao','Какао-порошок'] : (str_starts_with($name, 'Пакет') ? ['gift-packaging','Подарочная упаковка'] : ($quantity === 15 ? ['chocolate-showboxes','Шоколад — шоубоксы'] : ($quantity === 3 ? ['chocolate-sets','Шоколад — наборы'] : ['chocolate-large','Шоколад — крупная фасовка']))));
    $barcode = $row['Штрихкод (Серийный номер / EAN)'] ?? '';
    return ['sku'=>isset($map[$article]) ? 'theobroma-'.$map[$article] : 'ozon-'.$ozon,'article'=>$article,'name'=>$name,'ozon'=>$ozon,'product_id'=>$row['Ozon Product ID'],'price'=>$price,'barcode'=>$barcode,'ean'=>preg_match('/^\d{13}$/D', $barcode) ? $barcode : '', 'net_g'=>$net,'quantity'=>$quantity,'shipping'=>$validShipping ? $shipping : null,'shipping_note'=>$note,'category'=>$category,'source'=>$row];
}

function ozon_catalog_apply(array $plans, bool $apply): array
{
    // Resolve the entire batch before writing: never guess through conflicting identifiers.
    $targets = []; $seen = [];
    foreach ($plans as $plan) {
        if (isset($seen[$plan['sku']])) { throw new RuntimeException('Duplicate source SKU: '.$plan['sku']); }
        $seen[$plan['sku']] = true;
        $ids = array_filter([(int) wc_get_product_id_by_sku($plan['sku'])]);
        foreach (['_theobroma_ozon_sku'=>$plan['ozon'],'_theobroma_ozon_product_id'=>$plan['product_id']] as $key=>$value) {
            $ids = array_merge($ids, get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','fields'=>'ids','numberposts'=>-1,'meta_key'=>$key,'meta_value'=>$value]));
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) > 1 || ($ids !== [] && in_array($ids[0], $targets, true))) { throw new RuntimeException('Conflicting product match: '.$plan['article']); }
        if ($ids !== [] && !wc_get_product($ids[0])->is_type('simple')) { throw new RuntimeException('Expected simple product: '.$plan['article']); }
        $targets[] = $ids[0] ?? 0;
    }
    $result = ['created'=>0,'updated'=>0,'shipping'=>0,'missing_shipping'=>0];
    foreach ($plans as $index=>$plan) {
        $id = $targets[$index];
        $result[$id ? 'updated' : 'created']++;
        $result[$plan['shipping'] ? 'shipping' : 'missing_shipping']++;
        if (!$apply) { continue; }
        $product = $id ? wc_get_product($id) : new WC_Product_Simple();
        if (!$id) {
            $product->set_sku($plan['sku']); $product->set_name($plan['name']);
            $product->set_status('publish'); $product->set_catalog_visibility('visible');
            // The report describes Ozon warehouses, not shop inventory.
            $product->set_stock_status('outofstock');
            [$slug,$label] = $plan['category'];
            $term = term_exists($slug, 'product_cat') ?: wp_insert_term($label, 'product_cat', ['slug'=>$slug]);
            if (is_wp_error($term)) { throw new RuntimeException($term->get_error_message()); }
            $product->set_category_ids([(int) (is_array($term) ? $term['term_id'] : $term)]);
            $product->set_short_description($plan['name']);
            $product->set_description('<p>'.esc_html($plan['name']).'</p>');
        }
        $product->set_regular_price($plan['price']); $product->set_sale_price(''); $product->set_price($plan['price']);
        foreach (['_theobroma_client_article'=>$plan['article'],'_theobroma_ozon_sku'=>$plan['ozon'],'_theobroma_ozon_product_id'=>$plan['product_id'],'_theobroma_ozon_barcode'=>$plan['barcode'],'_theobroma_shipping_data_note'=>$plan['shipping_note'],'_theobroma_ozon_report'=>$plan['source']] as $key=>$value) { $product->update_meta_data($key,$value); }
        if ($plan['ean'] !== '') { $product->update_meta_data('_theobroma_ean',$plan['ean']); }
        if ($plan['net_g'] !== null) { $product->update_meta_data('_theobroma_net_weight_g',$plan['net_g']); $product->update_meta_data('_theobroma_pack_quantity',$plan['quantity']); }
        if ($plan['article'] === 'МШнКМ30_1') { $product->update_meta_data('_theobroma_match_note','Probable milk/date-powder 30g match approved by owner'); }
        $marketplaces = $product->get_meta('_theobroma_marketplaces',true);
        $marketplaces = is_array($marketplaces) ? $marketplaces : [];
        $marketplaces['ozon'] = 'https://www.ozon.ru/product/'.$plan['ozon'].'/';
        $product->update_meta_data('_theobroma_marketplaces',$marketplaces);
        if ($plan['shipping'] !== null) {
            [$length,$width,$height,$gross] = $plan['shipping'];
            $product->set_weight((string) wc_get_weight($gross, get_option('woocommerce_weight_unit','kg'),'g'));
            foreach (['length'=>$length,'width'=>$width,'height'=>$height] as $dimension=>$value) { $product->{'set_'.$dimension}((string) wc_get_dimension($value, get_option('woocommerce_dimension_unit','cm'),'cm')); }
        }
        $attributes = $product->get_attributes();
        $facts = ['Бренд'=>$plan['source']['Бренд'],'Тип товара'=>$plan['source']['Тип']];
        if ($plan['net_g'] !== null) { $facts['Масса нетто'] = $plan['net_g'].' г'; $facts['Количество в упаковке'] = (string) $plan['quantity']; }
        foreach ($facts as $label=>$value) {
            $attribute = new WC_Product_Attribute(); $attribute->set_name($label); $attribute->set_options([$value]); $attribute->set_visible(true); $attribute->set_variation(false);
            $attributes[sanitize_title($label)] = $attribute;
        }
        $product->set_attributes($attributes);
        $product->save();
        echo $plan['sku'].':'.$product->get_id()."\n";
    }
    return $result;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $file = $argv[1] ?? '';
        if ($file === '' || str_starts_with($file, '--')) { throw new RuntimeException('Usage: php sync-ozon-catalog.php products.xlsx [--apply]'); }
        $plans = array_map('ozon_catalog_plan', ozon_catalog_read($file));
        require_once (getenv('WORDPRESS_ROOT') ?: '/var/www/html') . '/wp-load.php';
        if (!class_exists('WC_Product_Simple')) { throw new RuntimeException('WooCommerce is unavailable'); }
        $apply = in_array('--apply',$argv,true);
        $result = ozon_catalog_apply($plans,$apply);
        echo json_encode(['mode'=>$apply?'applied':'preview','total'=>count($plans)]+$result, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    } catch (Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
}
