<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\Numeric\UsdtJettonDecimals;
use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Builder;
use Amashukov\TonWallet\Address;

final readonly class TonJettonDepositTxBuilder implements DepositTxBuilderInterface
{
    private const string OUTER_GAS_NANO = '100000000';

    private const int FORWARD_TON_AMOUNT_NANO = 50000000;

    public function supports(string $chain): bool
    {
        return 'usdt_jetton' === $chain;
    }

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        $vault       = $order->getVault()->getAddress();
        $fromAmount  = (string) $order->getFromAmount();
        $amountUnits = UsdtJettonDecimals::toAtomic($fromAmount);
        $memo        = (string) $order->getDepositMemo();

        $forwardCell = (new Builder())
            ->storeUint(0, 32)
            ->storeStringTail($memo)
            ->endCell();

        $body = (new Builder())
            ->storeUint(0x0F8A7EA5, 32)
            ->storeUint(0, 64)
            ->storeCoins($amountUnits)
            ->storeAddress(Address::parse($vault)->toCellData())
            ->storeAddress(Address::parse($vault)->toCellData())
            ->storeBit(0)
            ->storeCoins(self::FORWARD_TON_AMOUNT_NANO)
            ->storeBit(1)
            ->storeRef($forwardCell)
            ->endCell();

        return new DepositTxPayload('ton-jetton', [
            'address' => $vault,
            'amount'  => self::OUTER_GAS_NANO,
            'payload' => Boc::encodeBase64($body),
        ]);
    }

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep
    {
        $payload = $this->build($order);

        return new DepositTxStep(
            kind: 'ton-deposit-jetton',
            buttonLabel: sprintf('Send %s USDT-Jetton', (string) $order->getFromAmount()),
            tx: $payload->payload,
            done: false,
        );
    }
}
