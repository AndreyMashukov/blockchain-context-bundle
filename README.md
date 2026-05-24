# amashukov/blockchain-context-bundle

Symfony 7 bundle for crypto payments — autowires the pure-PHP TON + EVM SDKs into typed RPC clients, signature verification, key encryption, and per-chain finality / detection / gas / tx-builder services.

[![CI](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/blockchain-context-bundle/ci.yml?branch=main&label=CI)](https://github.com/AndreyMashukov/blockchain-context-bundle/actions)
[![PHPStan L9](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/blockchain-context-bundle/stan.yml?branch=main&label=PHPStan%20L9)](https://github.com/AndreyMashukov/blockchain-context-bundle/actions)
[![Latest Version](https://img.shields.io/packagist/v/amashukov/blockchain-context-bundle)](https://packagist.org/packages/amashukov/blockchain-context-bundle)
[![Downloads](https://img.shields.io/packagist/dt/amashukov/blockchain-context-bundle)](https://packagist.org/packages/amashukov/blockchain-context-bundle)
[![PHP](https://img.shields.io/packagist/dependency-v/amashukov/blockchain-context-bundle/php)](https://packagist.org/packages/amashukov/blockchain-context-bundle)
[![License](https://img.shields.io/packagist/l/amashukov/blockchain-context-bundle)](LICENSE)
[![Stars](https://img.shields.io/github/stars/AndreyMashukov/blockchain-context-bundle?style=social)](https://github.com/AndreyMashukov/blockchain-context-bundle)

`amashukov/blockchain-context-bundle` is a **Symfony 7 bundle for crypto payments** that turns the pure-PHP TON + EVM SDKs ([`amashukov/ton-php`](https://github.com/AndreyMashukov/ton-php) + [`amashukov/eth-php`](https://github.com/AndreyMashukov/eth-php)) into a ready, autowired blockchain surface for your application — typed JSON-RPC clients, deposit-detection / finality / gas / deposit-tx-builder services, EIP-191 + TON Connect signature verification, and AES-256-GCM private-key encryption — all configured from one place, env-agnostic, with full DI autowiring.

## Features

- **Autowired RPC clients** — `Amashukov\EthRpc\JsonRpcProvider` / `EthRpcClient` (ethers.js-style EVM JSON-RPC) and `Amashukov\Toncenter\ToncenterClient` (typed toncenter v2), wired over a PSR-18 transport (`amashukov/http-client-php` cURL client; toncenter gets an `X-Api-Key` + `429/5xx/542`-retry middleware pipeline).
- **Signing & wallet** — `Amashukov\Eip1559TxSigner\Eip1559Signer` (EIP-1559 offline signer) and `Amashukov\TonWallet\WalletV4R2` (built from a mnemonic via the bundle factory); `ToncenterWalletRpc` implements the wallet's RPC port.
- **Per-chain domain services** (host-app-agnostic, owning ports the host implements): `Detection\ChainDepositCheckChain`, `Finality\{ConfirmationCheckChain,ConfirmationCounterRegistry,DepthPollingFinalityVerifier}`, `Gas\{EthGasFetcher,TonGasFetcher,ZeroGasFetcher}`, `TxBuilder\DepositTxBuilderChain` (+ per-chain TON / TON-Jetton / ETH / USDT-ERC20 builders), `Explorer\DefaultExplorerUrl`, `Numeric\{BcDecimal,UuidIntCodec,UsdtJettonDecimals}`, `Time\RealSleeper`.
- **`SignatureVerifier`** — EIP-191 (`personal_sign`, secp256k1 ecrecover + Keccak-256) + Ed25519 (TON Connect) verification.
- **`PrivKeyEncrypter`** — AES-256-GCM authenticated encryption for keys at rest.
- **`DepositWalletDeriverInterface`** port + `DerivedWallet` / `DepositEvidence` value objects.
- **Tagged-iterator chains** — `ChainDepositCheckChain`, `ConfirmationCheckChain`, `ConfirmationCounterRegistry`, and `DepositTxBuilderChain` auto-collect their members via bundle-namespaced tags (`blockchain_context.*`); drop a new impl and it joins the chain with no DI edits.

## Why amashukov/blockchain-context-bundle

There is no maintained Symfony bundle for crypto-payment infrastructure — most projects glue a raw RPC client into a service by hand. This bundle fills that empty niche: it wires the entire TON + EVM stack into Symfony's container with autowiring, env-agnostic config, and a tagged-iterator extension model, so adding a new chain or deposit builder is a drop-in service, not a DI rewrite.

## Installation

```bash
composer require amashukov/blockchain-context-bundle
```

Register the bundle (Symfony Flex does this automatically):

```php
// config/bundles.php
return [
    // ...
    Amashukov\BlockchainContextBundle\BlockchainContextBundle::class => ['all' => true],
];
```

## Usage

Inject any autowired service directly:

```php
use Amashukov\EthRpc\JsonRpcProviderInterface;
use Amashukov\Toncenter\ToncenterClientInterface;
use Amashukov\BlockchainContextBundle\Service\SignatureVerifier;

final class SomeService
{
    public function __construct(
        private JsonRpcProviderInterface $eth,
        private ToncenterClientInterface $ton,
        private SignatureVerifier $verifier,
    ) {}
}
```

### Configuration

The bundle is **env-agnostic**: it exposes a config tree and reads only `%blockchain_context.*%` parameters internally. The host maps them to its own environment (use `%env(...)%`, with `default:` processors as you like). Config is split per chain (`eth:` / `ton:`), each toggleable via `enabled` (default `true` — set `false` for a single-chain deployment):

```yaml
# config/packages/blockchain_context.yaml
blockchain_context:
    eth:
        enabled:            true
        rpc_url:            '%env(ETH_RPC_URL)%'                  # EVM JSON-RPC endpoint
        chain_id:          '%env(int:BRIDGE_ETH_CHAIN_ID)%'
        wallet_private_key:'%env(BRIDGE_ETH_WALLET_PRIVATE_KEY)%'
        usdt_token_address:'%env(USDT_TOKEN_ADDRESS)%'           # USDT-ERC20 deposits
        explorer:          '%env(BRIDGE_ETH_EXPLORER)%'          # default https://etherscan.io
    ton:
        enabled:            true
        toncenter_api_key: '%env(TONCENTER_API_KEY)%'            # optional — lifts toncenter rate limit
        wallet_mnemonic:   '%env(BRIDGE_TON_WALLET_MNEMONIC)%'
        bridge_contract:   '%env(BRIDGE_TON_CONTRACT)%'          # USDT-Jetton deposits
        finality_polls:    '%env(int:TON_FINALITY_POLLS)%'       # 0 = skip depth re-poll
        explorer:          '%env(TON_EXPLORER)%'                 # default https://tonscan.org
    deposit_wallet_encryption_key: '%env(DEPOSIT_WALLET_ENCRYPTION_KEY)%' # base64 of 32 bytes
```

Every key is optional (sensible empty / `0` / explorer defaults), so a host that uses only one chain can leave the other section's values unset (or `enabled: false`).

## Requirements

- PHP 8.3+
- Symfony 7.x (`symfony/framework-bundle`)
- `ext-gmp`, `ext-sodium`, `ext-openssl`, `ext-bcmath`

## Related packages

| Package | Layer |
|---------|-------|
| [amashukov/ton-php](https://github.com/AndreyMashukov/ton-php) | Umbrella TON SDK (Cell/BOC, wallet, toncenter) |
| [amashukov/eth-php](https://github.com/AndreyMashukov/eth-php) | Umbrella EVM SDK (Keccak, secp256k1, RLP, EIP-1559, ABI, RPC) |
| [amashukov/http-client-php](https://github.com/AndreyMashukov/http-client-php) | PSR-18 cURL HTTP client |
| [amashukov/eth-rpc-client-php](https://github.com/AndreyMashukov/eth-rpc-client-php) | ethers.js v6-style JSON-RPC client |
| [amashukov/toncenter-client-php](https://github.com/AndreyMashukov/toncenter-client-php) | Typed toncenter v2 client |

## Quality

- **PHPStan level 9** across `src/`.
- **php-cs-fixer** with the `@PER-CS` ruleset.
- **GitHub Actions CI** on every push.

```bash
composer install
composer test     # PHPUnit
composer stan     # PHPStan (level 9)
composer cs       # php-cs-fixer (dry-run)
composer rector   # Rector (dry-run)
```

## License

MIT — see [LICENSE](LICENSE).
