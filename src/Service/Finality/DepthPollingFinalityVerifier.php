<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Time\SleeperInterface;
use Amashukov\Toncenter\TonRpcException;
use Amashukov\Toncenter\ToncenterClientInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class DepthPollingFinalityVerifier implements FinalityVerifierInterface
{
    private const int POLL_INTERVAL_SECONDS = 5;

    public function __construct(
        private ToncenterClientInterface $rpc,
        private SleeperInterface $sleeper,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private int $polls,
    ) {}

    public function verifyDepth(string $walletAddrRaw, string $hash, float $deadline): bool
    {
        if ($this->polls <= 0) {
            return true;
        }

        for ($i = 0; $i < $this->polls; ++$i) {
            if ((float) $this->clock->now()->format('U.u') >= $deadline) {
                return true;
            }
            $this->sleeper->sleep(self::POLL_INTERVAL_SECONDS);

            try {
                $txs = $this->rpc->getTypedTransactions($walletAddrRaw, ['limit' => 10]);
            } catch (TonRpcException $exception) {
                $this->logger->warning(
                    sprintf('DepthPollingFinalityVerifier: getTypedTransactions failed during depth re-poll #%d: %s', $i + 1, $exception->getMessage()),
                    [
                        'wallet'    => $walletAddrRaw,
                        'hash'      => $hash,
                        'exception' => $exception,
                    ],
                );

                continue;
            }

            $stillPresent = false;
            foreach ($txs as $entry) {
                if ($entry->hash === $hash) {
                    $stillPresent = true;
                    break;
                }
            }
            if (!$stillPresent) {
                return false;
            }
        }

        return true;
    }

    public function checkPresence(string $walletAddrRaw, string $hash): bool
    {
        try {
            $txs = $this->rpc->getTypedTransactions($walletAddrRaw, ['limit' => 10]);
        } catch (TonRpcException $exception) {
            $this->logger->warning(
                sprintf('DepthPollingFinalityVerifier::checkPresence failed (treating as present, no false-positive orphan): %s', $exception->getMessage()),
                [
                    'wallet'    => $walletAddrRaw,
                    'hash'      => $hash,
                    'exception' => $exception,
                ],
            );

            return true;
        }

        foreach ($txs as $entry) {
            if ($entry->hash === $hash) {
                return true;
            }
        }

        return false;
    }
}
