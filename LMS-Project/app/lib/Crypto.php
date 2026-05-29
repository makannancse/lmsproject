<?php

declare(strict_types=1);

class Crypto
{
    public static function encrypt(string $plainText): string
    {
        $key = self::key();
        if ($key === '') {
            throw new RuntimeException('Missing TOKEN_ENCRYPTION_KEY/APP_KEY.');
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $key = self::key();
        if ($key === '') {
            throw new RuntimeException('Missing TOKEN_ENCRYPTION_KEY/APP_KEY.');
        }

        $raw = base64_decode($encoded, true);
        if (!is_string($raw) || strlen($raw) <= 16) {
            throw new RuntimeException('Invalid encrypted payload.');
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return $plain;
    }

    private static function key(): string
    {
        $seed = (string) env('TOKEN_ENCRYPTION_KEY', env('APP_KEY', ''));
        return $seed === '' ? '' : hash('sha256', $seed, true);
    }
}

