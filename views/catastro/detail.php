<?php
declare(strict_types=1);
$title='Ficha catastral'; $render=function ($escape) use ($page) {
?>
<nav aria-label="Secciones"><a href="/catastro">Consulta catastral</a> · <a href="/empresas">Empresas</a></nav>
<h1>Ficha <?= $escape($page['tipo']) ?>: <?= $escape($page['atributos']['CODIGO_EXPEDIENTE']) ?></h1>
<p>Los valores ausentes se indican expresamente. Esta ficha no modifica la fuente.</p>
<dl id="cadastre-detail" class="row">
<?php foreach ($page['atributos'] as $key=>$value) { ?>
<dt class="col-md-4 text-break"><?= $escape($key) ?></dt><dd class="col-md-8 text-break"><?php if (is_array($value)) { ?><pre class="text-wrap"><?= $escape(json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre><?php } else { echo $escape($value===null?'No disponible (NULL)':$value); } ?></dd>
<?php } ?>
</dl>
<?php }; require dirname(__DIR__).'/layout.php';
