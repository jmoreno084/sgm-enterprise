<?php
declare(strict_types=1);
$title='Expedientes del proyecto'; $render=function ($escape) use ($page) {
?>
<nav aria-label="Secciones"><a href="/empresas">Empresas</a> · <a href="/empresas/<?= $escape($page['empresa_id']) ?>/proyectos">Proyectos</a></nav>
<h1><?= $escape($page['nombre']) ?></h1><div id="file-module" data-project="<?= $escape($page['id']) ?>" data-company="<?= $escape($page['empresa_id']) ?>"></div>
<p><a href="/proyectos/<?= $escape($page['id']) ?>/vecinas">Empresas vecinas del proyecto</a></p>
<p><a href="/proyectos/<?= $escape($page['id']) ?>/mapa">Abrir mapa del proyecto</a></p>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p><p id="centroid-status"></p>
<fieldset id="file-editor" disabled hidden><legend>Asociar expediente propio</legend>
<form id="file-form" class="row"><div class="form-group col-md-6"><label for="file-code">Código oficial</label><input id="file-code" name="codigo_expediente" required maxlength="100" class="form-control"></div>
<div class="form-group col-md-6"><label for="file-type">Tipo</label><select id="file-type" name="tipo_expediente" class="form-control"><option value="TITULO">Título</option><option value="SOLICITUD">Solicitud</option></select></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Asociar como propio</button></div></form></fieldset>
<div class="table-responsive mt-3"><table class="table"><caption>Expedientes vinculados</caption><thead><tr><th>Código</th><th>Tipo</th><th>Filiación</th><th>Fuente</th><th>Acciones</th></tr></thead><tbody id="file-list"></tbody></table></div>
<button id="previous-page" class="btn btn-secondary" type="button">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" class="btn btn-secondary" type="button">Siguiente</button>
<script type="module" src="/assets/js/modules/project-files.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
