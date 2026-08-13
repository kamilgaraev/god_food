<?php
declare(strict_types=1); namespace Theobroma\OneC\Http;
final class BasicAuthenticator {private \Closure $verify;public function __construct(private readonly string $username,private readonly string $hash,callable $verify){$this->verify=$verify(...);}public function valid(string $username,string $password):bool{return $this->username!==''&&$this->hash!==''&&hash_equals($this->username,$username)&&($this->verify)($password,$this->hash);}}
