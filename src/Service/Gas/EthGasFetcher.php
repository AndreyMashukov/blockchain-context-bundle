<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Gas;

use Amashukov\EthRpc\JsonRpcProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final readonly class EthGasFetcher implements EthGasFetcherInterface
{
    public function __construct(
        private JsonRpcProviderInterface $typedRpc,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @return null|numeric-string decimal wei (`gas_used * effective_gas_price`)
     */
    public function fetchWei(string $txHash): ?string
    {
        if ('' === $txHash) {
            return null;
        }

        try {
            $bundle = $this->typedRpc->getTypedTransaction($txHash);
        } catch (Throwable $e) {
            $this->logger->warning('EthGasFetcher: typed receipt fetch failed', [
                'exception' => $e,
                'txHash'    => $txHash,
            ]);

            return null;
        }

        if ($bundle->isStatusPending()) {
            return null;
        }

        $fee = $bundle->receipt->fee;
        if (null === $fee || !is_numeric($fee)) {
            return null;
        }

        return $fee;
    }
}
