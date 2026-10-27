<?php

declare(strict_types=1);

namespace Theobroma\Analytics;

final class MetrikaRenderer
{
    /** @param array{counter_id:string,clickmap:bool,track_links:bool,accurate_bounce:bool,webvisor:bool} $config */
    public function javascript(array $config): string
    {
        $counterId = (string) ($config['counter_id'] ?? '');
        if (!preg_match('/^[1-9][0-9]{0,14}$/', $counterId)) {
            return '';
        }

        $options = json_encode([
            'id' => (int) $counterId,
            'clickmap' => (bool) ($config['clickmap'] ?? true),
            'trackLinks' => (bool) ($config['track_links'] ?? true),
            'accurateTrackBounce' => (bool) ($config['accurate_bounce'] ?? true),
            'webvisor' => (bool) ($config['webvisor'] ?? false),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return sprintf(
            <<<'JS'
(function () {
    var id = %1$d, key = "theobroma_cookie_notice_accepted";
    var choice = null, script = null, ready = false, initialized = false;
    function readChoice() {
        try { return window.localStorage.getItem(key); } catch (_) { return choice; }
    }
    function initialize() {
        if (!ready || choice === "0" || initialized) return;
        initialized = true;
        window.ym(id, "init", %2$s);
    }
    function start() {
        window["disableYaCounter" + id] = false;
        if (ready) { initialize(); return; }
        if (script) return;
        window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
        window.ym.l = window.ym.l || +new Date();
        script = document.createElement("script");
        script.async = true;
        script.src = "https://mc.yandex.ru/metrika/tag.js";
        script.onload = function () { ready = true; initialize(); };
        script.onerror = function () { script = null; };
        document.head.appendChild(script);
    }
    function applyChoice(value) {
        choice = value;
        if (choice !== "0") { start(); return; }
        window["disableYaCounter" + id] = true;
        if (initialized) {
            window.ym(id, "destruct");
            initialized = false;
        }
    }
    window.addEventListener("theobroma:cookie-consent", function (event) {
        var value = event.detail && event.detail.choice;
        applyChoice(value === "0" || value === "1" ? value : readChoice());
    });
    window.addEventListener("storage", function (event) {
        if (event.key === key || event.key === null) applyChoice(readChoice());
    });
    applyChoice(readChoice());
})();
JS,
            (int) $counterId,
            $options
        );
    }
}
