<?php
declare(strict_types=1); namespace Theobroma\OneC\Admin;
final class Diagnostics {/** @return array<string,bool> */public function checks():array{return['https'=>is_ssl()||wp_get_environment_type()!=='production','xmlwriter'=>class_exists('XMLWriter'),'woocommerce'=>class_exists('WooCommerce')];}}
