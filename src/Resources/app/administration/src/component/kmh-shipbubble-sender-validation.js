import currentSalesChannelId from './sales-channel';

/**
 * "Validate sender" button: validates the saved sender address with Shipbubble
 * and stores its address code (spec §5). Quotes need it.
 */
Shopware.Component.register('kmh-shipbubble-sender-validation', {
    template: `
        <div class="kmh-shipbubble-sender-validation">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.validateSender') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.validateSenderHint') }}</p>
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
                const result = await this.kmhShipbubbleConfigService.validateSender(currentSalesChannelId(this));

                if (result.success) {
                    this.createNotificationSuccess({
                        message: this.$t('kmh-shipbubble.config.senderOk', { address: result.formattedAddress }),
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
