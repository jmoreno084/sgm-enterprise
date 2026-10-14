<?php
declare(strict_types=1);
?>
<fieldset id="company-editor" hidden disabled><legend>Datos de empresa</legend>
<form id="company-form" class="row"><input name="id" type="hidden"><input name="version" type="hidden">
<?php foreach (array('nit'=>'NIT','razon_social'=>'Razón social','correo'=>'Correo','telefono'=>'Teléfono','direccion'=>'Dirección','ciudad'=>'Ciudad','departamento'=>'Departamento') as $field=>$label) { ?>
<div class="form-group col-md-4"><label for="company-<?= $escape($field) ?>"><?= $escape($label) ?></label><input id="company-<?= $escape($field) ?>" name="<?= $escape($field) ?>" type="<?= $field==='correo'?'email':'text' ?>" class="form-control" maxlength="<?= in_array($field,array('nit','telefono'),true)?'50':(in_array($field,array('ciudad','departamento'),true)?'100':'255') ?>" <?= in_array($field,array('telefono','direccion'),true)?'':'required' ?>></div>
<?php } ?>
<div class="form-group col-md-4"><label for="company-state">Estado</label><select id="company-state" name="estado" class="form-control"><option>ACTIVO</option><option>INACTIVO</option></select></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Guardar empresa</button><button class="btn btn-secondary ml-2" type="reset">Nueva empresa</button></div>
</form>
<form id="logo-form" class="mt-3"><label for="company-logo">Logo PNG/JPEG (hasta 5 MiB; selecciona una empresa)</label><input id="company-logo" name="archivo" type="file" accept="image/png,image/jpeg" required class="form-control-file"><button class="btn btn-secondary mt-2" type="submit">Guardar logo</button></form>
</fieldset>
