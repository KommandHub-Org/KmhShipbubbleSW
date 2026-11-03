/**
 * Order permissions for the Shipbubble card (spec §12), under Settings >
 * Users & permissions > Roles > Orders. The server enforces the same
 * privileges: the entity read for viewing, `kmh_shipbubble_shipment:update`
 * on the create, refresh and cancel routes.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'orders',
    key: 'kmh_shipbubble_shipment',
    roles: {
        viewer: {
            privileges: ['kmh_shipbubble_shipment:read'],
            dependencies: ['order.viewer'],
        },
        editor: {
            privileges: ['kmh_shipbubble_shipment:update'],
            dependencies: ['kmh_shipbubble_shipment.viewer'],
        },
    },
});
