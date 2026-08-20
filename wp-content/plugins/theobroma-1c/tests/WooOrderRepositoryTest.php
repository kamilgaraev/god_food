<?php
declare(strict_types=1);

namespace Theobroma\OneC\Orders {
    final class WooOrderRepositoryTestQuery
    {
        /** @var list<object> */
        public static array $orders = [];
    }

    /** @param array<string, mixed> $args @return list<object> */
    function wc_get_orders(array $args): array
    {
        $limit = (int) ($args['limit'] ?? 10);
        $page = (int) ($args['paged'] ?? 1);

        return array_slice(WooOrderRepositoryTestQuery::$orders, ($page - 1) * $limit, $limit);
    }
}

namespace Theobroma\OneC\Tests {
    use Theobroma\OneC\Orders\WooOrderRepository;
    use Theobroma\OneC\Orders\WooOrderRepositoryTestQuery;

    final class WooOrderRepositoryTest
    {
        public function testFindsPendingOrdersBeyondAnAcknowledgedFirstPage(): void
        {
            WooOrderRepositoryTestQuery::$orders = [];
            for ($id = 1; $id <= 100; $id++) {
                WooOrderRepositoryTestQuery::$orders[] = new RepositoryOrderStub($id, 2);
            }
            WooOrderRepositoryTestQuery::$orders[] = new RepositoryOrderStub(101, 0);
            WooOrderRepositoryTestQuery::$orders[] = new RepositoryOrderStub(102, 1);

            $pending = (new WooOrderRepository())->pending(2);

            $this->same([101, 102], array_map(
                static fn (array $row): int => $row['order']->id,
                $pending
            ));
        }

        private function same(mixed $expected, mixed $actual): void
        {
            if ($expected !== $actual) {
                throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
            }
        }
    }

    final class RepositoryOrderStub
    {
        public function __construct(
            public readonly int $id,
            private readonly int $acknowledgedRevision
        ) {}

        public function get_meta(string $key, bool $single): int
        {
            unset($single);

            return $key === '_theobroma_1c_revision' ? 2 : $this->acknowledgedRevision;
        }
    }
}
