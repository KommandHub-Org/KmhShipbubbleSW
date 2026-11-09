# 1.0.1

- Customer address codes are stored permanently instead of for 30 days. Shipbubble charges for each address validation and its codes never expire, so you pay once per address. Only a hash of the address is kept; changes in case or spacing don't count as a new address.
- If Shipbubble refuses a stored address code when quoting, the address is validated again once, instead of checkout failing.

---

# 1.0.0

First release.

- Live Shipbubble courier prices at checkout: customers pick a courier (logo, delivery estimate, price incl. your markup) under "Courier delivery (Shipbubble)". The price always comes from the plugin's own Shipbubble quote.
- Quotes are cached per cart and address; changing a quantity or the address re-quotes. If an address cannot be quoted, checkout explains why and the customer picks another method; if Shipbubble is unreachable, an optional fallback price applies and you get an admin notification.
- If Shipbubble cannot be reached, checkout stops asking it for a minute, so customers wait for one timeout per minute instead of one on every page.
- Shipbubble card on the order: courier, prices, status, tracking link, courier tracking number and waybill, with Create shipment, Refresh status and Cancel shipment. When the checkout quote can no longer be used, you confirm a new courier price first; the customer's shipping price never changes.
- Tracking: the Shipbubble order id is written to the order's tracking codes and linked to Shipbubble's tracking page in the customer account and mails.
- Shipbubble webhooks keep the shipment and the order's delivery status up to date (picked up, in transit, delivered, returned, cancelled). Webhooks are verified with your API key; the settings page shows the URL to paste into Shipbubble.
- Ready-made, active flows: book the courier when the order is paid, tracking email to the customer, delivered email, booking-failed and parcel-returned alerts to you. Flow Builder action "Create Shipbubble shipment" and triggers for every shipment status.
- Settings per sales channel: sandbox or live, Test connection with wallet balance, sender address validation, couriers, package category, default weight and box, markup, fallback price. Warns when active products have no weight.
- Order permissions "Shipbubble shipments" (view, edit).

<!--
Format notes (this file is rendered by the Shopware Store):

- One `# <version>` heading per release, newest first, separated by `---`.
- Bullets describe user-visible behaviour, not commits. "Refunds no longer
  exceed the captured amount", not "refactor RefundProcessor".
- `make changelog` renders this exactly as the Store will display it.
- Localised variants live in CHANGELOG_de-DE.md / CHANGELOG_fr-FR.md and must
  carry the same version headings.
-->
