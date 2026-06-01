<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

final readonly class TonJettonDepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private DepositEncoderInterface $encoder,
    ) {}

    public function supports(string $chain): bool
    {
        return 'usdt_jetton' === $chain;
    }

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        return new DepositTxPayload('ton-jetton', $this->encoder->tonJettonDeposit($order, $order->getVault()->getAddress()));
    }

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep
    {
        return new DepositTxStep(
            kind: 'ton-deposit-jetton',
            buttonLabel: sprintf('Send %s USDT-Jetton', (string) $order->getFromAmount()),
            tx: $this->build($order)->payload,
            done: false,
        );
    }
}
