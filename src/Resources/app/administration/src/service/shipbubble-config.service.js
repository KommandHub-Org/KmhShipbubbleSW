const { Classes } = Shopware;

/**
 * Admin API calls behind the plugin configuration page.
 * Every call answers 200 with `{ success, ... }`; failures carry `reason` and `message`.
 */
export default class ShipbubbleConfigService extends Classes.ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'kmh-shipbubble');
        this.name = 'kmhShipbubbleConfigService';
    }

    testConnection(salesChannelId) {
        return this.post('test-connection', salesChannelId);
    }

    validateSender(salesChannelId) {
        return this.post('validate-sender', salesChannelId);
    }

    options(source, salesChannelId) {
        return this.httpClient
            .get(`_action/kmh-shipbubble/${source}`, {
                params: salesChannelId ? { salesChannelId } : {},
                headers: this.getBasicHeaders(),
            })
            .then((response) => Classes.ApiService.handleResponse(response));
    }

    productsWithoutWeight() {
        return this.httpClient
            .get('_action/kmh-shipbubble/products-without-weight', { headers: this.getBasicHeaders() })
            .then((response) => Classes.ApiService.handleResponse(response));
    }

    webhookStatus() {
        return this.httpClient
            .get('_action/kmh-shipbubble/webhook-status', { headers: this.getBasicHeaders() })
            .then((response) => Classes.ApiService.handleResponse(response));
    }

    shipmentAction(shipmentId, action, payload = {}) {
        return this.httpClient
            .post(`_action/kmh-shipbubble/shipment/${shipmentId}/${action}`, payload, { headers: this.getBasicHeaders() })
            .then((response) => Classes.ApiService.handleResponse(response));
    }

    post(action, salesChannelId) {
        return this.httpClient
            .post(`_action/kmh-shipbubble/${action}`, { salesChannelId }, { headers: this.getBasicHeaders() })
            .then((response) => Classes.ApiService.handleResponse(response));
    }
}
