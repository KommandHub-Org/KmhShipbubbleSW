const n={"kmh-shipbubble":{title:"Shipbubble Courier Delivery for Shopware 6",config:{testConnection:"Test connection",saveFirst:"Save the settings first; the test uses the saved API key.",connectionOk:"Connected to Shipbubble. Wallet balance: {balance} {currency}",validateSender:"Validate sender address",validateSenderHint:"Save first. Shipbubble checks the address; courier prices need a validated sender.",senderOk:"Sender address validated: {address}",requestFailed:"The request to the Shopware server failed."}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble Courier Delivery for Shopware 6",manage:"Manage"}}}},l={"kmh-shipbubble":{title:"Shipbubble Kurierversand für Shopware 6",config:{testConnection:"Verbindung testen",saveFirst:"Zuerst speichern; der Test verwendet den gespeicherten API-Schlüssel.",connectionOk:"Mit Shipbubble verbunden. Wallet-Guthaben: {balance} {currency}",validateSender:"Absenderadresse prüfen",validateSenderHint:"Zuerst speichern. Shipbubble prüft die Adresse; Kurierpreise brauchen einen geprüften Absender.",senderOk:"Absenderadresse geprüft: {address}",requestFailed:"Die Anfrage an den Shopware-Server ist fehlgeschlagen."}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble Kurierversand für Shopware 6",manage:"Manage"}}}},o={"kmh-shipbubble":{title:"Shipbubble Courier Delivery for Shopware 6",config:{testConnection:"Test connection",saveFirst:"Save the settings first; the test uses the saved API key.",connectionOk:"Connected to Shipbubble. Wallet balance: {balance} {currency}",validateSender:"Validate sender address",validateSenderHint:"Save first. Shipbubble checks the address; courier prices need a validated sender.",senderOk:"Sender address validated: {address}",requestFailed:"The request to the Shopware server failed."}},"sw-privileges":{additional_permissions:{shipbubble:{label:"Shipbubble Courier Delivery for Shopware 6",manage:"Manage"}}}},{Classes:r}=Shopware;class d extends r.ApiService{constructor(i,t){super(i,t,"kmh-shipbubble"),this.name="kmhShipbubbleConfigService"}testConnection(i){return this.post("test-connection",i)}validateSender(i){return this.post("validate-sender",i)}options(i,t){return this.httpClient.get(`_action/kmh-shipbubble/${i}`,{params:t?{salesChannelId:t}:{},headers:this.getBasicHeaders()}).then(s=>r.ApiService.handleResponse(s))}post(i,t){return this.httpClient.post(`_action/kmh-shipbubble/${i}`,{salesChannelId:t},{headers:this.getBasicHeaders()}).then(s=>r.ApiService.handleResponse(s))}}Shopware.Service("privileges").addPrivilegeMappingEntry({category:"additional_permissions",parent:null,key:"shipbubble",roles:{manage:{privileges:[],dependencies:[]}}});function a(e){let i=e.$parent;for(;i;){if(Object.prototype.hasOwnProperty.call(i,"currentSalesChannelId"))return i.currentSalesChannelId??null;i=i.$parent}return null}Shopware.Component.register("kmh-shipbubble-connection-test",{template:`
        <div class="kmh-shipbubble-connection-test">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.testConnection') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.saveFirst') }}</p>
        </div>
    `,inject:["kmhShipbubbleConfigService"],mixins:[Shopware.Mixin.getByName("notification")],data(){return{isLoading:!1}},methods:{async run(){this.isLoading=!0;try{const e=await this.kmhShipbubbleConfigService.testConnection(a(this));e.success?this.createNotificationSuccess({message:this.$tc("kmh-shipbubble.config.connectionOk",0,{balance:e.balance,currency:e.currency})}):this.createNotificationError({message:e.message})}catch{this.createNotificationError({message:this.$tc("kmh-shipbubble.config.requestFailed")})}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-sender-validation",{template:`
        <div class="kmh-shipbubble-sender-validation">
            <mt-button variant="secondary" :is-loading="isLoading" @click="run">
                {{ $tc('kmh-shipbubble.config.validateSender') }}
            </mt-button>
            <p class="sw-field__hint">{{ $tc('kmh-shipbubble.config.validateSenderHint') }}</p>
        </div>
    `,inject:["kmhShipbubbleConfigService"],mixins:[Shopware.Mixin.getByName("notification")],data(){return{isLoading:!1}},methods:{async run(){this.isLoading=!0;try{const e=await this.kmhShipbubbleConfigService.validateSender(a(this));e.success?this.createNotificationSuccess({message:this.$tc("kmh-shipbubble.config.senderOk",0,{address:e.formattedAddress})}):this.createNotificationError({message:e.message})}catch{this.createNotificationError({message:this.$tc("kmh-shipbubble.config.requestFailed")})}finally{this.isLoading=!1}}}});Shopware.Component.register("kmh-shipbubble-api-select",{template:`
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
    `,inject:["kmhShipbubbleConfigService"],emits:["update:value"],props:{value:{type:[String,Array],required:!1,default:null},label:{type:String,required:!1,default:""},helpText:{type:String,required:!1,default:null},source:{type:String,required:!0},multiple:{type:[Boolean,String],required:!1,default:!1},disabled:{type:Boolean,required:!1,default:!1}},data(){return{options:[],isLoading:!1,error:null}},computed:{isMultiple(){return this.multiple===!0||this.multiple==="true"}},created(){this.load()},methods:{async load(){this.isLoading=!0,this.error=null;try{const e=await this.kmhShipbubbleConfigService.options(this.source,a(this));e.success?this.options=e.options:this.error=e.message}catch{this.error=this.$tc("kmh-shipbubble.config.requestFailed")}finally{this.isLoading=!1}}}});Shopware.Locale.extend("en-GB",n);Shopware.Locale.extend("de-DE",l);Shopware.Locale.extend("fr-FR",o);Shopware.Service().register("kmhShipbubbleConfigService",e=>{const i=Shopware.Application.getContainer("init");return new d(i.httpClient,e.loginService)});
//# sourceMappingURL=kmh-shipbubble-s-w-D_AAofkz.js.map
