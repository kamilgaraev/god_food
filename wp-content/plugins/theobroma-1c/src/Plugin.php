<?php
declare(strict_types=1); namespace Theobroma\OneC;
use Theobroma\OneC\Admin\{OzonImportPage,SettingsPage};use Theobroma\OneC\Http\WordPressExchangeEndpoint;use Theobroma\OneC\Orders\OrderLifecycle;use Theobroma\OneC\Products\ProductFields;
final class Plugin {private static bool$booted=false;public static function boot():void{if(self::$booted||!class_exists('WooCommerce'))return;self::$booted=true;WordPressExchangeEndpoint::register();(new SettingsPage())->register();(new OzonImportPage())->register();(new ProductFields())->register();(new OrderLifecycle())->register();}public static function activate():void{WordPressExchangeEndpoint::rewrite();flush_rewrite_rules();}}
