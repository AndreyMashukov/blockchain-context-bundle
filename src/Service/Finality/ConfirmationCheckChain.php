<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

readonly class ConfirmationCheckChain
{
    /**
     * @param iterable<ConfirmationCheckInterface> $checks
     */
    public function __construct(
        private iterable $checks,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function check(OrderView $order): ConfirmationState
    {
        foreach ($this->checks as $check) {
            if ($check->supports($order)) {
                return $check->check($order);
            }
        }

        $this->logger->warning('ConfirmationCheckChain: no checker claims order fromChain — staying PENDING', [
            'orderId'   => $order->getOrderId(),
            'fromChain' => $order->getFromChain(),
        ]);

        return ConfirmationState::PENDING;
    }
}
