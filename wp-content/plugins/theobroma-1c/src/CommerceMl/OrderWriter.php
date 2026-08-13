<?php
declare(strict_types=1); namespace Theobroma\OneC\CommerceMl;
use Theobroma\OneC\Orders\OrderExportData; use Theobroma\OneC\Products\ProductIdentifierResolver;
final class OrderWriter {
 public function __construct(private readonly ProductIdentifierResolver $resolver=new ProductIdentifierResolver()){}
 /** @param list<OrderExportData> $orders */
 public function write(array $orders):string {
  $x=new \XMLWriter();$x->openMemory();$x->startDocument('1.0','UTF-8');$x->startElement('КоммерческаяИнформация');$x->writeAttribute('ВерсияСхемы','2.05');$x->writeAttribute('ДатаФормирования',gmdate('c'));
  foreach($orders as $o)$this->order($x,$o);$x->endElement();$x->endDocument();return $x->outputMemory();
 }
 private function order(\XMLWriter $x,OrderExportData $o):void {
  $x->startElement('Документ');$this->e($x,'Ид','WC-ORDER-'.$o->id);$this->e($x,'Номер',$o->number);$this->e($x,'Дата',$o->date);$this->e($x,'Время',$o->time);$this->e($x,'ХозОперация','Заказ товара');$this->e($x,'Роль','Продавец');$this->e($x,'Валюта',$o->currency);$this->e($x,'Сумма',$o->total);
  $x->startElement('Контрагенты');$x->startElement('Контрагент');$this->e($x,'Ид','WC-CUSTOMER-'.$o->id);$this->e($x,'Наименование',$o->customerName);foreach(['Email'=>$o->email,'Телефон'=>$o->phone,'Адрес'=>$o->address] as $n=>$v)$this->req($x,$n,$v);$x->endElement();$x->endElement();
  $x->startElement('Товары');foreach($o->lines as $line){$r=$this->resolver->resolve($line->identifiers);$x->startElement('Товар');$this->e($x,'Ид',$r->value);$this->e($x,'Артикул',$r->all['1c_article']??$r->all['client_article']??$r->all['woo_sku']??'');$this->e($x,'Наименование',$line->name);$this->e($x,'ЦенаЗаЕдиницу',$line->unitPrice);$this->e($x,'Количество',$line->quantity);$this->e($x,'Сумма',$line->total);$this->req($x,'Скидка',$line->discount);$this->req($x,'Тип основного идентификатора',$r->type);foreach($r->all as $type=>$value)$this->req($x,$type,$value);$x->endElement();}
  if($o->shippingName!==''||$o->shippingTotal!=='0.00'){$x->startElement('Товар');$this->e($x,'Ид','ORDER_DELIVERY');$this->e($x,'Наименование',$o->shippingName);$this->e($x,'Количество','1');$this->e($x,'ЦенаЗаЕдиницу',$o->shippingTotal);$this->e($x,'Сумма',$o->shippingTotal);$x->endElement();}$x->endElement();
  $this->req($x,'Заказ оплачен',$o->paid?'true':'false');$this->req($x,'Отменен',$o->cancelled?'true':'false');$this->req($x,'Статус заказа',$o->status);$this->req($x,'Дата оплаты',$o->paidAt);$this->req($x,'Метод оплаты',$o->paymentMethod);$this->req($x,'Списано бонусов',$o->bonusSpent);$this->req($x,'Начислено бонусов',$o->bonusAccrued);$this->req($x,'Купоны',implode(',',$o->coupons));
  if($o->refunds){$x->startElement('Возвраты');foreach($o->refunds as $r){$x->startElement('Возврат');$this->e($x,'Ид',(string)$r->id);$this->e($x,'Дата',$r->date);$this->e($x,'Сумма',$r->amount);$x->endElement();}$x->endElement();}$x->endElement();
 }
 private function req(\XMLWriter $x,string $n,string $v):void{$x->startElement('ЗначениеРеквизита');$this->e($x,'Наименование',$n);$this->e($x,'Значение',$v);$x->endElement();}
 private function e(\XMLWriter $x,string $n,string $v):void{$x->writeElement($n,$v);}
}
