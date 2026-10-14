import {request} from './http.js';
export function identify(project, id) {
    const panel = document.getElementById('identify-panel'); panel.hidden = false;
    const status = document.getElementById('identify-status'); status.textContent = 'Consultando atributos…';
    return request('/api/v1/proyectos/' + project + '/identify/' + id).then(function (result) {
        const data = result.data;
        status.textContent = data.fuente_disponible ? 'Fuente disponible.' : 'Fuente retirada: se conserva el vínculo histórico, sin inventar atributos.';
        document.getElementById('identify-affiliation').textContent = data.vinculo.filiacion === 'PROPIO' ? 'Proyecto: ' + data.proyecto_nombre : 'Vecina: ' + data.empresa_vecina_nombre;
        document.getElementById('identify-area').textContent = data.area_ha === null ? 'Área no disponible.' : 'Área: ' + data.area_ha + ' ha · ' + data.area_origen;
        const list = document.getElementById('identify-attributes'); list.textContent = '';
        Object.keys(data.atributos).forEach(function (key) {
            const term = document.createElement('dt'); term.className = 'col-md-4'; term.textContent = key;
            const value = document.createElement('dd'); value.className = 'col-md-8'; const item = data.atributos[key];
            value.textContent = item === null ? 'No disponible (NULL)' : (typeof item === 'object' ? JSON.stringify(item) : String(item));
            list.appendChild(term); list.appendChild(value);
        }); document.getElementById('identify-title').focus();
    }).catch(function (error) { status.textContent = error.message; document.getElementById('identify-title').focus(); });
}
