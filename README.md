# Shipbubble Courier Delivery

Live Shipbubble courier rates at checkout, shipment booking from the order,
tracking emails and automatic delivery status updates — all on the merchant's
own Shipbubble account. Free and open source.

[![CI](https://github.com/KommandHub-Org/KmhShipbubbleSW/actions/workflows/php.yml/badge.svg?branch=main)](https://github.com/KommandHub-Org/KmhShipbubbleSW/actions/workflows/php.yml)
[![Release](https://img.shields.io/github/v/release/KommandHub-Org/KmhShipbubbleSW?sort=semver)](https://github.com/KommandHub-Org/KmhShipbubbleSW/releases)
[![Shopware](https://img.shields.io/badge/Shopware-6.7-189eff)](https://www.shopware.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen)](phpstan.dist.neon)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](.github/workflows/php.yml)
[![License](https://img.shields.io/github/license/KommandHub-Org/KmhShipbubbleSW)](LICENSE)

PHPStan level and coverage are enforced on every push by the CI workflow; the
build fails below 100% line coverage.

## Table of contents

- [What it does](#what-it-does)
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

## What it does

- **Checkout:** customers pick a courier under "Courier delivery (Shipbubble)"
  — logo, delivery estimate and live price (plus your optional markup). The
  price always comes from the plugin's own Shipbubble quote, never from the
  browser.
- **Orders:** a Shipbubble card on the order books the courier (or the
  "create shipment when paid" flow does it automatically), shows the tracking
  link, courier tracking number and waybill, refreshes the status and cancels.
- **Tracking:** the Shipbubble order id becomes the order's tracking code,
  linked to Shipbubble's tracking page in the customer account and mails.
- **Status updates:** Shipbubble webhooks move the order's delivery status
  (shipped, retour, cancelled) and trigger mails — no polling.
- **No manual setup:** the flows it needs are installed with the plugin.

You pay couriers from your own Shipbubble wallet; the plugin never collects or
relays delivery fees.

## Requirements

| | |
| --- | --- |
| Shopware | `~6.7.0` |
| PHP | 8.2 or newer |
| Database | MySQL 8.0+ / MariaDB 10.11+ |
| Shipbubble | an account with API keys ([app.shipbubble.com](https://app.shipbubble.com)) |
| Shop | NGN currency, customer phone number in addresses, a message queue worker — see [Requirements on your shop](#requirements-on-your-shop) |

## Installation

### Shopware Store

Install **Shipbubble Courier Delivery** from the Shopware Store under
**Extensions > Store**, then activate it.

### Release zip

Download `KmhShipbubbleSW.zip` from the
[GitHub releases](https://github.com/KommandHub-Org/KmhShipbubbleSW/releases)
and upload it under **Extensions > My extensions > Upload extension**, then
activate it.

### Composer

The package is not on Packagist; add the GitHub repository first:

```bash
composer config repositories.kmh-shipbubble vcs https://github.com/KommandHub-Org/KmhShipbubbleSW.git
composer require kommandhub/shipbubble-sw:^1.0
bin/console plugin:refresh
bin/console plugin:install --activate KmhShipbubbleSW
bin/console cache:clear
```

Then follow [Configuration](#configuration) and activate the shipping method.

## Configuration

**Settings > Extensions > Shipbubble Courier Delivery**

Before you start you need a Shipbubble account
([app.shipbubble.com](https://app.shipbubble.com)) with API keys
(Settings > API keys & webhooks; test key `sb_sandbox_…`, live key `sb_prod_…`)
and money in your Shipbubble wallet for live bookings. Shipbubble lets you fund
the wallet only after your account is verified, so finish verification first.
A booking with too little money fails with Shipbubble's "Insufficient wallet
balance" message; to avoid that, turn on automatic wallet funding or a
low-balance email in your Shipbubble wallet settings.

Every setting is sales-channel scoped: use the sales-channel selector at the top
of the configuration page to override a value for one channel only.

1. **Shipbubble account:** choose the mode (sandbox or live), enter the API key
   for that mode, **save**, then click **Test connection**. It shows your
   Shipbubble wallet balance, or Shipbubble's error.
2. **Webhook URL:** copy the URL shown under the keys into the Shipbubble
   dashboard (Settings > API keys & webhooks) as **Test webhook url** and
   **Live webhook url**. Status updates then reach the shop by themselves; they
   are verified with your API key, so there is no extra secret. The page also
   shows when the last update arrived.
3. **Sender:** enter the pickup address, **save**, then click
   **Validate sender address**. Courier prices need a validated sender; the
   address code is stored separately for sandbox and live.
4. **Couriers and packages:** pick the couriers to offer (empty = all) and your
   usual package category. Both lists are loaded from your Shipbubble account.
5. **Checkout price:** optional markup, and an optional fallback price used
   when Shipbubble cannot be reached.
6. **Activate the shipping method** "Courier delivery (Shipbubble)" under
   Settings > Shipping. The plugin creates it **inactive** and never changes it
   again (except adding the Shipbubble tracking link when the field is empty),
   so renaming it or changing its sales channels is safe.

Nothing has to be set up in Flow Builder: the plugin installs its flows (see
[Flows](#flows-installed-with-the-plugin)).

### Requirements on your shop

- **Currency NGN** in the sales channel: Shipbubble quotes in naira. In another
  currency the method shows "not available in this currency".
- **Customer phone number** in the shipping address: couriers need it. Turn on
  the phone field (Settings > Login & sign-up); without a phone the method asks
  the customer to complete the address.
- **Product weights** in kg: products without a weight are priced with the
  default product weight, and the configuration page warns how many active
  products have none. Product dimensions are not used; the parcel uses the first
  standard Shipbubble box that carries the weight, or your default box.
- **A message queue worker** (`bin/console messenger:consume`, as Shopware
  recommends for production): webhooks are processed in the background.

### When Shipbubble cannot price a delivery

Checkout never breaks: the courier method shows a reason and the customer picks
another method.

| Situation | Customer sees |
| --- | --- |
| No phone or incomplete address | "complete your delivery address, including a phone number" |
| Address Shipbubble cannot find | "check it or choose another delivery method" |
| No courier for the address | "No courier delivers to your address" |
| Shop currency is not NGN | "not available in this currency" |
| Shipbubble unreachable | your **fallback price** if set (you book the courier later from the order), else "temporarily unavailable" |
| Key missing or sender not validated | "currently unavailable"; you get an admin notification (at most once an hour) |

Do not make Shipbubble the only or the default shipping method of a sales
channel: when Shipbubble cannot quote an address, customers need another option.

You pay couriers from your own Shipbubble wallet; the plugin never collects or
relays delivery fees.

### Flows installed with the plugin

All active from the start; edit, switch off or delete them in Flow Builder —
updates never bring them back.

| Flow | When | Does |
| --- | --- | --- |
| Shipbubble: create shipment when paid | payment is paid | books the customer's courier (charged to your wallet) |
| Shipbubble: tracking email to customer | shipment booked | emails courier and tracking link |
| Shipbubble: booking failed alert | booking fails | emails you Shipbubble's reason |
| Shipbubble: delivered email to customer | Shipbubble reports delivered | confirms the delivery |
| Shipbubble: parcel returned alert | Shipbubble reports returned | tells you a parcel is coming back |

Further triggers (picked up, in transit, cancelled) are available for your own
flows, e.g. SMS or WhatsApp messages.

### Uninstalling

Deactivating or uninstalling switches the shipping method off; it is never
deleted, because orders refer to it. Uninstalling **without** "keep user data"
also removes the shipment and webhook tables, the stored sender address codes,
the plugin's flows and their mail templates. With "keep user data" everything
stays.

## Architecture

Feature-first modules under `src/`, following Shopware's own plugin layout. A
top-level directory *is* a boundary; inside it, flat Symfony-idiomatic folders
(`Service`, `Subscriber`, `Handler`, `Struct`, `Event`, `Enum`, `Controller`).
No `Application/Domain/Infrastructure` nesting — one obvious home per class.

| Path | Responsibility |
| --- | --- |
| `src/KmhShipbubbleSW.php` | plugin bootstrap and lifecycle hooks (installers, uninstall clean-up) |
| `src/Client/` | the only code that talks HTTP to Shipbubble: transport, resources, DTOs |
| `src/Quote/` | checkout quotes: receiver address, parcel, rates, cache, failure notices |
| `src/Shipping/` | cart processor, courier choice, shipping method installer, sender address |
| `src/Shipment/` | booking, re-quote, refresh, cancel; delivery state updates; order snapshot |
| `src/Webhook/` | webhook endpoint, signature check, storage, background handler |
| `src/Flow/` | Flow Builder events, action and the flow installer |
| `src/Administration/Controller/` | Admin API routes behind the settings page and the order card |
| `src/Core/Content/` | DAL entities `kmh_shipbubble_shipment`, `kmh_shipbubble_webhook_event` |
| `src/Setting/Service/Config.php` | typed, sales-channel-aware settings reader |
| `src/Logging/ConfigurableLogger.php` | PSR-3 wrapper gated on the debug settings |
| `src/ScheduledTask/` | daily clean-up of old webhook events |
| `src/Resources/` | `config/` (services, routes, `config.xml`, packages), admin app, storefront Twig, snippets |
| `tests/Unit` | mirrors `src/` |

## Features

### Administration

Vue/JS sources live in `src/Resources/app/administration/src/`. Built output in
`src/Resources/public/` is **generated** — never hand-edit it. Rebuild it with
`bin/build-administration.sh` in the container after changing a source file and
commit the result: `make zip` ships the committed bundle as is (asset builds are
off in `.shopware-extension.yml`).

- `main.js` — entry point: registers locales, ACL, components and services.
- `acl/index.js` — order permissions `kmh_shipbubble_shipment.viewer` / `.editor`.
- `component/` — settings-page parts (Test connection, sender validation,
  courier and category selects, webhook URL, weight warning) and the order
  card `kmh-shipbubble-order-shipment`.
- `extension/` — mounts the order card on the order's General tab and
  registers the Flow Builder action label, icon and modal.
- `service/shipbubble-config.service.js` — client for the plugin's Admin API.
- `snippet/*.json` — en-GB, de-DE, fr-FR, mirroring the same key tree.

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

### Shipments in the Administration

The order's General tab shows a **Shipbubble** card per Shipbubble delivery
(`kmh-shipbubble-order-shipment`), backed by `Shipment/Service/ShipmentService`
and the routes `POST /api/_action/kmh-shipbubble/shipment/{id}/create|refresh|cancel`
(ACL `kmh_shipbubble_shipment:update`; viewing needs `kmh_shipbubble_shipment:read`).

- **Create** books the courier with the checkout `request_token`. A token books
  one label only and may expire, so when the shipment needs a quote, the token
  is older than six days (Shipbubble keeps it for seven), or Shipbubble rejects it, the delivery is re-quoted
  (never from the cache), stored as `needs_quote`, and the merchant confirms
  the new carrier price within 15 minutes before it is booked. The row is
  locked during booking, so a delivery is never booked twice.
- **Refresh** reads the label back and applies its status; picked up / in
  transit ship the order delivery, returned moves it to retour, cancelled
  cancels it (core states only, a transition the state does not allow is
  skipped).
- **Cancel** asks Shipbubble; a refusal is shown with Shipbubble's reason and
  the status stays. Before pickup Shipbubble refunds the label to your wallet
  minus a ₦150 cancellation fee; once the courier has picked the parcel up it
  can no longer be cancelled.
- After booking, the Shipbubble order id is added to the delivery's tracking
  codes; the shipping method's tracking URL points to Shipbubble tracking.

Flow Builder: action `action.kmh.shipbubble.create_shipment` books every
`quoted` delivery of the order; triggers `kmh_shipbubble.shipment.created`,
`.failed`, `.picked_up`, `.in_transit`, `.delivered`, `.returned` and
`.cancelled` expose the order and `courierName`,
`trackingUrl`, `status`, `errorMessage`.

`Flow/FlowInstaller` installs every flow the plugin needs, **active**, on
install and update — no manual Flow Builder setup:

| Flow | Trigger | Does |
| --- | --- | --- |
| Shipbubble: create shipment when paid | payment paid | books the courier |
| Shipbubble: tracking email to customer | shipment created | emails the customer the courier and tracking link |
| Shipbubble: booking failed alert | shipment failed | emails the shop admin Shipbubble's reason |
| Shipbubble: delivered email to customer | shipment delivered | confirms the delivery to the customer |
| Shipbubble: parcel returned alert | shipment returned | tells the shop admin a parcel is coming back |

Each flow has a fixed id and is only created when missing, so an edited,
switched-off or deleted flow is left alone by updates. Uninstalling without
"keep user data" removes the flows and their mail templates.

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

- `src/Resources/views/storefront/` — Twig overrides. **Namespace every block**
  you add (`{% block kmh_shipbubble_foo %}`) so it cannot collide with another plugin
  extending the same template.
- `src/Resources/snippet/<locale>/` — storefront translations; every locale file
  must carry the same key tree.

The storefront needs no JavaScript: the courier picker posts with the core
shipping form.

### Webhooks

`POST /kmh-shipbubble/webhook/{salesChannelId}` (or `/default` for the global
settings), storefront scope, no login. The configuration page shows the URL to
paste into the Shipbubble dashboard (Settings > API keys & webhooks, test and
live URL) and when the last webhook arrived.

- `Webhook/Service/WebhookSignatureValidator`: `x-ship-signature` must be the
  HMAC-SHA512 (hex) of the raw body with the sales channel's sandbox or live
  API key; otherwise 401 and nothing is stored.
- `Webhook/Service/WebhookReceiver`: stores the body in
  `kmh_shipbubble_webhook_event` keyed by its SHA-256 (a redelivery is
  acknowledged and not processed again) and queues
  `ShipbubbleWebhookMessage`; Shipbubble gets its 200 immediately.
- `Webhook/Message/ShipbubbleWebhookHandler` (needs a running worker): takes
  the new status from the **last `package_status` entry** — the body's
  `status` is the previous one — and applies it through
  `ShipmentService::applyWebhook()`: shipment status, tracking, waybill,
  courier tracking number, Shopware delivery state, Flow event. Events the
  plugin does not use (e.g. `shipment.cod.remitted`) are stored with a note.

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

`.github/workflows/php.yml` runs on every push to `main` and on every pull
request: composer validate, PHP lint, PHPStan, php-cs-fixer (dry-run),
PHPUnit with coverage, and a coverage threshold gate.

CI runs **without a Shopware kernel**, so kernel-dependent tests are excluded
there and the plugin bootstrap is excluded from coverage in `phpunit.dist.xml`.

## Release process

1. Make sure the local gate passes and CI is green on `main`.
2. Bump `version` in `composer.json`.
3. Add a `# <version>` section at the top of `CHANGELOG.md` and
   `CHANGELOG_de-DE.md`. Check the rendering with `make changelog`.
4. `make validate-plugin` — must be clean for a Store submission.
5. `make zip` — the artefact lands in `build/`.
6. Tag `v<version>` on `main`, create the GitHub release with the zip, and
   upload the same zip to the Shopware Store.

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

See [CONTRIBUTING.md](CONTRIBUTING.md). Pull requests target `main`, commits
are signed off (`git commit -s`), and the local gate must pass.

## License

Apache-2.0 — see [LICENSE](LICENSE) and [NOTICE](NOTICE). The KommandHub name
and logo are covered by [TRADEMARKS.md](TRADEMARKS.md).

Shipbubble is a trademark of its owner. This plugin is an independent
integration by Kommandhub Limited and is not affiliated with or endorsed by
Shipbubble.
