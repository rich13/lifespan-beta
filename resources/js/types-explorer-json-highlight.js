/**
 * Syntax-coloured JSON in the types explorer fourth column (keys / strings / numbers / booleans / null).
 */
function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function indentNewline(depth) {
    return '\n' + '  '.repeat(depth);
}

function highlightJsonValue(value, depth) {
    if (value === null) {
        return '<span class="types-explorer-json__null">null</span>';
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
        return `<span class="types-explorer-json__number">${value}</span>`;
    }
    if (typeof value === 'boolean') {
        return `<span class="types-explorer-json__bool">${value}</span>`;
    }
    if (typeof value === 'string') {
        return `<span class="types-explorer-json__string">"${escapeHtml(value)}"</span>`;
    }
    if (Array.isArray(value)) {
        if (value.length === 0) {
            return '[]';
        }
        let out = '[';
        value.forEach((item, i) => {
            out += indentNewline(depth + 1) + highlightJsonValue(item, depth + 1);
            if (i < value.length - 1) {
                out += ',';
            }
        });
        out += indentNewline(depth) + ']';
        return out;
    }
    if (typeof value === 'object') {
        const keys = Object.keys(value);
        if (keys.length === 0) {
            return '{}';
        }
        let out = '{';
        keys.forEach((key, i) => {
            out += indentNewline(depth + 1);
            out += `<span class="types-explorer-json__key">"${escapeHtml(key)}"</span>: `;
            out += highlightJsonValue(value[key], depth + 1);
            if (i < keys.length - 1) {
                out += ',';
            }
        });
        out += indentNewline(depth) + '}';
        return out;
    }
    return `<span class="types-explorer-json__string">"${escapeHtml(String(value))}"</span>`;
}

function initTypesExplorerJsonHighlight() {
    const $pre = $('pre.types-explorer__json');
    if (!$pre.length) {
        return;
    }
    const raw = $pre.text().trim();
    if (!raw) {
        return;
    }
    try {
        const data = JSON.parse(raw);
        $pre.html(highlightJsonValue(data, 0));
    } catch {
        /* keep server-rendered plain text */
    }
}

$(initTypesExplorerJsonHighlight);
