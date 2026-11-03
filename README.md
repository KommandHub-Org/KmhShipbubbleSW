# Shipbubble Courier Delivery for Shopware 6

Live courier rates from Shipbubble at checkout, shipment booking and tracking, on the merchant's own Shipbubble account.

[![Shopware](https://img.shields.io/badge/Shopware-~6.7.0-189eff)](https://www.shopware.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Apache-2.0-blue)](LICENSE)

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Features](#features)
- [Local development](#local-development)
- [Makefile commands](#makefile-commands)
- [Testing](#testing)
- [Code quality](#code-quality)
- [CI/CD](#cicd)
- [Release process](#release-process)
- [Logging and debugging](#logging-and-debugging)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Requirements

| | |
| --- | --- |
| Shopware | `~6.7.0` |
| PHP | 8.2 or newer |
| Database | MySQL 8.0+ / MariaDB 10.11+ |

## Installation

### Via Composer (recommended)

```bash
composer require kommandhub/shipbubble-sw
bin/console plugin:refresh
bin/console plugin:install --activate KmhShipbubbleSW
bin/console cache:clear
```

### Manual upload

Download the release zip and install it under **Extensions > My extensions >
Upload extension**, then activate it.

## Configuration

**Settings > Extensions > Shipbubble Courier Delivery for Shopware 6**

Every setting is sales-channel scoped: use the sales-channel selector at the top
of the configuration page to override a value for one channel only.

1. **Shipbubble account:** choose the mode (sandbox or live), enter the API key
   for that mode and the webhook secret, **save**, then click **Test connection**.
   It shows your Shipbubble wallet balance, or Shipbubble's error.
2. **Sender:** enter the pickup address, **save**, then click
   **Validate sender address**. Courier prices need a validated sender; the
   address code is stored separately for sandbox and live.
3. **Couriers and packages:** pick the couriers to offer (empty = all) and your
   usual package category. Both lists are loaded from your Shipbubble account.
   Products without a weight use the default product weight.
4. **Checkout price:** optional markup, and an optional fallback price used
   when Shipbubble cannot be reached.
5. **Activate the shipping method** "Courier delivery (Shipbubble)" under
   Settings > Shipping. The plugin creates it **inactive** and never changes it
   again, so renaming it or changing its sales channels is safe.

Do not make Shipbubble the only or the default shipping method of a sales
channel: when Shipbubble cannot quote an address, customers need another option.

You pay couriers from your own Shipbubble wallet; the plugin never collects or
relays delivery fees.

## Architecture

Feature-first modules under `src/`, following Shopware's own plugin layout. A
top-level directory *is* a boundary; inside it, flat Symfony-idiomatic folders
(`Service`, `Subscriber`, `Handler`, `Struct`, `Event`, `Enum`, `Controller`).
No `Application/Domain/Infrastructure` nesting — one obvious home per class.

Always present:

| Path | Responsibility |
| --- | --- |
| `src/KmhShipbubbleSW.php` | plugin bootstrap and lifecycle hooks |
| `src/Setting/Service/Config.php` | typed, sales-channel-aware settings reader |
| `src/Logging/ConfigurableLogger.php` | PSR-3 wrapper gated on the debug settings |
| `src/Exception/` | plugin-scoped exception base |
| `src/Resources/config/` | `services.yml`, `config.xml`, `packages/` |
| `tests/Unit`, `tests/Integration` | mirror `src/` |

## Features

### Administration

Vue/JS sources live in `src/Resources/app/administration/src/`. Built output in
`src/Resources/public/` is **generated** — never hand-edit it. Rebuild it with
`bin/build-administration.sh` in the container after changing a source file and
commit the result: `make zip` ships the committed bundle as is (asset builds are
off in `.shopware-extension.yml`).

- `main.js` — entry point: registers locales, ACL, modules and services.
- `acl/index.js` — the plugin's admin permissions.
- `snippet/*.json` — one file per locale, mirroring the same key tree.

Any privilege enforced here **must** also be enforced server-side via `_acl` on
the route.

### Shipbubble API client

`src/Client/` is the only place that talks HTTP.

| Layer | Responsibility |
| --- | --- |
| `Http/ShipbubbleHttpClient` | base URL, API key per mode, 3 s timeout |
| `Resource/*` | addresses, rates, couriers, labels, wallet; failures become `ShipbubbleApiException` with an `ApiErrorReason` |
| `Dto/*` | typed results, read defensively from the JSON |
| `ShipbubbleClient` | facade services depend on |

Add a new call by adding a method to the matching `Resource/` class — never by
issuing HTTP from a service.

### Checkout

When the customer selects "Courier delivery (Shipbubble)", the cart processor
`Shipping/ShipbubbleDeliveryProcessor` (priority -6000, after the core delivery
processor) prices the delivery through `Quote/QuoteService`:

1. The shipping address is validated with Shipbubble once and its address code
   cached for 30 days (an address Shipbubble rejects: for an hour). Street,
   city, country and **phone** are required.
2. The parcel is built from the product line items (`Quote/PackageBuilder`):
   weight in kg (default product weight when missing), unit price, quantity,
   the first standard Shipbubble box that carries the weight.
3. Rates are fetched and cached for the configured quote cache time, keyed by
   sender, receiver, parcel, currency and enabled couriers.
4. The customer's courier (stored in the sales-channel context, never a price)
   or the cheapest one sets the shipping cost: carrier total plus markup.

The quote shows on the confirm page as a courier list under the method. On
order placement the chosen courier, `request_token`, carrier price and charged
price are written to `kmh_shipbubble_shipment` (status `quoted`, or
`needs_quote` when the fallback price was charged).

Shipbubble quotes in NGN; a sales channel in another currency gets a
"not available in this currency" message for this method.

### Data model

| Table | Holds |
| --- | --- |
| `kmh_shipbubble_shipment` | one Shipbubble delivery per order delivery: chosen courier, quote, Shipbubble order id, status, tracking |
| `kmh_shipbubble_webhook_event` | received webhooks, unique per body hash (redeliveries are processed once) |

`order_delivery.kmhShipbubbleShipment` exposes the shipment on the order
delivery (loaded only when associated). Uninstalling without "keep user data"
drops both tables; the shipping method is only deactivated, never deleted.

### Scheduled tasks

`kmh_shipbubble.webhook_event_cleanup` runs daily and deletes stored webhook
events older than 90 days. It only runs when a worker is running
(`bin/console messenger:consume`).

### Storefront

- `src/Resources/app/storefront/src/` — JS plugins, registered in `main.js`.
- `src/Resources/views/storefront/` — Twig overrides. **Namespace every block**
  you add (`{% block kmh_shipbubble_foo %}`) so it cannot collide with another plugin
  extending the same template.
- `src/Resources/snippet/<locale>/` — storefront translations; every locale file
  must carry the same key tree.

Built output under `src/Resources/app/storefront/dist/` is generated.

### Webhooks

Not built yet (SB05). `Webhook/Service/WebhookSignatureValidator` checks the
`x-ship-signature` HMAC against the configured webhook secret; the algorithm
(SHA-256 or SHA-512) is confirmed against the Shipbubble sandbox before the
endpoint ships.

## Local development

The plugin is developed inside a Docker stack that runs a full Shopware install
with this directory mounted at `custom/static-plugins/KmhShipbubbleSW`.

```bash
git clone https://github.com/KommandHub-Org/KmhShipbubbleSW.git
cd KmhShipbubbleSW

make up     # build the image, start Shopware, install dependencies
make shell  # bash into the container

# inside the container
bin/console plugin:refresh
bin/console plugin:install --activate KmhShipbubbleSW
```

Storefront: <http://localhost> · Administration: <http://localhost/admin>
(`admin` / `shopware`).

## Makefile commands

| Command | What it does |
| --- | --- |
| `make up` / `make down` | start / tear down the stack |
| `make restart` | `down` then `up` |
| `make shell` | shell into the container |
| `make test` | PHPUnit; filter with `make test FILTER=SomeTest` |
| `make test-coverage` | coverage text report |
| `make analyse` | PHPStan on `src/` |
| `make cs` / `make cs-fix` | php-cs-fixer dry-run / apply |
| `make validate-plugin` | shopware-cli store-compliance check |
| `make changelog` | render `CHANGELOG.md` as the Store will |
| `make zip` | build a distributable zip into `build/` |
| `make cli ARGS="..."` | any other shopware-cli command |

## Testing

```bash
make test
make test FILTER=ConfigTest
make test-coverage
```

- `tests/Unit/` mirrors `src/`. No kernel, no database — fast, and what CI runs.
- `tests/Integration/` needs a booted Shopware kernel. Mark those tests
  `#[Group('kernel')]`; CI runs `--exclude-group kernel`.
- Add or update a test with every behaviour change.

## Code quality

```bash
make cs-fix && make analyse && make test
```

All three must pass before a commit. PHPStan runs at the level pinned in
`phpstan.dist.neon`; php-cs-fixer enforces PSR-12 plus the rules in
`.php-cs-fixer.dist.php`.

## CI/CD

`.github/workflows/php.yml` runs on every push to `main`/`develop` and on every
pull request: composer validate, PHP lint, PHPStan, php-cs-fixer (dry-run),
PHPUnit with coverage, and a coverage threshold gate.

CI runs **without a Shopware kernel**, so kernel-dependent tests are excluded
there and the plugin bootstrap is excluded from coverage in `phpunit.dist.xml`.

## Release process

1. Land everything on `develop`; make sure the local gate passes.
2. Bump `version` in `composer.json`.
3. Add a `# <version>` section at the top of `CHANGELOG.md` (and the localised
   variants). Check the rendering with `make changelog`.
4. `make validate-plugin` — must be clean for a Store submission.
5. `make zip` — the artefact lands in `build/`.
6. Merge `develop` into `main` and tag the release.

## Logging and debugging

Enable **debug logging** in the plugin configuration; entries land in
`var/log/kmh_shipbubble_<env>.log` (rotating, 7 files).

`error` and above are **always** written regardless of the toggle, so production
keeps a trail of failures. Both the toggle and the level filter are
sales-channel scoped — pass the sales channel id in the log context so it
resolves against the right scope:

```php
$this->logger->info('something happened', [
    ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $salesChannelId,
]);
```

## Security

Report vulnerabilities privately — see [SECURITY.md](SECURITY.md). Never open a
public issue for one, and never paste real credentials into an issue.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Pull requests target `develop`, commits
are signed off (`git commit -s`), and the local gate must pass.

## License

Apache-2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE).
