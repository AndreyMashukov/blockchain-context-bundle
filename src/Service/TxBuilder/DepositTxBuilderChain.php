<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use LogicException;

readonly class DepositTxBuilderChain
{
    /**
     * @param iterable<DepositTxBuilderInterface> $builders
     */
    public function __construct(
        private iterable $builders,
    ) {}

    /**
     * @param array<string, string> $context
     */
    public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
    {
        $chain = (string) $order->getFromChain();
        foreach ($this->builders as $builder) {
            if ($builder->supports($chain)) {
                return $builder->build($order, $context);
            }
        }

        throw new LogicException(sprintf('DepositTxBuilderChain: no builder supports chain "%s"', $chain));
    }
}
