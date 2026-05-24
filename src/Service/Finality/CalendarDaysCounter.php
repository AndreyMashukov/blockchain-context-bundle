<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use Psr\Clock\ClockInterface;

final readonly class CalendarDaysCounter implements ConfirmationCounterInterface
{
    private const int SECONDS_PER_DAY = 86_400;

    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function supports(string $chain): bool
    {
        return 'stars' === $chain;
    }

    public function count(DepositTxView $depositTx, OrderView $order): int
    {
        $required = $depositTx->getConfirmationsRequired() ?? 0;
        if ($required <= 0) {
            return 0;
        }

        $elapsedSeconds = $this->clock->now()->getTimestamp() - $order->getCreatedAt()->getTimestamp();
        if ($elapsedSeconds <= 0) {
            return 0;
        }

        $days = intdiv($elapsedSeconds, self::SECONDS_PER_DAY);

        return min($days, $required);
    }
}
