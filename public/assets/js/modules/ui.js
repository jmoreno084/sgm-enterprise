export function status(message, kind) {
    const target = document.getElementById('sgpm-status');
    target.className = 'alert ' + (kind === 'error' ? 'alert-danger' : 'alert-info');
    target.textContent = message;
    target.hidden = !message;
}
export function text(tag, value) {
    const element = document.createElement(tag);
    element.textContent = value === null || value === undefined ? 'No disponible' : String(value);
    return element;
}
export function busy(button, state) {
    button.disabled = state;
    button.setAttribute('aria-busy', state ? 'true' : 'false');
}

