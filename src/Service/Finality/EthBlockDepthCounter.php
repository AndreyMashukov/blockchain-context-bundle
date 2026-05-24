<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use Amashukov\EthRpc\EthRpcClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

final readonly class EthBlockDepthCounter implements ConfirmationCounterInterface
{
    private const string CACHE_KEY     = 'finality.eth_head';

    private const int CACHE_TTL_SECONDS = 10;

    public function __construct(
        private EthRpcClientInterface $ethRpc,
        private CacheInterface $cache,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function supports(string $chain): bool
    {
        return 'eth' === $chain || 'usdt_erc20' === $chain;
    }

    public function count(DepositTxView $depositTx, OrderView $order): int
    {
        $blockNumber = $depositTx->getBlockNumber();
        $required    = $depositTx->getConfirmationsRequired() ?? 0;
        if (null === $blockNumber || '' === $blockNumber || $required <= 0) {
            return 0;
        }

        try {
            $head = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): int {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $this->ethRpc->eth_blockNumber();
            });
        } catch (Throwable $e) {
            $this->logger->warning('EthBlockDepthCounter: head fetch failed', ['exception' => $e]);

            return 0;
        }

        $dep = ctype_digit($blockNumber) ? (int) $blockNumber : 0;
        if ($dep <= 0 || $head < $dep) {
            return 0;
        }

        $current = $head - $dep + 1;

        return min($current, $required);
    }
}
