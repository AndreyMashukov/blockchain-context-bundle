<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service;

interface SignatureVerifierInterface
{
    /**
     * Verify an EIP-191 (`personal_sign`) signature.
     *
     * @param string $message   plain-text message that was signed
     * @param string $signature 0x-prefixed 65-byte hex (r+s+v)
     * @param string $address   expected signer address (0x-prefixed, any case)
     */
    public function verifyEth(string $message, string $signature, string $address): bool;

    /**
     * Verify an Ed25519 (TON Connect) signature.
     *
     * @param string $message   plain-text message that was signed
     * @param string $signature base64-encoded 64-byte Ed25519 signature
     * @param string $publicKey hex-encoded 32-byte Ed25519 public key
     */
    public function verifyTon(string $message, string $signature, string $publicKey): bool;
}
