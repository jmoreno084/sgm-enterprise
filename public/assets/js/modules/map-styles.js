export function styles(map, identify, metrics) {
    const L = window.L;
    const svg = L.svg().addTo(map);
    const canvas = L.canvas();
    const namespace = 'http://www.w3.org/2000/svg';
    const defs = document.createElementNS(namespace, 'defs'); svg._container.appendChild(defs);
    const patterns = {};
    function pattern(properties) {
        const id = 'sgpm-pattern-' + properties.proyecto_id + '-' + properties.empresa_vecina_id + '-' + properties.patron_indice;
        if (!patterns[id]) {
            const spacing = 6 + (properties.patron_indice % 4) * 2;
            const node = document.createElementNS(namespace, 'pattern'); node.id = id;
            node.setAttribute('patternUnits', 'userSpaceOnUse'); node.setAttribute('width', spacing); node.setAttribute('height', spacing); node.setAttribute('patternTransform', 'rotate(45)');
            const rect = document.createElementNS(namespace, 'rect'); rect.setAttribute('width', spacing); rect.setAttribute('height', spacing); rect.setAttribute('fill', properties.color_hex); node.appendChild(rect);
            const line = document.createElementNS(namespace, 'line'); line.setAttribute('x1', '0'); line.setAttribute('x2', '0'); line.setAttribute('y1', '0'); line.setAttribute('y2', spacing); line.setAttribute('stroke', '#212529'); line.setAttribute('stroke-width', 1 + ((properties.patron_indice - 1) % 3)); node.appendChild(line);
            defs.appendChild(node); patterns[id] = true;
        }
        return id;
    }
    return function (feature) {
        const properties = feature.properties;
        const depth = feature.geometry.type === 'MultiPolygon' ? 2 : 1;
        const coordinates = L.GeoJSON.coordsToLatLngs(feature.geometry.coordinates, depth);
        const layer = L.polygon(coordinates, {color: properties.color_hex, fillColor: properties.color_hex, fillOpacity: 0.4, weight: 2, renderer: properties.patron_indice ? svg : canvas});
        let entered = 0;
        layer.on('mouseover', function () { entered = performance.now(); });
        layer.bindTooltip(function () { const tooltip = document.createElement('span'); tooltip.textContent = properties.tooltip; return tooltip; }, {sticky: true});
        layer.on('tooltipopen', function () { if (entered) { metrics.tooltip_ms.push(performance.now() - entered); } });
        layer.on('click', function () { identify(properties.vinculo_id); });
        if (properties.patron_indice) { layer.on('add', function () { const element = layer.getElement(); if (element) { element.setAttribute('fill', 'url(#' + pattern(properties) + ')'); } }); }
        return layer;
    };
}
