/**
 * Label, icon and (empty) settings modal for the Flow Builder action
 * `action.kmh.shipbubble.create_shipment`. Flow Builder derives the lookup key
 * `kmhShipbubbleCreate_shipment` and the modal name from the action name.
 */
Shopware.Application.addServiceProviderDecorator('flowBuilderService', (flowBuilderService) => {
    flowBuilderService.addLabels({ kmhShipbubbleCreate_shipment: 'kmh-shipbubble.flow.createShipment' });
    flowBuilderService.addIcons({ kmhShipbubbleCreate_shipment: 'regular-truck' });

    return flowBuilderService;
});

Shopware.Component.register('sw-flow-kmh-shipbubble-create_shipment-modal', {
    template: `
        <sw-modal :title="$tc('kmh-shipbubble.flow.createShipment')" variant="small" @modal-close="$emit('modal-close')">
            <p>{{ $tc('kmh-shipbubble.flow.createShipmentHelp') }}</p>
            <template #modal-footer>
                <mt-button size="small" variant="secondary" @click="$emit('modal-close')">{{ $tc('global.default.cancel') }}</mt-button>
                <mt-button size="small" variant="primary" @click="save">{{ $tc('global.default.save') }}</mt-button>
            </template>
        </sw-modal>
    `,

    props: {
        sequence: { type: Object, required: true },
    },

    emits: ['modal-close', 'process-finish'],

    methods: {
        save() {
            this.$emit('process-finish', { ...this.sequence, config: {} });
        },
    },
});
