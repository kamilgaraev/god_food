<?php
declare(strict_types=1);
namespace Theobroma\OneC\Products;

final readonly class ProductIdentifiers
{
    public function __construct(
        public string $wooSku,
        public string $oneCGuid = '',
        public string $oneCArticle = '',
        public string $clientArticle = '',
        public string $ozonProductId = '',
        public string $ozonSku = '',
        public string $ean = ''
    ) {}

    /** @return array<string,string> */
    public function all(): array
    {
        return array_filter([
            '1c_guid' => trim($this->oneCGuid), '1c_article' => trim($this->oneCArticle),
            'client_article' => trim($this->clientArticle), 'woo_sku' => trim($this->wooSku),
            'ozon_product_id' => trim($this->ozonProductId), 'ozon_sku' => trim($this->ozonSku),
            'ean' => trim($this->ean),
        ], static fn(string $value): bool => $value !== '');
    }
}
