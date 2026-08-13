<?php
declare(strict_types=1); namespace Theobroma\OneC\Ozon;
final readonly class OzonRow {public function __construct(public string $clientArticle,public string $ozonProductId,public string $ozonSku,public string $ean,public string $name,public bool $valid){}}
