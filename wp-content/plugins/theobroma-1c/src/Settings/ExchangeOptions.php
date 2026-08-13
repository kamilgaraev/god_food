<?php
declare(strict_types=1);
namespace Theobroma\OneC\Settings;
final readonly class ExchangeOptions {
 public function __construct(public bool $exportOrders,public bool $importOrderStatuses,public bool $importStock,public bool $importPrices,public int $uploadLimitMb){}
 /** @param array<string,mixed> $v */ public static function fromArray(array $v):self{return new self(self::flag($v,'export_orders',true),self::flag($v,'import_order_statuses',false),self::flag($v,'import_stock',false),self::flag($v,'import_prices',false),min(20,max(1,(int)($v['upload_limit_mb']??10))));}
 /** @param array<string,mixed> $v */ private static function flag(array $v,string $key,bool $default):bool{return array_key_exists($key,$v)?filter_var($v[$key],FILTER_VALIDATE_BOOL):$default;}
 /** @return array<string,bool|int> */ public function toArray():array{return['export_orders'=>$this->exportOrders,'import_order_statuses'=>$this->importOrderStatuses,'import_stock'=>$this->importStock,'import_prices'=>$this->importPrices,'upload_limit_mb'=>$this->uploadLimitMb];}
}
