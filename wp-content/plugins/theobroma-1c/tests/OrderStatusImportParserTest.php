<?php
declare(strict_types=1); namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Import\OrderStatusImportParser;
final class OrderStatusImportParserTest {
 public function testParsesOnlyExplicitWooOrderIdAndAllowedStatus():void{$xml='<КоммерческаяИнформация><Документ><Ид>WC-ORDER-42</Ид><Номер>42</Номер><ЗначенияРеквизитов><ЗначениеРеквизита><Наименование>Статус заказа</Наименование><Значение>Завершен</Значение></ЗначениеРеквизита></ЗначенияРеквизитов></Документ><Документ><Ид>foreign</Ид><Номер>43</Номер></Документ></КоммерческаяИнформация>';$u=(new OrderStatusImportParser())->parse($xml);$this->same(1,count($u));$this->same(42,$u[0]->orderId);$this->same('completed',$u[0]->status);}
 private function same(mixed$e,mixed$a):void{if($e!==$a)throw new \RuntimeException('Expected '.var_export($e,true).', got '.var_export($a,true));}
}
