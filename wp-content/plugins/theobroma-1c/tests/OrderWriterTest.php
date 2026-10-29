<?php
declare(strict_types=1);
namespace Theobroma\OneC\Tests;
use Theobroma\OneC\CommerceMl\OrderWriter;
use Theobroma\OneC\Orders\{OrderExportData,OrderLineData,RefundData,ServiceLineData};
use Theobroma\OneC\Products\ProductIdentifiers;

final class OrderWriterTest {
 public function testWritesCompleteEscapedCommerceMlOrder(): void {
  $order=new OrderExportData(17,'A-17','2026-08-13','12:30:00','RUB','1200.00','processing',true,false,'2026-08-13T12:31:00+03:00','Иван & Ко','a@example.test','+7','Москва <центр>','Карта', '100.00','50.00',
   [new OrderLineData(new ProductIdentifiers('woo-1','GUID&1','','ART-1','','42','4601234567890'),'Шоколад <70%>','2','600.00','1100.00','100.00')],
   [new RefundData(3,'2026-08-14','200.00')],'СДЭК','100.00',['SALE10'],[new ServiceLineData('fee-9','Подарочная упаковка','50.00')]);
  $xml=(new OrderWriter())->write([$order]);
  foreach(['ВерсияСхемы="2.05"','WC-ORDER-17','GUID&amp;1','Шоколад &lt;70%&gt;','Заказ оплачен','100.00','Возвраты','SALE10','ozon_sku','42','ORDER_FEE_fee-9','Подарочная упаковка','50.00'] as $needle){if(!str_contains($xml,$needle))throw new \RuntimeException("Missing $needle");}
 }

 public function testWritesContactsAtTheCounterpartyPathExpectedByOneC(): void {
  $xpath=$this->contactsXml('buyer&test@example.test','+7 (999) 123-45-67');
  $base='/КоммерческаяИнформация/Документ/Контрагенты/Контрагент';
  $contacts=[];
  foreach($xpath->query($base.'/Контакты/Контакт') as $contact){
   $type=$contact->getElementsByTagName('Тип')->item(0)?->textContent;
   $contacts[$type]=$contact->getElementsByTagName('Значение')->item(0)?->textContent;
  }
  $this->same('buyer&test@example.test',$contacts['Почта']??null);
  $this->same('+7 (999) 123-45-67',$contacts['ТелефонРабочий']??null);
  $this->same(2,$xpath->query($base.'/Контакты/Контакт')->length);
  foreach($xpath->query($base.'/ЗначениеРеквизита/Наименование') as $name){
   if(in_array($name->textContent,['Email','Телефон'],true))throw new \RuntimeException('Legacy contact field remains');
  }
 }

 public function testOmitsEmptyContactsAndKeepsPhoneWithoutEmail(): void {
  $base='/КоммерческаяИнформация/Документ/Контрагенты/Контрагент/Контакты';
  $empty=$this->contactsXml('','');
  $this->same(0,$empty->query($base)->length);
  $phone=$this->contactsXml('','+7');
  $this->same(1,$phone->query($base.'/Контакт')->length);
  $this->same('ТелефонРабочий',$phone->query($base.'/Контакт/Тип')->item(0)?->textContent);
 }

 private function contactsXml(string $email,string $phone): \DOMXPath {
  $order=new OrderExportData(548,'548','2026-10-29','14:06:25','RUB','297.00','processing',true,false,'','Тест & Покупатель',$email,$phone,'','Карта','0.00','0.00',[]);
  $document=new \DOMDocument();
  if(!$document->loadXML((new OrderWriter())->write([$order]),LIBXML_NONET))throw new \RuntimeException('Invalid CommerceML XML');
  return new \DOMXPath($document);
 }

 private function same(mixed $expected,mixed $actual): void {
  if($expected!==$actual)throw new \RuntimeException('Unexpected XML contact value: '.var_export($actual,true));
 }
}
