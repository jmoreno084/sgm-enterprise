<?php
declare(strict_types=1);
$title='Reportes'; $render=function ($escape) use ($page) {
?>
<nav aria-label="Secciones"><a href="/">Inicio</a> · <a href="/empresas">Empresas</a></nav>
<h1>Reportes</h1><div id="reports-module" data-company="<?= $escape($page['id']) ?>"></div>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<form id="report-form">
<label for="report-format">Formato</label><select id="report-format" name="formato" class="form-control"><?php foreach (array('PDF','XLSX','CSV','GEOJSON','KML','SHAPEFILE') as $format) { ?><option><?= $escape($format) ?></option><?php } ?></select>
<label for="report-project">ID del proyecto (opcional)</label><input id="report-project" name="proyecto_id" type="number" min="1" class="form-control">
<label for="report-codes">Códigos separados por coma (opcional)</label><input id="report-codes" name="codigos" class="form-control" maxlength="10000">
<label for="report-affiliation">Filiación</label><select id="report-affiliation" name="filiacion" class="form-control"><option value="">Todas</option><option>PROPIO</option><option>VECINO</option></select>
<label for="report-type">Tipo</label><select id="report-type" name="tipo" class="form-control"><option value="">Todos</option><option>TITULO</option><option>SOLICITUD</option></select>
<?php foreach (array('titular'=>'Titular','municipio'=>'Municipio','departamento'=>'Departamento','mineral'=>'Mineral') as $key=>$label) { ?><label for="report-<?= $escape($key) ?>"><?= $escape($label) ?></label><input id="report-<?= $escape($key) ?>" name="<?= $escape($key) ?>" maxlength="255" class="form-control"><?php } ?>
<button type="submit" class="btn btn-primary my-3">Solicitar reporte</button></form>
<p>Los archivos caducan a las 24 horas. Las fuentes ausentes se indican sin inventar geometrías.</p>
<ul id="report-jobs" aria-label="Reportes solicitados"></ul><a id="schedule-link" hidden href="/empresas/<?= $escape($page['id']) ?>/programaciones">Programación de reportes</a>
<script type="module" src="/assets/js/modules/reports.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
