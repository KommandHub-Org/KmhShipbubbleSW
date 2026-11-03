import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';
import frFR from './snippet/fr-FR.json';
import ShipbubbleConfigService from './service/shipbubble-config.service';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('fr-FR', frFR);

Shopware.Service().register('kmhShipbubbleConfigService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new ShipbubbleConfigService(initContainer.httpClient, container.loginService);
});

import './acl';
import './component/kmh-shipbubble-connection-test';
import './component/kmh-shipbubble-sender-validation';
import './component/kmh-shipbubble-api-select';
import './component/kmh-shipbubble-weight-warning';
