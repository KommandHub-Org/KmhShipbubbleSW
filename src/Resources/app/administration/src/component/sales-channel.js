/**
 * The sales channel currently selected on the plugin configuration page
 * (null = all sales channels). sw-system-config holds it; config components
 * are rendered several levels below it.
 */
export default function currentSalesChannelId(component) {
    let parent = component.$parent;

    while (parent) {
        if (Object.prototype.hasOwnProperty.call(parent, 'currentSalesChannelId')) {
            return parent.currentSalesChannelId ?? null;
        }
        parent = parent.$parent;
    }

    return null;
}
