export function tools(map) {
    const L = window.L; const turf = window.turf;
    const work = new L.FeatureGroup().addTo(map); let last = null;
    map.addControl(new L.Control.Draw({draw: {polyline: true, polygon: true, rectangle: false, circle: false, marker: true, circlemarker: false}, edit: {featureGroup: work}}));
    map.addControl(new L.Control.Measure({primaryLengthUnit: 'meters', secondaryLengthUnit: 'kilometers', primaryAreaUnit: 'sqmeters', secondaryAreaUnit: 'hectares', units: {hectares: {factor: 0.0001, display: 'ha', decimals: 3}}}));
    function describe(layer) {
        const feature = layer.toGeoJSON(); const status = document.getElementById('measure-status');
        if (feature.geometry.type === 'Polygon') { const area = turf.area(feature); status.textContent = 'Medición auxiliar: ' + area.toFixed(2) + ' m² · ' + (area / 10000).toFixed(4) + ' ha · ' + (area / 1000000).toFixed(6) + ' km²'; }
        else if (feature.geometry.type === 'LineString') { const km = turf.length(feature, {units: 'kilometers'}); status.textContent = 'Medición auxiliar: ' + (km * 1000).toFixed(2) + ' m · ' + km.toFixed(4) + ' km'; }
        else { status.textContent = 'Marcador de trabajo: ' + feature.geometry.coordinates.join(', '); }
    }
    map.on(L.Draw.Event.CREATED, function (event) { work.addLayer(event.layer); last = event.layer; describe(last); });
    map.on(L.Draw.Event.EDITED, function (event) { event.layers.eachLayer(function (layer) { last = layer; describe(layer); }); });
    document.getElementById('visual-centroid').addEventListener('click', function () {
        if (!last || !work.hasLayer(last)) { document.getElementById('measure-status').textContent = 'Dibuja un trazo de trabajo antes de calcular su centroide visual.'; return; }
        const point = turf.centroid(last.toGeoJSON()); const marker = L.marker([point.geometry.coordinates[1], point.geometry.coordinates[0]]).addTo(work);
        const label = document.createElement('span'); label.textContent = 'Centroide visual auxiliar del trabajo'; marker.bindTooltip(label).openTooltip();
        document.getElementById('measure-status').textContent = 'Centroide visual auxiliar. El centroide oficial del proyecto se conserva.';
    });
    return work;
}
