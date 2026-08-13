<?php
declare(strict_types=1); namespace Theobroma\OneC\Http;
final readonly class ExchangeResponse {/** @param array<string,string> $headers */public function __construct(public int $status,public string $body,public string $contentType='text/plain; charset=UTF-8',public array $headers=[]){}}
