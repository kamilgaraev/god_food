<?php
declare(strict_types=1);
namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Http\{BasicAuthenticator,ExchangeController};
final class ExchangeControllerTest {
 public function testRejectsDisabledUnauthorizedAndUnknownModes():void{$auth=new BasicAuthenticator('u','h',fn($p,$h)=>$p==='p');$disabled=(new ExchangeController(false,$auth,fn()=>'<xml/>',fn()=>null))->handle('query','u','p');$unauth=(new ExchangeController(true,$auth,fn()=>'',fn()=>null))->handle('query','u','x');$bad=(new ExchangeController(true,$auth,fn()=>'',fn()=>null))->handle('wat','u','p');foreach([[503,$disabled->status],[401,$unauth->status],[400,$bad->status]] as[$e,$a])if($e!==$a)throw new \RuntimeException("Expected $e got $a");}
 public function testImplementsCommerceMlHandshake():void{$ack=0;$c=new ExchangeController(true,new BasicAuthenticator('u','h',fn($p,$h)=>$p==='p'),fn()=>'<xml/>',function()use(&$ack){$ack++;});$this->body('success',$c->handle('checkauth','u','p')->body);$this->body('zip=no',$c->handle('init','u','p')->body);$this->body('<xml/>',$c->handle('query','u','p')->body);$this->body('success',$c->handle('success','u','p')->body);if($ack!==1)throw new \RuntimeException('Batch not acknowledged');}
 private function body(string$n,string$b):void{if(!str_contains($b,$n))throw new \RuntimeException("Missing $n");}
}
