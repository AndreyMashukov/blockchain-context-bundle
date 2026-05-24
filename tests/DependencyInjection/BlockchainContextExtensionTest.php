<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\DependencyInjection;

use Amashukov\BlockchainContextBundle\DependencyInjection\BlockchainContextExtension;
use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationCounterRegistry;
use Amashukov\BlockchainContextBundle\Service\PrivKeyEncrypter;
use Amashukov\BlockchainContextBundle\Service\SignatureVerifier;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxBuilderChain;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BlockchainContextExtensionTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'eth' => [
                'rpc_url'            => 'http://rpc-proxy:9999',
                'chain_id'           => 11155111,
                'wallet_private_key' => str_repeat('1', 64),
                'usdt_token_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'explorer'           => 'https://etherscan.io',
            ],
            'ton' => [
                'enabled'           => false,
                'toncenter_api_key' => 'k',
                'wallet_mnemonic'   => '',
                'bridge_contract'   => 'EQbridge',
                'finality_polls'    => 3,
            ],
            'deposit_wallet_encryption_key' => base64_encode(str_repeat("\x00", 32)),
        ];
    }

    private function load(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new BlockchainContextExtension())->load([$this->config()], $container);

        return $container;
    }

    public function testConfigValuesBecomeContainerParameters(): void
    {
        $container = $this->load();

        self::assertSame('http://rpc-proxy:9999', $container->getParameter('blockchain_context.eth.rpc_url'));
        self::assertSame(11155111, $container->getParameter('blockchain_context.eth.chain_id'));
        self::assertTrue($container->getParameter('blockchain_context.eth.enabled'));
        self::assertSame(3, $container->getParameter('blockchain_context.ton.finality_polls'));
        self::assertSame('EQbridge', $container->getParameter('blockchain_context.ton.bridge_contract'));
        self::assertFalse($container->getParameter('blockchain_context.ton.enabled'));
    }

    public function testDefaultsApplyWhenHostOmitsConfig(): void
    {
        $container = new ContainerBuilder();
        (new BlockchainContextExtension())->load([], $container);

        self::assertSame('', $container->getParameter('blockchain_context.eth.rpc_url'));
        self::assertSame(0, $container->getParameter('blockchain_context.eth.chain_id'));
        self::assertSame(0, $container->getParameter('blockchain_context.ton.finality_polls'));
        self::assertSame('https://tonscan.org', $container->getParameter('blockchain_context.ton.explorer'));
        self::assertTrue($container->getParameter('blockchain_context.eth.enabled'));
        self::assertTrue($container->getParameter('blockchain_context.ton.enabled'));
    }

    public function testCoreServicesAreRegistered(): void
    {
        $container = $this->load();

        self::assertTrue($container->hasDefinition(SignatureVerifier::class));
        self::assertTrue($container->hasDefinition(PrivKeyEncrypter::class));
    }

    public function testPrivKeyEncrypterMasterKeyBoundToConfigParameter(): void
    {
        $container = $this->load();

        self::assertSame(
            '%blockchain_context.deposit_wallet_encryption_key%',
            $container->getDefinition(PrivKeyEncrypter::class)->getArgument('$masterKeyB64'),
        );
    }

    public function testTaggedIteratorChainsWiredToBundleTags(): void
    {
        $container = $this->load();

        $builders = $container->getDefinition(DepositTxBuilderChain::class)->getArgument('$builders');
        if (!$builders instanceof TaggedIteratorArgument) {
            self::fail('DepositTxBuilderChain $builders must be a tagged iterator');
        }
        self::assertSame('blockchain_context.deposit_tx_builder', $builders->getTag());

        $counters = $container->getDefinition(ConfirmationCounterRegistry::class)->getArgument('$counters');
        if (!$counters instanceof TaggedIteratorArgument) {
            self::fail('ConfirmationCounterRegistry $counters must be a tagged iterator');
        }
        self::assertSame('blockchain_context.confirmation_counter', $counters->getTag());
    }
}
