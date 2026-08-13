<?php
declare(strict_types=1);
namespace Theobroma\OneC\Tests;
use Theobroma\OneC\Http\BasicAuthenticator;
final class BasicAuthenticatorTest {public function testChecksUsernameAndHashWithoutExposingSecret():void{$a=new BasicAuthenticator('sync','$hash',static fn(string $p,string $h):bool=>$p==='secret'&&$h==='$hash');if(!$a->valid('sync','secret')||$a->valid('sync','wrong')||$a->valid('other','secret'))throw new \RuntimeException('Authentication result mismatch');}}
