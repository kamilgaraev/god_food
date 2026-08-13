<?php
declare(strict_types=1); namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Import\ProductMatcher;
final class ProductMatcherTest {
 public function testMatchesOnlyWhenEveryFoundIdentifierPointsToOneProduct():void{$lookup=fn(string$f,string$v)=>match($f.':'.$v){'1c_guid:g'=>[7],'ean:e'=>[7],default=>[]};$m=new ProductMatcher($lookup);$this->same(7,$m->match(['1c_guid'=>'g','ean'=>'e'])->productId);$this->same('matched',$m->match(['1c_guid'=>'g'])->status);}
 public function testRejectsAmbiguousAndNeverUsesName():void{$lookup=fn(string$f,string$v)=>$f==='1c_guid'?[7]:($f==='ean'?[8]:[]);$m=new ProductMatcher($lookup);$this->same('ambiguous',$m->match(['1c_guid'=>'g','ean'=>'e','name'=>'same'])->status);$this->same('missing',$m->match(['name'=>'same'])->status);}
 private function same(mixed$e,mixed$a):void{if($e!==$a)throw new \RuntimeException('Expected '.var_export($e,true).', got '.var_export($a,true));}
}
