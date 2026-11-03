import './kmh-shipbubble-order-shipment.scss';
const { Criteria } = Shopware.Data;

/**
 * "Shipbubble" card on the order: per Shipbubble delivery the chosen courier,
 * prices, status, tracking and waybill, with Create, Refresh and Cancel
 * (spec §12). Renders nothing for orders without a Shipbubble delivery.
 */
Shopware.Component.register('kmh-shipbubble-order-shipment', {
    template: `
        <mt-card v-if="shipments.length" :title="$tc('kmh-shipbubble.shipment.title')" :is-loading="isLoading" class="kmh-shipbubble-order-shipment">
            <div v-for="shipment in shipments" :key="shipment.id" class="kmh-shipbubble-order-shipment__item">
                <sw-description-list grid="1fr 2fr">
                    <dt>{{ $tc('kmh-shipbubble.shipment.courier') }}</dt>
                    <dd>{{ shipment.courierName || '–' }}</dd>
                    <dt>{{ $tc('kmh-shipbubble.shipment.status') }}</dt>
                    <dd>{{ statusLabel(shipment.status) }}</dd>
                    <dt>{{ $tc('kmh-shipbubble.shipment.charged') }}</dt>
                    <dd>{{ money(shipment.chargedAmount, shipment.currency) }}</dd>
                    <dt>{{ $tc('kmh-shipbubble.shipment.carrierPrice') }}</dt>
                    <dd>{{ money(shipment.quotedAmount, shipment.currency) }}</dd>
                    <template v-if="shipment.shipbubbleOrderId">
                        <dt>{{ $tc('kmh-shipbubble.shipment.orderId') }}</dt>
                        <dd>{{ shipment.shipbubbleOrderId }}</dd>
                    </template>
                    <template v-if="courierTrackingCode(shipment)">
                        <dt>{{ $tc('kmh-shipbubble.shipment.trackingCode') }}</dt>
                        <dd>{{ courierTrackingCode(shipment) }}</dd>
                    </template>
                    <template v-if="shipment.trackingUrl">
                        <dt>{{ $tc('kmh-shipbubble.shipment.tracking') }}</dt>
                        <dd><a :href="shipment.trackingUrl" target="_blank" rel="noopener">{{ $tc('kmh-shipbubble.shipment.openTracking') }}</a></dd>
                    </template>
                    <template v-if="shipment.waybillUrl">
                        <dt>{{ $tc('kmh-shipbubble.shipment.waybill') }}</dt>
                        <dd><a :href="shipment.waybillUrl" target="_blank" rel="noopener">{{ $tc('kmh-shipbubble.shipment.downloadWaybill') }}</a></dd>
                    </template>
                </sw-description-list>

                <mt-banner v-if="shipment.errorMessage" variant="critical" class="kmh-shipbubble-order-shipment__error">
                    {{ shipment.errorMessage }}
                </mt-banner>

                <div v-if="canEdit" class="kmh-shipbubble-order-shipment__actions">
                    <mt-button v-if="isBookable(shipment)" variant="primary" size="small" :is-loading="busyId === shipment.id" @click="create(shipment, false)">
                        {{ $tc('kmh-shipbubble.shipment.create') }}
                    </mt-button>
                    <mt-button v-if="shipment.shipbubbleOrderId" variant="secondary" size="small" :is-loading="busyId === shipment.id" @click="refresh(shipment)">
                        {{ $tc('kmh-shipbubble.shipment.refresh') }}
                    </mt-button>
                    <mt-button v-if="isOpen(shipment)" variant="critical" size="small" :disabled="busyId === shipment.id" @click="cancelTarget = shipment">
                        {{ $tc('kmh-shipbubble.shipment.cancel') }}
                    </mt-button>
                </div>
            </div>

            <sw-modal v-if="requote" :title="$tc('kmh-shipbubble.shipment.requoteTitle')" variant="small" @modal-close="requote = null">
                <p>{{ $t('kmh-shipbubble.shipment.requoteText', {
                    courier: requote.courierName,
                    old: money(requote.oldCarrierPrice, requote.currency),
                    new: money(requote.newCarrierPrice, requote.currency),
                }) }}</p>
                <p>{{ $tc('kmh-shipbubble.shipment.requoteCharged') }}</p>
                <template #modal-footer>
                    <mt-button size="small" variant="secondary" @click="requote = null">{{ $tc('global.default.cancel') }}</mt-button>
                    <mt-button size="small" variant="primary" :is-loading="busyId !== null" @click="confirmRequote">{{ $tc('kmh-shipbubble.shipment.requoteConfirm') }}</mt-button>
                </template>
            </sw-modal>

            <sw-modal v-if="cancelTarget" :title="$tc('kmh-shipbubble.shipment.cancelTitle')" variant="small" @modal-close="cancelTarget = null">
                <p>{{ $tc('kmh-shipbubble.shipment.cancelText') }}</p>
                <template #modal-footer>
                    <mt-button size="small" variant="secondary" @click="cancelTarget = null">{{ $tc('global.default.cancel') }}</mt-button>
                    <mt-button size="small" variant="critical" :is-loading="busyId !== null" @click="cancel">{{ $tc('kmh-shipbubble.shipment.cancelConfirm') }}</mt-button>
                </template>
            </sw-modal>
        </mt-card>
    `,

    inject: ['repositoryFactory', 'acl', 'kmhShipbubbleConfigService'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        orderId: { type: String, required: true },
    },

    data() {
        return { shipments: [], isLoading: false, busyId: null, requote: null, cancelTarget: null };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('kmh_shipbubble_shipment');
        },

        canEdit() {
            return this.acl.can('kmh_shipbubble_shipment.editor');
        },
    },

    watch: {
        // The order page is reused when moving between orders.
        orderId() {
            this.load();
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.isLoading = true;

            try {
                const criteria = new Criteria(1, 25).addFilter(Criteria.equals('orderId', this.orderId));
                this.shipments = Array.from(await this.repository.search(criteria, Shopware.Context.api));
            } finally {
                this.isLoading = false;
            }
        },

        isBookable(shipment) {
            return ['quoted', 'needs_quote'].includes(shipment.status);
        },

        isOpen(shipment) {
            return ['pending', 'confirmed', 'picked_up', 'in_transit'].includes(shipment.status);
        },

        statusLabel(status) {
            return this.$tc(`kmh-shipbubble.shipment.statuses.${status}`);
        },

        courierTrackingCode(shipment) {
            return shipment.lastPayload?.courierTrackingCode ?? null;
        },

        money(value, currency) {
            if (value === null || value === undefined) {
                return '–';
            }

            return Shopware.Utils.format.currency(Number(value), currency || 'NGN');
        },

        async create(shipment, confirmed) {
            const result = await this.act(shipment, 'create', { confirmed });

            if (result?.success && !result.booked) {
                this.requote = { ...result, shipmentId: shipment.id };
            }
        },

        async confirmRequote() {
            const shipment = this.shipments.find((item) => item.id === this.requote.shipmentId);
            this.requote = null;

            await this.create(shipment, true);
        },

        async refresh(shipment) {
            await this.act(shipment, 'refresh');
        },

        async cancel() {
            const shipment = this.cancelTarget;
            this.cancelTarget = null;

            await this.act(shipment, 'cancel');
        },

        /**
         * Runs an action, shows Shipbubble's own message on a refusal and
         * reloads the card either way (a refusal stores its error on the row).
         */
        async act(shipment, action, payload = {}) {
            this.busyId = shipment.id;

            try {
                const result = await this.kmhShipbubbleConfigService.shipmentAction(shipment.id, action, payload);

                if (!result.success) {
                    this.createNotificationError({ message: result.message });
                } else if (action !== 'create' || result.booked) {
                    this.createNotificationSuccess({ message: this.$tc(`kmh-shipbubble.shipment.done.${action}`) });
                }

                return result;
            } catch (e) {
                this.createNotificationError({ message: this.$tc('kmh-shipbubble.config.requestFailed') });

                return null;
            } finally {
                this.busyId = null;
                await this.load();
            }
        },
    },
});
