<?php
declare(strict_types=1);
$title='Empresas'; $render=function ($escape) {
?>
<nav aria-label="Secciones"><a href="/">Inicio</a> · <a href="/usuarios">Usuarios</a> · <a href="/catastro">Consulta catastral</a></nav>
<h1>Empresas</h1><p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<?php require __DIR__.'/form.php'; ?>
<form id="company-search" class="form-inline my-3"><label for="company-filter" class="mr-2">Razón social</label><input id="company-filter" name="razon_social" type="search" class="form-control mr-2"><button class="btn btn-secondary" type="submit">Buscar</button></form>
<div class="table-responsive"><table class="table"><caption>Empresas autorizadas</caption><thead><tr><th>NIT</th><th>Razón social</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="company-list"></tbody></table></div>
<button id="previous-page" class="btn btn-secondary" type="button">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" class="btn btn-secondary" type="button">Siguiente</button>
<script type="module" src="/assets/js/modules/companies.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
