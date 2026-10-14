<?php
declare(strict_types=1);
$title='Detalle de auditoría'; $render=function ($escape) use ($page) { $event=$page['event'];
?>
<nav aria-label="Secciones"><a href="<?= $page['company']?'/empresas/'.$escape($page['company']).'/auditoria':'/auditoria' ?>">Auditoría</a> · <a href="/empresas">Empresas</a></nav>
<h1>Evento <?= $escape($event['id']) ?></h1>
<dl class="row"><?php foreach (array('fecha','usuario_id','accion','entidad','entidad_id','empresa_id_contexto','proyecto_id_contexto','ip','request_id') as $key) { ?><dt class="col-md-4 text-break"><?= $escape($key) ?></dt><dd class="col-md-8 text-break"><?= $escape($event[$key]===null?'No disponible':$event[$key]) ?></dd><?php } ?></dl>
<?php foreach (array('valores_anteriores'=>'Antes','valores_nuevos'=>'Después') as $key=>$label) { ?><h2><?= $escape($label) ?></h2><pre class="text-wrap text-break"><?= $escape(json_encode($event[$key],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre><?php } ?>
<?php }; require dirname(__DIR__).'/layout.php';
