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
    ) {}

    public function supports(string $chain): bool
    {
        return 'usdt_erc20' === $chain;
    }

    public function build(DepositTxOrderView $order): DepositTxPayload
    {
        $vault       = strtolower($order->getVault()->getAddress());
        $token       = strtolower($this->usdtTokenAddress);
        $orderId     = $this->uuidIntCodec->encode((string) $order->getOrderId());
        $amountUnits = $this->amountUnits($order);

        return new DepositTxPayload('evm-erc20', [
            'approve' => $this->approveTx($token, $vault, $amountUnits),
            'deposit' => $this->depositTx($token, $vault, $amountUnits, $orderId),
        ]);
    }

    public function nextStep(DepositTxOrderView $order, SignerInterface $signer): DepositTxStep
    {
        $vault       = strtolower($order->getVault()->getAddress());
        $token       = strtolower($this->usdtTokenAddress);
        $orderId     = $this->uuidIntCodec->encode((string) $order->getOrderId());
        $amountUnits = $this->amountUnits($order);
        $fromAmount  = (string) $order->getFromAmount();

        if (!$this->hasSufficientBalance($token, $signer->getAddress(), $amountUnits)) {
            throw new InvalidArgumentException('Erc20DepositTxBuilder: signer USDT balance is below the deposit amount.');
        }

        if ($this->hasSufficientAllowance($token, $signer->getAddress(), $vault, $amountUnits)) {
            return new DepositTxStep(
                kind: 'evm-deposit-erc20',
                buttonLabel: sprintf('Deposit %s USDT to bridge', $fromAmount),
                tx: $this->depositTx($token, $vault, $amountUnits, $orderId),
                done: false,
            );
        }

        return new DepositTxStep(
            kind: 'evm-approve',
            buttonLabel: sprintf('Approve %s USDT spending', $fromAmount),
            tx: $this->approveTx($token, $vault, $amountUnits),
            done: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approveTx(string $token, string $vault, string $amountUnits): array
    {
        return [
            'to'      => $token,
            'data'    => AbiEncoder::encodeCall('approve(address,uint256)', [
                ['address', $vault],
                ['uint256', $amountUnits],
            ]),
            'value'   => '0x0',
            'chainId' => HexInt::toHex($this->chainId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function depositTx(string $token, string $vault, string $amountUnits, string $orderId): array
    {
        return [
            'to'      => $vault,
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

    private function hasSufficientBalance(string $token, string $signer, string $requiredUnits): bool
    {
        try {
            $callData = AbiEncoder::encodeCall('balanceOf(address)', [['address', strtolower($signer)]]);
            $result   = $this->ethRpc->eth_call(['to' => $token, 'data' => $callData], 'latest');
        } catch (Throwable) {
            return false;
        }

        if ('' === $result || '0x' === $result || !is_numeric($requiredUnits)) {
            return false;
        }

        return bccomp(HexBig::fromHex($result), $requiredUnits, 0) >= 0;
    }

    private function hasSufficientAllowance(string $token, string $signer, string $vault, string $requiredUnits): bool
    {
        try {
            $callData = AbiEncoder::encodeCall(
                'allowance(address,address)',
                [
                    ['address', strtolower($signer)],
                    ['address', $vault],
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
