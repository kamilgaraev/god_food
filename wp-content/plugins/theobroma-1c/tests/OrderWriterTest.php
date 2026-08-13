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
}
