<?php
declare(strict_types=1); namespace Theobroma\OneC\Orders;
final readonly class OrderExportData {
 /** @param list<OrderLineData> $lines @param list<RefundData> $refunds @param list<string> $coupons */
 public function __construct(public int $id,public string $number,public string $date,public string $time,public string $currency,public string $total,public string $status,public bool $paid,public bool $cancelled,public string $paidAt,public string $customerName,public string $email,public string $phone,public string $address,public string $paymentMethod,public string $bonusSpent,public string $bonusAccrued,public array $lines,public array $refunds=[],public string $shippingName='',public string $shippingTotal='0.00',public array $coupons=[]){}
}
