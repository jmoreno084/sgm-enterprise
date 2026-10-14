import {request} from './http.js';
import {notice, failure} from './resource-ui.js';
import {bases} from './map-baselayers.js';
import {styles} from './map-styles.js';
import {identify} from './map-identify.js';
import {tools} from './map-tools.js';
import {imports} from './map-imports.js';
const config = document.getElementById('map-config'); const project = Number(config.dataset.project);
const map = window.L.map('map', {preferCanvas: true}).setView([4.61,-74.1],11);
const metrics = {complete: false, rendered: 0, vertices: 0, source_total: 0, tooltip_ms: [], load_ms: null, first_hover_point: null};
window.sgpmMapMetrics = metrics;
bases(map, config.dataset.googleKey); tools(map); imports(map, project);
const centroid = JSON.parse(config.dataset.centroid);
if (centroid) { window.L.circleMarker([centroid.coordinates[1],centroid.coordinates[0]], {radius: 6, color: '#212529'}).addTo(map); }
document.getElementById('official-centroid').textContent = centroid ? 'Centroide oficial persistido: ' + centroid.coordinates.join(', ') : 'No hay centroide oficial disponible.';
const official = window.L.featureGroup().addTo(map);
const draw = styles(map, function (id) { identify(project, id); }, metrics);
const form = document.getElementById('map-filters'); let busy = false; let records = []; let listed = 0;
let legendKeys = {}; let firstCoordinate = null;
function inspectCoordinates(coordinates, bounds) {
    if (!Array.isArray(coordinates) || !coordinates.length) { return 0; }
    if (typeof coordinates[0] === 'number') {
        bounds.extend([coordinates[1], coordinates[0]]);
        return 1;
    }
    return coordinates.reduce(function (sum, value) { return sum + inspectCoordinates(value, bounds); }, 0);
}
function listMore() {
    const list = document.getElementById('map-files'); const limit = Math.min(listed + 100, records.length);
    for (; listed < limit; listed += 1) { const record = records[listed]; const li = document.createElement('li'); const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-link'; button.textContent = record.tooltip; button.addEventListener('click', function () { identify(project, record.vinculo_id); }); li.appendChild(button); list.appendChild(li); }
    document.getElementById('map-list-status').textContent = 'Mostrando ' + listed + ' de ' + records.length + ' expedientes dibujados.';
    document.getElementById('map-more-files').hidden = listed >= records.length;
}
document.getElementById('map-more-files').addEventListener('click', listMore);
function batch(features, bounds) {
    return new Promise(function (resolve) {
        let index = 0;
        function step() {
            const limit = Math.min(index + 1000, features.length);
            for (; index < limit; index += 1) {
                const feature = features[index]; official.addLayer(draw(feature)); records.push(feature.properties); metrics.rendered += 1;
                metrics.vertices += inspectCoordinates(feature.geometry.coordinates, bounds);
                if (!firstCoordinate && feature.geometry.type === 'MultiPolygon') { firstCoordinate = feature.geometry.coordinates[0][0][0]; }
                const properties = feature.properties;
                if (properties.filiacion === 'VECINO' && !legendKeys[properties.empresa_vecina_id]) {
                    const item = document.createElement('li'); item.dataset.neighbor = properties.empresa_vecina_id;
                    const swatch = document.createElement('span'); swatch.className = 'map-color'; swatch.style.backgroundColor = properties.color_hex;
                    if (properties.patron_indice) { swatch.style.backgroundImage = 'repeating-linear-gradient(45deg, transparent, transparent 4px, #212529 4px, #212529 5px)'; }
                    item.appendChild(swatch); item.appendChild(document.createTextNode(properties.empresa_vecina_nombre + ' · ' + properties.color_hex + ' · patrón ' + properties.patron_indice));
                    document.getElementById('map-legend').appendChild(item); legendKeys[properties.empresa_vecina_id] = true;
                }
            }
            if (index < features.length) { requestAnimationFrame(step); } else { requestAnimationFrame(resolve); }
        } step();
    });
}
function load() {
    if (busy) { return; } busy = true; const submit = form.querySelector('button'); submit.disabled = true;
    official.clearLayers(); records = []; listed = 0; document.getElementById('map-files').textContent = '';
    legendKeys = {}; Array.prototype.forEach.call(document.querySelectorAll('#map-legend [data-neighbor]'), function (node) { node.parentNode.removeChild(node); });
    metrics.complete = false; metrics.rendered = 0; metrics.vertices = 0; metrics.tooltip_ms = []; metrics.first_hover_point = null; firstCoordinate = null; const bounds = window.L.latLngBounds([]); const start = performance.now(); let missing = 0;
    notice('Cargando geometrías…', false);
    const filterQuery = new URLSearchParams(new FormData(form)).toString();
    const pending = {};
    function fetchPage(number) {
        if (!pending[number]) {
            const query = new URLSearchParams(filterQuery); query.set('page', number); query.set('page_size', 1000);
            // Resolve errors as values until consumed, avoiding an unhandled
            // rejection in a prefetched page while the prior page is drawing.
            pending[number] = request('/api/v1/proyectos/' + project + '/mapa?' + query.toString()).then(function (result) { return {result: result}; }, function (error) { return {error: error}; });
        }
        return pending[number];
    }
    function page(number) {
        return fetchPage(number).then(function (response) {
            delete pending[number];
            if (response.error) { throw response.error; }
            const data = response.result.data; metrics.source_total = data.meta.total; missing += data.meta.fuentes_no_disponibles.length;
            const last = Math.ceil(data.meta.total / data.meta.page_size);
            if (data.meta.next_page) {
                for (let next = data.meta.next_page; next <= Math.min(data.meta.next_page + 3, last); next += 1) { fetchPage(next); }
            }
            return batch(data.features, bounds).then(function () { if (data.meta.next_page) { return page(data.meta.next_page); } return null; });
        });
    }
    page(1).then(function () {
        if (bounds.isValid()) { map.fitBounds(bounds, {padding: [12,12], animate: false}); }
        return new Promise(function (resolve) { requestAnimationFrame(function () { requestAnimationFrame(resolve); }); });
    }).then(function () {
        if (firstCoordinate) { const point = map.latLngToContainerPoint([firstCoordinate[1], firstCoordinate[0]]); metrics.first_hover_point = {x: point.x, y: point.y}; }
        metrics.complete = true; metrics.load_ms = performance.now() - start; listMore();
        notice('Carga completa: ' + metrics.rendered + ' geometrías. ' + (missing ? missing + ' fuentes retiradas; se conservan sus vínculos históricos.' : ''), false);
    }).catch(function (error) { notice('Carga incompleta: ' + error.message, true); listMore(); }).then(function () { busy = false; submit.disabled = false; });
}
request('/api/v1/sesion').then(load).catch(failure);
form.addEventListener('submit', function (event) { event.preventDefault(); load(); });
