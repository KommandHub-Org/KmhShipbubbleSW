const o={"kmh-shipbubble":{title:"Shipbubble Courier Delivery for Shopware 6",config:{testConnection:"Test connection",saveFirst:"Save the settings first; the test uses the saved API key.",connectionOk:"Connected to Shipbubble. Wallet balance: {balance} {currency}",validateSender:"Validate sender address",validateSenderHint:"Save first. Shipbubble checks the address; courier prices need a validated sender.",senderOk:"Sender address validated: {address}",requestFailed:"The request to the Shopware server failed.",productsWithoutWeight:"Active products without a weight: {count}. Checkout uses the default product weight for them, so courier prices may be off.",showProducts:"Show products"}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble Courier Delivery for Shopware 6",manage:"Manage"}}}},l={"kmh-shipbubble":{title:"Shipbubble Kurierversand für Shopware 6",config:{testConnection:"Verbindung testen",saveFirst:"Zuerst speichern; der Test verwendet den gespeicherten API-Schlüssel.",connectionOk:"Mit Shipbubble verbunden. Wallet-Guthaben: {balance} {currency}",validateSender:"Absenderadresse prüfen",validateSenderHint:"Zuerst speichern. Shipbubble prüft die Adresse; Kurierpreise brauchen einen geprüften Absender.",senderOk:"Absenderadresse geprüft: {address}",requestFailed:"Die Anfrage an den Shopware-Server ist fehlgeschlagen.",productsWithoutWeight:"Aktive Produkte ohne Gewicht: {count}. Im Checkout wird für sie das Standard-Produktgewicht verwendet, daher können Kurierpreise abweichen.",showProducts:"Produkte anzeigen"}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble Kurierversand für Shopware 6",manage:"Manage"}}}},h={"kmh-shipbubble":{title:"Shipbubble Courier Delivery for Shopware 6",config:{testConnection:"Test connection",saveFirst:"Save the settings first; the test uses the saved API key.",connectionOk:"Connected to Shipbubble. Wallet balance: {balance} {currency}",validateSender:"Validate sender address",validateSenderHint:"Save first. Shipbubble checks the address; courier prices need a validated sender.",senderOk:"Sender address validated: {address}",requestFailed:"The request to the Shopware server failed.",productsWithoutWeight:"Produits actifs sans poids : {count}. Le paiement utilise le poids par défaut pour eux, les prix des coursiers peuvent donc être inexacts.",showProducts:"Afficher les produits"}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble Courier Delivery for Shopware 6",manage:"Manage"}}}},{Classes:s}=Shopware;class d extends s.ApiService{constructor(t,i){super(t,i,"kmh-shipbubble"),this.name="kmhShipbubbleConfigService"}testConnection(t){return this.post("test-connection",t)}validateSender(t){return this.post("validate-sender",t)}options(t,i){return this.httpClient.get(`_action/kmh-shipbubble/${t}`,{params:i?{salesChannelId:i}:{},headers:this.getBasicHeaders()}).then(r=>s.ApiService.handleResponse(r))}productsWithoutWeight(){return this.httpClient.get("_action/kmh-shipbubble/products-without-weight",{headers:this.getBasicHeaders()}).then(t=>s.ApiService.handleResponse(t))}post(t,i){return this.httpClient.post(`_action/kmh-shipbubble/${t}`,{salesChannelId:i},{headers:this.getBasicHeaders()}).then(r=>s.ApiService.handleResponse(r))}}Shopware.Service("privileges").addPrivilegeMappingEntry({category:"additional_permissions",parent:null,key:"shipbubble",roles:{manage:{privileges:[],dependencies:[]}}});function a(e){let t=e.$parent;for(;t;){if("actualConfigData"in t&&"currentSalesChannelId"in t)return t;t=t.$parent}return null}function n(e){var t;return((t=a(e))==null?void 0:t.currentSalesChannelId)??null}Shopware.Component.register("kmh-shipbubble-connection-test",{template:`
        <div class="kmh-shipbubble-connection-test">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.testConnection') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.saveFirst') }}</p>
        </div>
    `,inject:["kmhShipbubbleConfigService"],mixins:[Shopware.Mixin.getByName("notification")],data(){return{isLoading:!1}},methods:{async run(){this.isLoading=!0;try{const e=await this.kmhShipbubbleConfigService.testConnection(n(this));e.success?this.createNotificationSuccess({message:this.$t("kmh-shipbubble.config.connectionOk",{balance:e.balance,currency:e.currency})}):this.createNotificationError({message:e.message})}catch{this.createNotificationError({message:this.$tc("kmh-shipbubble.config.requestFailed")})}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-sender-validation",{template:`
        <div class="kmh-shipbubble-sender-validation">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.validateSender') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.validateSenderHint') }}</p>
        </div>
    `,inject:["kmhShipbubbleConfigService"],mixins:[Shopware.Mixin.getByName("notification")],data(){return{isLoading:!1}},methods:{async run(){this.isLoading=!0;try{const e=await this.kmhShipbubbleConfigService.validateSender(n(this));e.success?this.createNotificationSuccess({message:this.$t("kmh-shipbubble.config.senderOk",{address:e.formattedAddress})}):this.createNotificationError({message:e.message})}catch{this.createNotificationError({message:this.$tc("kmh-shipbubble.config.requestFailed")})}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-api-select",{template:`
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
    `,inject:["kmhShipbubbleConfigService"],emits:["update:value"],props:{value:{type:[String,Array],required:!1,default:null},label:{type:String,required:!1,default:""},helpText:{type:String,required:!1,default:null},source:{type:String,required:!0},multiple:{type:[Boolean,String],required:!1,default:!1},disabled:{type:Boolean,required:!1,default:!1}},data(){return{options:[],isLoading:!1,error:null}},computed:{isMultiple(){return this.multiple===!0||this.multiple==="true"},configLoading(){var e;return((e=a(this))==null?void 0:e.isLoading)??!1}},watch:{configLoading(e,t){t&&!e&&this.load()}},created(){this.load()},methods:{async load(){this.isLoading=!0,this.error=null;try{const e=await this.kmhShipbubbleConfigService.options(this.source,n(this));e.success?this.options=e.options:this.error=e.message}catch{this.error=this.$tc("kmh-shipbubble.config.requestFailed")}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-weight-warning",{template:`
        <mt-banner v-if="count > 0" variant="attention" class="kmh-shipbubble-weight-warning">
            {{ $t('kmh-shipbubble.config.productsWithoutWeight', { count }) }}
            <router-link :to="{ name: 'sw.product.index' }">{{ $tc('kmh-shipbubble.config.showProducts') }}</router-link>
        </mt-banner>
    `,inject:["kmhShipbubbleConfigService"],data(){return{count:0}},async created(){try{this.count=(await this.kmhShipbubbleConfigService.productsWithoutWeight()).count}catch{this.count=0}}});Shopware.Locale.extend("en-GB",o);Shopware.Locale.extend("de-DE",l);Shopware.Locale.extend("fr-FR",h);Shopware.Service().register("kmhShipbubbleConfigService",e=>{const t=Shopware.Application.getContainer("init");return new d(t.httpClient,e.loginService)});
//# sourceMappingURL=kmh-shipbubble-s-w-C5QsDOav.js.map
