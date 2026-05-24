<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\ValueObject;

final readonly class DerivedWallet
{
    public function __construct(
        public string $chain,
        public int $derivationIndex,
        public string $address,
        public string $privKey,
    ) {}
}
