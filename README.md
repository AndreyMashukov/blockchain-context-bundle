# blockchain-context-bundle

A Symfony 7 bundle integrating the TON and EVM PHP stacks into a single DI surface — autowired RPC clients, signer, ABI encoder, cell / BOC primitives, wallet v4r2, signature verifiers (EIP-191 + Ed25519 / TON Connect), and a pluggable HD deposit-wallet pool backed by Doctrine.

The bundle is a thin Symfony shell: every cryptographic and on-chain primitive lives in the underlying `amashukov/*` packages. The bundle's own surface is wiring, configuration, and the Doctrine + console workers needed to make those primitives idiomatic to use from a Symfony application.

## Status

Pre-1.0. Public API may change before the 1.0 tag.

## Requirements

- PHP 8.3+
- Symfony 7.x (`symfony/framework-bundle`)
- `doctrine/orm`
- `ext-gmp`, `ext-sodium`, `ext-curl`

## Dependencies

- [`amashukov/ton-php`](https://github.com/AndreyMashukov/ton-php) — full TON SDK
- [`amashukov/eth-php`](https://github.com/AndreyMashukov/eth-php) — full EVM SDK

## License

MIT License.
