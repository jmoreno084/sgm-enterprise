<?php
declare(strict_types=1);
$title='Visor del proyecto'; $render=function ($escape) use ($page) {
?>
<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css"><link rel="stylesheet" href="/assets/vendor/leaflet-draw/dist/leaflet.draw.css"><link rel="stylesheet" href="/assets/vendor/leaflet-measure/dist/leaflet-measure.css"><link rel="stylesheet" href="/assets/css/map.css">
<nav aria-label="Secciones"><a href="/empresas">Empresas</a> · <a href="/proyectos/<?= $escape($page['id']) ?>">Expedientes</a> · <a href="/proyectos/<?= $escape($page['id']) ?>/vecinas">Vecinas</a></nav>
<h1>Mapa: <?= $escape($page['nombre']) ?></h1>
<div id="map-config" data-project="<?= $escape($page['id']) ?>" data-google-key="<?= $escape($page['google_browser_key']) ?>" data-centroid="<?= $escape(json_encode($page['centroide'])) ?>"></div>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p><p id="basemap-status" role="status" aria-live="polite"></p>
<div class="row"><div class="form-group col-md-4"><label for="map-basemap">Mapa base</label><select id="map-basemap" class="form-control"></select></div>
<div class="col-md-8"><form id="map-filters" class="form-inline"><label for="map-type" class="mr-2">Tipo</label><select id="map-type" name="tipo" class="form-control mr-2"><option value="">Todos</option><option>TITULO</option><option>SOLICITUD</option></select><label for="map-affiliation" class="mr-2">Filiación</label><select id="map-affiliation" name="filiacion" class="form-control mr-2"><option value="">Todas</option><option>PROPIO</option><option>VECINO</option></select><button class="btn btn-secondary" type="submit">Aplicar</button></form></div></div>
<div id="map" tabindex="0" role="region" aria-label="Mapa interactivo del proyecto"></div>
<section aria-labelledby="legend-title" class="my-3"><h2 id="legend-title">Leyenda</h2><ul id="map-legend"><li>Títulos propios: azul #007bff</li><li>Solicitudes propias: verde #28a745</li><li>Vecinas: violeta asignado; rayado desde la novena.</li></ul></section>
<section aria-labelledby="tools-title"><h2 id="tools-title">Mediciones y trazos de trabajo</h2><p>Las mediciones son auxiliares. El área oficial se consulta en la identificación del expediente.</p><p id="measure-status" role="status" aria-live="polite"></p><button id="visual-centroid" type="button" class="btn btn-secondary">Centroide visual del último trazo</button><p id="official-centroid"></p></section>
<section class="my-3" aria-labelledby="import-title"><h2 id="import-title">Capas temporales de trabajo</h2>
<form id="map-import-form" class="row"><div class="form-group col-md-6"><label for="map-import-file">Shapefile ZIP, GeoJSON o KML (hasta 20 MiB)</label><input id="map-import-file" name="archivo" type="file" accept=".zip,.geojson,.json,.kml" required class="form-control-file"></div><div class="col-md-6"><button type="submit" class="btn btn-primary">Importar capa</button></div></form><ul id="imported-layers"></ul></section>
<section aria-labelledby="map-files-title"><h2 id="map-files-title">Expedientes del mapa</h2><p id="map-list-status"></p><ul id="map-files"></ul><button id="map-more-files" type="button" class="btn btn-secondary" hidden>Ver más expedientes</button></section>
<?php require __DIR__.'/identify.php'; ?>
<script src="/assets/vendor/leaflet/leaflet.js"></script><script src="/assets/vendor/leaflet-draw/dist/leaflet.draw.js"></script><script src="/assets/vendor/leaflet-measure/dist/leaflet-measure.es.js"></script><script src="/assets/vendor/leaflet.gridlayer.googlemutant/dist/Leaflet.GoogleMutant.js"></script><script src="/assets/vendor/esri-leaflet/dist/esri-leaflet.js"></script><script src="/assets/vendor/turf-turf/turf.min.js"></script><script type="module" src="/assets/js/modules/map.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
