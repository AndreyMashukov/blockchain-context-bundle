<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Gas;

use Amashukov\Toncenter\ToncenterClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final readonly class TonGasFetcher implements TonGasFetcherInterface
{
    public function __construct(
        private ToncenterClientInterface $tonRpc,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @return null|numeric-string `in_msg.fwd_fee` (nanotons)
     */
    public function fetchFwdFeeNano(string $depositAddress, string $txHash): ?string
    {
        if ('' === $depositAddress || '' === $txHash) {
            return null;
        }

        try {
            $txs = $this->tonRpc->getTypedTransactions($depositAddress, ['limit' => 50]);
        } catch (Throwable $e) {
            $this->logger->warning('TonGasFetcher: getTypedTransactions failed', [
                'exception' => $e,
                'address'   => $depositAddress,
                'txHash'    => $txHash,
            ]);

            return null;
        }

        foreach ($txs as $tx) {
            if ($tx->hash !== $txHash) {
                continue;
            }
            $fwdFee = $tx->inMsg?->fwdFee;
            if (null === $fwdFee || !is_numeric($fwdFee)) {
                return null;
            }

            return $fwdFee;
        }

        return null;
    }
}
