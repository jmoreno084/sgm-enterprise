<?php
declare(strict_types=1);
$title='Proyectos'; $render=function ($escape) use ($page) {
?>
<nav aria-label="Secciones"><a href="/">Inicio</a> · <a href="/empresas">Empresas</a></nav>
<h1>Proyectos</h1><div id="project-module" data-company="<?= $escape($page['empresa_id']) ?>"></div>
<p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<?php require __DIR__.'/form.php'; ?>
<form id="project-search" class="form-inline my-3"><label for="project-filter" class="mr-2">Nombre</label><input id="project-filter" name="nombre" type="search" class="form-control mr-2"><button class="btn btn-secondary" type="submit">Buscar</button></form>
<div class="table-responsive"><table class="table"><caption>Proyectos de la empresa</caption><thead><tr><th>Nombre</th><th>Mineral</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="project-list"></tbody></table></div>
<button id="previous-page" class="btn btn-secondary" type="button">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" class="btn btn-secondary" type="button">Siguiente</button>
<script type="module" src="/assets/js/modules/projects.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
