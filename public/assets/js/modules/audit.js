import {request} from './http.js';
import {notice, failure, cell, link, paging} from './resource-ui.js';
const company = Number(document.getElementById('audit-module').dataset.company);
const api = company ? '/api/v1/empresas/' + company + '/auditoria' : '/api/v1/auditoria';
const page = company ? '/empresas/' + company + '/auditoria' : '/auditoria';
const form = document.getElementById('audit-search');
const pages = paging(load);
function load() {
    const query = new URLSearchParams(new FormData(form)); query.set('page', pages.current());
    notice('Consultando auditoría…', false);
    return request(api + '?' + query.toString()).then(function (result) {
        const list = document.getElementById('audit-list'); list.textContent = '';
        result.data.items.forEach(function (event) {
            const row = document.createElement('tr');
            [event.fecha, event.usuario_id, event.accion, event.entidad].forEach(function (value) { cell(row, value); });
            const action = document.createElement('td'); action.appendChild(link('Antes y después', page + '/' + event.id)); row.appendChild(action); list.appendChild(row);
        }); pages.show(result.data);
    }).catch(failure);
}
request('/api/v1/sesion').then(load).catch(failure);
form.addEventListener('submit', function (event) { event.preventDefault(); pages.reset(); load(); });
