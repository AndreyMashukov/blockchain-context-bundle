<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use Amashukov\Toncenter\ToncenterClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

final readonly class TonMasterchainDepthCounter implements ConfirmationCounterInterface
{
    private const string CACHE_KEY     = 'finality.ton_mc_head';

    private const int CACHE_TTL_SECONDS = 10;

    public function __construct(
        private ToncenterClientInterface $tonRpc,
        private CacheInterface $cache,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function supports(string $chain): bool
    {
        return 'ton' === $chain || 'usdt_jetton' === $chain;
    }

    public function count(DepositTxView $depositTx, OrderView $order): int
    {
        $baseline = $depositTx->getMcSeqno();
        $required = $depositTx->getConfirmationsRequired() ?? 0;
        if (null === $baseline || $required <= 0) {
            return 0;
        }

        try {
            $head = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): int {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);

                return $this->tonRpc->getMasterchainInfo()->last->seqno;
            });
        } catch (Throwable $exception) {
            $this->logger->warning('TonMasterchainDepthCounter: masterchain head fetch failed', ['exception' => $exception]);

            return 0;
        }

        if ($head <= $baseline) {
            return 0;
        }
        $current = $head - $baseline;

        return min($current, $required);
    }
}
