import {request} from './http.js';
import {notice, failure, button, cell, fill, values, paging, submitting} from './resource-ui.js';
const form = document.getElementById('identity-form');
const pages = paging(load);
function load() {
    notice('Cargando identidades…', false);
    return request('/api/v1/vecinas?page=' + pages.current() + '&nombre=' + encodeURIComponent(document.getElementById('identity-filter').value)).then(function (result) {
        const list = document.getElementById('identity-list'); list.textContent = '';
        result.data.items.forEach(function (identity) {
            const row = document.createElement('tr'); [identity.nombre, identity.nit, identity.observaciones].forEach(function (value) { cell(row, value); });
            const actions = document.createElement('td'); actions.appendChild(button('Editar identidad', function () { fill(form, identity); form.elements.nombre.focus(); }));
            actions.appendChild(button('Eliminar identidad', function () {
                if (window.confirm('¿Eliminar la identidad global? Los vínculos existentes impiden su borrado.')) { request('/api/v1/vecinas/' + identity.id, {method: 'DELETE', body: {version: identity.version}}).then(load).catch(failure); }
            })); row.appendChild(actions); list.appendChild(row);
        }); pages.show(result.data);
    }).catch(failure);
}
request('/api/v1/sesion').then(load).catch(failure);
submitting(form, function () {
    if (!form.elements.id.value) { throw new Error('Selecciona una identidad desde el catálogo. Las nuevas se crean desde un proyecto.'); }
    return request('/api/v1/vecinas/' + form.elements.id.value, {method: 'PUT', body: values(form)}).then(function () { form.reset(); return load(); });
});
document.getElementById('identity-search').addEventListener('submit', function (event) { event.preventDefault(); pages.reset(); load(); });
