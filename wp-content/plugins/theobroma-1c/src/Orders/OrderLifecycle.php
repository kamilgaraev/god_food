<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
final class OrderLifecycle {
 public function __construct(private readonly ExportPolicy $policy=new ExportPolicy()){}
 public function register():void{foreach(['woocommerce_payment_complete','woocommerce_order_status_changed','woocommerce_order_refunded'] as $hook)add_action($hook,[$this,'queue'],20);}
 public function queue(int $orderId):void{$order=wc_get_order($orderId);if(!$order instanceof \WC_Order)return;$state=$this->state($order);if(!$this->policy->shouldQueue($order->is_paid(),$state))return;$next=$state->queue();$order->update_meta_data('_theobroma_1c_revision',$next->revision);$order->save();}
 public function state(\WC_Order $order):ExportState{return new ExportState((int)$order->get_meta('_theobroma_1c_revision',true),(int)$order->get_meta('_theobroma_1c_ack_revision',true),(string)$order->get_meta('_theobroma_1c_exported_at',true));}
}
