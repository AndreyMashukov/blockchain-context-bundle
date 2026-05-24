<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('blockchain_context');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('eth')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('rpc_url')->defaultValue('')->end()
                        ->integerNode('chain_id')->defaultValue(0)->end()
                        ->scalarNode('wallet_private_key')->defaultValue('')->end()
                        ->scalarNode('usdt_token_address')->defaultValue('')->end()
                        ->scalarNode('explorer')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('ton')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('toncenter_api_key')->defaultValue('')->end()
                        ->scalarNode('wallet_mnemonic')->defaultValue('')->end()
                        ->scalarNode('bridge_contract')->defaultValue('')->end()
                        ->integerNode('finality_polls')->defaultValue(0)->min(0)->end()
                        ->scalarNode('explorer')->defaultValue('https://tonscan.org')->end()
                    ->end()
                ->end()
                ->scalarNode('deposit_wallet_encryption_key')->defaultValue('')->end()
            ->end();

        return $treeBuilder;
    }
}
