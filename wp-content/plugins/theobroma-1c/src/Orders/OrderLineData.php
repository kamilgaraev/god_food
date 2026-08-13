<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
use Theobroma\OneC\Products\ProductIdentifiers;
final readonly class OrderLineData { public function __construct(public ProductIdentifiers $identifiers,public string $name,public string $quantity,public string $unitPrice,public string $total,public string $discount='0.00'){} }
