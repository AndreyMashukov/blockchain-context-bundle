<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class BlockchainContextExtension extends Extension
{
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

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../Resources/config/'),
        );
        $loader->load('services.yaml');
    }
}
