<?php
declare(strict_types=1); namespace Theobroma\OneC\Import;
final readonly class OrderStatusUpdate {public function __construct(public int$orderId,public string$status){}}
