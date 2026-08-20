<?php
declare(strict_types=1);

namespace Theobroma\OneC\Admin {
    final class SettingsPageHookRecorder
    {
        /** @var list<array{hook:string,callback:callable}> */
        public static array $filters = [];
    }

    function add_action(string $hook, callable $callback): void
    {
        unset($hook, $callback);
    }

    function add_filter(string $hook, callable $callback): void
    {
        SettingsPageHookRecorder::$filters[] = ['hook' => $hook, 'callback' => $callback];
    }
}

namespace Theobroma\OneC\Tests {
    use Theobroma\OneC\Admin\SettingsPage;
    use Theobroma\OneC\Admin\SettingsPageHookRecorder;

    final class SettingsPageCapabilityTest
    {
        public function testAllowsWooCommerceManagersToSavePluginSettings(): void
        {
            SettingsPageHookRecorder::$filters = [];

            (new SettingsPage())->register();

            $filter = SettingsPageHookRecorder::$filters[0] ?? null;
            $this->same('option_page_capability_theobroma_1c', $filter['hook'] ?? null);
            $this->same('manage_woocommerce', ($filter['callback'])('manage_options'));
        }

        private function same(mixed $expected, mixed $actual): void
        {
            if ($expected !== $actual) {
                throw new \RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
            }
        }
    }
}
