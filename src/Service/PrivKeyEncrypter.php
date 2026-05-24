<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service;

use InvalidArgumentException;
use RuntimeException;

final readonly class PrivKeyEncrypter
{
    private const string CIPHER = 'aes-256-gcm';

    private const int NONCE_LEN = 12;

    private const int TAG_LEN = 16;

    private string $masterKey;

    public function __construct(string $masterKeyB64)
    {
        $decoded = base64_decode($masterKeyB64, true);

        if (false === $decoded || 32 !== strlen($decoded)) {
            throw new InvalidArgumentException('Master key must be a base64-encoded 32-byte string.');
        }

        $this->masterKey = $decoded;
    }

    /**
     * @return string binary blob: nonce || ciphertext || tag
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_LEN);
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->masterKey,
            \OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LEN,
        );

        if (false === $ciphertext) {
            throw new RuntimeException('AES-256-GCM encryption failed: ' . openssl_error_string());
        }

        return $nonce . $ciphertext . $tag;
    }

    /**
     * @throws RuntimeException on authentication failure (tampered blob)
     */
    public function decrypt(string $blob): string
    {
        $minLen = self::NONCE_LEN + self::TAG_LEN;

        if (strlen($blob) <= $minLen) {
            throw new RuntimeException('Encrypted blob is too short to be valid.');
        }

        $nonce      = substr($blob, 0, self::NONCE_LEN);
        $tag        = substr($blob, -self::TAG_LEN);
        $ciphertext = substr($blob, self::NONCE_LEN, -self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->masterKey,
            \OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if (false === $plaintext) {
            throw new RuntimeException('AES-256-GCM decryption failed — blob is tampered or key is wrong.');
        }

        return $plaintext;
    }
}
