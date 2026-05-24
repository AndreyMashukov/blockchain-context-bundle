<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service;

use Amashukov\Keccak\Keccak;
use Amashukov\Secp256k1\Ecdsa;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

final readonly class SignatureVerifier implements SignatureVerifierInterface
{
    private const string ETH_PREFIX = "\x19Ethereum Signed Message:\n";

    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param string $message   plain-text message that was signed
     * @param string $signature 0x-prefixed 65-byte hex (r+s+v)
     * @param string $address   expected signer address (0x-prefixed, any case)
     */
    public function verifyEth(string $message, string $signature, string $address): bool
    {
        try {
            $prefix  = self::ETH_PREFIX . strlen($message);
            $msgHash = Keccak::hash($prefix . $message, 256, true);

            $hexSig = str_starts_with($signature, '0x') || str_starts_with($signature, '0X')
                ? substr($signature, 2)
                : $signature;
            $sig = (0 === strlen($hexSig) % 2) ? hex2bin($hexSig) : false;
            if (false === $sig || 65 !== strlen($sig)) {
                return false;
            }

            $r = substr($sig, 0, 32);
            $s = substr($sig, 32, 32);
            $v = ord($sig[64]);

            if ($v >= 27) {
                $v -= 27;
            }
            if (0 !== $v && 1 !== $v) {
                return false;
            }

            $publicKey = Ecdsa::recover($msgHash, $v, $r, $s);
            if (null === $publicKey) {
                return false;
            }

            $recovered = '0x' . substr(Keccak::hash(substr($publicKey, 1), 256), 24);

            return strtolower($recovered) === strtolower($address);
        } catch (Throwable $exception) {
            $this->logger->warning(
                sprintf('signature-verifier: ETH signature verification failed: %s', $exception->getMessage()),
                [
                    'address'   => $address,
                    'exception' => $exception,
                ],
            );

            return false;
        }
    }

    /**
     * @param string $message   plain-text message that was signed
     * @param string $signature base64-encoded 64-byte Ed25519 signature
     * @param string $publicKey hex-encoded 32-byte Ed25519 public key
     */
    public function verifyTon(string $message, string $signature, string $publicKey): bool
    {
        try {
            if (!extension_loaded('sodium')) {
                throw new RuntimeException('ext-sodium is required for TON signature verification.');
            }

            $sigBytes = base64_decode($signature, true);
            if (false === $sigBytes || \SODIUM_CRYPTO_SIGN_BYTES !== strlen($sigBytes)) {
                return false;
            }

            $pkBytes = (0 === strlen($publicKey) % 2) ? hex2bin($publicKey) : false;
            if (false === $pkBytes || \SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen($pkBytes)) {
                return false;
            }

            return sodium_crypto_sign_verify_detached($sigBytes, $message, $pkBytes);
        } catch (Throwable $exception) {
            $this->logger->warning(
                sprintf('signature-verifier: TON signature verification failed: %s', $exception->getMessage()),
                [
                    'publicKey' => $publicKey,
                    'exception' => $exception,
                ],
            );

            return false;
        }
    }
}
