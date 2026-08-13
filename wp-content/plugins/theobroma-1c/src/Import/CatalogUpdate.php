<?php
declare(strict_types=1); namespace Theobroma\OneC\Import;
final readonly class CatalogUpdate {/** @param array<string,string> $identifiers */public function __construct(public array$identifiers,public ?string$price,public ?float$stock){}}
