import {request} from './http.js';
import {notice, failure, link, submitting} from './resource-ui.js';
const company = document.getElementById('reports-module').dataset.company;
const form = document.getElementById('report-form');
const jobs = document.getElementById('report-jobs');
function track(job) {
    const row = document.createElement('li'); const status = document.createElement('span'); row.appendChild(status); jobs.prepend(row);
    function poll() {
        return request('/api/v1/exportaciones/' + job.id).then(function (result) {
            const data = result.data; status.textContent = 'Reporte ' + data.id + ' · ' + data.formato + ' · ' + data.estado + ' · Caduca: ' + data.expira_en + ' UTC ';
            if (data.estado === 'LISTO') {
                row.appendChild(link('Descargar reporte ' + data.id, '/api/v1/exportaciones/' + data.id + '/archivo'));
            } else if (data.estado === 'PENDIENTE' || data.estado === 'PROCESANDO') { window.setTimeout(poll, 2000); }
            else if (data.estado === 'FALLIDO') { status.textContent += ' Solicita una nueva exportación o revisa tus permisos.'; }
        }).catch(function (error) { status.textContent = 'Reporte ' + job.id + ': ' + error.message; failure(error); });
    }
    poll();
}
request('/api/v1/sesion').then(function (result) { document.getElementById('schedule-link').hidden = !result.data.usuario.es_administrador; notice('No has solicitado reportes en esta página.', false); }).catch(failure);
submitting(form, function () {
    const filters = {};
    ['tipo', 'filiacion', 'titular', 'municipio', 'departamento', 'mineral'].forEach(function (name) { if (form.elements[name].value) { filters[name] = form.elements[name].value; } });
    if (form.elements.codigos.value.trim()) { filters.codigos = form.elements.codigos.value.split(',').map(function (value) { return value.trim(); }); }
    const data = {formato: form.elements.formato.value, filtros: filters};
    if (form.elements.proyecto_id.value) { data.proyecto_id = Number(form.elements.proyecto_id.value); }
    notice('Solicitando reporte…', false);
    return request('/api/v1/empresas/' + company + '/exportaciones', {method: 'POST', body: data}).then(function (result) { track(result.data); notice('Reporte solicitado. El worker generará el archivo.', true); });
});
