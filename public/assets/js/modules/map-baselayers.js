export function bases(map, key) {
    const L = window.L;
    const select = document.getElementById('map-basemap');
    const status = document.getElementById('basemap-status');
    const layers = [
        {name: 'OpenStreetMap', create: function () { return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}); }},
        {name: 'ESRI Satelital', create: function () { return L.esri.basemapLayer('Imagery'); }},
        {name: 'ESRI Topográfica', create: function () { return L.esri.basemapLayer('Topographic'); }}
    ];
    ['roadmap','satellite','hybrid','terrain'].forEach(function (type, index) {
        layers.push({name: ['Google Calles','Google Satélite','Google Híbrido','Google Terreno'][index], google: true, create: function () { return L.gridLayer.googleMutant({type: type, maxZoom: 20}); }});
    });
    layers.push({name: 'Sin fondo (consulta de atributos)', create: function () { return L.layerGroup(); }});
    let current = null;
    layers.forEach(function (layer, index) {
        const option = document.createElement('option'); option.value = index; option.textContent = layer.name;
        if (layer.google) { option.disabled = true; option.textContent += key ? ' (cargando)' : ' (sin configurar)'; }
        select.appendChild(option);
    });
    function activate(index) {
        if (current) { map.removeLayer(current); }
        const layer = layers[index]; current = layer.create(); current.addTo(map); status.textContent = '';
        current.on('tileerror', function () {
            status.textContent = 'El proveedor de fondo no está disponible. Los expedientes y sus atributos siguen consultables.';
        });
    }
    select.addEventListener('change', function () { activate(Number(select.value)); });
    activate(0);
    if (key) {
        window.sgpmGoogleReady = function () {
            layers.forEach(function (layer, index) { if (layer.google) { select.options[index].disabled = false; select.options[index].textContent = layer.name; } });
        };
        const script = document.createElement('script'); script.async = true;
        script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&callback=sgpmGoogleReady';
        script.onerror = function () { status.textContent = 'Google Maps no está disponible. Puedes usar otra base y consultar los atributos.'; };
        document.head.appendChild(script);
    }
}
