<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

use Amashukov\AbiEncoder\AbiEncoder;
use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use Amashukov\EthRpc\EthRpcClientInterface;
use Amashukov\EthRpc\Numeric\HexBig;
use Amashukov\EthRpc\Numeric\HexInt;
use InvalidArgumentException;
use Throwable;

final readonly class Erc20DepositTxBuilder implements DepositTxBuilderInterface
{
    public function __construct(
        private string $usdtTokenAddress,
        private int $chainId,
        private UuidIntCodec $uuidIntCodec,
        private EthRpcClientInterface $ethRpc,
        private GasEstimatorInterface $gasEstimator,
    ) {}

    public function supports(string $chain): bool
    {
        return 'usdt_erc20' === $chain;
    }

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        $bridge      = strtolower((string) $order->getDepositAddress());
        $token       = strtolower($this->usdtTokenAddress);
        $orderId     = $this->uuidIntCodec->encode((string) $order->getOrderId());
        $amountUnits = $this->amountUnits($order);

        return new DepositTxPayload('evm-erc20', [
            'approve' => $this->approveTx($token, $bridge, $amountUnits),
            'deposit' => $this->depositTx($token, $bridge, $amountUnits, $orderId),
        ]);
    }

    public function nextStep(DepositTxOrderView $order): DepositTxStep
    {
        $userAddress = $order->getUserWallet()?->userAddress();
        if (null === $userAddress || '' === $userAddress) {
            throw new InvalidArgumentException('Erc20DepositTxBuilder::nextStep requires a bound user wallet for the allowance check.');
        }

        $fromAmount  = (string) $order->getFromAmount();
        $bridge      = strtolower((string) $order->getDepositAddress());
        $token       = strtolower($this->usdtTokenAddress);
        $orderId     = $this->uuidIntCodec->encode((string) $order->getOrderId());
        $amountUnits = $this->amountUnits($order);

        if ($this->hasSufficientAllowance($token, $userAddress, $bridge, $amountUnits)) {
            return new DepositTxStep(
                kind: 'evm-deposit-erc20',
                buttonLabel: sprintf('Deposit %s USDT to bridge', $fromAmount),
                tx: $this->withGas($this->depositTx($token, $bridge, $amountUnits, $orderId), $userAddress),
                done: false,
            );
        }

        return new DepositTxStep(
            kind: 'evm-approve',
            buttonLabel: sprintf('Approve %s USDT spending', $fromAmount),
            tx: $this->withGas($this->approveTx($token, $bridge, $amountUnits), $userAddress),
            done: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approveTx(string $token, string $bridge, string $amountUnits): array
    {
        return [
            'to'      => $token,
            'data'    => AbiEncoder::encodeCall('approve(address,uint256)', [
                ['address', $bridge],
                ['uint256', $amountUnits],
            ]),
            'value'   => '0x0',
            'chainId' => HexInt::toHex($this->chainId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function depositTx(string $token, string $bridge, string $amountUnits, string $orderId): array
    {
        return [
            'to'      => $bridge,
            'data'    => AbiEncoder::encodeCall('depositTokenForBridge(address,uint256,uint256)', [
                ['address', $token],
                ['uint256', $amountUnits],
                ['uint256', $orderId],
            ]),
            'value'   => '0x0',
            'chainId' => HexInt::toHex($this->chainId),
        ];
    }

    private function amountUnits(DepositTxOrderView $order): string
    {
        $fromAmount = (string) $order->getFromAmount();
        if (!is_numeric($fromAmount)) {
            throw new InvalidArgumentException(sprintf('Erc20DepositTxBuilder: order.fromAmount must be numeric-string; got "%s".', $fromAmount));
        }

        return bcmul($fromAmount, '1000000', 0);
    }

    /**
     * @param array<string, mixed> $tx
     *
     * @return array<string, mixed>
     */
    private function withGas(array $tx, string $from): array
    {
        $gas = $this->gasEstimator->estimateForTx($tx, $from);
        if (null !== $gas) {
            $tx['gas'] = $gas;
        }

        return $tx;
    }

    private function hasSufficientAllowance(string $token, string $userAddress, string $bridge, string $requiredUnits): bool
    {
        try {
            $callData = AbiEncoder::encodeCall(
                'allowance(address,address)',
                [
                    ['address', strtolower($userAddress)],
                    ['address', $bridge],
                ],
            );
            $result = $this->ethRpc->eth_call([
                'to'   => $token,
                'data' => $callData,
            ], 'latest');
        } catch (Throwable) {
            return false;
        }

        if ('' === $result || '0x' === $result) {
            return false;
        }

        $allowance = HexBig::fromHex($result);

        if (!is_numeric($requiredUnits)) {
            return false;
        }

        return bccomp($allowance, $requiredUnits, 0) >= 0;
    }
}
