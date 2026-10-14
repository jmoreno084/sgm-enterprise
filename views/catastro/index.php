<?php
declare(strict_types=1);
$title='Consulta catastral'; $render=function ($escape) {
?>
<nav aria-label="Secciones"><a href="/">Inicio</a> · <a href="/empresas">Empresas</a></nav>
<h1>Consulta catastral</h1><p>Información oficial de solo lectura.</p><p id="module-status" role="status" tabindex="-1" aria-live="polite"></p>
<form id="cadastre-search" class="row">
<div class="form-group col-md-4"><label for="cadastre-type">Tipo</label><select id="cadastre-type" name="tipo" class="form-control"><option value="">Ambos</option><option>TITULO</option><option>SOLICITUD</option></select></div>
<?php foreach (array('codigo'=>'Código','titular'=>'Titular','municipio'=>'Municipio','departamento'=>'Departamento','mineral'=>'Mineral') as $key=>$label) { ?>
<div class="form-group col-md-4"><label for="cadastre-<?= $escape($key) ?>"><?= $escape($label) ?></label><input id="cadastre-<?= $escape($key) ?>" name="<?= $escape($key) ?>" type="search" maxlength="255" class="form-control"></div>
<?php } ?>
<div class="col-12"><button class="btn btn-primary" type="submit">Buscar</button></div></form>
<div class="table-responsive mt-3"><table class="table"><caption>Resultados oficiales</caption><thead><tr><th>Código</th><th>Tipo</th><th>Titular</th><th>Municipios</th><th>Acciones</th></tr></thead><tbody id="cadastre-list"></tbody></table></div>
<button id="previous-page" type="button" class="btn btn-secondary">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" type="button" class="btn btn-secondary">Siguiente</button>
<script type="module" src="/assets/js/modules/cadastre.js"></script>
<?php }; require dirname(__DIR__).'/layout.php';
