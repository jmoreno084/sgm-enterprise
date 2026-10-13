<?php
declare(strict_types=1);
?>
<form id="user-form" class="row">
<input name="id" type="hidden"><input name="version" type="hidden">
<?php foreach (array('correo'=>'Correo','nombre'=>'Nombre','numero_documento'=>'Documento') as $field=>$label) { ?>
<div class="form-group col-md-4"><label for="user-<?= $escape($field) ?>"><?= $escape($label) ?></label><input id="user-<?= $escape($field) ?>" name="<?= $escape($field) ?>" type="<?= $field==='correo'?'email':'text' ?>" required maxlength="<?= $field==='numero_documento'?'50':'255' ?>" class="form-control"></div>
<?php } ?>
<div class="form-group col-md-4"><label for="user-password">Clave (obligatoria al crear)</label><input id="user-password" name="password" type="password" autocomplete="new-password" minlength="12" class="form-control"></div>
<div class="form-group col-md-4"><label for="user-estado">Estado</label><select id="user-estado" name="estado" class="form-control"><option>ACTIVO</option><option>INACTIVO</option></select></div>
<div class="form-group col-md-4"><label for="user-admin">Administrador global</label><input id="user-admin" name="es_administrador" type="checkbox" class="ml-2"></div>
<div class="col-12"><button type="submit" class="btn btn-primary">Guardar usuario</button><button id="new-user" type="button" class="btn btn-secondary ml-2">Nuevo usuario</button></div>
</form>
