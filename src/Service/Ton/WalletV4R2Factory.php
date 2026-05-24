<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Ton;

use Amashukov\TonCrypto\KeyPair;
use Amashukov\TonCrypto\Mnemonic;
use Amashukov\TonWallet\WalletV4R2;

final readonly class WalletV4R2Factory
{
    public function create(?string $mnemonic): WalletV4R2
    {
        $phrase = trim((string) $mnemonic);
        if ('' === $phrase) {
            return new WalletV4R2(KeyPair::fromSeed(str_repeat("\x00", KeyPair::SEED_BYTES)));
        }

        return new WalletV4R2(Mnemonic::toKeyPair($phrase));
    }
}
