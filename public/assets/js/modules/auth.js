import {request} from './http.js';
const form = document.getElementById('auth-form');
const status = document.getElementById('auth-status');
const button = form.querySelector('button[type="submit"]');
const match = /^#token=([a-f0-9]{64})$/.exec(window.location.hash);
let token = match ? match[1] : '';
if (window.location.hash) { window.history.replaceState(null, '', window.location.pathname); }
if (token && form.dataset.operation === 'recovery') {
    document.getElementById('email-fields').hidden = true;
    form.elements.correo.disabled = true;
    document.getElementById('password-fields').hidden = false;
    document.getElementById('password-fields').disabled = false;
}
request('/api/v1/sesion').then(function () { button.disabled = false; }).catch(showError);
function showError(error) { status.textContent = error.message; status.focus(); }
form.addEventListener('submit', function (event) {
    event.preventDefault(); button.disabled = true; status.textContent = 'Procesando…';
    let path = '/api/v1/sesion';
    let body = {correo: form.elements.correo.value, password: form.elements.password.value};
    if (form.dataset.operation === 'recovery') {
        path = token ? '/api/v1/recuperaciones/confirmar' : '/api/v1/recuperaciones';
        body = token ? {token: token, password: form.elements.password.value, confirmacion: form.elements.confirmacion.value} : {correo: form.elements.correo.value};
    }
    request(path, {method: 'POST', body: body}).then(function (result) {
        form.reset();
        if (form.dataset.operation === 'login') {
            status.textContent = 'Sesión iniciada.';
            const destination = result.data.usuario.es_administrador ? '/usuarios' : '/';
            window.location.assign(destination);
        } else {
            status.textContent = result.data.message;
            if (token) { token = ''; window.location.assign('/login'); }
        }
    }).catch(function (error) {
        if (form.elements.password) { form.elements.password.value = ''; }
        if (form.elements.confirmacion) { form.elements.confirmacion.value = ''; }
        showError(error);
    }).then(function () { button.disabled = false; });
});
