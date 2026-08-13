<?php
declare(strict_types=1);
namespace Theobroma\OneC\Products;

final readonly class ResolvedProductIdentifier
{
    /** @param array<string,string> $all */
    public function __construct(public string $type, public string $value, public array $all) {}
}
