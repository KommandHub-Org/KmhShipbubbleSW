import currentSalesChannelId, { systemConfig } from './sales-channel';

/**
 * Select whose options come from the merchant's Shipbubble account
 * (`source`: couriers | package-categories). Shows the API error inline when
 * the key is missing or wrong, so the field never looks silently empty.
 */
Shopware.Component.register('kmh-shipbubble-api-select', {
    template: `
        <div class="kmh-shipbubble-api-select">
            <sw-multi-select
                v-if="isMultiple"
                :label="label"
                :help-text="helpText"
                :options="options"
                :value="value || []"
                :is-loading="isLoading"
                :disabled="disabled"
                value-property="value"
                label-property="label"
                @update:value="$emit('update:value', $event)"
            />
            <sw-single-select
                v-else
                :label="label"
                :help-text="helpText"
                :options="options"
                :value="value"
                :is-loading="isLoading"
                :disabled="disabled"
                value-property="value"
                label-property="label"
                @update:value="$emit('update:value', $event)"
            />
            <p v-if="error" class="sw-field__error">{{ error }}</p>
        </div>
    `,

    inject: ['kmhShipbubbleConfigService'],

    emits: ['update:value'],

    props: {
        value: { type: [String, Array], required: false, default: null },
        label: { type: String, required: false, default: '' },
        helpText: { type: String, required: false, default: null },
        source: { type: String, required: true },
        multiple: { type: [Boolean, String], required: false, default: false },
        disabled: { type: Boolean, required: false, default: false },
    },

    data() {
        return { options: [], isLoading: false, error: null };
    },

    computed: {
        isMultiple() {
            return this.multiple === true || this.multiple === 'true';
        },

        // sw-system-config sets isLoading while it saves or switches sales channel.
        configLoading() {
            return systemConfig(this)?.isLoading ?? false;
        },
    },

    watch: {
        // Reload after a save or a sales channel switch, so a newly saved key takes effect.
        configLoading(loading, wasLoading) {
            if (wasLoading && !loading) {
                this.load();
            }
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.isLoading = true;
            this.error = null;

            try {
                const result = await this.kmhShipbubbleConfigService.options(this.source, currentSalesChannelId(this));

                if (result.success) {
                    this.options = result.options;
                } else {
                    this.error = result.message;
                }
            } catch (e) {
                this.error = this.$tc('kmh-shipbubble.config.requestFailed');
            } finally {
                this.isLoading = false;
            }
        },
    },
});
