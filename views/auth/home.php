<?php
declare(strict_types=1);
$title='Inicio SGPM';
$render=function ($escape) {
?>
<h1>Inicio SGPM</h1><p id="session-status" role="status" aria-live="polite"></p>
<h2>Empresas autorizadas</h2><ul id="session-companies"></ul>
<a id="users-link" href="/usuarios" hidden>Administrar usuarios y membresías</a>
<button id="logout" type="button" class="btn btn-outline-secondary mt-3">Cerrar sesión</button>
<script type="module" src="/assets/js/modules/home.js"></script>
<?php
};
require dirname(__DIR__).'/layout.php';
