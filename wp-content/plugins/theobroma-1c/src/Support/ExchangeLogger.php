<?php
declare(strict_types=1); namespace Theobroma\OneC\Support;
final class ExchangeLogger {/** @param array<string,int|string|bool> $context */public function info(string $event,array $context=[]):void{wc_get_logger()->info($event,['source'=>'theobroma-1c']+array_intersect_key($context,array_flip(['mode','result','order_count','order_id'])));}}
