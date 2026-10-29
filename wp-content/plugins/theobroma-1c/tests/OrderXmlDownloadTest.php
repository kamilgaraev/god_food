<?php
declare(strict_types=1);

namespace Theobroma\OneC\Tests;

final class OrderXmlDownloadTest
{
    public function testChecksCapabilitiesNonceAndIdBeforeReadingAnOrder(): void
    {
        foreach (['forbidden' => 403, 'bad_nonce' => 403, 'invalid_id' => 400, 'negative_id' => 400, 'array_id' => 400, 'missing' => 404, 'refund' => 404] as $scenario => $status) {
            [$body] = $this->request($scenario);
            $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (($response['status'] ?? null) !== $status) {
                throw new \RuntimeException('Unexpected download response for ' . $scenario);
            }
        }
    }

    public function testDownloadsPrivateXmlWithoutSavingOrAcknowledgingTheOrder(): void
    {
        [$body, $headers] = $this->request('success');
        $document = new \DOMDocument();
        if (!$document->loadXML($body, LIBXML_NONET)) {
            throw new \RuntimeException('Download is not XML');
        }
        foreach (['WC-ORDER-548', 'Почта', 'ТелефонРабочий', 'buyer@example.test'] as $value) {
            if (!str_contains($body, $value)) {
                throw new \RuntimeException('Missing order XML value: ' . $value);
            }
        }
        foreach (['application/xml; charset=UTF-8', 'attachment; filename="theobroma-order-548.xml"', 'Cache-Control: private, no-store', 'X-Content-Type-Options: nosniff', 'nocache_headers'] as $value) {
            if (!str_contains($headers, $value)) {
                throw new \RuntimeException('Missing private download header: ' . $value);
            }
        }
    }

    public function testRegistersOnlyAnAuthenticatedAdminAction(): void
    {
        [$body] = $this->request('register');
        $hooks = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if ($hooks !== ['admin_post_theobroma_1c_download_order_xml']) {
            throw new \RuntimeException('Download action is not restricted to authenticated administrators');
        }
    }

    /** @return array{string,string} */
    private function request(string $scenario): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/order-xml-download.php', $scenario], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start download test');
        }
        fclose($pipes[0]);
        $body = stream_get_contents($pipes[1]);
        $headers = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new \RuntimeException('Download failed: ' . $headers);
        }
        return [$body, $headers];
    }
}
