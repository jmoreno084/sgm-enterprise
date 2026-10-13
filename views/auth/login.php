<?php
declare(strict_types=1);
$title='Ingresar a SGPM';
$render=function ($escape) {
?>
<h1>Ingresar a SGPM</h1>
<form id="auth-form" data-operation="login" class="col-md-6 p-0">
<div class="form-group"><label for="correo">Correo</label><input id="correo" name="correo" type="email" autocomplete="username" required class="form-control" maxlength="255"></div>
<div class="form-group"><label for="password">Clave</label><input id="password" name="password" type="password" autocomplete="current-password" required class="form-control"></div>
<button class="btn btn-primary" type="submit" disabled>Ingresar</button>
<a class="ml-3" href="/recuperar">Recuperar acceso</a>
</form><p id="auth-status" role="status" aria-live="polite" tabindex="-1"></p>
<script type="module" src="/assets/js/modules/auth.js"></script>
<?php
};
require dirname(__DIR__).'/layout.php';
