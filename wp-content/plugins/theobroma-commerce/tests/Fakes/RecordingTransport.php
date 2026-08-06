<?php

declare(strict_types=1);

namespace Theobroma\Commerce\Tests\Fakes;

use Theobroma\Commerce\Contracts\Transport;

final class RecordingTransport implements Transport
{
    /** @var list<array{method:string,url:string,options:array<mixed>}> */
    public array $requests = [];

    /** @param list<array{status:int,body:array<mixed>,headers?:array<mixed>}> $responses */
    public function __construct(private array $responses)
    {
    }

    public function request(string $method, string $url, array $options = []): array
    {
        $this->requests[] = compact('method', 'url', 'options');
        if ($this->responses === []) {
            throw new \RuntimeException('No recorded response available');
        }

        return array_shift($this->responses);
    }
}
