import {request} from './http.js';
import {notice, failure, button, link, cell, fill, values, editor, paging, permissions, submitting} from './resource-ui.js';
const company = Number(document.getElementById('project-module').dataset.company);
const form = document.getElementById('project-form');
let access = {edit: false};
const pages = paging(load);
function load() {
    notice('Cargando proyectos…', false);
    return request('/api/v1/empresas/' + company + '/proyectos?page=' + pages.current() + '&nombre=' + encodeURIComponent(document.getElementById('project-filter').value)).then(function (result) {
        const list = document.getElementById('project-list'); list.textContent = '';
        result.data.items.forEach(function (project) {
            const row = document.createElement('tr'); cell(row, project.nombre); cell(row, project.mineral_principal); cell(row, project.estado);
            const actions = document.createElement('td'); actions.appendChild(link('Expedientes', '/proyectos/' + project.id));
            if (access.edit) {
                actions.appendChild(button('Editar', function () { fill(form, project); form.elements.nombre.focus(); }));
                actions.appendChild(button('Eliminar', function () {
                    if (window.confirm('¿Eliminar este proyecto?')) { request('/api/v1/proyectos/' + project.id, {method: 'DELETE', body: {version: project.version}}).then(load).catch(failure); }
                }));
            }
            row.appendChild(actions); list.appendChild(row);
        }); pages.show(result.data);
    }).catch(failure);
}
permissions(company).then(function (result) { access = result; editor('project-editor', access.edit); return load(); }).catch(failure);
submitting(form, function () {
    const id = form.elements.id.value;
    return request(id ? '/api/v1/proyectos/' + id : '/api/v1/empresas/' + company + '/proyectos', {method: id ? 'PUT' : 'POST', body: values(form)}).then(function () { form.reset(); return load(); });
});
document.getElementById('project-search').addEventListener('submit', function (event) { event.preventDefault(); pages.reset(); load(); });
