<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class DepositTxBuilderChain
{
    /**
     * @param iterable<DepositTxBuilderInterface> $builders
     */
    public function __construct(
        #[AutowireIterator('app.deposit_tx_builder')]
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
        throw new LogicException(sprintf('No DepositTxBuilder supports chain "%s"', $chain));
    }

    /**
     * @param array<string, string> $context
     */
    public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
    {
        $chain = (string) $order->getFromChain();
        foreach ($this->builders as $builder) {
            if ($builder->supports($chain)) {
                return $builder->nextStep($order, $context);
            }
        }
        throw new LogicException(sprintf('No DepositTxBuilder supports chain "%s"', $chain));
    }
}
