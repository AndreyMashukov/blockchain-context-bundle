<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use Amashukov\AbiEncoder\AbiEncoder;
use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use Amashukov\EthRpc\Numeric\HexBig;
use Amashukov\EthRpc\Numeric\HexInt;
use InvalidArgumentException;

final readonly class EthDepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private int $chainId,
        private UuidIntCodec $uuidIntCodec,
    ) {}

    public function supports(string $chain): bool
    {
        return 'eth' === $chain;
    }

    public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
    {
        $to         = strtolower((string) $order->getDepositAddress());
        $orderId    = $this->uuidIntCodec->encode((string) $order->getOrderId());
        $fromAmount = (string) $order->getFromAmount();
        if (!is_numeric($fromAmount)) {
            throw new InvalidArgumentException(sprintf('EthDepositTxBuilder: order.fromAmount must be numeric-string; got "%s".', $fromAmount));
        }
        $valueWei = bcmul($fromAmount, '1000000000000000000', 0);

        $data = AbiEncoder::encodeCall(
            'depositForBridge(uint256)',
            [['uint256', $orderId]],
        );

        return new DepositTxPayload('evm-native', [
            'to'      => $to,
            'data'    => $data,
            'value'   => HexBig::toHex($valueWei),
            'chainId' => HexInt::toHex($this->chainId),
        ]);
    }

    public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
    {
        $payload = $this->build($order, $context);

        return new DepositTxStep(
            kind: 'evm-deposit-native',
            buttonLabel: sprintf('Send %s ETH', (string) $order->getFromAmount()),
            tx: $payload->payload,
            done: false,
        );
    }
}
