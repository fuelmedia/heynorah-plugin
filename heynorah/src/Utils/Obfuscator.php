<?php
declare(strict_types=1);

namespace HeyNorah\Utils;

use HeyNorah\Core\Config;

class Obfuscator
{
    private const METHOD = 'AES-256-CBC';

    public static function decrypt(string $data): string
    {
        if (empty($data))
            return '';

        $key = substr(hash('sha256', Config::get_internal_key()), 0, 32);

        $payload = base64_decode($data);
        if (!str_contains($payload, '::'))
            return '';

        [$encrypted_data, $iv] = explode('::', $payload, 2);

        return openssl_decrypt($encrypted_data, self::METHOD, $key, 0, $iv) ?: '';
    }
}