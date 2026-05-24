<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\DependencyInjection;

use Amashukov\BlockchainContextBundle\Service\Detection\ChainDepositCheckInterface;
use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationCheckInterface;
use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationCounterInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxBuilderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class BlockchainContextExtension extends Extension
{
    private const array TAGGED_PORTS = [
        ChainDepositCheckInterface::class => 'blockchain_context.chain_deposit_check',
        ConfirmationCheckInterface::class => 'blockchain_context.confirmation_check',
        ConfirmationCounterInterface::class => 'blockchain_context.confirmation_counter',
        DepositTxBuilderInterface::class => 'blockchain_context.deposit_tx_builder',
    ];

    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (['eth', 'ton'] as $chain) {
            foreach ($config[$chain] as $key => $value) {
                $container->setParameter(sprintf('blockchain_context.%s.%s', $chain, $key), $value);
            }
        }
        $container->setParameter('blockchain_context.deposit_wallet_encryption_key', $config['deposit_wallet_encryption_key']);

        foreach (self::TAGGED_PORTS as $interface => $tag) {
            $container->registerForAutoconfiguration($interface)->addTag($tag);
        }

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../Resources/config/'),
        );
        $loader->load('services.yaml');
    }
}
