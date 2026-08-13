<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
final readonly class RefundData { public function __construct(public int $id,public string $date,public string $amount){} }
