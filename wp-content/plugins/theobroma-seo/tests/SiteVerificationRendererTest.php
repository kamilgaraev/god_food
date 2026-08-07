<?php

declare(strict_types=1);

namespace Theobroma\Seo\Tests;

use Theobroma\Seo\SiteVerificationRenderer;

final class SiteVerificationRendererTest extends TestCase
{
    public function testRendersValidYandexVerificationToken(): void
    {
        $renderer = new SiteVerificationRenderer();
        $token = '0123456789abcdef0123456789abcdef';

        $this->assertSame($token, $renderer->sanitize($token));
        $this->assertContains(
            '<meta name="yandex-verification" content="0123456789abcdef0123456789abcdef">',
            $renderer->render($token)
        );
    }

    public function testRejectsUnsafeOrEmptyVerificationTokens(): void
    {
        $renderer = new SiteVerificationRenderer();

        $this->assertSame('', $renderer->sanitize('" onload="alert(1)'));
        $this->assertSame('', $renderer->render(''));
        $this->assertSame('', $renderer->render('<script>'));
    }
}
