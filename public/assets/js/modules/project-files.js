import {request} from './http.js';
import {notice, failure, button, cell, editor, paging, permissions, submitting} from './resource-ui.js';
const module = document.getElementById('file-module');
const project = Number(module.dataset.project);
const form = document.getElementById('file-form');
let access = {edit: false};
const pages = paging(load);
function load() {
    notice('Cargando expedientes…', false);
    return request('/api/v1/proyectos/' + project + '/expedientes?page=' + pages.current()).then(function (result) {
        const list = document.getElementById('file-list'); list.textContent = '';
        result.data.items.forEach(function (file) {
            const row = document.createElement('tr'); cell(row, file.codigo_expediente); cell(row, file.tipo_expediente); cell(row, file.filiacion); cell(row, file.fuente_disponible ? 'Disponible' : 'Fuente retirada');
            const actions = document.createElement('td');
            if (access.edit) { actions.appendChild(button('Desvincular', function () {
                if (window.confirm('¿Desvincular el expediente? La fuente oficial se conserva.')) {
                    request('/api/v1/proyectos/' + project + '/expedientes/' + file.id, {method: 'DELETE', body: {version: file.version}}).then(load).catch(failure);
                }
            })); }
            row.appendChild(actions); list.appendChild(row);
        }); pages.show(result.data);
        return request('/api/v1/proyectos/' + project);
    }).then(function (result) {
        document.getElementById('centroid-status').textContent = result.data.centroide ? 'Centroide calculado en el servidor a partir de expedientes propios.' : 'Centroide no disponible: no hay geometrías propias disponibles.';
    }).catch(failure);
}
permissions(module.dataset.company).then(function (result) { access = result; editor('file-editor', access.edit); return load(); }).catch(failure);
submitting(form, function () {
    return request('/api/v1/proyectos/' + project + '/expedientes', {method: 'POST', body: {codigo_expediente: form.elements.codigo_expediente.value, tipo_expediente: form.elements.tipo_expediente.value, filiacion: 'PROPIO'}}).then(function () { form.reset(); return load(); });
});
