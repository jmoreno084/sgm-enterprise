import {request} from './http.js';
import {notice, failure, button, cell, fill, editor, paging, permissions, submitting} from './resource-ui.js';
const module = document.getElementById('neighbor-module');
const project = Number(module.dataset.project);
const api = '/api/v1/proyectos/' + project + '/vecinas';
const form = document.getElementById('neighbor-form');
let access = {edit: false};
const pages = paging(load);
function load() {
    notice('Cargando vecinas…', false);
    return request(api + '?page=' + pages.current()).then(function (result) {
        const list = document.getElementById('neighbor-list'); list.textContent = '';
        const select = document.getElementById('neighbor-file-identity'); select.textContent = '';
        result.data.items.forEach(function (neighbor) {
            const row = document.createElement('tr');
            [neighbor.nombre, neighbor.nit, neighbor.orden_registro, neighbor.color_hex, neighbor.patron_indice, neighbor.observaciones].forEach(function (value) { cell(row, value); });
            const actions = document.createElement('td');
            if (access.edit) {
                actions.appendChild(button('Editar notas', function () {
                    fill(form, {id: neighbor.empresa_vecina_id, version: neighbor.version, empresa_vecina_id: neighbor.empresa_vecina_id, nombre: '', nit: '', observaciones: neighbor.observaciones});
                    form.elements.observaciones.focus();
                }));
                actions.appendChild(button('Desvincular', function () {
                    if (window.confirm('¿Desvincular esta vecina solo de este proyecto?')) { request(api + '/' + neighbor.empresa_vecina_id, {method: 'DELETE', body: {version: neighbor.version}}).then(load).catch(failure); }
                }));
            }
            const option = document.createElement('option'); option.value = neighbor.empresa_vecina_id; option.textContent = neighbor.nombre; select.appendChild(option);
            row.appendChild(actions); list.appendChild(row);
        }); pages.show(result.data);
    }).catch(failure);
}
permissions(module.dataset.company).then(function (result) { access = result; editor('neighbor-editor', access.edit); return load(); }).catch(failure);
submitting(form, function () {
    const id = form.elements.id.value;
    const data = {observaciones: form.elements.observaciones.value};
    if (id) { data.version = Number(form.elements.version.value); }
    else if (form.elements.empresa_vecina_id.value) { data.empresa_vecina_id = Number(form.elements.empresa_vecina_id.value); }
    else { data.nueva_identidad = {nombre: form.elements.nombre.value, nit: form.elements.nit.value}; }
    return request(api + (id ? '/' + id : ''), {method: id ? 'PUT' : 'POST', body: data}).then(function () { form.reset(); return load(); });
});
submitting(document.getElementById('resolve-neighbor'), function () {
    return request(api + '/resolver', {method: 'POST', body: {nit: document.getElementById('resolve-nit').value}}).then(function (result) {
        form.reset(); form.elements.empresa_vecina_id.value = result.data.id; notice('Identidad encontrada: ' + result.data.nombre + '. Puedes reutilizarla sin consultar proyectos ajenos.', true);
    });
});
submitting(document.getElementById('neighbor-file-form'), function () {
    const file = document.getElementById('neighbor-file-form');
    return request('/api/v1/proyectos/' + project + '/expedientes', {method: 'POST', body: {codigo_expediente: file.elements.codigo_expediente.value, tipo_expediente: file.elements.tipo_expediente.value, filiacion: 'VECINO', empresa_vecina_id: Number(file.elements.empresa_vecina_id.value)}}).then(function () { file.reset(); notice('Expediente asociado a la vecina. Consulta los expedientes del proyecto.', true); });
});
