<?php
declare(strict_types=1); namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Ozon\{OzonMatchService,OzonRow};
final class OzonMatchServiceTest {public function testNeverMatchesByNameAndClassifiesUniqueOrAmbiguousIds():void{$s=new OzonMatchService(fn($field,$value)=>$field==='ozon_sku'&&$value==='42'?[7]:($field==='ean'&&$value==='11'?[8,9]:[]));$matched=$s->match(new OzonRow('A','1','42','','Same',true));$ambiguous=$s->match(new OzonRow('B','2','','11','Same',true));$missing=$s->match(new OzonRow('C','3','','','Same',true));if($matched->status!=='matched'||$matched->productId!==7||$ambiguous->status!=='ambiguous'||$missing->status!=='missing')throw new \RuntimeException('Match classification mismatch');}}
