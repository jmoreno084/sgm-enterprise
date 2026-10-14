<?php
declare(strict_types=1);
$title='Vecinas del proyecto'; $render=function ($escape) use ($page) {
?>
<nav aria-label="Secciones"><a href="/empresas">Empresas</a> · <a href="/proyectos/<?= $escape($page['id']) ?>">Expedientes del proyecto</a></nav>
<h1>Vecinas: <?= $escape($page['nombre']) ?></h1><div id="neighbor-module" data-project="<?= $escape($page['id']) ?>" data-company="<?= $escape($page['empresa_id']) ?>"></div>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<fieldset id="neighbor-editor" hidden disabled><legend>Identidad compartida y vínculo del proyecto</legend>
<form id="resolve-neighbor" class="form-inline mb-3"><label for="resolve-nit" class="mr-2">Buscar NIT exacto</label><input id="resolve-nit" name="nit" maxlength="50" required class="form-control mr-2"><button class="btn btn-secondary" type="submit">Buscar identidad</button></form>
<form id="neighbor-form" class="row"><input name="id" type="hidden"><input name="version" type="hidden">
<div class="form-group col-md-4"><label for="neighbor-existing">ID de identidad existente</label><input id="neighbor-existing" name="empresa_vecina_id" type="number" min="1" class="form-control"><small>Usa una identidad existente o completa los datos de una nueva.</small></div>
<div class="form-group col-md-4"><label for="neighbor-name">Nombre de nueva identidad</label><input id="neighbor-name" name="nombre" maxlength="255" class="form-control"></div>
<div class="form-group col-md-4"><label for="neighbor-nit">NIT de nueva identidad (opcional)</label><input id="neighbor-nit" name="nit" maxlength="50" class="form-control"></div>
<div class="form-group col-12"><label for="neighbor-notes">Notas exclusivas de este proyecto</label><textarea id="neighbor-notes" name="observaciones" maxlength="100000" class="form-control"></textarea></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Guardar vínculo</button><button class="btn btn-secondary ml-2" type="reset">Nuevo vínculo</button></div></form>
<form id="neighbor-file-form" class="row mt-3"><div class="form-group col-md-4"><label for="neighbor-file-identity">Vecina de esta página</label><select id="neighbor-file-identity" name="empresa_vecina_id" required class="form-control"></select></div>
<div class="form-group col-md-4"><label for="neighbor-file-code">Código oficial</label><input id="neighbor-file-code" name="codigo_expediente" maxlength="100" required class="form-control"></div>
<div class="form-group col-md-4"><label for="neighbor-file-type">Tipo</label><select id="neighbor-file-type" name="tipo_expediente" class="form-control"><option>TITULO</option><option>SOLICITUD</option></select></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Asociar a vecina</button></div></form>
</fieldset>
<div class="table-responsive mt-3"><table class="table"><caption>Vínculos y estilos del proyecto</caption><thead><tr><th>Nombre</th><th>NIT</th><th>Orden</th><th>Color</th><th>Patrón</th><th>Notas</th><th>Acciones</th></tr></thead><tbody id="neighbor-list"></tbody></table></div>
<button id="previous-page" type="button" class="btn btn-secondary">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" type="button" class="btn btn-secondary">Siguiente</button>
<script type="module" src="/assets/js/modules/project-neighbors.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
