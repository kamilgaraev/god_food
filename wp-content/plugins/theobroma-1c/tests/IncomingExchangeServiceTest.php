<?php
declare(strict_types=1); namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Http\{ExchangeFileStore,IncomingExchangeService};use Theobroma\OneC\Import\ImportResult;
final class IncomingExchangeServiceTest {
 public function testRejectsDisabledAndUnsafeFiles():void{$dir=sys_get_temp_dir().'/theobroma-1c-test-'.bin2hex(random_bytes(4));$s=new IncomingExchangeService(false,new ExchangeFileStore($dir,'session',1024),fn()=>new ImportResult());$this->same(403,$s->handle('file','offers.xml','x')->status);$enabled=new IncomingExchangeService(true,new ExchangeFileStore($dir,'session',1024),fn()=>new ImportResult());$this->same(400,$enabled->handle('file','../bad.xml','x')->status);}
 public function testStoresImportsAndDeletesXml():void{$dir=sys_get_temp_dir().'/theobroma-1c-test-'.bin2hex(random_bytes(4));$store=new ExchangeFileStore($dir,'session',1024);$seen='';$s=new IncomingExchangeService(true,$store,function(string$xml)use(&$seen){$seen=$xml;return new ImportResult(2,1);});$this->same(200,$s->handle('file','offers.xml','<x>')->status);$this->same(200,$s->handle('file','offers.xml','ok</x>')->status);$response=$s->handle('import','offers.xml','');$this->same('<x>ok</x>',$seen);$this->same(true,str_contains($response->body,'applied=2'));$this->same(false,$store->exists('offers.xml'));}
 private function same(mixed$e,mixed$a):void{if($e!==$a)throw new \RuntimeException('Expected '.var_export($e,true).', got '.var_export($a,true));}
}
