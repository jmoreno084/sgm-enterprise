import {request} from './http.js';
import {notice, failure, cell, button, fill, submitting} from './resource-ui.js';
const company = document.getElementById('schedules-module').dataset.company;
const form = document.getElementById('schedule-form');
let currentFilters = {};
form.addEventListener('reset', function () { currentFilters = {}; });
function load() {
    return request('/api/v1/empresas/' + company + '/programaciones').then(function (result) {
        const list = document.getElementById('schedule-list'); list.textContent = '';
        result.data.forEach(function (schedule) {
            const row = document.createElement('tr'); [schedule.id, schedule.formato, schedule.hora_local, schedule.estado, schedule.destinatarios.join(', ')].forEach(function (value) { cell(row, value); });
            const actions = document.createElement('td');
            actions.appendChild(button('Editar ' + schedule.id, function () { currentFilters = Object.assign({}, schedule.filtros); fill(form, Object.assign({}, schedule, {hora_local: schedule.hora_local.slice(0, 5), destinatarios: schedule.destinatarios.join(', '), proyecto_id: schedule.filtros.proyecto_id || ''})); form.elements.formato.focus(); }));
            actions.appendChild(button('Eliminar ' + schedule.id, function () { if (window.confirm('¿Eliminar programación? Si tiene reportes generados, desactívala.')) { request('/api/v1/programaciones/' + schedule.id, {method: 'DELETE', body: {version: schedule.version}}).then(load).catch(failure); } }));
            row.appendChild(actions); list.appendChild(row);
        }); notice(result.data.length ? '' : 'No hay programaciones.', false);
    });
}
request('/api/v1/sesion').then(load).catch(failure);
submitting(form, function () {
    const filters = Object.assign({}, currentFilters); delete filters.proyecto_id;
    if (form.elements.proyecto_id.value) { filters.proyecto_id = Number(form.elements.proyecto_id.value); }
    const data = {formato: form.elements.formato.value, hora_local: form.elements.hora_local.value, estado: form.elements.estado.value, filtros: filters, destinatarios: form.elements.destinatarios.value.trim() ? form.elements.destinatarios.value.split(',').map(function (value) { return Number(value.trim()); }) : []};
    const id = form.elements.id.value; if (id) { data.version = Number(form.elements.version.value); }
    return request(id ? '/api/v1/programaciones/' + id : '/api/v1/empresas/' + company + '/programaciones', {method: id ? 'PUT' : 'POST', body: data}).then(function () { form.reset(); return load(); });
});
