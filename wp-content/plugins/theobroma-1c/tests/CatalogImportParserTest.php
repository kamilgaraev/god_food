<?php
declare(strict_types=1); namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Import\CatalogImportParser;
final class CatalogImportParserTest {
 public function testParsesPriceAndSumsWarehousesWithoutCatalogContent():void{$xml='<?xml version="1.0"?><КоммерческаяИнформация><ПакетПредложений><Предложения><Предложение><Ид>guid-1#variant</Ид><Артикул>A-1</Артикул><Наименование>НЕ МЕНЯТЬ</Наименование><Цены><Цена><ЦенаЗаЕдиницу>125.50</ЦенаЗаЕдиницу></Цена></Цены><Склад ИдСклада="s1" КоличествоНаСкладе="2"/><Склад ИдСклада="s2" КоличествоНаСкладе="3.5"/></Предложение></Предложения></ПакетПредложений></КоммерческаяИнформация>';$u=(new CatalogImportParser())->parse($xml)[0];$this->same('guid-1',$u->identifiers['1c_guid']);$this->same('A-1',$u->identifiers['1c_article']);$this->same('125.50',$u->price);$this->same(5.5,$u->stock);}
 public function testRejectsDtdAndEntities():void{try{(new CatalogImportParser())->parse('<!DOCTYPE x [<!ENTITY y SYSTEM "file:///etc/passwd">]><x>&y;</x>');throw new \RuntimeException('DTD accepted');}catch(\InvalidArgumentException $e){$this->same('Unsafe XML declarations are forbidden',$e->getMessage());}}
 private function same(mixed$e,mixed$a):void{if($e!==$a)throw new \RuntimeException('Expected '.var_export($e,true).', got '.var_export($a,true));}
}
