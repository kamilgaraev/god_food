<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Integrations\Ozon;

use Theobroma\Commerce\Contracts\Transport;
use Theobroma\Commerce\Support\ProviderException;

final class OzonClient implements OzonOrderApi
{
    public function __construct(
        private readonly Transport $transport,
        private readonly string $accessToken,
        private readonly string $baseUrl = 'https://api-seller.ozon.ru'
    ) {
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function deliveryCheck(array $payload): array
    {
        return $this->post('/v1/delivery/check', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function deliveryMap(array $payload): array
    {
        return $this->post('/v1/delivery/map', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function deliveryPointList(array $payload): array
    {
        return $this->post('/v1/delivery/point/list', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function deliveryPointInfo(array $payload): array
    {
        return $this->post('/v1/delivery/point/info', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function deliveryCheckout(array $payload): array
    {
        return $this->post('/v2/delivery/checkout', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function createOrder(array $payload): array
    {
        return $this->post('/v2/order/create', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelReasons(array $payload): array
    {
        return $this->post('/v1/cancel-reason/list', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelReasonsByOrder(array $payload): array
    {
        return $this->post('/v1/cancel-reason/list-by-order', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelReasonsByPosting(array $payload): array
    {
        return $this->post('/v1/cancel-reason/list-by-posting', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelCheck(array $payload): array
    {
        return $this->post('/v1/order/cancel/check', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelOrder(array $payload): array
    {
        return $this->post('/v1/order/cancel', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelPosting(array $payload): array
    {
        return $this->post('/v1/posting/cancel', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelStatus(array $payload): array
    {
        return $this->post('/v1/order/cancel/status', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function cancelPostingStatus(array $payload): array
    {
        return $this->post('/v1/posting/cancel/status', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function returnsList(array $payload): array
    {
        return $this->post('/v1/returns/list', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function analyticsStocks(array $payload): array
    {
        return $this->post('/v1/analytics/stocks', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function productStocks(array $payload): array
    {
        return $this->post('/v4/product/info/stocks', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function fboPostingList(array $payload): array
    {
        return $this->post('/v2/posting/fbo/list', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function fboPostingGet(array $payload): array
    {
        return $this->post('/v2/posting/fbo/get', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function fbsPostingList(array $payload): array
    {
        return $this->post('/v3/posting/fbs/list', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function fbsPostingGet(array $payload): array
    {
        return $this->post('/v3/posting/fbs/get', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    public function postingMarks(array $payload): array
    {
        return $this->post('/v1/posting/marks', $payload);
    }

    /** @param array<mixed> $payload @return array<mixed> */
    private function post(string $path, array $payload): array
    {
        if ($this->accessToken === '') {
            throw ProviderException::fromResponse('Ozon private application token is not configured', 0);
        }

        $response = $this->transport->request('POST', rtrim($this->baseUrl, '/') . $path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
            ],
            'json' => $payload,
        ]);
        if ($response['status'] !== 200) {
            throw ProviderException::fromResponse('Ozon request failed', $response['status'], ['response' => $response['body']]);
        }

        $result = $response['body']['result'] ?? null;
        if (!is_array($result)) {
            throw ProviderException::fromResponse('Ozon returned an invalid response', 502, ['response' => $response['body']]);
        }

        return $result;
    }
}
