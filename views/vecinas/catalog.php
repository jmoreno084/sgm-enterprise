<?php
declare(strict_types=1);
$title='Identidades vecinas'; $render=function ($escape) {
?>
<nav aria-label="Secciones"><a href="/">Inicio</a> · <a href="/empresas">Empresas</a></nav>
<h1>Catálogo de identidades vecinas</h1><p>Los cambios de nombre, NIT y observaciones globales afectan a todos los proyectos que reutilicen la identidad. Las notas de cada proyecto se editan desde su vínculo.</p>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<form id="identity-form" class="row"><input name="id" type="hidden"><input name="version" type="hidden">
<?php foreach (array('nombre'=>'Nombre','nit'=>'NIT','observaciones'=>'Observaciones globales') as $key=>$label) { ?>
<div class="form-group col-md-4"><label for="identity-<?= $escape($key) ?>"><?= $escape($label) ?></label><input id="identity-<?= $escape($key) ?>" name="<?= $escape($key) ?>" class="form-control" maxlength="<?= $key==='nit'?'50':($key==='nombre'?'255':'100000') ?>" <?= $key==='nombre'?'required':'' ?>></div>
<?php } ?>
<div class="col-12"><button class="btn btn-primary" type="submit">Guardar identidad seleccionada</button></div></form>
<form id="identity-search" class="form-inline my-3"><label for="identity-filter" class="mr-2">Nombre</label><input id="identity-filter" name="nombre" class="form-control mr-2" type="search"><button class="btn btn-secondary" type="submit">Buscar</button></form>
<div class="table-responsive"><table class="table"><caption>Identidades compartidas</caption><thead><tr><th>Nombre</th><th>NIT</th><th>Observaciones globales</th><th>Acciones</th></tr></thead><tbody id="identity-list"></tbody></table></div>
<button id="previous-page" type="button" class="btn btn-secondary">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" type="button" class="btn btn-secondary">Siguiente</button>
<script type="module" src="/assets/js/modules/neighbors.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
