/**
 * Warns on the config page when active products have no weight: checkout then
 * prices them with the default product weight (spec §6).
 */
Shopware.Component.register('kmh-shipbubble-weight-warning', {
    template: `
        <mt-banner v-if="count > 0" variant="attention" class="kmh-shipbubble-weight-warning">
            {{ $t('kmh-shipbubble.config.productsWithoutWeight', { count }) }}
            <router-link :to="{ name: 'sw.product.index' }">{{ $tc('kmh-shipbubble.config.showProducts') }}</router-link>
        </mt-banner>
    `,

    inject: ['kmhShipbubbleConfigService'],

    data() {
        return { count: 0 };
    },

    async created() {
        try {
            this.count = (await this.kmhShipbubbleConfigService.productsWithoutWeight()).count;
        } catch {
            // Without product read access there is nothing to warn about.
            this.count = 0;
        }
    },
});
