import currentSalesChannelId from './sales-channel';

/**
 * "Test connection" button: checks the saved API key with a wallet balance call.
 */
Shopware.Component.register('kmh-shipbubble-connection-test', {
    template: `
        <div class="kmh-shipbubble-connection-test">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.testConnection') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.saveFirst') }}</p>
        </div>
    `,

    inject: ['kmhShipbubbleConfigService'],

    mixins: [Shopware.Mixin.getByName('notification')],

    data() {
        return { isLoading: false };
    },

    methods: {
        async run() {
            this.isLoading = true;

            try {
                const result = await this.kmhShipbubbleConfigService.testConnection(currentSalesChannelId(this));

                if (result.success) {
                    this.createNotificationSuccess({
                        message: this.$t('kmh-shipbubble.config.connectionOk', {
                            balance: result.balance,
                            currency: result.currency,
                        }),
                    });
                } else {
                    this.createNotificationError({ message: result.message });
                }
            } catch (e) {
                this.createNotificationError({ message: this.$tc('kmh-shipbubble.config.requestFailed') });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
