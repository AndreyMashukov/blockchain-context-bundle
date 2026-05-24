<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Detection;

use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

readonly class ChainDepositCheckChain
{
    /**
     * @param iterable<ChainDepositCheckInterface> $checks
     */
    public function __construct(
        private iterable $checks,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function check(OrderView $order): bool
    {
        foreach ($this->checks as $check) {
            if ($check->supports($order)) {
                return $check->check($order);
            }
        }

        $this->logger->warning('ChainDepositCheckChain: no checker claims order fromChain', [
            'orderId'   => $order->getOrderId(),
            'fromChain' => $order->getFromChain(),
        ]);

        return false;
    }
}
