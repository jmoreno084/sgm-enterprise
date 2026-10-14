<?php
declare(strict_types=1);
$title='Auditoría'; $render=function ($escape) use ($page) {
?>
<nav aria-label="Secciones"><a href="/">Inicio</a> · <a href="/empresas">Empresas</a></nav>
<h1>Auditoría <?= $page['company']?'empresarial':'global' ?></h1><p>Historial de solo lectura, fechas en UTC.</p><div id="audit-module" data-company="<?= $escape($page['company']) ?>"></div>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<form id="audit-search" class="row">
<?php foreach (array('fecha_desde'=>'Desde','fecha_hasta'=>'Hasta','entidad'=>'Entidad','usuario_id'=>'ID de usuario') as $key=>$label) { ?>
<div class="form-group col-md-3"><label for="audit-<?= $escape($key) ?>"><?= $escape($label) ?></label><input id="audit-<?= $escape($key) ?>" name="<?= $escape($key) ?>" type="<?= strpos($key,'fecha_')===0?'date':($key==='usuario_id'?'number':'text') ?>" class="form-control" maxlength="100" <?= $key==='usuario_id'?'min="1"':'' ?>></div>
<?php } ?>
<div class="form-group col-md-3"><label for="audit-action">Acción</label><select id="audit-action" name="accion" class="form-control"><option value="">Todas</option><option>INSERT</option><option>UPDATE</option><option>DELETE</option><option>LOGIN</option><option>LOGOUT</option></select></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Buscar</button></div></form>
<div class="table-responsive mt-3"><table class="table"><caption>Eventos autorizados</caption><thead><tr><th>Fecha UTC</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th></tr></thead><tbody id="audit-list"></tbody></table></div>
<button id="previous-page" class="btn btn-secondary" type="button">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" class="btn btn-secondary" type="button">Siguiente</button>
<script type="module" src="/assets/js/modules/audit.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
