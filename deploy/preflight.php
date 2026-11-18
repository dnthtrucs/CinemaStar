<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Encryption\Encrypter;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('production')) {
    fwrite(STDOUT, "Non-production environment.\n");
    exit(0);
}
$errors = [];
$url = (string) config('app.url');
$host = parse_url($url, PHP_URL_HOST);
if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || ! $host || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
    $errors[] = 'APP_URL must be the public HTTPS address.';
}
if (config('app.debug')) {
    $errors[] = 'APP_DEBUG must be false.';
}
$key = (string) config('app.key');
$decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
if (! is_string($decoded) || ! Encrypter::supported($decoded, config('app.cipher'))) {
    $errors[] = 'APP_KEY must be a valid, stable application key.';
}
if (! config('session.secure')) {
    $errors[] = 'SESSION_SECURE_COOKIE must be true.';
}
if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error."\n");
    } exit(1);
}
fwrite(STDOUT, "Production HTTPS preflight passed.\n");
