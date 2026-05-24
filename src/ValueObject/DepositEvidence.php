<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\ValueObject;

final readonly class DepositEvidence
{
    /**
     * @param array<string, mixed> $rpcPayload
     */
    public function __construct(
        public string $chain,
        public ?string $txHash,
        public ?string $blockNumber,
        public ?string $fromAddress,
        public ?string $toAddress,
        public string $amount,
        public array $rpcPayload = [],
    ) {}
}
