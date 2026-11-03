# 0.1.0

Initial release.

- Shipping method "Courier delivery (Shipbubble)", created inactive on install.
- Configuration per sales channel: sandbox/live mode and keys, webhook secret, sender address, couriers, package category, default weight and box, markup, fallback price.
- "Test connection" shows your Shipbubble wallet balance; "Validate sender address" registers your pickup address with Shipbubble.
- Courier and package-category lists load from your Shipbubble account.
- Live courier prices at checkout: customers pick a courier (logo, delivery estimate, price including your markup) under "Courier delivery (Shipbubble)". The price always comes from the plugin's own Shipbubble quote.
- Quotes are cached per cart and address, so reloading checkout does not call Shipbubble again; changing a quantity or the address re-quotes.
- When an address cannot be quoted, checkout explains why and asks the customer to choose another delivery method. If Shipbubble is unreachable, your fallback price is charged instead (when set), and you get an admin notification at most once an hour.
- The chosen courier, Shipbubble quote and charged price are saved with each order.
- The configuration page warns when active products have no weight.

<!--
Format notes (this file is rendered by the Shopware Store):

- One `# <version>` heading per release, newest first, separated by `---`.
- Bullets describe user-visible behaviour, not commits. "Refunds no longer
  exceed the captured amount", not "refactor RefundProcessor".
- `make changelog` renders this exactly as the Store will display it.
- Localised variants live in CHANGELOG_de-DE.md / CHANGELOG_fr-FR.md and must
  carry the same version headings.
-->
