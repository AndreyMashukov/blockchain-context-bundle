<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

final readonly class NoopFinalityVerifier implements FinalityVerifierInterface
{
    public function verifyDepth(string $walletAddrRaw, string $hash, float $deadline): bool
    {
        return true;
    }

    public function checkPresence(string $walletAddrRaw, string $hash): bool
    {
        return true;
    }
}
