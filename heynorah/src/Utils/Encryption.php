<?php
declare(strict_types=1);

namespace HeyNorah\Utils;
use RuntimeException;

class Encryption
{

    private const METHOD = 'AES-256-CBC';

    /**
     * Encrypt data using AES-256-CBC
     *
     * SECURITY: Requires AUTH_SALT to be defined in wp-config.php
     * @param string $data Data to encrypt
     * @return string Base64 encoded encrypted data
     * @throws RuntimeException if AUTH_SALT is not defined
     */
    public static function encrypt(string $data): string
    {
        if (empty($data))
            return '';

        // SECURITY FIX: Require AUTH_SALT, no weak fallback
        if (!defined('AUTH_SALT') || empty(AUTH_SALT)) {
            throw new RuntimeException(
                'AUTH_SALT must be defined in wp-config.php for encryption. ' .
                'Please add a secure AUTH_SALT constant.'
            );
        }

        $key = AUTH_SALT;
        $key = substr(hash('sha256', $key), 0, 32);

        // SECURITY: Use random_bytes (cryptographically secure)
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = random_bytes(max(1, $iv_length)); // Ensure positive int for PHPStan
        $encrypted = openssl_encrypt($data, self::METHOD, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt data encrypted with encrypt()
     *
     * SECURITY: Requires AUTH_SALT to be defined in wp-config.php
     * @param string $data Base64 encoded encrypted data
     * @return string Decrypted data
     * @throws RuntimeException if AUTH_SALT is not defined
     */
    public static function decrypt(string $data): string
    {
        if (empty($data))
            return '';

        // SECURITY FIX: Require AUTH_SALT, no weak fallback
        if (!defined('AUTH_SALT') || empty(AUTH_SALT)) {
            throw new RuntimeException(
                'AUTH_SALT must be defined in wp-config.php for decryption. ' .
                'Please add a secure AUTH_SALT constant.'
            );
        }

        $key = AUTH_SALT;
        $key = substr(hash('sha256', $key), 0, 32);

        $payload = base64_decode($data);

        if (!str_contains($payload, '::')) {
            return '';
        }

        [$encrypted_data, $iv] = explode('::', $payload, 2);

        return openssl_decrypt($encrypted_data, self::METHOD, $key, 0, $iv) ?: '';
    }
}