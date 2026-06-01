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

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        $chain = (string) $order->getFromChain();
        foreach ($this->builders as $builder) {
            if ($builder->supports($chain)) {
                return $builder->build($order);
            }
        }
        throw new LogicException(sprintf('No DepositTxBuilder supports chain "%s"', $chain));
    }

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep
    {
        $chain = (string) $order->getFromChain();
        foreach ($this->builders as $builder) {
            if ($builder->supports($chain)) {
                return $builder->nextStep($order, $signer);
            }
        }
        throw new LogicException(sprintf('No DepositTxBuilder supports chain "%s"', $chain));
    }
}
