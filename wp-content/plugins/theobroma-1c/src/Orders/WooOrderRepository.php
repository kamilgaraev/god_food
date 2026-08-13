<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
final class WooOrderRepository {
 /** @return list<array{order:\WC_Order,revision:int}> */ public function pending(int $limit):array{$orders=wc_get_orders(['limit'=>$limit,'orderby'=>'date','order'=>'ASC','meta_query'=>[['key'=>'_theobroma_1c_revision','compare'=>'EXISTS']]]);$out=[];foreach($orders as $o){$r=(int)$o->get_meta('_theobroma_1c_revision',true);if($r>(int)$o->get_meta('_theobroma_1c_ack_revision',true))$out[]=['order'=>$o,'revision'=>$r];}return$out;}
 /** @param list<array{order_id:int,revision:int}> $batch */ public function acknowledge(array $batch):void{foreach($batch as $row){$o=wc_get_order($row['order_id']);if(!$o instanceof \WC_Order)continue;$current=(int)$o->get_meta('_theobroma_1c_ack_revision',true);if($row['revision']>$current){$o->update_meta_data('_theobroma_1c_ack_revision',$row['revision']);$o->update_meta_data('_theobroma_1c_exported_at',gmdate('c'));$o->save();}}}
}
