<?php
declare(strict_types=1); namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Orders\{ExportPolicy,ExportState};
final class ExportPolicyTest {
 public function testQueuesFirstExportOnlyAfterPayment():void{$p=new ExportPolicy();$this->same(false,$p->shouldQueue(false,new ExportState()));$this->same(true,$p->shouldQueue(true,new ExportState()));}
 public function testQueuesChangesAfterAnOrderWasExportedEvenIfNoLongerPaid():void{$p=new ExportPolicy();$this->same(true,$p->shouldQueue(false,new ExportState(2,2,'2026-08-13')));}
 public function testRevisionAcknowledgementIsIdempotent():void{$s=(new ExportState())->queue()->acknowledge(1)->acknowledge(1);$this->same(1,$s->revision);$this->same(1,$s->acknowledgedRevision);$this->same(false,$s->pending());}
 private function same(mixed $e,mixed $a):void{if($e!==$a)throw new \RuntimeException('Expected '.var_export($e,true).', got '.var_export($a,true));}
}
