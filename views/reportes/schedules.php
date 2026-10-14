<?php
declare(strict_types=1);
$title='Programación de reportes'; $render=function ($escape) use ($page) {
?>
<nav aria-label="Secciones"><a href="/">Inicio</a> · <a href="/empresas/<?= $escape($page['id']) ?>/reportes">Reportes</a></nav>
<h1>Programación de reportes</h1><div id="schedules-module" data-company="<?= $escape($page['id']) ?>"></div>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<form id="schedule-form"><input name="id" type="hidden"><input name="version" type="hidden">
<label for="schedule-format">Formato</label><select id="schedule-format" name="formato" class="form-control"><?php foreach (array('PDF','XLSX','CSV','GEOJSON','KML','SHAPEFILE') as $format) { ?><option><?= $escape($format) ?></option><?php } ?></select>
<label for="schedule-hour">Hora diaria en America/Bogota</label><input id="schedule-hour" name="hora_local" type="time" value="09:00" required class="form-control">
<label for="schedule-project">ID del proyecto (opcional)</label><input id="schedule-project" name="proyecto_id" type="number" min="1" class="form-control">
<label for="schedule-recipients">IDs de destinatarios separados por coma</label><input id="schedule-recipients" name="destinatarios" maxlength="10000" class="form-control" aria-describedby="recipient-help"><p id="recipient-help">Deben ser usuarios activos autorizados para exportar en esta empresa. No se aceptan direcciones arbitrarias.</p>
<label for="schedule-state">Estado</label><select id="schedule-state" name="estado" class="form-control"><option>INACTIVO</option><option>ACTIVO</option></select>
<button type="submit" class="btn btn-primary my-3">Guardar programación</button><button type="reset" class="btn btn-secondary">Nueva programación</button></form>
<div class="table-responsive"><table class="table"><caption>Programaciones diarias</caption><thead><tr><th>ID</th><th>Formato</th><th>Hora Bogotá</th><th>Estado</th><th>Destinatarios</th><th>Acciones</th></tr></thead><tbody id="schedule-list"></tbody></table></div>
<script type="module" src="/assets/js/modules/schedules.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
