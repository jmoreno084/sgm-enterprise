<?php
declare(strict_types=1);
$title='Recuperar acceso';
$render=function ($escape) {
?>
<h1>Recuperar acceso</h1>
<form id="auth-form" data-operation="recovery" class="col-md-6 p-0">
<div id="email-fields" class="form-group"><label for="correo">Correo</label><input id="correo" name="correo" type="email" autocomplete="username" required class="form-control" maxlength="255"></div>
<fieldset id="password-fields" hidden disabled><legend>Nueva clave</legend>
<div class="form-group"><label for="password">Clave (mínimo 12 caracteres, máximo 72 bytes)</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required class="form-control"></div>
<div class="form-group"><label for="confirmacion">Confirmar clave</label><input id="confirmacion" name="confirmacion" type="password" autocomplete="new-password" minlength="12" required class="form-control"></div>
</fieldset>
<button class="btn btn-primary" type="submit" disabled>Continuar</button><a class="ml-3" href="/login">Ingresar</a>
</form><p id="auth-status" role="status" aria-live="polite" tabindex="-1"></p>
<script type="module" src="/assets/js/modules/auth.js"></script>
<?php
};
require dirname(__DIR__).'/layout.php';
