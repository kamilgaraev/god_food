<?php
declare(strict_types=1); namespace Theobroma\OneC\Settings;
final class Settings {public const OPTION='theobroma_1c_settings';/** @return array{enabled:bool,username:string,password_hash:string,batch_size:int} */public function get():array{$v=(array)get_option(self::OPTION,[]);return['enabled'=>!empty($v['enabled']),'username'=>(string)($v['username']??''),'password_hash'=>(string)($v['password_hash']??''),'batch_size'=>min(100,max(1,(int)($v['batch_size']??50)))];}}
