<?php
declare(strict_types=1);
$title='Usuarios y membresías';
$render=function ($escape) {
?>
<h1>Usuarios y membresías</h1><button id="logout" type="button" class="btn btn-outline-secondary mb-3">Cerrar sesión</button>
<p id="users-status" role="status" aria-live="polite" tabindex="-1"></p>
<?php require __DIR__.'/form.php'; ?>
<h2 class="mt-4">Usuarios</h2>
<form id="search-users" class="form-inline mb-3"><label for="search-correo" class="mr-2">Correo</label><input id="search-correo" name="correo" type="search" class="form-control mr-2"><button type="submit" class="btn btn-secondary">Buscar</button></form>
<div class="table-responsive"><table class="table"><caption>Usuarios registrados</caption><thead><tr><th>Nombre</th><th>Correo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="users-list"></tbody></table></div>
<button id="previous-page" class="btn btn-secondary" type="button">Anterior</button><span id="page-status" class="mx-2"></span><button id="next-page" class="btn btn-secondary" type="button">Siguiente</button>
<h2 class="mt-4">Membresías por empresa</h2>
<form id="membership-form" class="row">
<input name="id" type="hidden"><input name="version" type="hidden">
<div class="form-group col-md-3"><label for="member-company">Empresa</label><select id="member-company" name="empresa_id" required class="form-control"></select></div>
<div class="form-group col-md-3"><label for="member-user">ID de usuario</label><input id="member-user" name="usuario_id" type="number" min="1" required class="form-control"></div>
<div class="form-group col-md-3"><label for="member-role">Rol empresarial</label><select id="member-role" name="rol" class="form-control"><option>GERENTE</option><option>GEOLOGO</option><option>INVERSIONISTA</option></select></div>
<div class="form-group col-md-3"><label for="member-status">Estado</label><select id="member-status" name="estado" class="form-control"><option>ACTIVO</option><option>INACTIVO</option></select></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Guardar membresía</button><button id="new-membership" class="btn btn-secondary ml-2" type="button">Nueva membresía</button></div>
</form><ul id="memberships-list" class="mt-3"></ul>
<script type="module" src="/assets/js/modules/users.js"></script>
<?php
};
require dirname(__DIR__).'/layout.php';
