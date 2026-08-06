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
            '(function(){var id=%1$d,loaded=false;function load(){if(loaded)return;loaded=true;(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t);a=e.getElementsByTagName(t)[0];k.async=1;k.src=r;a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");ym(id,"init",%2$s)}if(localStorage.getItem("theobroma_cookie_notice_accepted")==="1")load();else window.addEventListener("theobroma:cookie-consent",load,{once:true})})();',
            (int) $counterId,
            $options
        );
    }
}
