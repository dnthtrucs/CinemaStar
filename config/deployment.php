<?php

$proxies = (string) env('TRUSTED_PROXIES', '');

return [
    'trusted_proxies' => $proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))),
];
