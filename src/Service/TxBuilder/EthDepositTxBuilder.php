<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

final readonly class EthDepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private DepositEncoderInterface $encoder,
    ) {}

    public function supports(string $chain): bool
    {
        return 'eth' === $chain;
    }

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        return new DepositTxPayload('evm-native', $this->encoder->evmNativeDeposit($order, $order->getVault()->getAddress()));
    }

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep
    {
        return new DepositTxStep(
            kind: 'evm-deposit-native',
            buttonLabel: sprintf('Send %s ETH', (string) $order->getFromAmount()),
            tx: $this->build($order)->payload,
            done: false,
        );
    }
}
