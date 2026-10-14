<?php
declare(strict_types=1);
?>
<fieldset id="project-editor" hidden disabled><legend>Datos del proyecto</legend><form id="project-form" class="row">
<input name="id" type="hidden"><input name="version" type="hidden">
<?php foreach (array('nombre'=>'Nombre','mineral_principal'=>'Mineral principal','fecha_inicio'=>'Fecha inicial','fecha_fin'=>'Fecha final') as $field=>$label) { ?>
<div class="form-group col-md-6"><label for="project-<?= $escape($field) ?>"><?= $escape($label) ?></label><input id="project-<?= $escape($field) ?>" name="<?= $escape($field) ?>" type="<?= strpos($field,'fecha_')===0?'date':'text' ?>" class="form-control" <?= strpos($field,'fecha_')===0?'':'required maxlength="'.($field==='nombre'?'255':'100').'"' ?>></div>
<?php } ?>
<?php foreach (array('descripcion'=>'Descripción','observaciones'=>'Observaciones') as $field=>$label) { ?>
<div class="form-group col-md-6"><label for="project-<?= $escape($field) ?>"><?= $escape($label) ?></label><textarea id="project-<?= $escape($field) ?>" name="<?= $escape($field) ?>" class="form-control" maxlength="100000"></textarea></div>
<?php } ?>
<div class="form-group col-md-6"><label for="project-state">Estado</label><select id="project-state" name="estado" class="form-control"><option>ACTIVO</option><option>INACTIVO</option></select></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Guardar proyecto</button><button class="btn btn-secondary ml-2" type="reset">Nuevo proyecto</button></div>
</form></fieldset>
