import {request} from './http.js';
import {notice, failure, link, cell, paging} from './resource-ui.js';
const form = document.getElementById('cadastre-search');
const pages = paging(load);
function load() {
    const query = new URLSearchParams(new FormData(form)); query.set('page', pages.current());
    notice('Consultando catastro…', false);
    return request('/api/v1/catastro?' + query.toString()).then(function (result) {
        const list = document.getElementById('cadastre-list'); list.textContent = '';
        result.data.items.forEach(function (record) {
            const row = document.createElement('tr');
            [record.CODIGO_EXPEDIENTE, record.tipo, record.SOLICITANTES_O_TITULARES, record.MUNICIPIOS].forEach(function (value) { cell(row, value); });
            const actions = document.createElement('td'); actions.appendChild(link('Ficha de 30 atributos', '/catastro/' + record.tipo + '/' + encodeURIComponent(record.CODIGO_EXPEDIENTE)));
            row.appendChild(actions); list.appendChild(row);
        }); pages.show(result.data);
    }).catch(failure);
}
request('/api/v1/sesion').then(load).catch(failure);
form.addEventListener('submit', function (event) { event.preventDefault(); pages.reset(); load(); });
