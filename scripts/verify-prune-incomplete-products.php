<?php
declare(strict_types=1);
require __DIR__.'/prune-incomplete-products.php';
function prune_fixture(array $overrides = []): object {
    return new class($overrides) {
        public function __construct(private array $v) {}
        public function get_status($context = '') { return $this->v['status'] ?? 'publish'; }
        public function get_type() { return $this->v['type'] ?? 'simple'; }
        public function get_image_id($context = '') { return $this->v['image'] ?? 0; }
        public function get_gallery_image_ids($context = '') { return $this->v['gallery'] ?? []; }
        public function get_meta($key, $single = true) { return $this->v[$key] ?? ''; }
        public function get_description($context = '') { return $this->v['description'] ?? ''; }
        public function get_short_description($context = '') { return $this->v['short_description'] ?? ''; }
        public function get_length($context = '') { return $this->v['length'] ?? '10'; }
        public function get_width($context = '') { return $this->v['width'] ?? '5'; }
        public function get_height($context = '') { return $this->v['height'] ?? '2'; }
    };
}
function prune_check(bool $condition, string $label): void { if (!$condition) { throw new RuntimeException($label); } }
prune_check(!prune_product_decision(prune_fixture())['remove'], 'Complete dimensions must stay');
foreach (['length','width','height'] as $key) {
    foreach (['', '0', '-1', 'invalid'] as $value) {
        prune_check(prune_product_decision(prune_fixture([$key=>$value]))['remove'], 'Missing/invalid dimension must be excluded');
    }
}
foreach ([['image'=>10],['gallery'=>[11]],['_theobroma_product_detail_image_id'=>12],['_theobroma_product_image_source'=>'https://example.test/photo.jpg'],['description'=>'<img src="photo.jpg">'],['short_description'=>'<IMG src="photo.jpg">']] as $image) {
    prune_check(!prune_product_decision(prune_fixture($image+['length'=>'']))['remove'], 'Product with image must stay');
}
prune_check(!prune_product_decision(prune_fixture(['status'=>'draft','length'=>'']))['remove'], 'Repeat must skip drafts');
prune_check(!prune_product_decision(prune_fixture(['type'=>'variable','length'=>'']))['remove'], 'Do not infer variation dimensions from parent');
prune_check(!prune_product_decision(prune_fixture(['length'=>'0.1']))['remove'], 'Small positive dimensions are valid');
echo "PASS: dimension cases, image protections, drafts and repeat behavior\n";
