import currentSalesChannelId, { systemConfig } from './sales-channel';

const { Criteria } = Shopware.Data;

/**
 * Shows the webhook URL to paste into the Shipbubble dashboard (Settings >
 * API keys & webhooks, test and live) and when the last webhook arrived
 * (spec §7). The URL uses a storefront domain of the selected sales channel;
 * "All sales channels" gets the `default` URL, which checks the global keys.
 */
Shopware.Component.register('kmh-shipbubble-webhook-info', {
    template: `
        <div class="kmh-shipbubble-webhook-info">
            <mt-text-field
                :label="$tc('kmh-shipbubble.webhook.url')"
                :model-value="url || $tc('kmh-shipbubble.webhook.noDomain')"
                :help-text="$tc('kmh-shipbubble.webhook.urlHelp')"
                disabled
                copyable
            />
            <p class="sw-field__hint">
                {{ lastReceivedAt
                    ? $t('kmh-shipbubble.webhook.lastReceived', { date: formatDate(lastReceivedAt) })
                    : $tc('kmh-shipbubble.webhook.neverReceived') }}
            </p>
        </div>
    `,

    inject: ['repositoryFactory', 'kmhShipbubbleConfigService'],

    data() {
        return { url: null, lastReceivedAt: null };
    },

    computed: {
        salesChannelId() {
            return systemConfig(this)?.currentSalesChannelId ?? null;
        },
    },

    watch: {
        salesChannelId() {
            this.loadUrl();
        },
    },

    created() {
        this.loadUrl();
        this.kmhShipbubbleConfigService.webhookStatus()
            .then((result) => { this.lastReceivedAt = result.lastReceivedAt; })
            .catch(() => { this.lastReceivedAt = null; });
    },

    methods: {
        async loadUrl() {
            const salesChannelId = currentSalesChannelId(this);
            // Only real web domains: headless channels carry placeholder "domains".
            const criteria = new Criteria(1, 1).addFilter(Criteria.prefix('url', 'http'));
            if (salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannelId', salesChannelId));
            }

            const domain = (await this.repositoryFactory.create('sales_channel_domain').search(criteria, Shopware.Context.api)).first();
            this.url = domain ? `${domain.url.replace(/\/$/, '')}/kmh-shipbubble/webhook/${salesChannelId || 'default'}` : null;
        },

        formatDate(value) {
            return Shopware.Utils.format.date(value);
        },
    },
});
