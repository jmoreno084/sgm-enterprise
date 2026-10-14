import {request} from './http.js';
import {failure, submitting} from './resource-ui.js';
export function imports(map, project) {
    const form = document.getElementById('map-import-form');
    submitting(form, function () {
        return request('/api/v1/proyectos/' + project + '/capas/importar', {method: 'POST', body: new FormData(form)}).then(function (result) {
            const data = result.data; const layer = window.L.geoJSON(data, {style: {color: '#ff6600', fillColor: '#ff6600', fillOpacity: 0.6}, pointToLayer: function (feature, latlng) { return window.L.circleMarker(latlng, {color: '#ff6600', fillColor: '#ff6600', fillOpacity: 0.6}); }, onEachFeature: function (feature, item) {
                const label = document.createElement('span'); label.textContent = data.nombre_capa; item.bindTooltip(label);
            }}).addTo(map);
            const item = document.createElement('li'); const label = document.createElement('span'); label.textContent = data.nombre_capa + ' · ' + data.features.length + ' objetos '; item.appendChild(label);
            const colorLabel = document.createElement('label'); colorLabel.textContent = 'Color '; const color = document.createElement('input'); color.type = 'color'; color.value = '#ff6600'; colorLabel.appendChild(color); item.appendChild(colorLabel);
            const opacityLabel = document.createElement('label'); opacityLabel.textContent = 'Opacidad '; const opacity = document.createElement('input'); opacity.type = 'range'; opacity.min = '0'; opacity.max = '1'; opacity.step = '0.1'; opacity.value = '0.6'; opacityLabel.appendChild(opacity); item.appendChild(opacityLabel);
            function update() { layer.setStyle({color: color.value, fillColor: color.value, opacity: Number(opacity.value), fillOpacity: Number(opacity.value)}); }
            color.addEventListener('input', update); opacity.addEventListener('input', update);
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn btn-sm btn-secondary'; remove.textContent = 'Retirar capa'; remove.addEventListener('click', function () { map.removeLayer(layer); item.parentNode.removeChild(item); }); item.appendChild(remove);
            document.getElementById('imported-layers').appendChild(item); if (layer.getBounds().isValid()) { map.fitBounds(layer.getBounds()); } form.reset();
        }).catch(failure);
    });
}
