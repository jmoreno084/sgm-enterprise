import {request} from './http.js';
const status = document.getElementById('session-status');
request('/api/v1/sesion').then(function (result) {
    if (!result.data.autenticado) { window.location.assign('/login'); return; }
    status.textContent = 'Sesión de ' + result.data.usuario.nombre;
    document.getElementById('users-link').hidden = !result.data.usuario.es_administrador;
    const list = document.getElementById('session-companies');
    result.data.empresas.forEach(function (company) { const item = document.createElement('li'); item.textContent = company.razon_social; list.appendChild(item); });
    if (!result.data.empresas.length) { status.textContent += '. No tienes empresas activas asignadas.'; }
}).catch(function (error) { status.textContent = error.message; });
document.getElementById('logout').addEventListener('click', function () {
    request('/api/v1/sesion', {method: 'DELETE', body: {}}).then(function () { window.location.assign('/login'); }).catch(function (error) { status.textContent = error.message; });
});
