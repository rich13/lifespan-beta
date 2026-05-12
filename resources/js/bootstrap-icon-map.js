/**
 * Uses {@link window.LifespanBootstrapIcons} from layouts/app (JSON from App\Support\BootstrapIconMap).
 * Suffix is the Bootstrap Icons name after "bi-".
 */
function lifespanBootstrapIconSuffix(category, typeId) {
    const data = window.LifespanBootstrapIcons;
    const type = typeId === null || typeId === undefined || typeId === '' ? null : String(typeId);
    if (!data || !data.defaults) {
        return 'box';
    }
    if (type === null) {
        return data.defaults[category] || data.defaults.span || 'box';
    }
    const map = data[category];
    if (map && Object.prototype.hasOwnProperty.call(map, type)) {
        return map[type];
    }
    return data.defaults[category] || data.defaults.span || 'box';
}

function lifespanSpanTypeIconSuffix(typeId) {
    return lifespanBootstrapIconSuffix('span', typeId);
}

window.lifespanBootstrapIconSuffix = lifespanBootstrapIconSuffix;
window.lifespanSpanTypeIconSuffix = lifespanSpanTypeIconSuffix;
