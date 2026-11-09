const c={"kmh-shipbubble":{title:"Shipbubble",config:{testConnection:"Test connection",saveFirst:"Save the settings first; the test uses the saved API key.",connectionOk:"Connected to Shipbubble. Wallet balance: {balance} {currency}",validateSender:"Validate sender address",validateSenderHint:"Save first. Shipbubble checks the address; courier prices need a validated sender.",senderOk:"Sender address validated: {address}",requestFailed:"The request to the Shopware server failed.",productsWithoutWeight:"Active products without a weight: {count}. Checkout uses the default product weight for them, so courier prices may be off.",showProducts:"Show products"},flow:{createShipment:"Create Shipbubble shipment",createShipmentHelp:"Books the customer's chosen Shipbubble courier for every Shipbubble delivery of the order that is not booked yet. A delivery that needs a new price is left for you to confirm in the order."},shipment:{title:"Shipbubble",courier:"Courier",status:"Status",charged:"Charged to customer",carrierPrice:"Shipbubble price",orderId:"Shipbubble order",trackingCode:"Courier tracking number",tracking:"Tracking",openTracking:"Open tracking page",waybill:"Waybill",downloadWaybill:"Download waybill",create:"Create shipment",refresh:"Refresh status",cancel:"Cancel shipment",requoteTitle:"Confirm new courier price",requoteText:"The courier quote has to be renewed. {courier} now costs {new} (was {old}).",requoteCharged:"The shipping price the customer paid does not change. Book the shipment at the new price?",requoteConfirm:"Book shipment",cancelTitle:"Cancel shipment?",cancelText:"Before pickup, Shipbubble refunds the label to your wallet minus a ₦150 cancellation fee. Once the courier has picked the parcel up, it can no longer be cancelled.",cancelConfirm:"Cancel shipment",done:{create:"Shipment created at Shipbubble.",refresh:"Status updated from Shipbubble.",cancel:"Shipment cancelled."},statuses:{quoted:"Not booked yet",needs_quote:"Needs a new price",pending:"Booked",confirmed:"Confirmed",picked_up:"Picked up",in_transit:"In transit",completed:"Delivered",returned:"Returned",cancelled:"Cancelled",failed:"Failed"}},webhook:{url:"Webhook URL",urlHelp:"Paste this URL into your Shipbubble dashboard under Settings > API keys & webhooks, as the test and the live webhook URL. Status updates then reach the shop by themselves.",noDomain:"Add a storefront domain to this sales channel first.",lastReceived:"Last update from Shipbubble: {date}",neverReceived:"No update from Shipbubble received yet."}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble",manage:"Manage"}}}},u={"kmh-shipbubble":{title:"Shipbubble",config:{testConnection:"Verbindung testen",saveFirst:"Zuerst speichern; der Test verwendet den gespeicherten API-Schlüssel.",connectionOk:"Mit Shipbubble verbunden. Wallet-Guthaben: {balance} {currency}",validateSender:"Absenderadresse prüfen",validateSenderHint:"Zuerst speichern. Shipbubble prüft die Adresse; Kurierpreise brauchen einen geprüften Absender.",senderOk:"Absenderadresse geprüft: {address}",requestFailed:"Die Anfrage an den Shopware-Server ist fehlgeschlagen.",productsWithoutWeight:"Aktive Produkte ohne Gewicht: {count}. Im Checkout wird für sie das Standard-Produktgewicht verwendet, daher können Kurierpreise abweichen.",showProducts:"Produkte anzeigen"},flow:{createShipment:"Shipbubble-Sendung erstellen",createShipmentHelp:"Bucht für jede noch nicht gebuchte Shipbubble-Lieferung der Bestellung den vom Kunden gewählten Kurier. Eine Lieferung, die einen neuen Preis braucht, bestätigen Sie in der Bestellung."},shipment:{title:"Shipbubble",courier:"Kurier",status:"Status",charged:"Dem Kunden berechnet",carrierPrice:"Shipbubble-Preis",orderId:"Shipbubble-Auftrag",trackingCode:"Sendungsnummer des Kuriers",tracking:"Sendungsverfolgung",openTracking:"Sendungsverfolgung öffnen",waybill:"Frachtbrief",downloadWaybill:"Frachtbrief herunterladen",create:"Sendung erstellen",refresh:"Status aktualisieren",cancel:"Sendung stornieren",requoteTitle:"Neuen Kurierpreis bestätigen",requoteText:"Das Kurierangebot musste erneuert werden. {courier} kostet jetzt {new} (vorher {old}).",requoteCharged:"Der vom Kunden bezahlte Versandpreis ändert sich nicht. Sendung zum neuen Preis buchen?",requoteConfirm:"Sendung buchen",cancelTitle:"Sendung stornieren?",cancelText:"Vor der Abholung erstattet Shipbubble das Label abzüglich einer Stornogebühr von ₦150 auf Ihr Wallet. Hat der Kurier das Paket abgeholt, lässt sich die Sendung nicht mehr stornieren.",cancelConfirm:"Sendung stornieren",done:{create:"Sendung bei Shipbubble erstellt.",refresh:"Status von Shipbubble aktualisiert.",cancel:"Sendung storniert."},statuses:{quoted:"Noch nicht gebucht",needs_quote:"Neuer Preis nötig",pending:"Gebucht",confirmed:"Bestätigt",picked_up:"Abgeholt",in_transit:"Unterwegs",completed:"Zugestellt",returned:"Retourniert",cancelled:"Storniert",failed:"Fehlgeschlagen"}},webhook:{url:"Webhook-URL",urlHelp:"Tragen Sie diese URL im Shipbubble-Dashboard unter Einstellungen > API-Schlüssel & Webhooks als Test- und Live-Webhook-URL ein. Statusänderungen kommen dann automatisch im Shop an.",noDomain:"Legen Sie zuerst eine Storefront-Domain für diesen Verkaufskanal an.",lastReceived:"Letzte Meldung von Shipbubble: {date}",neverReceived:"Noch keine Meldung von Shipbubble empfangen."}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble",manage:"Manage"}}}},d={"kmh-shipbubble":{title:"Shipbubble",config:{testConnection:"Test connection",saveFirst:"Save the settings first; the test uses the saved API key.",connectionOk:"Connected to Shipbubble. Wallet balance: {balance} {currency}",validateSender:"Validate sender address",validateSenderHint:"Save first. Shipbubble checks the address; courier prices need a validated sender.",senderOk:"Sender address validated: {address}",requestFailed:"The request to the Shopware server failed.",productsWithoutWeight:"Produits actifs sans poids : {count}. Le paiement utilise le poids par défaut pour eux, les prix des coursiers peuvent donc être inexacts.",showProducts:"Afficher les produits"},flow:{createShipment:"Créer l'envoi Shipbubble",createShipmentHelp:"Réserve le coursier Shipbubble choisi par le client pour chaque livraison Shipbubble non encore réservée. Une livraison qui nécessite un nouveau prix est à confirmer dans la commande."},shipment:{title:"Shipbubble",courier:"Coursier",status:"Statut",charged:"Facturé au client",carrierPrice:"Prix Shipbubble",orderId:"Commande Shipbubble",trackingCode:"Numéro de suivi du coursier",tracking:"Suivi",openTracking:"Ouvrir la page de suivi",waybill:"Lettre de transport",downloadWaybill:"Télécharger la lettre de transport",create:"Créer l'envoi",refresh:"Actualiser le statut",cancel:"Annuler l'envoi",requoteTitle:"Confirmer le nouveau prix",requoteText:"Le devis du coursier a dû être renouvelé. {courier} coûte maintenant {new} (avant {old}).",requoteCharged:"Le prix de livraison payé par le client ne change pas. Réserver l'envoi au nouveau prix ?",requoteConfirm:"Réserver l'envoi",cancelTitle:"Annuler l'envoi ?",cancelText:"Avant l'enlèvement, Shipbubble rembourse l'étiquette sur votre portefeuille, moins des frais d'annulation de 150 ₦. Une fois le colis enlevé par le coursier, l'envoi ne peut plus être annulé.",cancelConfirm:"Annuler l'envoi",done:{create:"Envoi créé chez Shipbubble.",refresh:"Statut actualisé depuis Shipbubble.",cancel:"Envoi annulé."},statuses:{quoted:"Pas encore réservé",needs_quote:"Nouveau prix requis",pending:"Réservé",confirmed:"Confirmé",picked_up:"Enlevé",in_transit:"En transit",completed:"Livré",returned:"Retourné",cancelled:"Annulé",failed:"Échoué"}},webhook:{url:"URL du webhook",urlHelp:"Collez cette URL dans votre tableau de bord Shipbubble sous Paramètres > Clés API et webhooks, comme URL de test et de production. Les mises à jour de statut arrivent alors automatiquement.",noDomain:"Ajoutez d'abord un domaine storefront à ce canal de vente.",lastReceived:"Dernière mise à jour de Shipbubble : {date}",neverReceived:"Aucune mise à jour de Shipbubble reçue."}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble",manage:"Manage"}}}},{Classes:n}=Shopware;class b extends n.ApiService{constructor(t,i){super(t,i,"kmh-shipbubble"),this.name="kmhShipbubbleConfigService"}testConnection(t){return this.post("test-connection",t)}validateSender(t){return this.post("validate-sender",t)}options(t,i){return this.httpClient.get(`_action/kmh-shipbubble/${t}`,{params:i?{salesChannelId:i}:{},headers:this.getBasicHeaders()}).then(r=>n.ApiService.handleResponse(r))}productsWithoutWeight(){return this.httpClient.get("_action/kmh-shipbubble/products-without-weight",{headers:this.getBasicHeaders()}).then(t=>n.ApiService.handleResponse(t))}webhookStatus(){return this.httpClient.get("_action/kmh-shipbubble/webhook-status",{headers:this.getBasicHeaders()}).then(t=>n.ApiService.handleResponse(t))}shipmentAction(t,i,r={}){return this.httpClient.post(`_action/kmh-shipbubble/shipment/${t}/${i}`,r,{headers:this.getBasicHeaders()}).then(h=>n.ApiService.handleResponse(h))}post(t,i){return this.httpClient.post(`_action/kmh-shipbubble/${t}`,{salesChannelId:i},{headers:this.getBasicHeaders()}).then(r=>n.ApiService.handleResponse(r))}}Shopware.Service("privileges").addPrivilegeMappingEntry({category:"permissions",parent:"orders",key:"kmh_shipbubble_shipment",roles:{viewer:{privileges:["kmh_shipbubble_shipment:read"],dependencies:["order.viewer"]},editor:{privileges:["kmh_shipbubble_shipment:update"],dependencies:["kmh_shipbubble_shipment.viewer"]}}});function l(e){let t=e.$parent;for(;t;){if("actualConfigData"in t&&"currentSalesChannelId"in t)return t;t=t.$parent}return null}function s(e){var t;return((t=l(e))==null?void 0:t.currentSalesChannelId)??null}Shopware.Component.register("kmh-shipbubble-connection-test",{template:`
        <div class="kmh-shipbubble-connection-test">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.testConnection') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.saveFirst') }}</p>
        </div>
    `,inject:["kmhShipbubbleConfigService"],mixins:[Shopware.Mixin.getByName("notification")],data(){return{isLoading:!1}},methods:{async run(){this.isLoading=!0;try{const e=await this.kmhShipbubbleConfigService.testConnection(s(this));e.success?this.createNotificationSuccess({message:this.$t("kmh-shipbubble.config.connectionOk",{balance:e.balance,currency:e.currency})}):this.createNotificationError({message:e.message})}catch{this.createNotificationError({message:this.$tc("kmh-shipbubble.config.requestFailed")})}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-sender-validation",{template:`
        <div class="kmh-shipbubble-sender-validation">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.validateSender') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.validateSenderHint') }}</p>
        </div>
    `,inject:["kmhShipbubbleConfigService"],mixins:[Shopware.Mixin.getByName("notification")],data(){return{isLoading:!1}},methods:{async run(){this.isLoading=!0;try{const e=await this.kmhShipbubbleConfigService.validateSender(s(this));e.success?this.createNotificationSuccess({message:this.$t("kmh-shipbubble.config.senderOk",{address:e.formattedAddress})}):this.createNotificationError({message:e.message})}catch{this.createNotificationError({message:this.$tc("kmh-shipbubble.config.requestFailed")})}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-api-select",{template:`
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
    `,inject:["kmhShipbubbleConfigService"],emits:["update:value"],props:{value:{type:[String,Array],required:!1,default:null},label:{type:String,required:!1,default:""},helpText:{type:String,required:!1,default:null},source:{type:String,required:!0},multiple:{type:[Boolean,String],required:!1,default:!1},disabled:{type:Boolean,required:!1,default:!1}},data(){return{options:[],isLoading:!1,error:null}},computed:{isMultiple(){return this.multiple===!0||this.multiple==="true"},configLoading(){var e;return((e=l(this))==null?void 0:e.isLoading)??!1}},watch:{configLoading(e,t){t&&!e&&this.load()}},created(){this.load()},methods:{async load(){this.isLoading=!0,this.error=null;try{const e=await this.kmhShipbubbleConfigService.options(this.source,s(this));e.success?this.options=e.options:this.error=e.message}catch{this.error=this.$tc("kmh-shipbubble.config.requestFailed")}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-weight-warning",{template:`
        <mt-banner v-if="count > 0" variant="attention" class="kmh-shipbubble-weight-warning">
            {{ $t('kmh-shipbubble.config.productsWithoutWeight', { count }) }}
            <router-link :to="{ name: 'sw.product.index' }">{{ $tc('kmh-shipbubble.config.showProducts') }}</router-link>
        </mt-banner>
    `,inject:["kmhShipbubbleConfigService"],data(){return{count:0}},async created(){try{this.count=(await this.kmhShipbubbleConfigService.productsWithoutWeight()).count}catch{this.count=0}}});const{Criteria:o}=Shopware.Data;Shopware.Component.register("kmh-shipbubble-order-shipment",{template:`
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
    `,inject:["repositoryFactory","acl","kmhShipbubbleConfigService"],mixins:[Shopware.Mixin.getByName("notification")],props:{orderId:{type:String,required:!0}},data(){return{shipments:[],isLoading:!1,busyId:null,requote:null,cancelTarget:null}},computed:{repository(){return this.repositoryFactory.create("kmh_shipbubble_shipment")},canEdit(){return this.acl.can("kmh_shipbubble_shipment.editor")}},watch:{orderId(){this.load()}},created(){this.load()},methods:{async load(){this.isLoading=!0;try{const e=new o(1,25).addFilter(o.equals("orderId",this.orderId));this.shipments=Array.from(await this.repository.search(e,Shopware.Context.api))}finally{this.isLoading=!1}},isBookable(e){return["quoted","needs_quote"].includes(e.status)},isOpen(e){return["pending","confirmed","picked_up","in_transit"].includes(e.status)},statusLabel(e){return this.$tc(`kmh-shipbubble.shipment.statuses.${e}`)},courierTrackingCode(e){var t;return((t=e.lastPayload)==null?void 0:t.courierTrackingCode)??null},money(e,t){return e==null?"–":Shopware.Utils.format.currency(Number(e),t||"NGN")},async create(e,t){const i=await this.act(e,"create",{confirmed:t});i!=null&&i.success&&!i.booked&&(this.requote={...i,shipmentId:e.id})},async confirmRequote(){const e=this.shipments.find(t=>t.id===this.requote.shipmentId);this.requote=null,await this.create(e,!0)},async refresh(e){await this.act(e,"refresh")},async cancel(){const e=this.cancelTarget;this.cancelTarget=null,await this.act(e,"cancel")},async act(e,t,i={}){this.busyId=e.id;try{const r=await this.kmhShipbubbleConfigService.shipmentAction(e.id,t,i);return r.success?(t!=="create"||r.booked)&&this.createNotificationSuccess({message:this.$tc(`kmh-shipbubble.shipment.done.${t}`)}):this.createNotificationError({message:r.message}),r}catch{return this.createNotificationError({message:this.$tc("kmh-shipbubble.config.requestFailed")}),null}finally{this.busyId=null,await this.load()}}}});const{Criteria:a}=Shopware.Data;Shopware.Component.register("kmh-shipbubble-webhook-info",{template:`
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
    `,inject:["repositoryFactory","kmhShipbubbleConfigService"],data(){return{url:null,lastReceivedAt:null}},computed:{salesChannelId(){var e;return((e=l(this))==null?void 0:e.currentSalesChannelId)??null}},watch:{salesChannelId(){this.loadUrl()}},created(){this.loadUrl(),this.kmhShipbubbleConfigService.webhookStatus().then(e=>{this.lastReceivedAt=e.lastReceivedAt}).catch(()=>{this.lastReceivedAt=null})},methods:{async loadUrl(){const e=s(this),t=new a(1,1).addFilter(a.prefix("url","http"));e&&t.addFilter(a.equals("salesChannelId",e));const i=(await this.repositoryFactory.create("sales_channel_domain").search(t,Shopware.Context.api)).first();this.url=i?`${i.url.replace(/\/$/,"")}/kmh-shipbubble/webhook/${e||"default"}`:null},formatDate(e){return Shopware.Utils.format.date(e)}}});const p=`{% block sw_order_detail_general_line_items_card %} {% parent %} <kmh-shipbubble-order-shipment v-if="$route.params.id && acl.can('kmh_shipbubble_shipment.viewer')" :order-id="$route.params.id" /> {% endblock %}`;Shopware.Component.override("sw-order-detail-general",{template:p});Shopware.Application.addServiceProviderDecorator("flowBuilderService",e=>(e.addLabels({kmhShipbubbleCreate_shipment:"kmh-shipbubble.flow.createShipment"}),e.addIcons({kmhShipbubbleCreate_shipment:"regular-truck"}),e));Shopware.Component.register("sw-flow-kmh-shipbubble-create_shipment-modal",{template:`
        <sw-modal :title="$tc('kmh-shipbubble.flow.createShipment')" variant="small" @modal-close="$emit('modal-close')">
            <p>{{ $tc('kmh-shipbubble.flow.createShipmentHelp') }}</p>
            <template #modal-footer>
                <mt-button size="small" variant="secondary" @click="$emit('modal-close')">{{ $tc('global.default.cancel') }}</mt-button>
                <mt-button size="small" variant="primary" @click="save">{{ $tc('global.default.save') }}</mt-button>
            </template>
        </sw-modal>
    `,props:{sequence:{type:Object,required:!0}},emits:["modal-close","process-finish"],methods:{save(){this.$emit("process-finish",{...this.sequence,config:{}})}}});Shopware.Locale.extend("en-GB",c);Shopware.Locale.extend("de-DE",u);Shopware.Locale.extend("fr-FR",d);Shopware.Service().register("kmhShipbubbleConfigService",e=>{const t=Shopware.Application.getContainer("init");return new b(t.httpClient,e.loginService)});
//# sourceMappingURL=kmh-shipbubble-s-w-BJ_Rs29-.js.map
