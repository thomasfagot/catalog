<?php

namespace App\Service;

class EncryptionService
{
    private const string METHOD = 'aes-256-gcm';

    public function encrypt(string $data, string $password): string
    {
        $salt = random_bytes(16);
        $key = $this->deriveKey($password, $salt);
        $iv = random_bytes(openssl_cipher_iv_length(self::METHOD));

        $encrypted = openssl_encrypt($data, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($salt . $iv . $tag . $encrypted);
    }

    public function decrypt(string $encodedData, string $password): ?string
    {
        $decoded = base64_decode($encodedData);
        $salt = substr($decoded, 0, 16);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = substr($decoded, 16, $ivLength);
        $tag = substr($decoded, 16 + $ivLength, 16);
        $encrypted = substr($decoded, 32 + $ivLength);

        $key = $this->deriveKey($password, $salt);

        $decrypted = openssl_decrypt($encrypted, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $decrypted !== false ? $decrypted : null;
    }

    private function deriveKey(string $password, string $salt): string
    {
        return hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
    }
}
