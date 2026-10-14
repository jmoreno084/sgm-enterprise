import {request} from './http.js';
import {notice, failure, button, link, cell, fill, values, editor, paging, submitting} from './resource-ui.js';
const form = document.getElementById('company-form');
let admin = false;
let companyAccess = [];
const pages = paging(load);
function load() {
    notice('Cargando empresas…', false);
    return request('/api/v1/empresas?page=' + pages.current() + '&razon_social=' + encodeURIComponent(document.getElementById('company-filter').value)).then(function (result) {
        const list = document.getElementById('company-list'); list.textContent = '';
        result.data.items.forEach(function (company) {
            const row = document.createElement('tr'); cell(row, company.nit); cell(row, company.razon_social); cell(row, company.estado);
            const actions = document.createElement('td'); actions.appendChild(link('Proyectos', '/empresas/' + company.id + '/proyectos'));
            const membership = companyAccess.filter(function (item) { return Number(item.id) === company.id; })[0];
            if (admin || (membership && membership.rol !== 'GEOLOGO')) { actions.appendChild(link('Auditoría', '/empresas/' + company.id + '/auditoria')); actions.appendChild(link('Reportes', '/empresas/' + company.id + '/reportes')); }
            if (company.logo_url) { actions.appendChild(link('Ver logo', company.logo_url)); }
            if (admin) {
                actions.appendChild(button('Editar', function () { fill(form, company); form.elements.razon_social.focus(); }));
                actions.appendChild(button('Eliminar', function () {
                    if (window.confirm('¿Eliminar esta empresa? Sus dependencias pueden impedirlo.')) {
                        request('/api/v1/empresas/' + company.id, {method: 'DELETE', body: {version: company.version}}).then(load).catch(failure);
                    }
                }));
            }
            row.appendChild(actions); list.appendChild(row);
        }); pages.show(result.data);
    }).catch(failure);
}
request('/api/v1/sesion').then(function (result) { admin = result.data.usuario.es_administrador; companyAccess = result.data.empresas; editor('company-editor', admin); return load(); }).catch(failure);
submitting(form, function () {
    const id = form.elements.id.value;
    return request('/api/v1/empresas' + (id ? '/' + id : ''), {method: id ? 'PUT' : 'POST', body: values(form)}).then(function (result) { form.reset(); fill(form, result.data); return load(); });
});
document.getElementById('company-search').addEventListener('submit', function (event) { event.preventDefault(); pages.reset(); load(); });
submitting(document.getElementById('logo-form'), function () {
    if (!form.elements.id.value) { throw new Error('Selecciona una empresa con Editar antes de guardar el logo.'); }
    const upload = new FormData(document.getElementById('logo-form')); upload.append('version', form.elements.version.value);
    return request('/api/v1/empresas/' + form.elements.id.value + '/logo', {method: 'POST', body: upload}).then(function (result) { fill(form, result.data); document.getElementById('logo-form').reset(); return load(); });
});
