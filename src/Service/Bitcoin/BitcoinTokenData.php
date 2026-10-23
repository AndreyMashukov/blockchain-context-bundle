<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinTokenData
{
    public function __construct(
        public string $category,
        public string $amount,
        public ?string $nftCapability,
        public ?string $nftCommitment,
    ) {}
}
